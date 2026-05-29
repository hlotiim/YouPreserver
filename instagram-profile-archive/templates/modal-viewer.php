<?php
/**
 * Modal viewer template.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<string, mixed> $display_settings
 * @var array<string, mixed> $profile
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ipa-modal ipa-hidden" id="ipa-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php esc_attr_e( 'Post viewer', 'instagram-profile-archive' ); ?>">
	<div class="ipa-modal-backdrop" data-ipa-close aria-hidden="true"></div>
	<div class="ipa-modal-panel">
		<header class="ipa-modal-header">
			<button type="button" class="ipa-icon-btn ipa-modal-close" data-ipa-close aria-label="<?php esc_attr_e( 'Close', 'instagram-profile-archive' ); ?>">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</button>
			<?php
			$modal_profile_url = ipa_get_instagram_profile_url( $profile['username'] ?? '', $profile['instagram_profile_url'] ?? '' );
			$modal_username    = ipa_format_username( $profile['username'] ?? '' );
			?>
			<?php if ( $modal_profile_url && $modal_username ) : ?>
				<a href="<?php echo esc_url( $modal_profile_url ); ?>" class="ipa-modal-title ipa-username-link" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $modal_username ); ?></a>
			<?php else : ?>
				<span class="ipa-modal-title"><?php echo esc_html( $modal_username ); ?></span>
			<?php endif; ?>
			<span class="ipa-modal-spacer"></span>
		</header>

		<div class="ipa-modal-body">
			<button type="button" class="ipa-modal-nav ipa-modal-prev" id="ipa-modal-prev" aria-label="<?php esc_attr_e( 'Previous', 'instagram-profile-archive' ); ?>">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
			</button>

			<div class="ipa-modal-media-wrap">
				<div class="ipa-modal-media" id="ipa-modal-media"></div>
				<div class="ipa-modal-dots" id="ipa-modal-dots" aria-hidden="true"></div>
			</div>

			<button type="button" class="ipa-modal-nav ipa-modal-next" id="ipa-modal-next" aria-label="<?php esc_attr_e( 'Next', 'instagram-profile-archive' ); ?>">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
			</button>
		</div>

		<footer class="ipa-modal-footer">
			<?php if ( ! empty( $display_settings['show_dates_modal'] ) ) : ?>
				<time class="ipa-modal-date" id="ipa-modal-date"></time>
			<?php endif; ?>
			<?php if ( ! empty( $display_settings['show_captions_modal'] ) ) : ?>
				<p class="ipa-modal-caption" id="ipa-modal-caption"></p>
			<?php endif; ?>
			<?php if ( ! empty( $display_settings['show_instagram_link'] ) ) : ?>
				<a href="#" class="ipa-modal-link" id="ipa-modal-link" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View original post', 'instagram-profile-archive' ); ?>
				</a>
			<?php endif; ?>
		</footer>
	</div>
</div>
