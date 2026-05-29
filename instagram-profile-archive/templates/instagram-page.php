<?php
/**
 * Instagram archive page template.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<string, mixed> $data
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

$profile          = $data['profile'];
$media            = $data['media'];
$reels            = $data['reels'] ?? array();
$display_settings = $data['settings'];
$layout_width     = $display_settings['layout_width'] ?? '390';
$is_dark          = ! empty( $display_settings['enable_dark_mode'] );
$is_sticky        = ! empty( $display_settings['enable_sticky_header'] );
$has_posts        = ! empty( $media );
$has_reels        = ! empty( $reels );
$highlights       = $data['highlights'] ?? array();
$has_highlights   = ! empty( $highlights );
$show_highlights  = ! empty( $profile['show_highlights'] ) && $has_highlights;

$width_class = 'ipa-width-full' === $layout_width || 'full' === $layout_width ? 'ipa-width-full' : 'ipa-width-fixed';
$width_style = ( 'full' !== $layout_width && 'ipa-width-full' !== $layout_width )
	? '--ipa-frame-width:' . esc_attr( $layout_width ) . 'px;'
	: '';

$frontend           = IPA_Frontend::instance();
$posts_modal_payload      = ipa_build_modal_payload( $media );
$reels_modal_payload      = ipa_build_modal_payload( $reels );
$highlights_modal_payload = ipa_build_highlights_modal_payload( $highlights );
$instagram_profile_url    = ipa_get_instagram_profile_url( $profile['username'] ?? '', $profile['instagram_profile_url'] ?? '' );
$username_label           = ipa_format_username( $profile['username'] ?? '' );
?>
<div
	class="ipa-instagram-archive <?php echo esc_attr( $width_class ); ?><?php echo $is_dark ? ' ipa-dark' : ''; ?>"
	style="<?php echo esc_attr( $width_style ); ?>"
	data-offset="<?php echo esc_attr( (string) $data['offset'] ); ?>"
	data-reels-offset="<?php echo esc_attr( (string) ( $data['reels_offset'] ?? 0 ) ); ?>"
	data-has-more="<?php echo ! empty( $data['has_more'] ) ? '1' : '0'; ?>"
	data-reels-has-more="<?php echo ! empty( $data['reels_has_more'] ) ? '1' : '0'; ?>"
	data-active-filter="grid"
>
	<div class="ipa-phone-frame">
		<header class="ipa-topbar<?php echo $is_sticky ? ' ipa-topbar-sticky' : ''; ?>">
			<div class="ipa-topbar-inner">
				<button type="button" class="ipa-icon-btn ipa-topbar-back" aria-label="<?php esc_attr_e( 'Back', 'instagram-profile-archive' ); ?>">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
				</button>
				<?php if ( $instagram_profile_url && $username_label ) : ?>
					<a href="<?php echo esc_url( $instagram_profile_url ); ?>" class="ipa-topbar-username ipa-username-link" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $username_label ); ?></a>
				<?php else : ?>
					<span class="ipa-topbar-username"><?php echo esc_html( $username_label ); ?></span>
				<?php endif; ?>
				<span class="ipa-topbar-spacer" aria-hidden="true"></span>
			</div>
		</header>

		<section class="ipa-profile">
			<div class="ipa-profile-row">
				<div class="ipa-avatar-ring">
					<img src="<?php echo ipa_esc_img_src( $profile['avatar_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>" alt="<?php echo esc_attr( $profile['display_name'] ); ?>" class="ipa-avatar" loading="lazy" decoding="async" />
				</div>
			</div>

			<div class="ipa-profile-details">
				<?php if ( $instagram_profile_url && $username_label ) : ?>
					<p class="ipa-profile-username-desktop">
						<a href="<?php echo esc_url( $instagram_profile_url ); ?>" class="ipa-username-link" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $username_label ); ?></a>
					</p>
				<?php elseif ( $username_label ) : ?>
					<p class="ipa-profile-username-desktop"><?php echo esc_html( $username_label ); ?></p>
				<?php endif; ?>
				<h1 class="ipa-display-name"><?php echo esc_html( $profile['display_name'] ); ?></h1>
				<?php if ( ! empty( $profile['category'] ) ) : ?>
					<p class="ipa-category"><?php echo esc_html( $profile['category'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $profile['bio'] ) ) : ?>
					<div class="ipa-bio"><?php echo ipa_format_bio_html( $profile['bio'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $profile['external_link'] ) ) : ?>
					<a href="<?php echo esc_url( $profile['external_link'] ); ?>" class="ipa-profile-link" target="_blank" rel="noopener noreferrer">
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
						<span><?php echo esc_html( ipa_format_external_link_display( $profile['external_link'] ) ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( $show_highlights ) : ?>
			<?php include IPA_PLUGIN_DIR . 'templates/partials/highlights-row.php'; ?>
		<?php endif; ?>

		<?php include IPA_PLUGIN_DIR . 'templates/partials/tabs-row.php'; ?>

		<div class="ipa-content-area">
			<?php if ( $has_posts ) : ?>
				<div class="ipa-grid ipa-grid-posts" id="ipa-grid" data-grid-type="posts">
					<?php $frontend->render_grid( $media ); ?>
				</div>
			<?php else : ?>
				<div class="ipa-empty-state ipa-empty-posts" id="ipa-grid-empty">
					<div class="ipa-empty-icon" aria-hidden="true">
						<svg viewBox="0 0 64 64" width="64" height="64" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="48" height="48" rx="8"/><circle cx="32" cy="28" r="8"/><path d="M16 48c4-8 12-12 16-12s12 4 16 12"/></svg>
					</div>
					<p><?php esc_html_e( 'Your archive is ready. Run your first sync from WordPress admin → YouPreserver to display posts here.', 'instagram-profile-archive' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $has_reels ) : ?>
				<div class="ipa-grid ipa-grid-reels ipa-hidden" id="ipa-reels-grid" data-grid-type="reels">
					<?php $frontend->render_grid( $reels ); ?>
				</div>
			<?php else : ?>
				<div class="ipa-empty-state ipa-empty-reels ipa-hidden" id="ipa-reels-empty">
					<p><?php esc_html_e( 'No reels archived yet.', 'instagram-profile-archive' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $has_posts || $has_reels ) : ?>
				<div class="ipa-load-more-wrap<?php echo ( empty( $data['has_more'] ) && empty( $data['reels_has_more'] ) ) ? ' ipa-hidden' : ''; ?>" id="ipa-load-more-wrap">
					<div class="ipa-load-more-sentinel" id="ipa-load-more-sentinel" aria-hidden="true"></div>
					<p class="ipa-load-more-status" id="ipa-load-more-status" aria-live="polite"></p>
				</div>
			<?php endif; ?>

			<?php include IPA_PLUGIN_DIR . 'templates/partials/site-credit.php'; ?>
		</div>
	</div>

	<?php if ( $has_posts || $has_reels || $has_highlights ) : ?>
		<?php include IPA_PLUGIN_DIR . 'templates/modal-viewer.php'; ?>
		<?php if ( $has_posts ) : ?>
			<script type="application/json" id="ipa-media-data"><?php echo wp_json_encode( $posts_modal_payload ); ?></script>
		<?php endif; ?>
		<?php if ( $has_reels ) : ?>
			<script type="application/json" id="ipa-reels-data"><?php echo wp_json_encode( $reels_modal_payload ); ?></script>
		<?php endif; ?>
		<?php if ( $has_highlights ) : ?>
			<script type="application/json" id="ipa-highlights-data"><?php echo wp_json_encode( $highlights_modal_payload ); ?></script>
		<?php endif; ?>
	<?php endif; ?>
</div>
