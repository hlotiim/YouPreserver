<?php
/**
 * Plugin settings management.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Settings
 */
class IPA_Settings {

	/**
	 * Default settings values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults() {
		return array(
			'ig_user_id'                => '',
			'access_token'              => '',
			'token_type'                => 'bearer',
			'token_expires_at'          => '',
			'token_last_refreshed_at'   => '',
			'app_id'                    => '',
			'app_secret'                => '',
			'api_version'               => 'v23.0',
			'enable_auto_sync'          => false,
			'sync_frequency'            => 'daily',
			'posts_per_request'         => 25,
			'max_posts_per_sync'        => 100,
			'download_media_locally'    => true,
			'download_videos_locally'   => true,
			'sync_captions'             => true,
			'sync_carousels'            => true,
			'sync_only_new'             => true,
			'preserve_deleted'          => true,
			'delete_missing'            => false,
			'last_successful_sync'      => '',
			'last_sync_status'          => '',
			'last_sync_message'         => '',
			'display_name'              => '',
			'username'                  => '',
			'category'                  => '',
			'bio'                       => '',
			'profile_image_id'          => 0,
			'profile_image_url'         => '',
			'external_link'             => '',
			'secondary_link'            => '',
			'secondary_link_label'      => '',
			'show_stats'                => true,
			'posts_count_label'         => '',
			'followers_count'           => '',
			'following_count'           => '',
			'button_text'               => 'View on Instagram',
			'message_button_text'       => 'Message',
			'message_button_url'        => '',
			'instagram_profile_url'     => '',
			'show_highlights'           => true,
			'show_bottom_nav'           => false,
			'layout_width'              => '390',
			'show_captions_modal'       => true,
			'show_dates_modal'          => true,
			'show_instagram_link_modal' => true,
			'enable_dark_mode'          => false,
			'enable_sticky_header'      => true,
			'enable_mock_mode'          => false,
			'enable_debug_logging'      => false,
			'delete_data_on_uninstall'  => false,
		);
	}

	/**
	 * Mask placeholder used in admin password fields.
	 *
	 * @return string
	 */
	public static function get_masked_secret_placeholder() {
		return '********';
	}

	/**
	 * Check whether submitted secret should be preserved.
	 *
	 * @param string $submitted Submitted value.
	 * @return bool
	 */
	public static function should_preserve_secret( $submitted ) {
		$submitted = trim( (string) $submitted );
		return '' === $submitted || self::get_masked_secret_placeholder() === $submitted;
	}

	/**
	 * Options that contain sensitive data and must be encrypted at rest.
	 *
	 * @return array<int, string>
	 */
	public static function get_encrypted_keys() {
		return array( 'access_token', 'app_secret' );
	}

	/**
	 * Set a sensitive option using encryption.
	 *
	 * @param string $key   Option key without ipa_ prefix.
	 * @param string $value Plaintext value.
	 * @return void
	 */
	public static function set_secure( $key, $value ) {
		$encrypted = IPA_Security::encrypt( (string) $value );
		update_option( 'ipa_' . $key, $encrypted, false );
	}

	/**
	 * Get all settings merged with defaults (sensitive fields decrypted).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		$settings = self::get_defaults();
		$secure   = self::get_encrypted_keys();

		foreach ( array_keys( $settings ) as $key ) {
			$value = get_option( 'ipa_' . $key, null );
			if ( null !== $value ) {
				if ( in_array( $key, $secure, true ) ) {
					$value = IPA_Security::decrypt( $value );
				}
				$settings[ $key ] = $value;
			}
		}

		return $settings;
	}

	/**
	 * Get a single setting (sensitive fields decrypted).
	 *
	 * @param string $key     Setting key without ipa_ prefix.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$defaults = self::get_defaults();
		$fallback = null !== $default ? $default : ( $defaults[ $key ] ?? '' );
		$value    = get_option( 'ipa_' . $key, $fallback );

		if ( in_array( $key, self::get_encrypted_keys(), true ) ) {
			$value = IPA_Security::decrypt( (string) $value );
		}

		return $value;
	}

	/**
	 * Save settings from POST data for a given tab.
	 *
	 * @param string $tab Tab identifier.
	 * @return void
	 */
	public static function save_from_post( $tab ) {
		switch ( $tab ) {
			case 'connection':
				self::save_connection_settings();
				break;
			case 'sync':
				self::save_sync_settings();
				break;
			case 'profile':
				self::save_profile_settings();
				break;
			case 'highlights':
				self::save_highlights_settings();
				break;
			case 'tools':
				self::save_tools_settings();
				break;
		}
	}

	/**
	 * Save app credentials from POST (connection tab / connect flow).
	 *
	 * @return void
	 */
	public static function save_app_credentials_from_post() {
		update_option( 'ipa_app_id', self::sanitize_app_id( wp_unslash( $_POST['ipa_app_id'] ?? '' ) ) );

		$secret = sanitize_text_field( wp_unslash( $_POST['ipa_app_secret'] ?? '' ) );
		if ( ! self::should_preserve_secret( $secret ) ) {
			self::set_secure( 'app_secret', $secret );
			if ( class_exists( 'IPA_Security' ) ) {
				IPA_Security::audit( 'app_secret_updated' );
			}
		}

		update_option( 'ipa_api_version', self::sanitize_api_version( wp_unslash( $_POST['ipa_api_version'] ?? 'v23.0' ) ) );
	}

	/**
	 * Normalize the API version string (e.g. "v23.0").
	 *
	 * @param string $version Raw version.
	 * @return string
	 */
	public static function sanitize_api_version( $version ) {
		$version = preg_replace( '/[^v0-9.]/', '', (string) $version );
		if ( '' === $version ) {
			return 'v23.0';
		}
		if ( 0 !== strpos( $version, 'v' ) ) {
			$version = 'v' . ltrim( $version, 'v' );
		}
		return $version;
	}

	/**
	 * Normalize Instagram App ID input.
	 *
	 * @param string $app_id Raw app ID.
	 * @return string
	 */
	public static function sanitize_app_id( $app_id ) {
		return preg_replace( '/\D+/', '', (string) $app_id );
	}

	/**
	 * Validate stored Instagram App credentials format.
	 *
	 * @return string Error message or empty string if valid.
	 */
	public static function validate_app_credentials_format() {
		$app_id = trim( (string) ipa_get_setting( 'app_id', '' ) );

		if ( empty( $app_id ) ) {
			return __( 'Instagram App ID is missing. Please enter your Meta App ID first.', 'instagram-profile-archive' );
		}

		if ( ! preg_match( '/^\d{5,20}$/', $app_id ) ) {
			return __( 'Instagram App ID must be a numeric ID from Meta App Dashboard → Instagram → API setup with Instagram login → Business login settings. Do not use your Instagram username here.', 'instagram-profile-archive' );
		}

		if ( empty( trim( (string) ipa_get_setting( 'app_secret', '' ) ) ) ) {
			return __( 'Instagram App Secret is missing. Please enter your Meta App Secret first.', 'instagram-profile-archive' );
		}

		return '';
	}

	/**
	 * @return string
	 */
	public static function get_missing_credentials_message() {
		$format_error = self::validate_app_credentials_format();
		if ( $format_error ) {
			return $format_error;
		}

		return '';
	}

	/**
	 * Save connection tab settings.
	 *
	 * @return void
	 */
	private static function save_connection_settings() {
		self::save_app_credentials_from_post();
	}

	/**
	 * Save sync tab settings.
	 *
	 * @return void
	 */
	private static function save_sync_settings() {
		update_option( 'ipa_enable_auto_sync', ! empty( $_POST['ipa_enable_auto_sync'] ) );
		update_option( 'ipa_sync_frequency', sanitize_text_field( wp_unslash( $_POST['ipa_sync_frequency'] ?? 'daily' ) ) );
		update_option( 'ipa_posts_per_request', min( 50, max( 1, (int) ( $_POST['ipa_posts_per_request'] ?? 25 ) ) ) );
		update_option( 'ipa_max_posts_per_sync', max( 1, (int) ( $_POST['ipa_max_posts_per_sync'] ?? 100 ) ) );
		update_option( 'ipa_download_media_locally', ! empty( $_POST['ipa_download_media_locally'] ) );
		update_option( 'ipa_download_videos_locally', ! empty( $_POST['ipa_download_videos_locally'] ) );
		update_option( 'ipa_sync_captions', ! empty( $_POST['ipa_sync_captions'] ) );
		update_option( 'ipa_sync_carousels', ! empty( $_POST['ipa_sync_carousels'] ) );
		update_option( 'ipa_sync_only_new', ! empty( $_POST['ipa_sync_only_new'] ) );
		update_option( 'ipa_preserve_deleted', ! empty( $_POST['ipa_preserve_deleted'] ) );
		update_option( 'ipa_delete_missing', ! empty( $_POST['ipa_delete_missing'] ) );
		update_option( 'ipa_enable_mock_mode', ! empty( $_POST['ipa_enable_mock_mode'] ) );
		update_option( 'ipa_enable_debug_logging', ! empty( $_POST['ipa_enable_debug_logging'] ) );

		IPA_Cron::instance()->reschedule();
	}

	/**
	 * Save profile display settings.
	 *
	 * @return void
	 */
	private static function save_profile_settings() {
		update_option( 'ipa_display_name', sanitize_text_field( wp_unslash( $_POST['ipa_display_name'] ?? '' ) ) );
		update_option( 'ipa_username', sanitize_text_field( wp_unslash( $_POST['ipa_username'] ?? '' ) ) );
		update_option( 'ipa_category', sanitize_text_field( wp_unslash( $_POST['ipa_category'] ?? '' ) ) );
		update_option( 'ipa_bio', sanitize_textarea_field( wp_unslash( $_POST['ipa_bio'] ?? '' ) ) );
		update_option( 'ipa_profile_image_id', absint( $_POST['ipa_profile_image_id'] ?? 0 ) );
		update_option( 'ipa_profile_image_url', esc_url_raw( wp_unslash( $_POST['ipa_profile_image_url'] ?? '' ) ) );
		update_option( 'ipa_external_link', esc_url_raw( wp_unslash( $_POST['ipa_external_link'] ?? '' ) ) );
		update_option( 'ipa_secondary_link', esc_url_raw( wp_unslash( $_POST['ipa_secondary_link'] ?? '' ) ) );
		update_option( 'ipa_secondary_link_label', sanitize_text_field( wp_unslash( $_POST['ipa_secondary_link_label'] ?? '' ) ) );
		update_option( 'ipa_show_stats', ! empty( $_POST['ipa_show_stats'] ) );
		update_option( 'ipa_posts_count_label', sanitize_text_field( wp_unslash( $_POST['ipa_posts_count_label'] ?? '' ) ) );
		update_option( 'ipa_followers_count', sanitize_text_field( wp_unslash( $_POST['ipa_followers_count'] ?? '' ) ) );
		update_option( 'ipa_following_count', sanitize_text_field( wp_unslash( $_POST['ipa_following_count'] ?? '' ) ) );
		update_option( 'ipa_button_text', sanitize_text_field( wp_unslash( $_POST['ipa_button_text'] ?? 'View on Instagram' ) ) );
		update_option( 'ipa_message_button_text', sanitize_text_field( wp_unslash( $_POST['ipa_message_button_text'] ?? 'Message' ) ) );
		update_option( 'ipa_message_button_url', esc_url_raw( wp_unslash( $_POST['ipa_message_button_url'] ?? '' ) ) );
		update_option( 'ipa_instagram_profile_url', esc_url_raw( wp_unslash( $_POST['ipa_instagram_profile_url'] ?? '' ) ) );
		update_option( 'ipa_show_bottom_nav', ! empty( $_POST['ipa_show_bottom_nav'] ) );
		update_option( 'ipa_layout_width', sanitize_text_field( wp_unslash( $_POST['ipa_layout_width'] ?? '390' ) ) );
		update_option( 'ipa_show_captions_modal', ! empty( $_POST['ipa_show_captions_modal'] ) );
		update_option( 'ipa_show_dates_modal', ! empty( $_POST['ipa_show_dates_modal'] ) );
		update_option( 'ipa_show_instagram_link_modal', ! empty( $_POST['ipa_show_instagram_link_modal'] ) );
		update_option( 'ipa_enable_dark_mode', ! empty( $_POST['ipa_enable_dark_mode'] ) );
		update_option( 'ipa_enable_sticky_header', ! empty( $_POST['ipa_enable_sticky_header'] ) );

		ipa_sync_gallery_page_title();
		ipa_clear_frontend_cache();
	}

	/**
	 * Save highlights tab settings.
	 *
	 * @return void
	 */
	private static function save_highlights_settings() {
		update_option( 'ipa_show_highlights', ! empty( $_POST['ipa_show_highlights'] ) );
		ipa_clear_frontend_cache();
	}

	/**
	 * Save tools tab settings.
	 *
	 * @return void
	 */
	private static function save_tools_settings() {
		update_option( 'ipa_delete_data_on_uninstall', ! empty( $_POST['ipa_delete_data_on_uninstall'] ) );
	}
}
