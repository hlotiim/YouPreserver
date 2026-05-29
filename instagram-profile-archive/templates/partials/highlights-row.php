<?php
/**
 * Highlights row partial.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<int, array<string, mixed>> $highlights
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $highlights ) ) {
	return;
}
?>
<section class="ipa-highlights" aria-label="<?php esc_attr_e( 'Highlights', 'instagram-profile-archive' ); ?>">
	<div class="ipa-highlights-track">
		<?php
		$clickable_index = 0;
		foreach ( $highlights as $highlight ) :
			$title      = (string) ( $highlight['title'] ?? '' );
			$cover_url  = (string) ( $highlight['cover_url'] ?? '' );
			$has_slides = ! empty( ipa_get_highlight_modal_slides( $highlight ) );
			if ( '' === $cover_url ) {
				continue;
			}
			?>
			<button
				type="button"
				class="ipa-highlight<?php echo $has_slides ? '' : ' ipa-highlight-static'; ?>"
				data-highlight-index="<?php echo $has_slides ? esc_attr( (string) $clickable_index ) : ''; ?>"
				aria-label="<?php echo esc_attr( $title ?: __( 'Highlight', 'instagram-profile-archive' ) ); ?>"
				<?php echo $has_slides ? '' : 'disabled'; ?>
			>
				<div class="ipa-highlight-ring">
					<?php if ( $cover_url ) : ?>
						<img
							src="<?php echo ipa_esc_img_src( $cover_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>"
							alt=""
							class="ipa-highlight-cover"
							loading="lazy"
							decoding="async"
						/>
					<?php endif; ?>
				</div>
				<span class="ipa-highlight-label"><?php echo esc_html( $title ); ?></span>
			</button>
			<?php
			if ( $has_slides ) {
				++$clickable_index;
			}
		endforeach;
		?>
	</div>
</section>
