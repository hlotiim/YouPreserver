<?php
/**
 * Admin Sync Logs tab.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<int, object> $logs
 * @var int                $logs_total
 * @var int                $logs_page
 * @var int                $logs_limit
 * @var IPA_Admin          $this
 */

defined( 'ABSPATH' ) || exit;

$latest_error = null;
foreach ( $logs as $log ) {
	if ( in_array( $log->status, array( 'failed', 'error', 'rate_limited' ), true ) ) {
		$latest_error = $log;
		break;
	}
}

$total_pages = max( 1, (int) ceil( $logs_total / max( 1, $logs_limit ) ) );
?>
<div class="ipa-panel">
	<div class="ipa-panel-header">
		<h2><?php esc_html_e( 'Sync Logs', 'instagram-profile-archive' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-inline-form">
			<?php wp_nonce_field( 'ipa_clear_logs' ); ?>
			<input type="hidden" name="action" value="ipa_clear_logs" />
			<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Clear all sync logs?', 'instagram-profile-archive' ) ); ?>');"><?php esc_html_e( 'Clear Logs', 'instagram-profile-archive' ); ?></button>
		</form>
	</div>

	<?php if ( $latest_error ) : ?>
		<div class="ipa-error-box">
			<strong><?php esc_html_e( 'Latest Error:', 'instagram-profile-archive' ); ?></strong>
			<p><?php echo esc_html( $latest_error->message ); ?></p>
			<?php if ( ! empty( $latest_error->technical_message ) ) : ?>
				<details><summary><?php esc_html_e( 'Technical details', 'instagram-profile-archive' ); ?></summary><pre><?php echo esc_html( $latest_error->technical_message ); ?></pre></details>
			<?php endif; ?>
			<small><?php echo esc_html( ipa_format_datetime( $latest_error->started_at ) ); ?></small>
		</div>
	<?php endif; ?>

	<?php if ( empty( $logs ) ) : ?>
		<p><?php esc_html_e( 'No sync logs yet.', 'instagram-profile-archive' ); ?></p>
	<?php else : ?>
		<table class="widefat striped ipa-logs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'Sync Type', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'Status', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'Total Found', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'New', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'Skipped', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'Failed', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'API Calls', 'instagram-profile-archive' ); ?></th>
					<th><?php esc_html_e( 'Message', 'instagram-profile-archive' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( ipa_format_datetime( $log->started_at ) ); ?></td>
						<td><?php echo esc_html( $log->sync_type ); ?></td>
						<td><span class="ipa-badge ipa-badge-<?php echo esc_attr( $log->status ); ?>"><?php echo esc_html( $log->status ); ?></span></td>
						<td><?php echo esc_html( (string) $log->total_found ); ?></td>
						<td><?php echo esc_html( (string) $log->total_new ); ?></td>
						<td><?php echo esc_html( (string) $log->total_updated ); ?></td>
						<td><?php echo esc_html( (string) ( $log->total_skipped ?? 0 ) ); ?></td>
						<td><?php echo esc_html( (string) $log->total_failed ); ?></td>
						<td><?php echo esc_html( (string) ( $log->api_calls_used ?? 0 ) ); ?></td>
						<td>
							<?php echo esc_html( $log->message ); ?>
							<?php if ( ! empty( $log->technical_message ) ) : ?>
								<details><summary><?php esc_html_e( 'Details', 'instagram-profile-archive' ); ?></summary><pre><?php echo esc_html( $log->technical_message ); ?></pre></details>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					for ( $i = 1; $i <= $total_pages; $i++ ) {
						$url = add_query_arg(
							array(
								'page'      => IPA_Admin::PAGE_SLUG,
								'tab'       => 'logs',
								'logs_page' => $i,
							),
							admin_url( 'admin.php' )
						);
						if ( $i === $logs_page ) {
							echo '<span class="tablenav-page-num current">' . esc_html( (string) $i ) . '</span> ';
						} else {
							echo '<a class="tablenav-page-num" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
						}
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
