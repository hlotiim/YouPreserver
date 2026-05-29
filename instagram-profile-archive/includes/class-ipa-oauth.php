<?php
/**
 * Instagram OAuth (Business Login) connection flow.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_OAuth
 */
class IPA_OAuth {

	const OAUTH_SCOPE = 'instagram_business_basic';

	const STATE_TRANSIENT_PREFIX = 'ipa_oauth_state_';

	const CALLBACK_ACTION = 'ipa_oauth_callback';

	const REST_NAMESPACE = 'instagram-profile-archive/v1';

	const REST_CALLBACK_ROUTE = '/oauth-callback';

	/**
	 * Legacy OAuth callback flag on admin.php (still supported).
	 */
	const CALLBACK_FLAG = '1';

	/**
	 * Get the redirect URI to register in the Meta App Dashboard.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return self::get_rest_redirect_uri();
	}

	/**
	 * REST API based callback URI (clean path, no query string).
	 *
	 * @return string
	 */
	public static function get_rest_redirect_uri() {
		$uri = rest_url( self::REST_NAMESPACE . self::REST_CALLBACK_ROUTE );

		if ( is_ssl() || 0 === stripos( (string) home_url(), 'https://' ) ) {
			$uri = set_url_scheme( $uri, 'https' );
		}

		return $uri;
	}

	/**
	 * Public admin-post callback URI (fallback for already-registered Meta apps).
	 *
	 * @return string
	 */
	public static function get_public_redirect_uri() {
		$uri = admin_url( 'admin-post.php' );

		if ( is_ssl() || 0 === stripos( (string) home_url(), 'https://' ) ) {
			$uri = set_url_scheme( $uri, 'https' );
		}

		return $uri;
	}

	/**
	 * Legacy admin.php callback URI (already registered in many Meta apps).
	 *
	 * @return string
	 */
	public static function get_legacy_redirect_uri() {
		$uri = add_query_arg(
			array(
				'page'      => IPA_Admin::PAGE_SLUG,
				'ipa_oauth' => self::CALLBACK_FLAG,
			),
			admin_url( 'admin.php' )
		);

		if ( is_ssl() || 0 === stripos( (string) home_url(), 'https://' ) ) {
			$uri = set_url_scheme( $uri, 'https' );
		}

		return $uri;
	}

	/**
	 * Detect whether the current request is an OAuth callback.
	 *
	 * @return bool
	 */
	public static function is_callback_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_code = ! empty( $_GET['code'] ) || ! empty( $_GET['error'] );

		if ( ! $has_code ) {
			return false;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH ) ?: '';

		// REST API callback (clean URL path).
		if ( false !== stripos( $path, self::REST_NAMESPACE . self::REST_CALLBACK_ROUTE ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rest_route = isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';
		if ( $rest_route && false !== stripos( $rest_route, self::REST_NAMESPACE . self::REST_CALLBACK_ROUTE ) ) {
			return true;
		}

		// admin-post.php callback (with or without our action param).
		if ( false !== stripos( $path, 'admin-post.php' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = ! empty( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( '' === $action || self::CALLBACK_ACTION === $action ) {
				return true;
			}
		}

		// Legacy admin.php callback (?page=instagram-profile-archive&ipa_oauth=1).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['page'] ) && IPA_Admin::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET['ipa_oauth'] ) && self::CALLBACK_FLAG === sanitize_text_field( wp_unslash( $_GET['ipa_oauth'] ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve redirect URI used for this callback request.
	 *
	 * @return string
	 */
	public static function get_redirect_uri_for_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH ) ?: '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rest_route  = isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';

		if ( false !== stripos( $path, self::REST_NAMESPACE . self::REST_CALLBACK_ROUTE )
			|| ( $rest_route && false !== stripos( $rest_route, self::REST_NAMESPACE . self::REST_CALLBACK_ROUTE ) )
		) {
			return self::get_rest_redirect_uri();
		}

		if ( false !== stripos( $path, 'admin-post.php' ) ) {
			return self::get_public_redirect_uri();
		}

		return self::get_legacy_redirect_uri();
	}

	/**
	 * Process OAuth callback early and redirect to admin with a notice.
	 *
	 * @return void
	 */
	public static function maybe_process_callback() {
		if ( ! self::is_callback_request() ) {
			return;
		}

		$oauth  = new self();
		$result = $oauth->handle_callback();

		IPA_Admin::redirect_oauth_result( $result );
		exit;
	}

	/**
	 * Instagram OAuth authorize endpoint.
	 *
	 * @return string
	 */
	public static function get_authorize_endpoint() {
		return 'https://www.instagram.com/oauth/authorize';
	}

	/**
	 * Build the Instagram authorization URL.
	 *
	 * @return string|WP_Error
	 */
	public function get_authorize_url() {
		$format_error = IPA_Settings::validate_app_credentials_format();
		if ( $format_error ) {
			return new WP_Error( 'ipa_invalid_app_credentials', $format_error );
		}

		if ( ! IPA_Security::rate_limit_allow( 'oauth_start', 10, 5 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error(
				'ipa_rate_limited',
				__( 'Too many connection attempts. Please wait a few minutes and try again.', 'instagram-profile-archive' )
			);
		}

		$app_id = trim( (string) ipa_get_setting( 'app_id', '' ) );
		$state  = IPA_Security::generate_signed_state( get_current_user_id() );

		$url = add_query_arg(
			array(
				'client_id'     => $app_id,
				'redirect_uri'  => self::get_rest_redirect_uri(),
				'response_type' => 'code',
				'scope'         => self::OAUTH_SCOPE,
				'state'         => $state,
			),
			self::get_authorize_endpoint()
		);

		IPA_Security::audit(
			'oauth_started',
			array(
				'app_id'       => $app_id,
				'redirect_uri' => self::get_rest_redirect_uri(),
			)
		);

		// Keep mobile browsers in the web OAuth flow when possible.
		return $url . '#weblink';
	}

	/**
	 * Register REST API callback route.
	 *
	 * @return void
	 */
	public static function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_CALLBACK_ROUTE,
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_callback_handler' ),
			)
		);
	}

	/**
	 * REST callback that processes the OAuth response.
	 *
	 * @return void
	 */
	public static function rest_callback_handler() {
		if ( ! IPA_Security::rate_limit_allow( 'rest_callback', 30, 5 * MINUTE_IN_SECONDS ) ) {
			IPA_Admin::redirect_oauth_result(
				new WP_Error(
					'ipa_rate_limited',
					__( 'Too many callback requests. Please try again in a few minutes.', 'instagram-profile-archive' )
				)
			);
		}

		IPA_Security::send_security_headers();

		$oauth  = new self();
		$result = $oauth->handle_callback();
		IPA_Admin::redirect_oauth_result( $result );
		exit;
	}

	/**
	 * Handle OAuth callback query parameters.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function handle_callback() {
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$reason = sanitize_text_field( wp_unslash( $_GET['error_reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return new WP_Error(
				'ipa_oauth_denied',
				'access_denied' === $error
					? __( 'Instagram connection was cancelled.', 'instagram-profile-archive' )
					: sprintf(
						/* translators: %s: error reason */
						__( 'Instagram connection failed: %s', 'instagram-profile-archive' ),
						$reason ?: $error
					)
			);
		}

		if ( ! IPA_Security::rate_limit_allow( 'oauth_callback', 20, 5 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error(
				'ipa_rate_limited',
				__( 'Too many callback attempts. Please wait a few minutes and try again.', 'instagram-profile-archive' )
			);
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $code ) ) {
			IPA_Security::audit( 'oauth_callback_missing_code' );
			return new WP_Error(
				'ipa_oauth_invalid_callback',
				__( 'Invalid Instagram OAuth callback (missing code).', 'instagram-profile-archive' )
			);
		}

		$code = preg_replace( '/#_.*$/', '', $code );

		// Verify signed state token (CSRF + replay protection). State must be present and valid.
		if ( empty( $state ) ) {
			IPA_Security::audit( 'oauth_state_missing' );
			return new WP_Error(
				'ipa_oauth_invalid_state',
				__( 'Instagram callback is missing the security state token. Please click Connect again.', 'instagram-profile-archive' )
			);
		}

		$state_data = IPA_Security::verify_signed_state( $state, 15 * MINUTE_IN_SECONDS );
		if ( false === $state_data ) {
			IPA_Security::audit( 'oauth_state_invalid' );
			return new WP_Error(
				'ipa_oauth_invalid_state',
				__( 'Security state token failed validation. Please click Connect again from the WordPress admin.', 'instagram-profile-archive' )
			);
		}

		$short_lived = $this->exchange_code_for_token( $code );
		if ( is_wp_error( $short_lived ) ) {
			return $short_lived;
		}

		$short_lived = $this->normalize_token_payload( $short_lived );
		$long_lived  = $this->exchange_long_lived_token( $short_lived['access_token'] );
		$using_short_lived = false;

		if ( is_wp_error( $long_lived ) ) {
			if ( self::can_use_short_lived_fallback( $long_lived ) ) {
				$long_lived = array(
					'access_token' => $short_lived['access_token'],
					'token_type'   => 'bearer',
					'expires_in'   => 3600,
				);
				$using_short_lived = true;
			} else {
				return $long_lived;
			}
		} else {
			$long_lived = $this->normalize_token_payload( $long_lived );
		}

		$ig_user_id   = (string) ( $short_lived['user_id'] ?? $short_lived['id'] ?? '' );
		$access_token = (string) ( $long_lived['access_token'] ?? '' );
		$expires_in   = isset( $long_lived['expires_in'] ) ? (int) $long_lived['expires_in'] : null;

		if ( empty( $ig_user_id ) && ! empty( $access_token ) ) {
			$ig_user_id = $this->fetch_user_id_from_token( $access_token );
		}

		if ( empty( $ig_user_id ) || empty( $access_token ) ) {
			return new WP_Error(
				'ipa_oauth_incomplete',
				__( 'Instagram did not return the required account details.', 'instagram-profile-archive' )
			);
		}

		update_option( 'ipa_ig_user_id', sanitize_text_field( $ig_user_id ) );
		IPA_Settings::set_secure( 'access_token', $access_token );
		update_option( 'ipa_token_type', sanitize_text_field( $long_lived['token_type'] ?? 'bearer' ) );
		update_option( 'ipa_token_last_refreshed_at', current_time( 'mysql', true ) );

		$token = new IPA_Token();
		if ( $expires_in ) {
			update_option( 'ipa_token_expires_at', $token->get_expiry_date_from_seconds( $expires_in ) );
		}

		update_option( 'ipa_last_connection_status', 'success' );
		update_option(
			'ipa_last_connection_message',
			$using_short_lived
				? __( 'Connected with a short-lived token (about 1 hour). Complete Meta verification and Instagram Tester setup, then reconnect for a 60-day token.', 'instagram-profile-archive' )
				: __( 'Connected successfully via Instagram login.', 'instagram-profile-archive' )
		);

		IPA_Security::audit(
			'oauth_connected',
			array(
				'ig_user_id'          => $ig_user_id,
				'expires_in'          => $expires_in,
				'short_lived_fallback' => $using_short_lived,
			)
		);

		$this->maybe_sync_profile_from_api( $ig_user_id, $access_token );

		ipa_disable_mock_mode();

		return array(
			'success'             => true,
			'ig_user_id'          => $ig_user_id,
			'short_lived_fallback' => $using_short_lived,
			'message'             => $using_short_lived
				? __( 'Instagram connected with a short-lived token. Complete Meta Business Verification and add your account as an Instagram Tester, then reconnect for a 60-day token.', 'instagram-profile-archive' )
				: __( 'Instagram account connected successfully.', 'instagram-profile-archive' ),
		);
	}

	/**
	 * Normalize token API payloads.
	 *
	 * @param array<string, mixed> $body Response body.
	 * @return array<string, mixed>
	 */
	private function normalize_token_payload( $body ) {
		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			$first = $body['data'][0] ?? null;
			if ( is_array( $first ) ) {
				return array_merge( $body, $first );
			}
		}

		return $body;
	}

	/**
	 * Fetch Instagram user ID from a valid access token.
	 *
	 * @param string $access_token Access token.
	 * @return string
	 */
	private function fetch_user_id_from_token( $access_token ) {
		$api_version = ipa_get_setting( 'api_version', 'v23.0' );
		if ( 0 !== strpos( $api_version, 'v' ) ) {
			$api_version = 'v' . ltrim( $api_version, 'v' );
		}

		$url = add_query_arg(
			array(
				'fields'       => 'id,username',
				'access_token' => $access_token,
			),
			'https://graph.instagram.com/' . $api_version . '/me'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $body['id'] ) ? (string) $body['id'] : '';
	}

	/**
	 * Exchange authorization code for a short-lived token.
	 *
	 * @param string $code Authorization code.
	 * @return array<string, mixed>|WP_Error
	 */
	private function exchange_code_for_token( $code ) {
		$response = wp_remote_post(
			'https://api.instagram.com/oauth/access_token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'client_id'     => IPA_Settings::sanitize_app_id( ipa_get_setting( 'app_id', '' ) ),
					'client_secret' => ipa_get_setting( 'app_secret', '' ),
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => self::get_redirect_uri_for_request(),
					'code'          => $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code_http = wp_remote_retrieve_response_code( $response );

		if ( $code_http < 200 || $code_http >= 300 ) {
			$message = $body['error_message'] ?? $body['error']['message'] ?? __( 'Could not exchange the Instagram authorization code.', 'instagram-profile-archive' );
			return new WP_Error( 'ipa_oauth_token_exchange', $message, IPA_Security::mask_sensitive_data( is_array( $body ) ? $body : array() ) );
		}

		$body = $this->normalize_token_payload( is_array( $body ) ? $body : array() );

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'ipa_oauth_token_exchange',
				__( 'Instagram token response did not include an access token.', 'instagram-profile-archive' ),
				IPA_Security::mask_sensitive_data( $body )
			);
		}

		return $body;
	}

	/**
	 * Exchange short-lived token for a long-lived token.
	 *
	 * @param string $short_lived_token Short-lived token.
	 * @return array<string, mixed>|WP_Error
	 */
	private function exchange_long_lived_token( $short_lived_token ) {
		$short_lived_token = trim( (string) $short_lived_token );

		$result = self::request_graph_token(
			'https://graph.instagram.com/access_token',
			array(
				'grant_type'    => 'ig_exchange_token',
				'client_secret' => trim( (string) ipa_get_setting( 'app_secret', '' ) ),
				'access_token'  => $short_lived_token,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['access_token'] ) ) {
			return new WP_Error(
				'ipa_oauth_long_lived_exchange',
				__( 'Instagram token response did not include an access token.', 'instagram-profile-archive' ),
				IPA_Security::mask_sensitive_data( $result )
			);
		}

		return $result;
	}

	/**
	 * Request a graph.instagram.com token endpoint (GET first, POST fallback).
	 *
	 * Meta documents GET for token exchange. POST is only attempted when GET is rejected
	 * for the HTTP method itself.
	 *
	 * @param string               $url    Endpoint URL.
	 * @param array<string, mixed> $params Request parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function request_graph_token( $url, $params ) {
		$methods      = array( 'GET', 'POST' );
		$last_body    = array();
		$last_code    = 0;
		$tried_methods = array();

		foreach ( $methods as $method ) {
			$tried_methods[] = $method;

			if ( 'POST' === $method ) {
				$response = wp_remote_post(
					$url,
					array(
						'timeout' => 30,
						'headers' => array(
							'Content-Type' => 'application/x-www-form-urlencoded',
						),
						'body'    => $params,
					)
				);
			} else {
				$response = wp_remote_get(
					add_query_arg( $params, $url ),
					array(
						'timeout' => 30,
					)
				);
			}

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$last_code = (int) wp_remote_retrieve_response_code( $response );
			$last_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$last_body = is_array( $last_body ) ? $last_body : array();

			if ( $last_code >= 200 && $last_code < 300 && ! empty( $last_body['access_token'] ) ) {
				return $last_body;
			}

			$error_message = $last_body['error']['message'] ?? '';
			if ( self::is_retryable_method_error( $error_message ) ) {
				continue;
			}

			break;
		}

		$message = $last_body['error']['message'] ?? __( 'Could not complete the Instagram token request.', 'instagram-profile-archive' );
		if ( self::is_meta_access_blocked_error( $message ) || self::is_token_permission_error( $message ) ) {
			$message = self::get_token_exchange_help_message();
		}

		$error_data = IPA_Security::mask_sensitive_data( $last_body );
		if ( is_array( $error_data ) ) {
			$error_data['methods_tried'] = $tried_methods;
		}

		return new WP_Error(
			'ipa_oauth_long_lived_exchange',
			$message,
			$error_data
		);
	}

	/**
	 * Whether an API error indicates we should retry with the alternate HTTP method.
	 *
	 * @param string $message Error message.
	 * @return bool
	 */
	private static function is_retryable_method_error( $message ) {
		$message = (string) $message;

		if ( false !== stripos( $message, 'Unsupported request - method type:' ) ) {
			return true;
		}

		// POST to /access_token is treated as a Graph object request, not token exchange.
		if ( false !== stripos( $message, 'Unsupported post request' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether Meta blocked long-lived token exchange due to app/account setup.
	 *
	 * @param string $message Error message.
	 * @return bool
	 */
	private static function is_meta_access_blocked_error( $message ) {
		$message = (string) $message;

		return false !== stripos( $message, 'Unsupported request - method type:' )
			|| false !== stripos( $message, 'does not exist, cannot be loaded due to missing permissions' )
			|| false !== stripos( $message, 'Unsupported post request' );
	}

	/**
	 * Whether we can still connect using the short-lived token from step 1.
	 *
	 * @param WP_Error $error Long-lived exchange error.
	 * @return bool
	 */
	private static function can_use_short_lived_fallback( $error ) {
		if ( ! is_wp_error( $error ) || 'ipa_oauth_long_lived_exchange' !== $error->get_error_code() ) {
			return false;
		}

		$data    = $error->get_error_data();
		$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';

		return self::is_meta_access_blocked_error( $message )
			|| self::is_token_permission_error( $message );
	}

	/**
	 * Whether an API error likely indicates app/account setup is incomplete.
	 *
	 * @param string $message Error message.
	 * @return bool
	 */
	private static function is_token_permission_error( $message ) {
		$message = (string) $message;

		return false !== stripos( $message, 'Unsupported get request' )
			|| false !== stripos( $message, 'Invalid OAuth access token' )
			|| false !== stripos( $message, 'Cannot parse access token' );
	}

	/**
	 * Helpful guidance when Meta rejects token exchange/refresh calls.
	 *
	 * @return string
	 */
	public static function get_token_exchange_help_message() {
		return __(
			'Instagram authorized your login, but Meta blocked the long-lived token exchange. In developers.facebook.com: (1) add your Instagram account under App Roles → Instagram Testers and accept the invite in the Instagram app, (2) use the Instagram App Secret from Instagram Login → Business login settings, and (3) complete Business Verification and Access Verification if Meta requires them. Until that is done, the plugin can still connect with a 1-hour token for testing.',
			'instagram-profile-archive'
		);
	}

	/**
	 * Prefill profile display settings from the connected account.
	 *
	 * @param string $ig_user_id   Instagram user ID.
	 * @param string $access_token Access token.
	 * @return void
	 */
	private function maybe_sync_profile_from_api( $ig_user_id, $access_token ) {
		ipa_sync_profile_from_api( $ig_user_id, $access_token );
	}
}
