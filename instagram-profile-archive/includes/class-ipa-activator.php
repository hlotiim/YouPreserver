<?php
/**
 * Plugin activation handler.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Activator
 */
class IPA_Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		IPA_DB::create_tables();
		self::create_instagram_page();
		self::set_default_options();
		IPA_Cron::instance()->schedule_sync();
		flush_rewrite_rules();
	}

	/**
	 * Ensure the public archive page uses the configured slug.
	 *
	 * @return void
	 */
	public static function ensure_archive_page_slug() {
		$target_slug = ipa_get_archive_page_slug();
		$page_id     = (int) get_option( 'ipa_instagram_page_id', 0 );
		$changed     = false;

		if ( $page_id > 0 ) {
			$page = get_post( $page_id );
			if ( $page instanceof WP_Post && 'page' === $page->post_type ) {
				if ( $page->post_name !== $target_slug ) {
					wp_update_post(
						array(
							'ID'        => $page_id,
							'post_name' => $target_slug,
						)
					);
					$changed = true;
				}
			} else {
				$page_id = 0;
			}
		}

		if ( $page_id <= 0 ) {
			$gallery_page = get_page_by_path( $target_slug, OBJECT, 'page' );
			if ( $gallery_page instanceof WP_Post && self::page_has_archive_shortcode( $gallery_page ) ) {
				update_option( 'ipa_instagram_page_id', $gallery_page->ID );
				$page_id = (int) $gallery_page->ID;
			} elseif ( ( $legacy_page = get_page_by_path( 'instagram', OBJECT, 'page' ) ) instanceof WP_Post && self::page_has_archive_shortcode( $legacy_page ) ) {
				wp_update_post(
					array(
						'ID'        => $legacy_page->ID,
						'post_name' => $target_slug,
					)
				);
				update_option( 'ipa_instagram_page_id', $legacy_page->ID );
				$page_id = (int) $legacy_page->ID;
				$changed = true;
			}
		}

		if ( $changed ) {
			flush_rewrite_rules( false );
		}

		if ( $page_id > 0 ) {
			ipa_sync_gallery_page_title();
		}
	}

	/**
	 * Create the public archive page.
	 *
	 * @return int Page ID.
	 */
	public static function create_instagram_page() {
		$target_slug = ipa_get_archive_page_slug();
		$existing_id = (int) get_option( 'ipa_instagram_page_id', 0 );

		if ( $existing_id > 0 ) {
			$page = get_post( $existing_id );
			if ( $page instanceof WP_Post && 'page' === $page->post_type ) {
				if ( $page->post_name !== $target_slug ) {
					wp_update_post(
						array(
							'ID'        => $existing_id,
							'post_name' => $target_slug,
						)
					);
				}
				ipa_sync_gallery_page_title();
				return $existing_id;
			}
		}

		$page_by_slug = get_page_by_path( $target_slug, OBJECT, 'page' );
		if ( $page_by_slug instanceof WP_Post ) {
			update_option( 'ipa_instagram_page_id', $page_by_slug->ID );
			wp_update_post(
				array(
					'ID'         => $page_by_slug->ID,
					'post_title' => ipa_get_gallery_page_title(),
				)
			);
			return $page_by_slug->ID;
		}

		$legacy_page = get_page_by_path( 'instagram', OBJECT, 'page' );
		if ( $legacy_page instanceof WP_Post && self::page_has_archive_shortcode( $legacy_page ) ) {
			wp_update_post(
				array(
					'ID'        => $legacy_page->ID,
					'post_name' => $target_slug,
				)
			);
			update_option( 'ipa_instagram_page_id', $legacy_page->ID );
			return $legacy_page->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => ipa_get_gallery_page_title(),
				'post_name'    => $target_slug,
				'post_content' => '[instagram_profile_archive]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id() ?: 1,
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( 'ipa_instagram_page_id', $page_id );

		return (int) $page_id;
	}

	/**
	 * @param WP_Post $page Page object.
	 * @return bool
	 */
	private static function page_has_archive_shortcode( $page ) {
		return has_shortcode( (string) $page->post_content, 'instagram_profile_archive' );
	}

	/**
	 * Set default plugin options on first activation.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = IPA_Settings::get_defaults();

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( 'ipa_' . $key ) ) {
				add_option( 'ipa_' . $key, $value );
			}
		}

		add_option( 'ipa_enable_mock_mode', false );
		add_option( 'ipa_db_version', IPA_DB::DB_VERSION );
	}
}
