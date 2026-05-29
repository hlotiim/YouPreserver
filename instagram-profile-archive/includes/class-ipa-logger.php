<?php
/**
 * Centralized sync logging.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Logger
 */
class IPA_Logger {

	/**
	 * Current log ID.
	 *
	 * @var int
	 */
	private $log_id = 0;

	/**
	 * Start a sync log entry.
	 *
	 * @param string $sync_type Sync type.
	 * @return int
	 */
	public function start_sync( $sync_type ) {
		$sync_id = wp_generate_uuid4();

		$this->log_id = IPA_DB::insert_log(
			array(
				'sync_id'   => $sync_id,
				'sync_type' => sanitize_text_field( $sync_type ),
				'status'    => 'running',
				'message'   => __( 'Sync started.', 'instagram-profile-archive' ),
			)
		);

		return $this->log_id;
	}

	/**
	 * Complete sync log.
	 *
	 * @param int                  $log_id  Log ID.
	 * @param array<string, mixed> $summary Summary.
	 * @return void
	 */
	public function complete_sync( $log_id, $summary ) {
		IPA_DB::update_log(
			$log_id,
			array(
				'status'            => $summary['status'] ?? 'success',
				'message'           => $summary['message'] ?? '',
				'technical_message' => $summary['technical_message'] ?? '',
				'total_found'       => (int) ( $summary['total_found'] ?? 0 ),
				'total_new'         => (int) ( $summary['total_new'] ?? 0 ),
				'total_updated'     => (int) ( $summary['total_updated'] ?? 0 ),
				'total_skipped'     => (int) ( $summary['total_skipped'] ?? 0 ),
				'total_failed'      => (int) ( $summary['total_failed'] ?? 0 ),
				'api_calls_used'    => (int) ( $summary['api_calls_used'] ?? 0 ),
				'completed_at'      => current_time( 'mysql', true ),
			)
		);

		$status = $summary['status'] ?? 'success';
		update_option( 'ipa_last_sync_status', $status );
		update_option( 'ipa_last_sync_message', $summary['message'] ?? '' );

		if ( in_array( $status, array( 'success', 'rate_limited', 'partial' ), true ) ) {
			update_option( 'ipa_last_successful_sync', current_time( 'mysql', true ) );
		}
	}

	/**
	 * Fail sync log.
	 *
	 * @param int    $log_id            Log ID.
	 * @param string $message           Message.
	 * @param string $technical_message Technical message.
	 * @return void
	 */
	public function fail_sync( $log_id, $message, $technical_message = '' ) {
		$this->complete_sync(
			$log_id,
			array(
				'status'            => 'failed',
				'message'           => $message,
				'technical_message' => $technical_message,
			)
		);

		update_option( 'ipa_last_sync_status', 'failed' );
		update_option( 'ipa_last_sync_message', $message );
	}

	/**
	 * Debug log.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public function info( $message, $context = array() ) {
		if ( ! ipa_get_setting( 'enable_debug_logging', false ) ) {
			return;
		}

		error_log( '[IPA] ' . $message . ' ' . $this->format_context( $context ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public function warning( $message, $context = array() ) {
		$this->info( 'WARNING: ' . $message, $context );
	}

	/**
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public function error( $message, $context = array() ) {
		$this->info( 'ERROR: ' . $message, $context );
	}

	/**
	 * Mask sensitive values in context.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	private function format_context( $context ) {
		if ( empty( $context ) ) {
			return '';
		}

		$safe = class_exists( 'IPA_Security' )
			? IPA_Security::mask_sensitive_data( $context )
			: $context;

		return wp_json_encode( $safe );
	}
}
