<?php
/**
 * Admin Tools tab.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<string, mixed> $settings
 * @var string               $page_url
 */

defined( 'ABSPATH' ) || exit;

$audit_log = get_option( 'ipa_audit_log', array() );
if ( ! is_array( $audit_log ) ) {
	$audit_log = array();
}
$recent_audit = array_slice( array_reverse( $audit_log ), 0, 25 );
?>
<div class="ipa-panel">
	<h2><?php esc_html_e( 'Tools', 'instagram-profile-archive' ); ?></h2>

	<div class="ipa-security-info">
		<strong><?php esc_html_e( 'Security', 'instagram-profile-archive' ); ?></strong>
		<ul>
			<li><?php esc_html_e( 'App Secret and Access Token are encrypted at rest with AES-256-CBC and HMAC verification using your WordPress secret keys.', 'instagram-profile-archive' ); ?></li>
			<li><?php esc_html_e( 'OAuth uses HMAC-signed state tokens with replay protection.', 'instagram-profile-archive' ); ?></li>
			<li><?php esc_html_e( 'All admin actions are rate-limited and require nonces + manage_options capability.', 'instagram-profile-archive' ); ?></li>
			<li><?php esc_html_e( 'Access tokens are never exposed to the frontend or to JavaScript.', 'instagram-profile-archive' ); ?></li>
		</ul>
	</div>

	<div class="ipa-tools-grid">
		<div class="ipa-tool-card">
			<h3><?php esc_html_e( 'Recreate Archive Page', 'instagram-profile-archive' ); ?></h3>
			<p><?php esc_html_e( 'Recreate the /gallery/ page with the shortcode if it was deleted.', 'instagram-profile-archive' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ipa_recreate_page' ); ?>
				<input type="hidden" name="action" value="ipa_recreate_page" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Recreate Page', 'instagram-profile-archive' ); ?></button>
			</form>
		</div>

		<div class="ipa-tool-card">
			<h3><?php esc_html_e( 'Clear Frontend Cache', 'instagram-profile-archive' ); ?></h3>
			<p><?php esc_html_e( 'Clear cached media data used for frontend display.', 'instagram-profile-archive' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ipa_clear_media_cache' ); ?>
				<input type="hidden" name="action" value="ipa_clear_media_cache" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Clear Cache', 'instagram-profile-archive' ); ?></button>
			</form>
		</div>

		<div class="ipa-tool-card">
			<h3><?php esc_html_e( 'Reset Full Sync Cursor', 'instagram-profile-archive' ); ?></h3>
			<p><?php esc_html_e( 'Clear the saved pagination cursor for full re-sync.', 'instagram-profile-archive' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ipa_reset_full_sync_cursor' ); ?>
				<input type="hidden" name="action" value="ipa_reset_full_sync_cursor" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Reset Cursor', 'instagram-profile-archive' ); ?></button>
			</form>
		</div>

		<div class="ipa-tool-card">
			<h3><?php esc_html_e( 'Export Media CSV', 'instagram-profile-archive' ); ?></h3>
			<p><?php esc_html_e( 'Download all archived media records as a CSV file.', 'instagram-profile-archive' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ipa_export_csv' ); ?>
				<input type="hidden" name="action" value="ipa_export_csv" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Export CSV', 'instagram-profile-archive' ); ?></button>
			</form>
		</div>

		<div class="ipa-tool-card">
			<h3><?php esc_html_e( 'Clear Sync Logs', 'instagram-profile-archive' ); ?></h3>
			<p><?php esc_html_e( 'Remove all sync log entries from the database.', 'instagram-profile-archive' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ipa_clear_logs' ); ?>
				<input type="hidden" name="action" value="ipa_clear_logs" />
				<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Clear all sync logs?', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Clear Logs', 'instagram-profile-archive' ); ?></button>
			</form>
		</div>

		<div class="ipa-tool-card ipa-tool-danger">
			<h3><?php esc_html_e( 'Delete All Plugin Data', 'instagram-profile-archive' ); ?></h3>
			<p><?php esc_html_e( 'Permanently delete all archived media records, sync logs, and cursors from the database.', 'instagram-profile-archive' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ipa_delete_all_data' ); ?>
				<input type="hidden" name="action" value="ipa_delete_all_data" />
				<label class="ipa-danger-check">
					<input type="checkbox" name="ipa_confirm_delete" value="1" />
					<?php esc_html_e( 'I understand this will delete all archived Instagram media data from this plugin.', 'instagram-profile-archive' ); ?>
				</label>
				<label class="ipa-danger-check">
					<input type="checkbox" name="ipa_delete_attachments" value="1" />
					<?php esc_html_e( 'Also delete downloaded media attachments', 'instagram-profile-archive' ); ?>
				</label>
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete All Data', 'instagram-profile-archive' ); ?></button>
			</form>
		</div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-form ipa-uninstall-option">
		<?php wp_nonce_field( 'ipa_save_settings' ); ?>
		<input type="hidden" name="action" value="ipa_save_settings" />
		<input type="hidden" name="ipa_tab" value="tools" />
		<label>
			<input type="checkbox" name="ipa_delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'] ); ?> />
			<?php esc_html_e( 'Delete all plugin data when uninstalling', 'instagram-profile-archive' ); ?>
		</label>
		<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save Uninstall Preference', 'instagram-profile-archive' ); ?></button>
	</form>

	<?php if ( ! empty( $recent_audit ) ) : ?>
		<div class="ipa-audit-log">
			<h3><?php esc_html_e( 'Security Audit Log (last 25 events)', 'instagram-profile-archive' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time (UTC)', 'instagram-profile-archive' ); ?></th>
						<th><?php esc_html_e( 'Event', 'instagram-profile-archive' ); ?></th>
						<th><?php esc_html_e( 'User', 'instagram-profile-archive' ); ?></th>
						<th><?php esc_html_e( 'IP', 'instagram-profile-archive' ); ?></th>
						<th><?php esc_html_e( 'Context', 'instagram-profile-archive' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_audit as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $entry['event'] ?? '' ); ?></code></td>
							<td><?php
								$uid = (int) ( $entry['user'] ?? 0 );
								if ( $uid > 0 ) {
									$user = get_userdata( $uid );
									echo esc_html( $user ? $user->user_login : '#' . $uid );
								} else {
									esc_html_e( 'guest', 'instagram-profile-archive' );
								}
							?></td>
							<td><?php echo esc_html( $entry['ip'] ?? '' ); ?></td>
							<td><code style="font-size:11px;"><?php echo esc_html( wp_json_encode( $entry['context'] ?? array() ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
