<?php
/**
 * Admin Delete tab.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<int, object> $delete_media
 * @var int                $delete_media_total
 * @var int                $delete_page
 * @var int                $delete_limit
 * @var IPA_Admin          $this
 */

defined( 'ABSPATH' ) || exit;

$delete_total_pages = max( 1, (int) ceil( $delete_media_total / max( 1, $delete_limit ) ) );
?>
<div class="ipa-panel">
	<h2><?php esc_html_e( 'Clean Up Archive', 'instagram-profile-archive' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Select posts or reels to remove from your archive. Carousel children are deleted automatically with the parent post. To manage highlights, use the Highlights tab.', 'instagram-profile-archive' ); ?></p>

	<div class="ipa-delete-section" data-ipa-delete-section="media">
		<div class="ipa-panel-header">
			<h3><?php esc_html_e( 'Posts & Reels', 'instagram-profile-archive' ); ?></h3>
			<span class="ipa-delete-count"><?php echo esc_html( sprintf( _n( '%d item', '%d items', $delete_media_total, 'instagram-profile-archive' ), $delete_media_total ) ); ?></span>
		</div>

		<?php if ( empty( $delete_media ) ) : ?>
			<p><?php esc_html_e( 'No archived posts or reels to delete.', 'instagram-profile-archive' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-delete-form">
				<?php wp_nonce_field( 'ipa_delete_archive_items' ); ?>
				<input type="hidden" name="action" value="ipa_delete_archive_items" />
				<input type="hidden" name="ipa_delete_type" value="media" />

				<div class="ipa-delete-toolbar">
					<button type="button" class="button button-secondary ipa-select-all"><?php esc_html_e( 'Select All', 'instagram-profile-archive' ); ?></button>
					<button type="button" class="button button-secondary ipa-deselect-all"><?php esc_html_e( 'Deselect All', 'instagram-profile-archive' ); ?></button>
					<label class="ipa-delete-attachment-option">
						<input type="checkbox" name="ipa_delete_attachments" value="1" checked />
						<?php esc_html_e( 'Also delete downloaded media files', 'instagram-profile-archive' ); ?>
					</label>
					<button type="submit" class="button button-secondary ipa-delete-selected" onclick="return confirm('<?php echo esc_js( __( 'Delete the selected archived posts?', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Delete Selected', 'instagram-profile-archive' ); ?></button>
				</div>

				<table class="widefat striped ipa-delete-table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" class="ipa-select-all-checkbox" aria-label="<?php esc_attr_e( 'Select all posts on this page', 'instagram-profile-archive' ); ?>" /></td>
							<th><?php esc_html_e( 'Preview', 'instagram-profile-archive' ); ?></th>
							<th><?php esc_html_e( 'Caption', 'instagram-profile-archive' ); ?></th>
							<th><?php esc_html_e( 'Type', 'instagram-profile-archive' ); ?></th>
							<th><?php esc_html_e( 'Posted', 'instagram-profile-archive' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $delete_media as $item ) : ?>
							<?php
							$thumb_url = ipa_get_admin_media_thumb_url( $item );
							$caption   = wp_trim_words( wp_strip_all_tags( (string) ( $item->caption ?? '' ) ), 12, '…' );
							if ( '' === $caption ) {
								$caption = '—';
							}
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="ipa_delete_ids[]" value="<?php echo esc_attr( (string) $item->id ); ?>" />
								</th>
								<td class="ipa-delete-thumb-cell">
									<?php if ( $thumb_url ) : ?>
										<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" class="ipa-delete-thumb" loading="lazy" decoding="async" />
									<?php else : ?>
										<span class="ipa-delete-thumb ipa-delete-thumb-empty" aria-hidden="true"></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $caption ); ?></td>
								<td><?php echo esc_html( ipa_get_admin_media_type_label( $item ) ); ?></td>
								<td><?php echo esc_html( $item->posted_at ? ipa_format_datetime( $item->posted_at ) : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</form>

			<?php if ( $delete_total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: current page, 2: total pages */
									__( 'Page %1$d of %2$d', 'instagram-profile-archive' ),
									$delete_page,
									$delete_total_pages
								)
							);
							?>
						</span>
						<?php if ( $delete_page > 1 ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'delete_page' => $delete_page - 1 ), $this->get_tab_url( 'delete' ) ) ); ?>"><?php esc_html_e( 'Previous', 'instagram-profile-archive' ); ?></a>
						<?php endif; ?>
						<?php if ( $delete_page < $delete_total_pages ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'delete_page' => $delete_page + 1 ), $this->get_tab_url( 'delete' ) ) ); ?>"><?php esc_html_e( 'Next', 'instagram-profile-archive' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-delete-all-form">
				<?php wp_nonce_field( 'ipa_delete_all_archive' ); ?>
				<input type="hidden" name="action" value="ipa_delete_all_archive" />
				<input type="hidden" name="ipa_delete_scope" value="media" />
				<label class="ipa-delete-attachment-option">
					<input type="checkbox" name="ipa_delete_attachments" value="1" checked />
					<?php esc_html_e( 'Also delete downloaded media files', 'instagram-profile-archive' ); ?>
				</label>
				<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete ALL archived posts and reels? This cannot be undone.', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Delete All Posts & Reels', 'instagram-profile-archive' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<div class="ipa-delete-section">
		<div class="ipa-panel-header">
			<h3><?php esc_html_e( 'Clean Up Duplicate Media', 'instagram-profile-archive' ); ?></h3>
		</div>
		<p><?php esc_html_e( 'Remove duplicate Instagram media files from the WordPress Media Library after a re-sync. Keeps the file linked to each archived post and deletes extra copies and unlinked YouPreserver files.', 'instagram-profile-archive' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-delete-all-form">
			<?php wp_nonce_field( 'ipa_cleanup_media' ); ?>
			<input type="hidden" name="action" value="ipa_cleanup_media" />
			<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Remove duplicate and unlinked YouPreserver media files from the Media Library?', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Clean Up Media Library', 'instagram-profile-archive' ); ?></button>
		</form>
	</div>

	<?php if ( ! empty( $delete_media ) ) : ?>
		<div class="ipa-delete-section ipa-delete-section-danger">
			<h3><?php esc_html_e( 'Delete Everything', 'instagram-profile-archive' ); ?></h3>
			<p><?php esc_html_e( 'Remove all archived posts, reels, and highlights at once.', 'instagram-profile-archive' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-delete-all-form">
				<?php wp_nonce_field( 'ipa_delete_all_archive' ); ?>
				<input type="hidden" name="action" value="ipa_delete_all_archive" />
				<input type="hidden" name="ipa_delete_scope" value="all" />
				<label class="ipa-delete-attachment-option">
					<input type="checkbox" name="ipa_delete_attachments" value="1" checked />
					<?php esc_html_e( 'Also delete downloaded media files', 'instagram-profile-archive' ); ?>
				</label>
				<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete ALL archived posts and highlights? This cannot be undone.', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Delete All Archived Content', 'instagram-profile-archive' ); ?></button>
			</form>
		</div>
	<?php endif; ?>
</div>
