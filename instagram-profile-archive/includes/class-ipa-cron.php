<?php
/**
 * WP-Cron scheduling.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Cron
 */
class IPA_Cron {

	const HOOK_SYNC  = 'ipa_cron_sync_instagram';
	const HOOK_TOKEN = 'ipa_cron_refresh_instagram_token';

	/**
	 * Singleton instance.
	 *
	 * @var IPA_Cron|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return IPA_Cron
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
		$this->register_hooks();
	}

	/**
	 * Register cron action hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( self::HOOK_SYNC, array( $this, 'run_scheduled_sync' ) );
		add_action( self::HOOK_TOKEN, array( $this, 'run_token_refresh_check' ) );
		add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_custom_schedules( $schedules ) {
		$schedules['twice_daily_ipa'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily (YouPreserver)', 'instagram-profile-archive' ),
		);
		return $schedules;
	}

	/**
	 * Schedule sync and token refresh events.
	 *
	 * @return void
	 */
	public function schedule_sync() {
		$this->reschedule();
		$this->schedule_token_refresh();
	}

	/**
	 * Clear sync cron event.
	 *
	 * @return void
	 */
	public function clear_sync() {
		wp_clear_scheduled_hook( self::HOOK_SYNC );
	}

	/**
	 * Clear token refresh cron event.
	 *
	 * @return void
	 */
	public function clear_token_refresh() {
		wp_clear_scheduled_hook( self::HOOK_TOKEN );
	}

	/**
	 * Reschedule cron based on settings.
	 *
	 * @return void
	 */
	public function reschedule() {
		wp_clear_scheduled_hook( self::HOOK_SYNC );

		if ( ! ipa_get_setting( 'enable_auto_sync', false ) ) {
			return;
		}

		$frequency  = ipa_get_setting( 'sync_frequency', 'daily' );
		$recurrence = 'daily';

		if ( 'hourly' === $frequency ) {
			$recurrence = 'hourly';
		} elseif ( 'twice_daily' === $frequency ) {
			$recurrence = 'twice_daily_ipa';
		}

		if ( ! wp_next_scheduled( self::HOOK_SYNC ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::HOOK_SYNC );
		}
	}

	/**
	 * Schedule daily token refresh check.
	 *
	 * @return void
	 */
	public function schedule_token_refresh() {
		if ( ! wp_next_scheduled( self::HOOK_TOKEN ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_TOKEN );
		}
	}

	/**
	 * Run scheduled sync.
	 *
	 * @return void
	 */
	public function run_scheduled_sync() {
		if ( ipa_get_setting( 'enable_mock_mode', true ) ) {
			return;
		}

		$token = new IPA_Token();
		if ( ! $token->is_token_configured() || $token->is_token_expired() ) {
			return;
		}

		$sync = new IPA_Sync();
		$sync->run_cron_sync();

		$cursor = IPA_DB::get_cursor( IPA_DB::CURSOR_FULL_SYNC );
		if ( $cursor && ! empty( $cursor->cursor_value ) ) {
			$sync->run_full_sync( true );
		}
	}

	/**
	 * Refresh token if expiring within 7 days.
	 *
	 * @return void
	 */
	public function run_token_refresh_check() {
		$token = new IPA_Token();

		if ( ! $token->is_token_configured() || $token->is_token_expired() ) {
			return;
		}

		if ( ! $token->is_token_expiring_soon( 7 ) ) {
			return;
		}

		$last_refreshed = ipa_get_setting( 'token_last_refreshed_at', '' );
		if ( $last_refreshed && ( time() - strtotime( $last_refreshed ) ) < DAY_IN_SECONDS ) {
			return;
		}

		$token->refresh_token();
	}
}
