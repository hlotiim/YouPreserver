<?php
/**
 * Admin Highlights tab — import, display, and manage story highlights.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<string, mixed> $settings
 * @var array<int, object>   $delete_highlights
 * @var IPA_Admin            $this
 */

defined( 'ABSPATH' ) || exit;

$highlights_count          = IPA_DB::count_active_highlights();
$highlights_import_at      = get_option( 'ipa_last_highlights_import_at', '' );
$highlights_import_message = get_option( 'ipa_last_highlights_import_message', '' );
$extension_path            = dirname( IPA_PLUGIN_DIR ) . '/chrome-extension';
?>
<div class="ipa-panel">
	<h2><?php esc_html_e( 'Highlights', 'instagram-profile-archive' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Instagram does not expose highlights through the official API. Use the YouPreserver Highlights Chrome extension to export highlights, then import the ZIP here.', 'instagram-profile-archive' ); ?></p>

	<div class="ipa-highlights-stats">
		<div class="ipa-highlights-stat">
			<span class="ipa-highlights-stat-label"><?php esc_html_e( 'Archived highlights', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-highlights-stat-value"><?php echo esc_html( (string) $highlights_count ); ?></strong>
		</div>
		<div class="ipa-highlights-stat">
			<span class="ipa-highlights-stat-label"><?php esc_html_e( 'Last import', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-highlights-stat-value"><?php echo $highlights_import_at ? esc_html( ipa_format_datetime( $highlights_import_at ) ) : esc_html__( 'Never', 'instagram-profile-archive' ); ?></strong>
		</div>
	</div>

	<?php if ( $highlights_import_message ) : ?>
		<p><strong><?php esc_html_e( 'Last import result:', 'instagram-profile-archive' ); ?></strong> <?php echo esc_html( $highlights_import_message ); ?></p>
	<?php endif; ?>
</div>

<div class="ipa-panel ipa-panel-nested">
	<h2><?php esc_html_e( 'Display Settings', 'instagram-profile-archive' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-form">
		<?php wp_nonce_field( 'ipa_save_settings' ); ?>
		<input type="hidden" name="action" value="ipa_save_settings" />
		<input type="hidden" name="ipa_tab" value="highlights" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Gallery Page', 'instagram-profile-archive' ); ?></th>
				<td>
					<label><input type="checkbox" name="ipa_show_highlights" value="1" <?php checked( $settings['show_highlights'] ?? true ); ?> /> <?php esc_html_e( 'Show highlights row on the public gallery page', 'instagram-profile-archive' ); ?></label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'instagram-profile-archive' ); ?></button>
		</p>
	</form>
</div>

<div class="ipa-panel ipa-panel-nested">
	<h2><?php esc_html_e( 'Import Highlights', 'instagram-profile-archive' ); ?></h2>

	<?php if ( is_dir( $extension_path ) ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: folder path */
				esc_html__( 'Chrome extension folder: %s — load it in chrome://extensions as an unpacked extension.', 'instagram-profile-archive' ),
				'<code>' . esc_html( $extension_path ) . '</code>'
			);
			?>
		</p>
	<?php endif; ?>

	<div class="ipa-highlights-import-layout">
		<div class="ipa-highlights-import-main">
			<form id="ipa-highlights-import-form" class="ipa-form" enctype="multipart/form-data">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ipa_highlights_zip"><?php esc_html_e( 'Highlights ZIP', 'instagram-profile-archive' ); ?></label></th>
						<td>
							<input type="file" id="ipa_highlights_zip" name="ipa_highlights_zip" accept=".zip,application/zip" required />
							<p class="description"><?php esc_html_e( 'Upload a ZIP exported by the YouPreserver Highlights Chrome extension (contains highlights.json + media files).', 'instagram-profile-archive' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Replace Existing', 'instagram-profile-archive' ); ?></th>
						<td>
							<label><input type="checkbox" id="ipa_replace_highlights" name="ipa_replace_highlights" value="1" checked /> <?php esc_html_e( 'Deactivate highlights that are not in this import', 'instagram-profile-archive' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" id="ipa-highlights-import-btn" class="button button-primary"><?php esc_html_e( 'Import Highlights ZIP', 'instagram-profile-archive' ); ?></button>
					<button type="button" id="ipa-highlights-import-cancel" class="button button-secondary" hidden><?php esc_html_e( 'Cancel Import', 'instagram-profile-archive' ); ?></button>
				</p>
			</form>
		</div>

		<div id="ipa-highlights-import-progress" class="ipa-highlights-import-progress" hidden>
			<h3><?php esc_html_e( 'Import Progress', 'instagram-profile-archive' ); ?></h3>
			<div class="ipa-import-progress-track" aria-hidden="true">
				<div id="ipa-import-progress-bar" class="ipa-import-progress-bar" style="width:0%" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
			</div>
			<p id="ipa-import-progress-status" class="ipa-import-progress-status"><?php esc_html_e( 'Preparing import…', 'instagram-profile-archive' ); ?></p>
			<div id="ipa-import-log" class="ipa-import-log" role="log" aria-live="polite" aria-relevant="additions"></div>
		</div>
	</div>
</div>

<div class="ipa-panel ipa-panel-nested">
	<div class="ipa-delete-section" data-ipa-delete-section="highlights">
		<div class="ipa-panel-header">
			<h2><?php esc_html_e( 'Manage Highlights', 'instagram-profile-archive' ); ?></h2>
			<span class="ipa-delete-count"><?php echo esc_html( sprintf( _n( '%d item', '%d items', count( $delete_highlights ), 'instagram-profile-archive' ), count( $delete_highlights ) ) ); ?></span>
		</div>
		<p class="description"><?php esc_html_e( 'Remove imported highlights from your archive. Story media files can be deleted from the Media Library at the same time.', 'instagram-profile-archive' ); ?></p>

		<?php if ( empty( $delete_highlights ) ) : ?>
			<p><?php esc_html_e( 'No highlights to delete.', 'instagram-profile-archive' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-delete-form">
				<?php wp_nonce_field( 'ipa_delete_archive_items' ); ?>
				<input type="hidden" name="action" value="ipa_delete_archive_items" />
				<input type="hidden" name="ipa_delete_type" value="highlights" />

				<div class="ipa-delete-toolbar">
					<button type="button" class="button button-secondary ipa-select-all"><?php esc_html_e( 'Select All', 'instagram-profile-archive' ); ?></button>
					<button type="button" class="button button-secondary ipa-deselect-all"><?php esc_html_e( 'Deselect All', 'instagram-profile-archive' ); ?></button>
					<label class="ipa-delete-attachment-option">
						<input type="checkbox" name="ipa_delete_attachments" value="1" checked />
						<?php esc_html_e( 'Also delete downloaded media files', 'instagram-profile-archive' ); ?>
					</label>
					<button type="submit" class="button button-secondary ipa-delete-selected" onclick="return confirm('<?php echo esc_js( __( 'Delete the selected highlights?', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Delete Selected', 'instagram-profile-archive' ); ?></button>
				</div>

				<table class="widefat striped ipa-delete-table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" class="ipa-select-all-checkbox" aria-label="<?php esc_attr_e( 'Select all highlights', 'instagram-profile-archive' ); ?>" /></td>
							<th><?php esc_html_e( 'Cover', 'instagram-profile-archive' ); ?></th>
							<th><?php esc_html_e( 'Title', 'instagram-profile-archive' ); ?></th>
							<th><?php esc_html_e( 'Stories', 'instagram-profile-archive' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $delete_highlights as $highlight ) : ?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="ipa_delete_ids[]" value="<?php echo esc_attr( (string) $highlight->id ); ?>" />
								</th>
								<td class="ipa-delete-thumb-cell">
									<?php if ( ! empty( $highlight->local_cover_url ) ) : ?>
										<img src="<?php echo esc_url( (string) $highlight->local_cover_url ); ?>" alt="" class="ipa-delete-thumb" loading="lazy" decoding="async" />
									<?php else : ?>
										<span class="ipa-delete-thumb ipa-delete-thumb-empty" aria-hidden="true"></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $highlight->title ?: __( 'Untitled highlight', 'instagram-profile-archive' ) ); ?></td>
								<td><?php echo esc_html( (string) (int) ( $highlight->item_count ?? 0 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-delete-all-form">
				<?php wp_nonce_field( 'ipa_delete_all_archive' ); ?>
				<input type="hidden" name="action" value="ipa_delete_all_archive" />
				<input type="hidden" name="ipa_delete_scope" value="highlights" />
				<label class="ipa-delete-attachment-option">
					<input type="checkbox" name="ipa_delete_attachments" value="1" checked />
					<?php esc_html_e( 'Also delete downloaded media files', 'instagram-profile-archive' ); ?>
				</label>
				<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete ALL highlights? This cannot be undone.', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Delete All Highlights', 'instagram-profile-archive' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>
