<?php
/**
 * Plugin deactivation handler.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Deactivator
 */
class IPA_Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( IPA_Cron::HOOK_SYNC );
		wp_clear_scheduled_hook( IPA_Cron::HOOK_TOKEN );
		flush_rewrite_rules();
	}
}
