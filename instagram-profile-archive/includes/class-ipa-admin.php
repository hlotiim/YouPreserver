<?php
/**
 * Admin settings page.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Admin
 */
class IPA_Admin {

	const PAGE_SLUG = 'instagram-profile-archive';

	/**
	 * Singleton instance.
	 *
	 * @var IPA_Admin|null
	 */
	private static $instance = null;

	/**
	 * Current tab.
	 *
	 * @var string
	 */
	private $current_tab = 'connection';

	/**
	 * Get current admin tab.
	 *
	 * @return string
	 */
	public function get_current_tab() {
		return $this->current_tab;
	}

	/**
	 * Get singleton instance.
	 *
	 * @return IPA_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_clean_stale_oauth_url' ) );
		add_action( 'admin_post_' . IPA_OAuth::CALLBACK_ACTION, array( $this, 'handle_oauth_callback_request' ) );
		add_action( 'admin_post_nopriv_' . IPA_OAuth::CALLBACK_ACTION, array( $this, 'handle_oauth_callback_request' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ipa_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ipa_connection_form', array( $this, 'handle_connection_form' ) );
		add_action( 'admin_post_ipa_connect_instagram', array( $this, 'handle_connect_instagram' ) );
		add_action( 'admin_post_ipa_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_ipa_refresh_token', array( $this, 'handle_refresh_token' ) );
		add_action( 'admin_post_ipa_manual_sync', array( $this, 'handle_manual_sync' ) );
		add_action( 'admin_post_ipa_import_highlights', array( $this, 'handle_import_highlights' ) );
		add_action( 'admin_post_ipa_full_sync', array( $this, 'handle_full_sync' ) );
		add_action( 'admin_post_ipa_continue_full_sync', array( $this, 'handle_continue_full_sync' ) );
		add_action( 'admin_post_ipa_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'admin_post_ipa_reset_full_sync_cursor', array( $this, 'handle_reset_cursor' ) );
		add_action( 'admin_post_ipa_recreate_page', array( $this, 'handle_recreate_page' ) );
		add_action( 'admin_post_ipa_clear_media_cache', array( $this, 'handle_clear_media_cache' ) );
		add_action( 'admin_post_ipa_cleanup_media', array( $this, 'handle_cleanup_media' ) );
		add_action( 'admin_post_ipa_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_post_ipa_delete_all_data', array( $this, 'handle_delete_all_data' ) );
		add_action( 'admin_post_ipa_delete_archive_items', array( $this, 'handle_delete_archive_items' ) );
		add_action( 'admin_post_ipa_delete_all_archive', array( $this, 'handle_delete_all_archive' ) );
		add_action( 'admin_post_ipa_save_pinned_posts', array( $this, 'handle_save_pinned_posts' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'YouPreserver', 'instagram-profile-archive' ),
			__( 'YouPreserver', 'instagram-profile-archive' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-camera',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ipa-admin',
			IPA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			IPA_VERSION
		);

		wp_enqueue_script(
			'ipa-admin',
			IPA_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			IPA_VERSION,
			true
		);

		wp_localize_script(
			'ipa-admin',
			'ipaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ipa_admin_ajax' ),
				'i18n'    => array(
					'importPreparing'     => __( 'Preparing import…', 'instagram-profile-archive' ),
					'importUploadProgress' => __( 'Uploading ZIP… %1$s of %2$s (%3$d%%)', 'instagram-profile-archive' ),
					'importUploadSent'    => __( 'Uploading ZIP… %1$s sent', 'instagram-profile-archive' ),
					'importExtracting'    => __( 'Extracting and validating ZIP…', 'instagram-profile-archive' ),
					'importStarting'      => __( 'Starting import…', 'instagram-profile-archive' ),
					'importProgress'      => __( 'Importing highlight %1$d of %2$d…', 'instagram-profile-archive' ),
					'importComplete'      => __( 'Import complete.', 'instagram-profile-archive' ),
					'importFailed'        => __( 'Import failed.', 'instagram-profile-archive' ),
					'importCancelled'     => __( 'Import cancelled.', 'instagram-profile-archive' ),
					'importNoFile'        => __( 'Please choose a highlights ZIP file first.', 'instagram-profile-archive' ),
					'importWorking'       => __( 'Importing…', 'instagram-profile-archive' ),
					'importNetworkError'  => __( 'Network error during upload. Check your connection and try again.', 'instagram-profile-archive' ),
					'importServerError'   => __( 'Server returned an invalid response. The upload may have timed out — try a smaller ZIP or increase PHP upload limits.', 'instagram-profile-archive' ),
					'cancelConfirm'       => __( 'Cancel the highlights import?', 'instagram-profile-archive' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			IPA_Security::audit( 'admin_page_blocked' );
			wp_die( esc_html__( 'You do not have permission to access this page.', 'instagram-profile-archive' ), 403 );
		}

		IPA_Security::send_security_headers();

		$this->current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed_tabs      = array( 'connection', 'sync', 'highlights', 'profile', 'logs', 'delete', 'tools' );

		if ( ! in_array( $this->current_tab, $allowed_tabs, true ) ) {
			$this->current_tab = 'connection';
		}

		$settings   = ipa_get_settings();
		$stats      = $this->get_dashboard_stats();
		$logs_page  = max( 1, (int) ( $_GET['logs_page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$logs_limit = 20;
		$logs       = IPA_DB::get_sync_logs( $logs_limit, ( $logs_page - 1 ) * $logs_limit );
		$logs_total = IPA_DB::count_sync_logs();
		$page_id    = (int) get_option( 'ipa_instagram_page_id', 0 );
		$page_url   = ipa_get_archive_page_url();
		$full_cursor = IPA_DB::get_cursor( IPA_DB::CURSOR_FULL_SYNC );
		$token_status = ( new IPA_Token() )->get_token_status();
		$delete_page  = max( 1, (int) ( $_GET['delete_page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$delete_limit = 50;
		$delete_media = IPA_DB::get_admin_parent_media( $delete_limit, ( $delete_page - 1 ) * $delete_limit );
		$delete_media_total = IPA_DB::count_admin_parent_media();
		$delete_highlights  = 'highlights' === $this->current_tab ? IPA_DB::get_active_highlights() : array();
		$pin_posts          = 'sync' === $this->current_tab ? IPA_DB::get_admin_posts_for_pins( 100 ) : array();
		$pinned_post_ids    = array_map( 'intval', (array) get_option( 'ipa_pinned_post_ids', array() ) );

		include IPA_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * Get dashboard stat cards data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_dashboard_stats() {
		$token       = new IPA_Token();
		$token_status = $token->get_token_status();
		$mock_mode   = ipa_get_setting( 'enable_mock_mode', true );

		$connection_status = 'disconnected';
		$connection_label  = __( 'Not connected', 'instagram-profile-archive' );

		if ( $mock_mode && $token->is_token_configured() && IPA_DB::get_total_archived_posts() > 0 ) {
			$connection_status = 'mock';
			$connection_label  = __( 'Mock mode hiding archive', 'instagram-profile-archive' );
		} elseif ( $mock_mode ) {
			$connection_status = 'mock';
			$connection_label  = __( 'Mock mode active', 'instagram-profile-archive' );
		} elseif ( $token->is_token_configured() ) {
			$connection_status = 'configured';
			$connection_label  = __( 'Connected', 'instagram-profile-archive' );

			if ( 'expired' === $token_status['status'] ) {
				$connection_status = 'expired';
				$connection_label  = __( 'Token expired', 'instagram-profile-archive' );
			} elseif ( 'expiring' === $token_status['status'] ) {
				$connection_status = 'expiring';
				$connection_label  = __( 'Expiring soon', 'instagram-profile-archive' );
			}
		}

		$last_sync_time = ipa_get_setting( 'last_successful_sync', '' );
		$last_sync_msg  = ipa_get_setting( 'last_sync_message', '' );
		$last_sync      = $last_sync_time ? ipa_format_datetime( $last_sync_time ) : __( 'Never', 'instagram-profile-archive' );

		if ( $last_sync_msg ) {
			$last_sync .= ' — ' . $last_sync_msg;
		}

		$auto_sync = ipa_get_setting( 'enable_auto_sync', false )
			? sprintf(
				/* translators: %s: frequency */
				__( 'Enabled, %s', 'instagram-profile-archive' ),
				ipa_get_setting( 'sync_frequency', 'daily' )
			)
			: __( 'Disabled', 'instagram-profile-archive' );

		return array(
			'connection_status' => $connection_status,
			'connection_label'  => $connection_label,
			'total_posts'       => IPA_DB::get_total_archived_posts(),
			'total_highlights'  => IPA_DB::count_active_highlights(),
			'last_sync'         => $last_sync,
			'last_highlights_sync' => $this->format_highlights_import_stat(),
			'auto_sync'         => $auto_sync,
			'token_status'      => $token_status,
		);
	}

	/**
	 * Format highlights import stat for dashboard card.
	 *
	 * @return string
	 */
	private function format_highlights_import_stat() {
		$count = IPA_DB::count_active_highlights();
		$at    = get_option( 'ipa_last_highlights_import_at', '' );
		$msg   = get_option( 'ipa_last_highlights_import_message', '' );

		if ( 0 === $count && '' === $at ) {
			return __( 'Not imported yet', 'instagram-profile-archive' );
		}

		$label = sprintf(
			/* translators: %d: number of highlights */
			_n( '%d highlight', '%d highlights', $count, 'instagram-profile-archive' ),
			$count
		);

		if ( $at ) {
			$label .= ' — ' . ipa_format_datetime( $at );
		}

		if ( $msg ) {
			$label .= ' — ' . $msg;
		}

		return $label;
	}

	/**
	 * Redirect after OAuth with an admin notice.
	 *
	 * @param array<string, mixed>|WP_Error $result OAuth result.
	 * @return void
	 */
	public static function redirect_oauth_result( $result ) {
		$notice = is_wp_error( $result ) ? 'error' : 'success';
		$msg    = is_wp_error( $result )
			? $result->get_error_message()
			: ( is_array( $result ) && isset( $result['message'] ) ? $result['message'] : __( 'Connected successfully.', 'instagram-profile-archive' ) );

		if ( ! is_wp_error( $result ) && is_array( $result ) && ! empty( $result['short_lived_fallback'] ) ) {
			$notice = 'warning';
		}

		$debug_payload = array(
			'time'    => current_time( 'mysql', true ),
			'status'  => $notice,
			'message' => $msg,
		);

		if ( is_wp_error( $result ) && $result->get_error_data() ) {
			$debug_payload['data'] = IPA_Security::mask_sensitive_data( $result->get_error_data() );
		} elseif ( ! is_wp_error( $result ) && is_array( $result ) && ! empty( $result['short_lived_fallback'] ) ) {
			$debug_payload['note'] = 'connected_with_short_lived_token';
		}

		update_option( 'ipa_last_oauth_debug', wp_json_encode( $debug_payload ), false );

		$url = add_query_arg(
			array(
				'page'       => self::PAGE_SLUG,
				'tab'        => 'connection',
				'ipa_notice' => $notice,
				'ipa_msg'    => $msg,
			),
			admin_url( 'admin.php' )
		);

		while ( ob_get_level() > 0 ) {
			@ob_end_clean(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		IPA_Security::send_security_headers();

		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		printf(
			'<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=%1$s"><title>%3$s</title></head><body style="font-family:sans-serif;padding:24px;"><p>%4$s <a href="%1$s">%5$s</a></p><script>window.location.replace(%2$s);</script></body></html>',
			esc_url( $url ),
			wp_json_encode( $url ),
			esc_html__( 'Redirecting…', 'instagram-profile-archive' ),
			esc_html__( 'Instagram connection complete.', 'instagram-profile-archive' ),
			esc_html__( 'Continue to settings', 'instagram-profile-archive' )
		);
		exit;
	}

	/**
	 * Handle admin-post OAuth callback.
	 *
	 * @return void
	 */
	public function handle_oauth_callback_request() {
		if ( ! IPA_OAuth::is_callback_request() ) {
			self::redirect_oauth_result(
				new WP_Error(
					'ipa_oauth_invalid_callback',
					__( 'Invalid Instagram OAuth callback.', 'instagram-profile-archive' )
				)
			);
		}

		$oauth  = new IPA_OAuth();
		$result = $oauth->handle_callback();
		self::redirect_oauth_result( $result );
	}

	/**
	 * Remove stale oauth query args when the auth code was lost.
	 *
	 * @return void
	 */
	public function maybe_clean_stale_oauth_url() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['ipa_oauth'] ) || IPA_OAuth::CALLBACK_FLAG !== sanitize_text_field( wp_unslash( $_GET['ipa_oauth'] ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['code'] ) || ! empty( $_GET['error'] ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'connection',
					'ipa_notice' => 'error',
					'ipa_msg'    => __( 'Instagram authorization did not complete. Please click Connect with Instagram again and stay logged in to WordPress in the same browser.', 'instagram-profile-archive' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle connection form (save or connect).
	 *
	 * @return void
	 */
	public function handle_connection_form() {
		self::guard_admin_request( 'ipa_connection_form', 'connection_form' );

		IPA_Settings::save_app_credentials_from_post();

		$action = sanitize_key( wp_unslash( $_POST['ipa_connection_action'] ?? 'connect' ) );

		if ( 'save' === $action ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::PAGE_SLUG,
						'tab'     => 'connection',
						'updated' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$this->redirect_to_instagram_oauth();
	}

	/**
	 * Centralized capability + nonce + rate limit + audit guard for admin-post handlers.
	 *
	 * @param string $nonce_action Nonce action.
	 * @param string $rate_bucket  Rate-limit bucket name.
	 * @param int    $max          Max requests.
	 * @param int    $window       Window seconds.
	 * @return void
	 */
	public static function guard_admin_request( $nonce_action, $rate_bucket, $max = 20, $window = 60 ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			IPA_Security::audit( 'admin_permission_denied', array( 'action' => $nonce_action ) );
			wp_die( esc_html__( 'Permission denied.', 'instagram-profile-archive' ), 403 );
		}

		check_admin_referer( $nonce_action );

		IPA_Security::send_security_headers();

		if ( ! IPA_Security::rate_limit_allow( $rate_bucket, $max, $window ) ) {
			IPA_Security::audit( 'admin_rate_limited', array( 'action' => $rate_bucket ) );
			wp_die( esc_html__( 'Too many requests. Please slow down and try again in a minute.', 'instagram-profile-archive' ), 429 );
		}
	}

	/**
	 * Start Instagram OAuth connect flow (reconnect shortcut).
	 *
	 * @return void
	 */
	public function handle_connect_instagram() {
		self::guard_admin_request( 'ipa_connect_instagram', 'connect_btn', 10, 300 );
		IPA_Settings::save_app_credentials_from_post();
		$this->redirect_to_instagram_oauth();
	}

	/**
	 * Redirect admin to Instagram OAuth authorize URL.
	 *
	 * @return void
	 */
	private function redirect_to_instagram_oauth() {
		$missing = IPA_Settings::get_missing_credentials_message();

		if ( $missing ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => self::PAGE_SLUG,
						'tab'        => 'connection',
						'ipa_notice' => 'error',
						'ipa_msg'    => rawurlencode( $missing ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$oauth = new IPA_OAuth();
		$url   = $oauth->get_authorize_url();

		if ( is_wp_error( $url ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => self::PAGE_SLUG,
						'tab'        => 'connection',
						'ipa_notice' => 'error',
						'ipa_msg'    => rawurlencode( $url->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		nocache_headers();
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External Instagram OAuth URL.
		exit;
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		self::guard_admin_request( 'ipa_save_settings', 'save_settings', 60, 60 );

		$tab_input    = sanitize_key( wp_unslash( $_POST['ipa_tab'] ?? 'connection' ) );
		$allowed_tabs = array( 'connection', 'sync', 'highlights', 'profile', 'tools' );
		$tab          = in_array( $tab_input, $allowed_tabs, true ) ? $tab_input : 'connection';
		IPA_Security::audit( 'settings_saved', array( 'tab' => $tab ) );
		IPA_Settings::save_from_post( $tab );

		delete_transient( 'ipa_frontend_data_mock' );
		delete_transient( 'ipa_frontend_data_live' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => $tab,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle test connection.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		self::guard_admin_request( 'ipa_test_connection', 'test_conn', 10, 60 );

		$api    = new IPA_API();
		$result = $api->test_connection();

		$status = is_wp_error( $result ) ? 'error' : 'success';
		$msg    = is_wp_error( $result ) ? $result->get_error_message() : __( 'Connected successfully.', 'instagram-profile-archive' );

		update_option( 'ipa_last_connection_status', $status );
		update_option( 'ipa_last_connection_message', $msg );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'connection',
					'ipa_notice' => $status,
					'ipa_msg'    => rawurlencode( $msg ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle refresh token.
	 *
	 * @return void
	 */
	public function handle_refresh_token() {
		self::guard_admin_request( 'ipa_refresh_token', 'refresh_token', 5, 60 );

		$token  = new IPA_Token();
		$result = $token->refresh_token();

		IPA_Security::audit( 'token_refresh_attempted', array( 'success' => ! is_wp_error( $result ) ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'connection',
					'ipa_notice' => is_wp_error( $result ) ? 'error' : 'success',
					'ipa_msg'    => rawurlencode(
						is_wp_error( $result )
							? $result->get_error_message()
							: __( 'Token refreshed successfully.', 'instagram-profile-archive' )
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle manual sync.
	 *
	 * @return void
	 */
	public function handle_manual_sync() {
		self::guard_admin_request( 'ipa_manual_sync', 'manual_sync', 6, 60 );

		$sync   = new IPA_Sync();
		$result = $sync->run_manual_sync();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'sync',
					'ipa_notice' => ! empty( $result['success'] ) ? 'success' : 'error',
					'ipa_msg'    => rawurlencode( $result['message'] ?? '' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle highlights ZIP import.
	 *
	 * @return void
	 */
	public function handle_import_highlights() {
		self::guard_admin_request( 'ipa_import_highlights', 'import_highlights', 6, 120 );

		if ( empty( $_FILES['ipa_highlights_zip']['tmp_name'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => self::PAGE_SLUG,
						'tab'        => 'sync',
						'ipa_notice' => 'error',
						'ipa_msg'    => rawurlencode( __( 'Please choose a highlights ZIP file to import.', 'instagram-profile-archive' ) ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$file = $_FILES['ipa_highlights_zip']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $file['error'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => self::PAGE_SLUG,
						'tab'        => 'sync',
						'ipa_notice' => 'error',
						'ipa_msg'    => rawurlencode( __( 'File upload failed. Check your server upload limits.', 'instagram-profile-archive' ) ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$checked = wp_check_filetype( $file['name'] ?? '' );
		if ( 'zip' !== strtolower( (string) ( $checked['ext'] ?? '' ) ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => self::PAGE_SLUG,
						'tab'        => 'sync',
						'ipa_notice' => 'error',
						'ipa_msg'    => rawurlencode( __( 'Please upload a .zip file exported by the YouPreserver Highlights Chrome extension.', 'instagram-profile-archive' ) ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$replace_existing = ! empty( $_POST['ipa_replace_highlights'] );
		$result           = ipa_import_highlights_zip( $file['tmp_name'], $replace_existing );
		$notice           = 'success';
		$message          = '';

		if ( is_wp_error( $result ) ) {
			$notice  = 'error';
			$message = $result->get_error_message();
		} else {
			$message = (string) get_option( 'ipa_last_highlights_import_message', '' );
			if ( '' === $message ) {
				$message = sprintf(
					/* translators: %d: number of highlights */
					__( 'Imported %d highlights.', 'instagram-profile-archive' ),
					(int) ( $result['highlights'] ?? 0 )
				);
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'sync',
					'ipa_notice' => $notice,
					'ipa_msg'    => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle full sync.
	 *
	 * @return void
	 */
	public function handle_full_sync() {
		self::guard_admin_request( 'ipa_full_sync', 'full_sync', 3, 300 );

		$sync   = new IPA_Sync();
		$result = $sync->run_full_sync( false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'sync',
					'ipa_notice' => ! empty( $result['success'] ) ? 'success' : 'error',
					'ipa_msg'    => rawurlencode( $result['message'] ?? '' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle continue full sync.
	 *
	 * @return void
	 */
	public function handle_continue_full_sync() {
		self::guard_admin_request( 'ipa_continue_full_sync', 'continue_full_sync', 5, 300 );

		$sync   = new IPA_Sync();
		$result = $sync->run_full_sync( true );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'sync',
					'ipa_notice' => ! empty( $result['success'] ) ? 'success' : 'error',
					'ipa_msg'    => rawurlencode( $result['message'] ?? '' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle clear logs.
	 *
	 * @return void
	 */
	public function handle_clear_logs() {
		self::guard_admin_request( 'ipa_clear_logs', 'clear_logs', 10, 60 );

		IPA_Security::audit( 'sync_logs_cleared' );
		IPA_DB::clear_sync_logs();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => 'logs',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle reset full sync cursor.
	 *
	 * @return void
	 */
	public function handle_reset_cursor() {
		self::guard_admin_request( 'ipa_reset_full_sync_cursor', 'reset_cursor', 10, 60 );

		IPA_DB::delete_cursor( IPA_DB::CURSOR_FULL_SYNC );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'tools',
					'ipa_notice' => 'success',
					'ipa_msg'    => rawurlencode( __( 'Full sync cursor reset.', 'instagram-profile-archive' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle recreate page.
	 *
	 * @return void
	 */
	public function handle_recreate_page() {
		self::guard_admin_request( 'ipa_recreate_page', 'recreate_page', 5, 60 );

		IPA_Security::audit( 'archive_page_recreated' );
		delete_option( 'ipa_instagram_page_id' );
		IPA_Activator::create_instagram_page();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'tools',
					'ipa_notice' => 'success',
					'ipa_msg'    => rawurlencode( __( 'Archive page recreated successfully.', 'instagram-profile-archive' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle clear media cache.
	 *
	 * @return void
	 */
	public function handle_clear_media_cache() {
		self::guard_admin_request( 'ipa_clear_media_cache', 'clear_cache', 20, 60 );

		delete_transient( 'ipa_frontend_data_mock' );
		delete_transient( 'ipa_frontend_data_live' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'tools',
					'ipa_notice' => 'success',
					'ipa_msg'    => rawurlencode( __( 'Frontend cache cleared.', 'instagram-profile-archive' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Clean duplicate and orphaned IPA media library attachments.
	 *
	 * @return void
	 */
	public function handle_cleanup_media() {
		self::guard_admin_request( 'ipa_cleanup_media', 'cleanup_media', 5, 300 );

		$deduped_rows = IPA_DB::dedupe_media_records();
		$result       = IPA_DB::cleanup_duplicate_attachments();

		delete_transient( 'ipa_frontend_data_mock' );
		delete_transient( 'ipa_frontend_data_live' );

		IPA_Security::audit(
			'media_cleanup',
			array(
				'deleted'    => $result['deleted'],
				'duplicates' => $result['duplicates'],
				'orphans'    => $result['orphans'],
				'db_rows'    => $deduped_rows,
			)
		);

		$message = sprintf(
			/* translators: 1: total deleted attachments, 2: duplicate attachments, 3: orphaned attachments, 4: duplicate DB rows */
			__( 'Media cleanup complete. Removed %1$d attachment(s) (%2$d duplicates, %3$d orphans) and %4$d duplicate archive record(s).', 'instagram-profile-archive' ),
			(int) $result['deleted'],
			(int) $result['duplicates'],
			(int) $result['orphans'],
			(int) $deduped_rows
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'delete',
					'ipa_notice' => 'success',
					'ipa_msg'    => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Export media CSV.
	 *
	 * @return void
	 */
	public function handle_export_csv() {
		self::guard_admin_request( 'ipa_export_csv', 'export_csv', 5, 60 );
		IPA_Security::audit( 'csv_exported' );

		$filename = 'instagram-archive-export-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$output = fopen( 'php://output', 'w' );
		fputcsv(
			$output,
			array(
				'id',
				'ig_media_id',
				'parent_ig_media_id',
				'media_type',
				'caption',
				'permalink',
				'local_file_url',
				'posted_at',
				'synced_at',
				'download_status',
				'status',
			)
		);

		foreach ( IPA_DB::get_all_media_for_export() as $row ) {
			fputcsv(
				$output,
				array(
					$row->id,
					$row->ig_media_id,
					$row->parent_ig_media_id,
					$row->media_type,
					$row->caption,
					$row->permalink,
					$row->local_file_url,
					$row->posted_at,
					$row->synced_at,
					$row->download_status,
					$row->status,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle delete all data.
	 *
	 * @return void
	 */
	public function handle_delete_all_data() {
		self::guard_admin_request( 'ipa_delete_all_data', 'delete_all', 3, 300 );

		if ( empty( $_POST['ipa_confirm_delete'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => self::PAGE_SLUG,
						'tab'        => 'tools',
						'ipa_notice' => 'error',
						'ipa_msg'    => rawurlencode( __( 'Please confirm deletion by checking the checkbox.', 'instagram-profile-archive' ) ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$delete_attachments = ! empty( $_POST['ipa_delete_attachments'] );

		IPA_Security::audit(
			'all_data_deleted',
			array(
				'delete_attachments' => $delete_attachments,
			)
		);

		IPA_DB::delete_all_data( $delete_attachments );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'tools',
					'ipa_notice' => 'success',
					'ipa_msg'    => rawurlencode( __( 'All plugin media data and logs deleted.', 'instagram-profile-archive' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete selected archived posts or highlights.
	 *
	 * @return void
	 */
	public function handle_delete_archive_items() {
		self::guard_admin_request( 'ipa_delete_archive_items', 'delete_archive_items', 20, 300 );

		$type               = isset( $_POST['ipa_delete_type'] ) ? sanitize_key( wp_unslash( $_POST['ipa_delete_type'] ) ) : '';
		$delete_attachments = ! empty( $_POST['ipa_delete_attachments'] );
		$ids                = isset( $_POST['ipa_delete_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ipa_delete_ids'] ) ) : array();
		$ids                = array_values( array_filter( $ids ) );
		$deleted            = 0;
		$notice             = 'success';
		$message            = '';

		if ( empty( $ids ) ) {
			$notice  = 'error';
			$message = __( 'No items were selected.', 'instagram-profile-archive' );
		} elseif ( 'highlights' === $type ) {
			$deleted = IPA_DB::delete_highlights_by_db_ids( $ids, $delete_attachments );
			$message = sprintf(
				/* translators: %d: number of highlights deleted */
				_n( 'Deleted %d highlight.', 'Deleted %d highlights.', $deleted, 'instagram-profile-archive' ),
				$deleted
			);
		} elseif ( 'media' === $type ) {
			$deleted = IPA_DB::delete_media_by_db_ids( $ids, $delete_attachments );
			$message = sprintf(
				/* translators: %d: number of archived posts deleted */
				_n( 'Deleted %d archived post.', 'Deleted %d archived posts.', $deleted, 'instagram-profile-archive' ),
				$deleted
			);
		} else {
			$notice  = 'error';
			$message = __( 'Invalid delete request.', 'instagram-profile-archive' );
		}

		if ( 'success' === $notice && 0 === $deleted ) {
			$notice  = 'error';
			$message = __( 'Nothing was deleted. The selected items may have already been removed.', 'instagram-profile-archive' );
		}

		if ( 'success' === $notice ) {
			IPA_Security::audit(
				'archive_items_deleted',
				array(
					'type'       => $type,
					'count'      => $deleted,
					'attachments'=> $delete_attachments,
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'highlights' === $type ? 'highlights' : 'delete',
					'ipa_notice' => $notice,
					'ipa_msg'    => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Resolve admin tab redirect after bulk archive delete.
	 *
	 * @param string $scope Delete scope.
	 * @return string
	 */
	private function get_archive_delete_redirect_tab( $scope ) {
		if ( 'highlights' === $scope ) {
			return 'highlights';
		}

		return 'delete';
	}

	/**
	 * Save manually selected pinned posts (max 3).
	 *
	 * @return void
	 */
	public function handle_save_pinned_posts() {
		self::guard_admin_request( 'ipa_save_pinned_posts', 'save_pins', 20, 60 );

		$ids = isset( $_POST['ipa_pinned_ids'] )
			? array_map( 'intval', (array) wp_unslash( $_POST['ipa_pinned_ids'] ) )
			: array();

		$count = IPA_DB::set_manual_pinned_posts( $ids );

		if ( $count > 0 ) {
			$message = sprintf(
				/* translators: %d: number of pinned posts */
				_n( 'Pinned %d post to the top of your grid.', 'Pinned %d posts to the top of your grid.', $count, 'instagram-profile-archive' ),
				$count
			);
		} else {
			$message = __( 'Pinned posts cleared. Future syncs may detect pins from Instagram again.', 'instagram-profile-archive' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => 'sync',
					'ipa_notice' => 'success',
					'ipa_msg'    => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete all archived posts or highlights.
	 *
	 * @return void
	 */
	public function handle_delete_all_archive() {
		self::guard_admin_request( 'ipa_delete_all_archive', 'delete_all_archive', 5, 300 );

		$scope              = isset( $_POST['ipa_delete_scope'] ) ? sanitize_key( wp_unslash( $_POST['ipa_delete_scope'] ) ) : '';
		$delete_attachments = ! empty( $_POST['ipa_delete_attachments'] );
		$deleted_media      = 0;
		$deleted_highlights = 0;
		$notice             = 'success';
		$message            = '';

		if ( 'media' === $scope ) {
			$deleted_media = IPA_DB::delete_all_archived_media( $delete_attachments );
			$message       = sprintf(
				/* translators: %d: number of archived posts deleted */
				_n( 'Deleted all %d archived post.', 'Deleted all %d archived posts.', $deleted_media, 'instagram-profile-archive' ),
				$deleted_media
			);
		} elseif ( 'highlights' === $scope ) {
			$deleted_highlights = IPA_DB::delete_all_highlights( $delete_attachments );
			$message            = sprintf(
				/* translators: %d: number of highlights deleted */
				_n( 'Deleted all %d highlight.', 'Deleted all %d highlights.', $deleted_highlights, 'instagram-profile-archive' ),
				$deleted_highlights
			);
		} elseif ( 'all' === $scope ) {
			$deleted_media      = IPA_DB::delete_all_archived_media( $delete_attachments );
			$deleted_highlights = IPA_DB::delete_all_highlights( $delete_attachments );
			$message            = sprintf(
				/* translators: 1: posts deleted, 2: highlights deleted */
				__( 'Deleted all archived content: %1$d posts and %2$d highlights.', 'instagram-profile-archive' ),
				$deleted_media,
				$deleted_highlights
			);
		} else {
			$notice  = 'error';
			$message = __( 'Invalid delete request.', 'instagram-profile-archive' );
		}

		if ( 'success' === $notice && 0 === $deleted_media && 0 === $deleted_highlights ) {
			$notice  = 'warning';
			$message = __( 'Nothing was deleted. There may be no archived content to remove.', 'instagram-profile-archive' );
		}

		if ( 'success' === $notice ) {
			IPA_Security::audit(
				'archive_bulk_deleted',
				array(
					'scope'       => $scope,
					'media'       => $deleted_media,
					'highlights'  => $deleted_highlights,
					'attachments' => $delete_attachments,
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => $this->get_archive_delete_redirect_tab( $scope ),
					'ipa_notice' => $notice,
					'ipa_msg'    => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Get admin page URL for a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public function get_tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}
}
