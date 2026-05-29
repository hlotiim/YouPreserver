<?php
/**
 * Instagram API client.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_API
 */
class IPA_API {

	/**
	 * @var string
	 */
	private $last_error = '';

	/**
	 * @var int
	 */
	private $api_call_count = 0;

	/**
	 * @var array<string, mixed>
	 */
	private $last_headers = array();

	/**
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * @return int
	 */
	public function get_api_call_count() {
		return $this->api_call_count;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_last_headers() {
		return $this->last_headers;
	}

	/**
	 * @return string
	 */
	public function get_api_version() {
		$version = ipa_get_setting( 'api_version', 'v23.0' );
		if ( 0 !== strpos( $version, 'v' ) ) {
			$version = 'v' . ltrim( $version, 'v' );
		}
		return $version;
	}

	/**
	 * @return string
	 */
	public function get_access_token() {
		return (string) ipa_get_setting( 'access_token', '' );
	}

	/**
	 * @return string
	 */
	public function get_ig_user_id() {
		return (string) ipa_get_setting( 'ig_user_id', '' );
	}

	/**
	 * @param string               $endpoint Endpoint.
	 * @param array<string, mixed> $params   Params.
	 * @return string
	 */
	public function build_url( $endpoint, $params = array() ) {
		$endpoint = '/' . ltrim( $endpoint, '/' );
		return 'https://graph.instagram.com/' . $this->get_api_version() . $endpoint;
	}

	/**
	 * @return true|WP_Error
	 */
	public function test_connection() {
		$user_id = $this->get_ig_user_id();
		$token   = $this->get_access_token();

		if ( empty( $user_id ) ) {
			$this->last_error = __( 'Instagram User ID is missing. Please add it in the Connection tab.', 'instagram-profile-archive' );
			return new WP_Error( 'ipa_missing_user_id', $this->last_error );
		}

		if ( empty( $token ) ) {
			$this->last_error = __( 'Access token is missing. Please add a valid Instagram access token.', 'instagram-profile-archive' );
			return new WP_Error( 'ipa_missing_token', $this->last_error );
		}

		$response = $this->get_media( 1 );

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return $response;
		}

		return true;
	}

	/**
	 * @param int         $limit Limit.
	 * @param string|null $after Cursor.
	 * @param string|null $since Since.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_media( $limit = 25, $after = null, $since = null ) {
		$user_id = $this->get_ig_user_id();
		$fields  = 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp,children';

		$args = array(
			'fields' => $fields,
			'limit'  => min( 50, max( 1, (int) $limit ) ),
		);

		if ( $after ) {
			$args['after'] = $after;
		}

		if ( $since ) {
			$args['since'] = $since;
		}

		return $this->api_get( '/' . $user_id . '/media', $args );
	}

	/**
	 * @param string $media_id Media ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_media_children( $media_id ) {
		$fields = 'id,media_type,media_url,thumbnail_url,permalink,timestamp';
		return $this->api_get( '/' . $media_id . '/children', array( 'fields' => $fields ) );
	}

	/**
	 * @param string               $endpoint Endpoint.
	 * @param array<string, mixed> $args     Args.
	 * @return array<string, mixed>|WP_Error
	 */
	public function api_get( $endpoint, $args = array() ) {
		$token = $this->get_access_token();

		if ( empty( $token ) ) {
			return new WP_Error(
				'ipa_no_token',
				__( 'Access token is missing. Please add a valid Instagram access token.', 'instagram-profile-archive' )
			);
		}

		$url = $this->build_url( $endpoint );

		// Defence-in-depth: only ever call graph.instagram.com.
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( 'graph.instagram.com' !== strtolower( (string) $host ) ) {
			return new WP_Error( 'ipa_invalid_endpoint', __( 'Refusing to call non-Instagram endpoint.', 'instagram-profile-archive' ) );
		}

		$args['access_token'] = $token;

		$response = wp_remote_get(
			add_query_arg( $args, $url ),
			array(
				'timeout'     => 30,
				'sslverify'   => true,
				'redirection' => 2,
				'headers'     => array(
					'Accept' => 'application/json',
				),
			)
		);

		++$this->api_call_count;

		if ( 0 === $this->api_call_count % 10 ) {
			sleep( 1 );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * @param array<string, mixed>|WP_HTTP_Response $response Response.
	 * @return array<string, mixed>|WP_Error
	 */
	public function parse_response( $response ) {
		$code    = wp_remote_retrieve_response_code( $response );
		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$headers = wp_remote_retrieve_headers( $response );

		$this->last_headers = is_array( $headers ) ? $headers : $headers->getAll();

		if ( $code < 200 || $code >= 300 ) {
			$message = $body['error']['message'] ?? __( 'Instagram API request failed. Please check your connection settings.', 'instagram-profile-archive' );
			$this->last_error = $message;

			$safe_body = class_exists( 'IPA_Security' ) ? IPA_Security::mask_sensitive_data( is_array( $body ) ? $body : array() ) : array();

			$error_code = $body['error']['code'] ?? $code;
			if ( in_array( (int) $error_code, array( 4, 17, 32, 613 ), true ) ) {
				return new WP_Error( 'ipa_rate_limited', __( 'Instagram API rate limit reached. Sync stopped safely and will retry on the next run.', 'instagram-profile-archive' ), $safe_body );
			}

			return new WP_Error( 'ipa_api_error', $message, $safe_body );
		}

		$paging = $body['paging'] ?? array();

		return array(
			'data'    => $body['data'] ?? array(),
			'paging'  => array(
				'cursors' => array(
					'before' => $paging['cursors']['before'] ?? '',
					'after'  => $paging['cursors']['after'] ?? '',
				),
				'next'    => $paging['next'] ?? '',
			),
			'headers' => $this->last_headers,
		);
	}
}
