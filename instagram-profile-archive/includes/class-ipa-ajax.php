<?php
/**
 * AJAX handlers.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_AJAX
 */
class IPA_AJAX {

	/**
	 * Singleton instance.
	 *
	 * @var IPA_AJAX|null
	 */
	private static $instance = null;

	/**
	 * @return IPA_AJAX
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
		add_action( 'wp_ajax_ipa_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ipa_manual_sync', array( $this, 'ajax_manual_sync' ) );
		add_action( 'wp_ajax_ipa_refresh_token', array( $this, 'ajax_refresh_token' ) );
		add_action( 'wp_ajax_ipa_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_ipa_reset_cursor', array( $this, 'ajax_reset_cursor' ) );
		add_action( 'wp_ajax_ipa_load_more', array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_ipa_load_more', array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_ipa_highlights_import_start', array( $this, 'ajax_highlights_import_start' ) );
		add_action( 'wp_ajax_ipa_highlights_import_step', array( $this, 'ajax_highlights_import_step' ) );
		add_action( 'wp_ajax_ipa_highlights_import_cancel', array( $this, 'ajax_highlights_import_cancel' ) );
	}

	/**
	 * Verify admin AJAX request.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function verify_admin_request( $action, $rate_bucket = '', $rate_max = 30, $rate_window = 60 ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			IPA_Security::audit( 'ajax_permission_denied', array( 'action' => $action ) );
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'instagram-profile-archive' ) ), 403 );
		}

		check_ajax_referer( $action, 'nonce' );

		IPA_Security::send_security_headers();

		if ( $rate_bucket && ! IPA_Security::rate_limit_allow( $rate_bucket, $rate_max, $rate_window ) ) {
			IPA_Security::audit( 'ajax_rate_limited', array( 'action' => $rate_bucket ) );
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please slow down.', 'instagram-profile-archive' ) ),
				429
			);
		}
	}

	/**
	 * @return void
	 */
	public function ajax_test_connection() {
		$this->verify_admin_request( 'ipa_admin_ajax', 'ajax_test_conn', 10, 60 );

		$api    = new IPA_API();
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connected successfully.', 'instagram-profile-archive' ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_manual_sync() {
		$this->verify_admin_request( 'ipa_admin_ajax', 'ajax_sync', 6, 60 );

		$sync   = new IPA_Sync();
		$result = $sync->run_manual_sync();

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? '' ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * @return void
	 */
	public function ajax_refresh_token() {
		$this->verify_admin_request( 'ipa_admin_ajax', 'ajax_refresh', 5, 60 );

		$token  = new IPA_Token();
		$result = $token->refresh_token();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Token refreshed successfully.', 'instagram-profile-archive' ),
				'status'  => $token->get_token_status(),
			)
		);
	}

	/**
	 * @return void
	 */
	public function ajax_clear_logs() {
		$this->verify_admin_request( 'ipa_admin_ajax' );

		IPA_DB::clear_sync_logs();

		wp_send_json_success( array( 'message' => __( 'Sync logs cleared.', 'instagram-profile-archive' ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_reset_cursor() {
		$this->verify_admin_request( 'ipa_admin_ajax' );

		IPA_DB::delete_cursor( IPA_DB::CURSOR_FULL_SYNC );

		wp_send_json_success( array( 'message' => __( 'Full sync cursor reset.', 'instagram-profile-archive' ) ) );
	}

	/**
	 * Frontend load more — local DB only.
	 *
	 * @return void
	 */
	public function ajax_load_more() {
		check_ajax_referer( 'ipa_load_more', 'nonce' );

		if ( ! IPA_Security::rate_limit_allow( 'load_more', 60, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests.', 'instagram-profile-archive' ) ), 429 );
		}

		$offset          = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
		$filter_input    = isset( $_POST['filter'] ) ? sanitize_text_field( wp_unslash( $_POST['filter'] ) ) : 'posts';
		$allowed_filters = array( 'posts', 'reels', 'all', 'videos', 'images', 'carousel' );
		$filter          = in_array( $filter_input, $allowed_filters, true ) ? $filter_input : 'posts';
		if ( 'all' === $filter ) {
			$filter = 'posts';
		}
		$limit           = 60;

		if ( ipa_get_setting( 'enable_mock_mode', true ) ) {
			wp_send_json_success(
				array(
					'html'     => '',
					'has_more' => false,
					'offset'   => $offset,
				)
			);
		}

		$frontend = IPA_Frontend::instance();
		$items    = IPA_DB::get_frontend_media( $limit, $offset, $filter );
		$media    = $frontend->format_db_media_for_frontend( $items );
		$html     = $frontend->render_grid_items_html( $media, $offset );
		$total    = IPA_DB::count_media( $filter );

		wp_send_json_success(
			array(
				'html'        => $html,
				'modal'       => ipa_build_modal_payload( $media ),
				'has_more'    => ( $offset + count( $items ) ) < $total,
				'next_offset' => $offset + count( $items ),
				'offset'      => $offset + count( $items ),
			)
		);
	}

	/**
	 * Start a chunked highlights ZIP import.
	 *
	 * @return void
	 */
	public function ajax_highlights_import_start() {
		$this->verify_admin_request( 'ipa_admin_ajax', 'hl_import_start', 10, 300 );

		if ( empty( $_FILES['ipa_highlights_zip']['tmp_name'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please choose a highlights ZIP file to import.', 'instagram-profile-archive' ) )
			);
		}

		$file = $_FILES['ipa_highlights_zip']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'File upload failed. Check your server upload limits.', 'instagram-profile-archive' ) )
			);
		}

		$checked = wp_check_filetype( $file['name'] ?? '' );
		if ( 'zip' !== strtolower( (string) ( $checked['ext'] ?? '' ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please upload a .zip file exported by the YouPreserver Highlights Chrome extension.', 'instagram-profile-archive' ) )
			);
		}

		$replace_existing = ! empty( $_POST['ipa_replace_highlights'] );
		$result           = ipa_highlights_import_create_job( $file['tmp_name'], $replace_existing, true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'job_id'   => $result['job_id'],
				'total'    => (int) ( $result['total'] ?? 0 ),
				'username' => (string) ( $result['username'] ?? '' ),
				'message'  => __( 'ZIP uploaded. Extracting and importing highlights…', 'instagram-profile-archive' ),
			)
		);
	}

	/**
	 * Process the next highlights import batch.
	 *
	 * @return void
	 */
	public function ajax_highlights_import_step() {
		$this->verify_admin_request( 'ipa_admin_ajax', 'hl_import_step', 300, 300 );

		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		if ( '' === $job_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing import job ID.', 'instagram-profile-archive' ) ) );
		}

		$result = ipa_highlights_import_step( $job_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Cancel an in-progress highlights import.
	 *
	 * @return void
	 */
	public function ajax_highlights_import_cancel() {
		$this->verify_admin_request( 'ipa_admin_ajax', 'hl_import_cancel', 20, 300 );

		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		if ( '' === $job_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing import job ID.', 'instagram-profile-archive' ) ) );
		}

		$result = ipa_highlights_import_cancel( $job_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array( 'message' => __( 'Highlights import cancelled.', 'instagram-profile-archive' ) )
		);
	}
}
