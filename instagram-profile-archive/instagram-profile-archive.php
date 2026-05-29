<?php
/**
 * Plugin Name:       YouPreserver
 * Plugin URI:        https://roktimsaha.com
 * Description:       YouPreserver preserves and displays your Instagram profile media on a public WordPress page.
 * Version:           1.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Roktim Saha
 * Author URI:        https://roktimsaha.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       instagram-profile-archive
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

define( 'IPA_VERSION', '1.6.0' );
define( 'IPA_AUTHOR_NAME', 'Roktim Saha' );
define( 'IPA_AUTHOR_URL', 'https://roktimsaha.com' );
define( 'IPA_ARCHIVE_PAGE_SLUG', 'gallery' );
define( 'IPA_PLUGIN_FILE', __FILE__ );
define( 'IPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IPA_UPLOAD_DIR', 'instagram-archive' );

require_once IPA_PLUGIN_DIR . 'includes/helpers.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-security.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-db.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-settings.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-activator.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-deactivator.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-api.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-media-downloader.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-logger.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-token.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-oauth.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-sync.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-highlights.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-highlights-import.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-cron.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-ajax.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-frontend.php';
require_once IPA_PLUGIN_DIR . 'includes/class-ipa-admin.php';

/**
 * Main plugin bootstrap class.
 */
final class Instagram_Profile_Archive {

	/**
	 * Singleton instance.
	 *
	 * @var Instagram_Profile_Archive|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Instagram_Profile_Archive
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
		register_activation_hook( IPA_PLUGIN_FILE, array( 'IPA_Activator', 'activate' ) );
		register_deactivation_hook( IPA_PLUGIN_FILE, array( 'IPA_Deactivator', 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	public function init() {
		load_plugin_textdomain( 'instagram-profile-archive', false, dirname( plugin_basename( IPA_PLUGIN_FILE ) ) . '/languages' );

		IPA_DB::maybe_upgrade();
		IPA_Activator::ensure_archive_page_slug();

		add_action( 'init', array( 'IPA_OAuth', 'maybe_process_callback' ), 1 );
		add_action( 'rest_api_init', array( 'IPA_OAuth', 'register_rest_route' ) );

		IPA_Frontend::instance();
		IPA_Admin::instance();
		IPA_Cron::instance();
		IPA_AJAX::instance();

		add_action( 'admin_notices', array( $this, 'maybe_show_token_notice' ) );
	}

	/**
	 * Show token expiry admin notice on plugin pages.
	 *
	 * @return void
	 */
	public function maybe_show_token_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'toplevel_page_instagram-profile-archive' !== $screen->id ) {
			return;
		}

		$token  = new IPA_Token();
		$status = $token->get_token_status();

		if ( 'expired' === $status['status'] ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Your Instagram token is expired. Sync will not work until you reconnect or refresh the token.', 'instagram-profile-archive' ) . '</p></div>';
		} elseif ( 'expiring' === $status['status'] ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Your Instagram token will expire soon. Refresh it to keep automatic sync working.', 'instagram-profile-archive' ) . '</p></div>';
		}
	}
}

Instagram_Profile_Archive::instance();
