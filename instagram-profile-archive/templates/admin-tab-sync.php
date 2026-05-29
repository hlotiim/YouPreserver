<?php
/**
 * Admin Sync Settings tab.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<string, mixed> $settings
 * @var array<int, object>   $pin_posts
 * @var array<int, int>      $pinned_post_ids
 */

defined( 'ABSPATH' ) || exit;

$has_full_cursor = ! empty( $full_cursor ) && ! empty( $full_cursor->cursor_value );
?>
<div class="ipa-panel">
	<h2><?php esc_html_e( 'Sync Settings', 'instagram-profile-archive' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-form">
		<?php wp_nonce_field( 'ipa_save_settings' ); ?>
		<input type="hidden" name="action" value="ipa_save_settings" />
		<input type="hidden" name="ipa_tab" value="sync" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Mock Mode', 'instagram-profile-archive' ); ?></th>
				<td>
					<label><input type="checkbox" name="ipa_enable_mock_mode" value="1" <?php checked( $settings['enable_mock_mode'] ); ?> /> <?php esc_html_e( 'Show placeholder posts on frontend (no API required)', 'instagram-profile-archive' ); ?></label>
					<p class="description"><?php esc_html_e( 'Turn this off after connecting Instagram and syncing. When enabled, your archived posts are hidden and placeholder tiles are shown instead.', 'instagram-profile-archive' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Debug Logging', 'instagram-profile-archive' ); ?></th>
				<td>
					<label><input type="checkbox" name="ipa_enable_debug_logging" value="1" <?php checked( $settings['enable_debug_logging'] ); ?> /> <?php esc_html_e( 'Write debug messages to the PHP error log', 'instagram-profile-archive' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Auto Sync', 'instagram-profile-archive' ); ?></th>
				<td>
					<label><input type="checkbox" name="ipa_enable_auto_sync" value="1" <?php checked( $settings['enable_auto_sync'] ); ?> /> <?php esc_html_e( 'Automatically sync via WP-Cron', 'instagram-profile-archive' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ipa_sync_frequency"><?php esc_html_e( 'Sync Frequency', 'instagram-profile-archive' ); ?></label></th>
				<td>
					<select id="ipa_sync_frequency" name="ipa_sync_frequency">
						<option value="hourly" <?php selected( $settings['sync_frequency'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'instagram-profile-archive' ); ?></option>
						<option value="twice_daily" <?php selected( $settings['sync_frequency'], 'twice_daily' ); ?>><?php esc_html_e( 'Twice Daily', 'instagram-profile-archive' ); ?></option>
						<option value="daily" <?php selected( $settings['sync_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'instagram-profile-archive' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ipa_posts_per_request"><?php esc_html_e( 'Posts per API Request', 'instagram-profile-archive' ); ?></label></th>
				<td><input type="number" id="ipa_posts_per_request" name="ipa_posts_per_request" value="<?php echo esc_attr( (string) $settings['posts_per_request'] ); ?>" min="1" max="50" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ipa_max_posts_per_sync"><?php esc_html_e( 'Max Posts per Sync Run', 'instagram-profile-archive' ); ?></label></th>
				<td><input type="number" id="ipa_max_posts_per_sync" name="ipa_max_posts_per_sync" value="<?php echo esc_attr( (string) $settings['max_posts_per_sync'] ); ?>" min="1" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Download Media Locally', 'instagram-profile-archive' ); ?></th>
				<td><label><input type="checkbox" name="ipa_download_media_locally" value="1" <?php checked( $settings['download_media_locally'] ); ?> /> <?php esc_html_e( 'Save images to WordPress uploads', 'instagram-profile-archive' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Download Videos Locally', 'instagram-profile-archive' ); ?></th>
				<td><label><input type="checkbox" name="ipa_download_videos_locally" value="1" <?php checked( $settings['download_videos_locally'] ); ?> /> <?php esc_html_e( 'Save videos to WordPress uploads', 'instagram-profile-archive' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Captions', 'instagram-profile-archive' ); ?></th>
				<td><label><input type="checkbox" name="ipa_sync_captions" value="1" <?php checked( $settings['sync_captions'] ); ?> /></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Carousels', 'instagram-profile-archive' ); ?></th>
				<td><label><input type="checkbox" name="ipa_sync_carousels" value="1" <?php checked( $settings['sync_carousels'] ); ?> /></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Only New Posts', 'instagram-profile-archive' ); ?></th>
				<td><label><input type="checkbox" name="ipa_sync_only_new" value="1" <?php checked( $settings['sync_only_new'] ); ?> /> <?php esc_html_e( 'Only fetch posts newer than latest synced timestamp', 'instagram-profile-archive' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preserve Deleted Posts', 'instagram-profile-archive' ); ?></th>
				<td><label><input type="checkbox" name="ipa_preserve_deleted" value="1" <?php checked( $settings['preserve_deleted'] ); ?> /></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete Missing Posts', 'instagram-profile-archive' ); ?></th>
				<td><label><input type="checkbox" name="ipa_delete_missing" value="1" <?php checked( $settings['delete_missing'] ); ?> /> <?php esc_html_e( 'Remove posts from archive if deleted on Instagram', 'instagram-profile-archive' ); ?></label></td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'instagram-profile-archive' ); ?></button>
		</p>
	</form>

	<div class="ipa-sync-actions">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-inline-form">
			<?php wp_nonce_field( 'ipa_manual_sync' ); ?>
			<input type="hidden" name="action" value="ipa_manual_sync" />
			<button type="submit" class="button button-primary ipa-btn-sync"><?php esc_html_e( 'Manual Sync Now', 'instagram-profile-archive' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-inline-form">
			<?php wp_nonce_field( 'ipa_full_sync' ); ?>
			<input type="hidden" name="action" value="ipa_full_sync" />
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Full Re-sync', 'instagram-profile-archive' ); ?></button>
		</form>
		<?php if ( $has_full_cursor ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-inline-form">
				<?php wp_nonce_field( 'ipa_continue_full_sync' ); ?>
				<input type="hidden" name="action" value="ipa_continue_full_sync" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Continue Full Re-sync', 'instagram-profile-archive' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>

<div class="ipa-panel ipa-pinned-posts-panel">
	<h2><?php esc_html_e( 'Pinned Posts', 'instagram-profile-archive' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Instagram’s API does not reliably expose pinned posts. Choose up to 3 grid posts to pin at the top of your gallery — matching Instagram’s profile grid. Sync will not overwrite your selection while pins are saved here.', 'instagram-profile-archive' ); ?>
	</p>

	<?php if ( empty( $pin_posts ) ) : ?>
		<p><?php esc_html_e( 'No archived grid posts yet. Run a sync first, then return here to pin posts.', 'instagram-profile-archive' ); ?></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-pinned-posts-form">
			<?php wp_nonce_field( 'ipa_save_pinned_posts' ); ?>
			<input type="hidden" name="action" value="ipa_save_pinned_posts" />

			<p class="ipa-pinned-posts-hint">
				<?php
				if ( ! empty( $pinned_post_ids ) ) {
					esc_html_e( 'Manual pins are active. Uncheck all and save to clear pins and allow automatic detection on the next sync.', 'instagram-profile-archive' );
				} else {
					esc_html_e( 'Select up to 3 posts. Order in the list follows your gallery; pinned posts appear first in the order you check them.', 'instagram-profile-archive' );
				}
				?>
				<strong><?php esc_html_e( 'Selected:', 'instagram-profile-archive' ); ?></strong>
				<span class="ipa-pinned-posts-count" data-max="3">0</span> / 3
			</p>

			<table class="widefat striped ipa-delete-table ipa-pinned-posts-table">
				<thead>
					<tr>
						<td class="check-column"><?php esc_html_e( 'Pin', 'instagram-profile-archive' ); ?></td>
						<th><?php esc_html_e( 'Preview', 'instagram-profile-archive' ); ?></th>
						<th><?php esc_html_e( 'Caption', 'instagram-profile-archive' ); ?></th>
						<th><?php esc_html_e( 'Posted', 'instagram-profile-archive' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pin_posts as $item ) : ?>
						<?php
						$thumb_url = ipa_get_admin_media_thumb_url( $item );
						$caption   = wp_trim_words( wp_strip_all_tags( (string) ( $item->caption ?? '' ) ), 12, '…' );
						if ( '' === $caption ) {
							$caption = '—';
						}
						$is_pinned = in_array( (int) $item->id, $pinned_post_ids, true );
						?>
						<tr>
							<th scope="row" class="check-column">
								<input
									type="checkbox"
									name="ipa_pinned_ids[]"
									value="<?php echo esc_attr( (string) $item->id ); ?>"
									<?php checked( $is_pinned ); ?>
									aria-label="<?php esc_attr_e( 'Pin this post', 'instagram-profile-archive' ); ?>"
								/>
							</th>
							<td class="ipa-delete-thumb-cell">
								<?php if ( $thumb_url ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" class="ipa-delete-thumb" loading="lazy" decoding="async" />
								<?php else : ?>
									<span class="ipa-delete-thumb ipa-delete-thumb-empty" aria-hidden="true"></span>
								<?php endif; ?>
								<?php if ( $is_pinned ) : ?>
									<span class="ipa-pinned-badge"><?php esc_html_e( 'Pinned', 'instagram-profile-archive' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $caption ); ?></td>
							<td><?php echo esc_html( $item->posted_at ? ipa_format_datetime( $item->posted_at ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Pinned Posts', 'instagram-profile-archive' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</div>
