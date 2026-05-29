<?php
/**
 * Frontend shortcode and assets.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Frontend
 */
class IPA_Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var IPA_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Whether shortcode is present on current page.
	 *
	 * @var bool
	 */
	private $shortcode_rendered = false;

	/**
	 * Get singleton instance.
	 *
	 * @return IPA_Frontend
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
		add_shortcode( 'instagram_profile_archive', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 100 );
		add_action( 'wp_footer', array( $this, 'print_theme_button_reset' ), 9999 );
		add_action( 'wp_footer', array( $this, 'late_enqueue_assets' ), 1 );
		add_filter( 'the_title', array( $this, 'filter_archive_page_title' ), 10, 2 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ) );
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 20 );
		add_filter( 'wpseo_title', array( $this, 'filter_seo_title' ) );
		add_filter( 'wpseo_metadesc', array( $this, 'filter_seo_description' ) );
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_seo_title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_seo_description' ) );
		add_action( 'wp_head', array( $this, 'output_archive_meta_tags' ), 1 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Hide the WordPress page title on the archive page.
	 *
	 * @param string $title Post title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function filter_archive_page_title( $title, $post_id = 0 ) {
		if ( is_admin() || ! $this->is_archive_page_id( (int) $post_id ) ) {
			return $title;
		}

		return '';
	}

	/**
	 * Use the gallery title for the browser tab and SEO meta title.
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public function filter_document_title_parts( $parts ) {
		if ( ! ipa_is_archive_page() ) {
			return $parts;
		}

		$parts['title'] = ipa_get_gallery_page_title();
		unset( $parts['site'], $parts['tagline'] );

		return $parts;
	}

	/**
	 * Override the full document title on the gallery page.
	 *
	 * @param string $title Document title.
	 * @return string
	 */
	public function filter_document_title( $title ) {
		if ( ! ipa_is_archive_page() ) {
			return $title;
		}

		return ipa_get_gallery_page_title();
	}

	/**
	 * Override SEO plugin titles on the gallery page.
	 *
	 * @param string $title SEO title.
	 * @return string
	 */
	public function filter_seo_title( $title ) {
		if ( ! ipa_is_archive_page() ) {
			return $title;
		}

		return ipa_get_gallery_page_title();
	}

	/**
	 * Override SEO plugin meta descriptions on the gallery page.
	 *
	 * @param string $description Meta description.
	 * @return string
	 */
	public function filter_seo_description( $description ) {
		if ( ! ipa_is_archive_page() ) {
			return $description;
		}

		return ipa_get_gallery_page_meta_description();
	}

	/**
	 * Output meta title tags for the gallery page.
	 *
	 * @return void
	 */
	public function output_archive_meta_tags() {
		if ( ! ipa_is_archive_page() ) {
			return;
		}

		$title       = ipa_get_gallery_page_title();
		$description = ipa_get_gallery_page_meta_description();
		printf( '<meta name="title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
			printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
		}
		printf( '<meta property="og:type" content="profile" />' . "\n" );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
	}

	/**
	 * Add body class for archive page styling hooks.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public function add_body_class( $classes ) {
		if ( ipa_is_archive_page() ) {
			$classes[] = 'ipa-archive-page';
		}

		return $classes;
	}

	/**
	 * Check whether a post ID is the Instagram archive page.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_archive_page_id( $post_id ) {
		if ( $post_id <= 0 ) {
			return ipa_is_archive_page();
		}

		$page_id = (int) get_option( 'ipa_instagram_page_id', 0 );

		return $page_id > 0 && $post_id === $page_id;
	}

	/**
	 * Enqueue frontend assets when needed.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if ( ! $this->should_enqueue_assets() ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Late enqueue for shortcodes rendered after wp_enqueue_scripts.
	 *
	 * @return void
	 */
	public function late_enqueue_assets() {
		if ( ! $this->shortcode_rendered || wp_style_is( 'ipa-frontend', 'enqueued' ) ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Check if frontend assets should load.
	 *
	 * @return bool
	 */
	private function should_enqueue_assets() {
		return ipa_is_archive_page() || $this->shortcode_rendered;
	}

	/**
	 * Register and enqueue frontend styles/scripts.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		if ( wp_style_is( 'ipa-frontend', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'ipa-frontend',
			IPA_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			IPA_VERSION
		);

		wp_enqueue_script(
			'ipa-frontend',
			IPA_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			IPA_VERSION,
			true
		);

		$settings = ipa_get_settings();

		wp_localize_script(
			'ipa-frontend',
			'ipaFrontend',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'ipa_load_more' ),
				'showCaptions'         => (bool) $settings['show_captions_modal'],
				'showDates'            => (bool) $settings['show_dates_modal'],
				'showInstagramLink'    => (bool) $settings['show_instagram_link_modal'],
				'loadMoreText'         => __( 'Load more', 'instagram-profile-archive' ),
				'loadingText'          => __( 'Loading…', 'instagram-profile-archive' ),
				'closeLabel'           => __( 'Close', 'instagram-profile-archive' ),
				'noRepostsText'        => __( 'No reposts archived', 'instagram-profile-archive' ),
				'prevLabel'            => __( 'Previous', 'instagram-profile-archive' ),
				'nextLabel'            => __( 'Next', 'instagram-profile-archive' ),
			)
		);
	}

	/**
	 * Print theme reset at the end of the page so it wins over block theme styles.
	 *
	 * @return void
	 */
	public function print_theme_button_reset() {
		if ( ! wp_style_is( 'ipa-frontend', 'enqueued' ) ) {
			return;
		}

		static $printed = false;
		if ( $printed ) {
			return;
		}

		$printed = true;
		echo '<style id="ipa-theme-button-reset">' . wp_strip_all_tags( $this->get_theme_button_reset_css() ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS only, tags stripped.
	}

	/**
	 * CSS overrides for theme pink button backgrounds inside the gallery UI.
	 *
	 * @return string
	 */
	private function get_theme_button_reset_css() {
		return '
body.ipa-archive-page #ipa-modal button.ipa-modal-close,
body.ipa-archive-page .ipa-instagram-archive #ipa-modal button.ipa-modal-close,
#ipa-modal button.ipa-modal-close {
	background:transparent!important;
	background-color:transparent!important;
	background-image:none!important;
	border:none!important;
	border-radius:0!important;
	box-shadow:none!important;
	color:#262626!important;
}
body.ipa-archive-page #ipa-modal button.ipa-modal-close svg,
#ipa-modal button.ipa-modal-close svg {
	stroke:#262626!important;
	color:#262626!important;
	fill:none!important;
}
body.ipa-archive-page #ipa-modal button.ipa-modal-nav,
body.ipa-archive-page .ipa-instagram-archive #ipa-modal button.ipa-modal-nav,
#ipa-modal button.ipa-modal-nav {
	background:#fff!important;
	background-color:#fff!important;
	background-image:none!important;
	color:#262626!important;
	border:none!important;
	border-radius:50%!important;
	box-shadow:none!important;
}
body.ipa-archive-page #ipa-modal button.ipa-modal-nav svg,
#ipa-modal button.ipa-modal-nav svg {
	stroke:#262626!important;
	color:#262626!important;
	fill:none!important;
}
body.ipa-archive-page .ipa-instagram-archive button.ipa-topbar-back,
body.ipa-archive-page .ipa-instagram-archive button.ipa-icon-btn:not(.ipa-modal-nav) {
	background:transparent!important;
	background-color:transparent!important;
	background-image:none!important;
	color:#262626!important;
	border:none!important;
	box-shadow:none!important;
}
#ipa-modal.ipa-modal-highlights button.ipa-modal-close,
#ipa-modal.ipa-modal-highlights button.ipa-modal-close svg {
	color:#fff!important;
	stroke:#fff!important;
}
#ipa-modal.ipa-modal-highlights button.ipa-modal-nav {
	background:rgba(255,255,255,.25)!important;
	background-color:rgba(255,255,255,.25)!important;
	color:#fff!important;
}
#ipa-modal.ipa-modal-highlights button.ipa-modal-nav svg {
	stroke:#fff!important;
	color:#fff!important;
}
body.ipa-archive-page .ipa-instagram-archive button.ipa-highlight:focus,
body.ipa-archive-page .ipa-instagram-archive button.ipa-highlight:focus-visible,
body.ipa-archive-page .ipa-instagram-archive button.ipa-highlight:active,
.ipa-instagram-archive button.ipa-highlight:focus,
.ipa-instagram-archive button.ipa-highlight:focus-visible,
.ipa-instagram-archive button.ipa-highlight:active {
	background:transparent!important;
	background-color:transparent!important;
	border:none!important;
	box-shadow:none!important;
	outline:none!important;
}
body.ipa-archive-page .ipa-instagram-archive button.ipa-highlight:focus .ipa-highlight-ring,
body.ipa-archive-page .ipa-instagram-archive button.ipa-highlight:focus-visible .ipa-highlight-ring,
body.ipa-archive-page .ipa-instagram-archive button.ipa-highlight:active .ipa-highlight-ring,
.ipa-instagram-archive button.ipa-highlight:focus .ipa-highlight-ring,
.ipa-instagram-archive button.ipa-highlight:focus-visible .ipa-highlight-ring,
.ipa-instagram-archive button.ipa-highlight:active .ipa-highlight-ring {
	border-color:#F57600!important;
	border-radius:50%!important;
	box-shadow:0 0 0 2px #F57600!important;
}
#ipa-modal .ipa-modal-footer,
#ipa-modal .ipa-modal-date,
#ipa-modal .ipa-modal-caption,
#ipa-modal .ipa-modal-link,
#ipa-modal .ipa-modal-title,
#ipa-modal .ipa-modal-title.ipa-username-link {
	color:#fff!important;
}
#ipa-modal .ipa-modal-date {
	color:rgba(255,255,255,.75)!important;
}';
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		$this->shortcode_rendered = true;

		// Ensure assets load even when shortcode is detected late.
		$this->maybe_enqueue_assets();

		$settings = ipa_get_settings();
		$data     = $this->get_display_data();

		ob_start();
		include IPA_PLUGIN_DIR . 'templates/instagram-page.php';
		return ob_get_clean();
	}

	/**
	 * Get profile and media data for display.
	 *
	 * @return array<string, mixed>
	 */
	public function get_display_data() {
		$settings   = ipa_get_settings();
		$mock_mode  = ! empty( $settings['enable_mock_mode'] );
		$cache_key  = 'ipa_frontend_data_' . ( $mock_mode ? 'mock' : 'live' );
		$cached     = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		if ( $mock_mode ) {
			$split = ipa_split_posts_and_reels( ipa_get_mock_media_items() );
			$media = $split['posts'];
			$reels = $split['reels'];
			$highlights = ipa_get_mock_highlights();
		} else {
			$media = $this->format_db_media_for_frontend(
				IPA_DB::get_frontend_media( 60, 0, 'posts' )
			);
			$reels = $this->format_db_media_for_frontend(
				IPA_DB::get_frontend_media( 60, 0, 'reels' )
			);
			$highlights = IPA_Highlights::format_for_frontend( IPA_DB::get_active_highlights() );
		}

		$posts_count = $mock_mode ? count( $media ) : IPA_DB::get_total_archived_posts();
		$profile     = $this->build_profile_data( $settings, $posts_count );

		$initial_limit   = 60;
		$total_posts     = $mock_mode ? count( $media ) : IPA_DB::count_media( 'posts' );
		$total_reels     = $mock_mode ? count( $reels ) : IPA_DB::count_media( 'reels' );

		$data = array(
			'profile'         => $profile,
			'media'           => $media,
			'reels'           => $reels,
			'highlights'      => $highlights,
			'mock_mode'       => $mock_mode,
			'has_more'        => ! $mock_mode && $total_posts > $initial_limit,
			'reels_has_more'  => ! $mock_mode && $total_reels > $initial_limit,
			'offset'          => count( $media ),
			'reels_offset'    => count( $reels ),
			'settings'   => array(
				'layout_width'          => $settings['layout_width'],
				'enable_dark_mode'      => (bool) $settings['enable_dark_mode'],
				'enable_sticky_header'  => (bool) $settings['enable_sticky_header'],
				'show_captions_modal'   => (bool) $settings['show_captions_modal'],
				'show_dates_modal'      => (bool) $settings['show_dates_modal'],
				'show_instagram_link'   => (bool) $settings['show_instagram_link_modal'],
			),
		);

		set_transient( $cache_key, $data, 10 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Build profile header data.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @param int                  $posts_count Posts count.
	 * @return array<string, mixed>
	 */
	private function build_profile_data( $settings, $posts_count ) {
		$username     = $settings['username'] ?: 'yourprofile';
		$display_name = $settings['display_name'] ?: ucwords( str_replace( array( '_', '.' ), ' ', $username ) );
		$avatar_url   = $settings['profile_image_url'];

		if ( ! empty( $settings['profile_image_id'] ) ) {
			$attachment_url = wp_get_attachment_url( (int) $settings['profile_image_id'] );
			if ( $attachment_url ) {
				$avatar_url = $attachment_url;
			}
		}

		if ( empty( $avatar_url ) ) {
			$avatar_url = ipa_get_placeholder_svg( '#F57600', strtoupper( substr( $username, 0, 1 ) ) );
		}

		$category = trim( (string) ( $settings['category'] ?? '' ) );
		if ( '' === $category && ! empty( $settings['enable_mock_mode'] ) ) {
			$category = 'Entrepreneur';
		}

		return array(
			'username'              => $username,
			'display_name'          => $display_name,
			'category'              => $category,
			'bio'                   => $settings['bio'],
			'avatar_url'            => $avatar_url,
			'external_link'         => $settings['external_link'],
			'secondary_link'        => $settings['secondary_link'] ?? '',
			'secondary_link_label'  => $settings['secondary_link_label'] ?? '',
			'show_stats'            => (bool) $settings['show_stats'],
			'posts_count'           => $settings['posts_count_label'] ?: (string) $posts_count,
			'followers_count'       => $settings['followers_count'] ?? '',
			'following_count'       => $settings['following_count'] ?? '',
			'button_text'           => $settings['button_text'] ?: __( 'View on Instagram', 'instagram-profile-archive' ),
			'message_button_text'   => $settings['message_button_text'] ?? __( 'Message', 'instagram-profile-archive' ),
			'message_button_url'    => $settings['message_button_url'] ?? '',
			'instagram_profile_url' => $settings['instagram_profile_url'],
			'show_highlights'       => ! empty( $settings['show_highlights'] ),
			'show_bottom_nav'       => ! empty( $settings['show_bottom_nav'] ),
		);
	}

	/**
	 * Format DB media rows for frontend JSON.
	 *
	 * @param array<int, object> $items DB rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function format_db_media_for_frontend( $items ) {
		$formatted = array();

		foreach ( $items as $index => $item ) {
			$thumb       = $this->resolve_local_media_url( $item, true, $index );
			$local_file  = $this->resolve_local_media_url( $item, false, $index );
			$entry = array(
				'id'                 => (int) $item->id,
				'ig_media_id'        => $item->ig_media_id,
				'media_type'         => $item->media_type,
				'media_product_type' => $item->media_product_type ?? '',
				'caption'            => $item->caption,
				'permalink'          => $item->permalink,
				'local_file_url'     => $local_file,
				'thumbnail_url'      => $thumb,
				'posted_at'          => $item->posted_at,
				'is_carousel_parent' => (int) $item->is_carousel_parent,
				'is_pinned'          => ! empty( $item->is_pinned ),
				'children'           => array(),
			);

			if ( ! empty( $item->children ) ) {
				foreach ( $item->children as $child_index => $child ) {
					$child_thumb = $this->resolve_local_media_url( $child, true, $index + $child_index );
					$child_file  = $this->resolve_local_media_url( $child, false, $index + $child_index );
					$entry['children'][] = array(
						'id'             => (int) $child->id,
						'ig_media_id'    => $child->ig_media_id,
						'media_type'     => $child->media_type,
						'local_file_url' => $child_file ?: $child_thumb,
						'thumbnail_url'  => $child_thumb,
					);
				}
			}

			$formatted[] = $entry;
		}

		return $formatted;
	}

	/**
	 * Resolve a local media URL for frontend display (never hotlink Instagram CDN).
	 *
	 * @param object $item Media row.
	 * @param bool   $prefer_thumbnail Prefer thumbnail over main file.
	 * @param int    $index Item index for preview palette fallbacks.
	 * @return string
	 */
	private function resolve_local_media_url( $item, $prefer_thumbnail = false, $index = 0 ) {
		if ( $prefer_thumbnail ) {
			$url = $item->local_thumbnail_url ?: $item->local_file_url;
		} else {
			$url = $item->local_file_url ?: $item->local_thumbnail_url;
		}

		if ( ! empty( $url ) ) {
			return (string) $url;
		}

		if ( ! empty( $item->children ) && is_array( $item->children ) ) {
			foreach ( $item->children as $child_index => $child ) {
				$child_url = $this->resolve_local_media_url( $child, $prefer_thumbnail, $index + $child_index );
				if ( $child_url && 0 !== strpos( $child_url, 'data:image/svg+xml' ) ) {
					return $child_url;
				}
			}
		}

		if ( ! empty( $item->mock_color ) ) {
			return ipa_get_placeholder_svg( $item->mock_color, '' );
		}

		return ipa_get_placeholder_svg( ipa_get_grid_palette_color( $index ), '' );
	}

	/**
	 * Render the posts grid.
	 *
	 * @param array<int, array<string, mixed>> $media Media items.
	 * @param string                           $layout preview|standard.
	 * @return void
	 */
	public function render_grid( $media, $layout = 'standard' ) {
		if ( empty( $media ) ) {
			return;
		}

		if ( 'preview' === $layout ) {
			$this->render_preview_featured_grid( $media );

			for ( $index = 5; $index < count( $media ); $index++ ) {
				$this->render_grid_item( $media[ $index ], $index );
			}

			return;
		}

		foreach ( $media as $index => $item ) {
			$this->render_grid_item( $item, $index );
		}
	}

	/**
	 * Render the curated 9-cell grid from the UI preview.
	 *
	 * @param array<int, array<string, mixed>> $media Media items.
	 * @return void
	 */
	public function render_preview_featured_grid( $media ) {
		$featured_count = min( 5, count( $media ) );

		foreach ( ipa_build_featured_grid_cells( $featured_count ) as $cell ) {
			if ( 'placeholder' === $cell['type'] ) {
				$this->render_grid_placeholder( $cell['mark'] ?? 'dot' );
				continue;
			}

			$index = (int) ( $cell['index'] ?? 0 );
			if ( ! isset( $media[ $index ] ) ) {
				$this->render_grid_placeholder( 'dot' );
				continue;
			}

			$this->render_grid_item(
				$media[ $index ],
				$index,
				array(
					'pinned' => ! empty( $cell['pinned'] ) || ! empty( $media[ $index ]['is_pinned'] ),
				)
			);
		}
	}

	/**
	 * Render a decorative placeholder cell from the preview layout.
	 *
	 * @param string $mark dot|loading.
	 * @return void
	 */
	public function render_grid_placeholder( $mark = 'dot' ) {
		?>
		<div class="ipa-grid-item ipa-grid-placeholder" aria-hidden="true">
			<?php if ( 'loading' === $mark ) : ?>
				<div class="ipa-grid-placeholder-dots">
					<span></span><span></span><span></span>
				</div>
			<?php else : ?>
				<span class="ipa-grid-placeholder-dot"></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render grid item HTML for AJAX responses.
	 *
	 * @param array<int, array<string, mixed>> $media Media items.
	 * @return string
	 */
	public function render_grid_items_html( $media, $start_index = 0 ) {
		ob_start();

		foreach ( $media as $offset => $item ) {
			$this->render_grid_item( $item, $start_index + (int) $offset );
		}

		return ob_get_clean();
	}

	/**
	 * Render a single grid item.
	 *
	 * @param array<string, mixed> $item  Media item.
	 * @param int                  $index Item index.
	 * @param array<string, bool>  $options Render options.
	 * @return void
	 */
	public function render_grid_item( $item, $index, $options = array() ) {
		$is_reel     = ipa_is_reel_media( $item );
		$is_video    = ! $is_reel && 'VIDEO' === strtoupper( (string) ( $item['media_type'] ?? '' ) );
		$is_carousel = ! empty( $item['is_carousel_parent'] ) || 'CAROUSEL_ALBUM' === ( $item['media_type'] ?? '' );
		$thumb = ! empty( $item['thumbnail_url'] ) ? $item['thumbnail_url'] : ( $item['local_file_url'] ?? '' );
		$is_placeholder = empty( $thumb ) || 0 === strpos( (string) $thumb, 'data:image/svg+xml' );

		if ( $is_placeholder ) {
			if ( ! empty( $item['mock_color'] ) ) {
				$thumb = ipa_get_placeholder_svg( $item['mock_color'], '' );
			} else {
				$thumb = ipa_get_placeholder_svg( ipa_get_grid_palette_color( $index ), '' );
			}
		}

		$filter_class = $is_reel ? ' ipa-filter-reels' : ' ipa-filter-grid';
		$is_pinned    = ! empty( $options['pinned'] ) || ! empty( $item['is_pinned'] );
		?>
		<button
			type="button"
			class="ipa-grid-item<?php echo esc_attr( $filter_class ); ?>"
			data-index="<?php echo esc_attr( (string) $index ); ?>"
			aria-label="<?php esc_attr_e( 'View post', 'instagram-profile-archive' ); ?>"
		>
			<img
				src="<?php echo ipa_esc_img_src( $thumb ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>"
				alt=""
				loading="lazy"
				decoding="async"
				class="ipa-grid-thumb"
			/>
			<?php if ( $is_pinned ) : ?>
				<span class="ipa-grid-overlay ipa-overlay-pin" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor"><path d="M16 9V4h1c.6 0 1-.4 1-1s-.4-1-1-1H7c-.6 0-1 .4-1 1s.4 1 1 1h1v5c0 1.7-1.3 3-3 3v2h5.2v7l.8-.8.8.8v-7H19v-2c-1.7 0-3-1.3-3-3z"/></svg>
				</span>
			<?php endif; ?>
			<?php if ( $is_video ) : ?>
				<span class="ipa-grid-overlay ipa-overlay-video" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
				</span>
			<?php endif; ?>
			<?php if ( $is_carousel ) : ?>
				<span class="ipa-grid-overlay ipa-overlay-carousel" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M4.5 4.5h11v11h-11z" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M8.5 8.5h11v11h-11z"/></svg>
				</span>
			<?php endif; ?>
		</button>
		<?php
	}
}
