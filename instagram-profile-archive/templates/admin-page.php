<?php
/**
 * Admin page template.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $stats
 * @var array<int, object>   $logs
 * @var int                  $page_id
 * @var string               $page_url
 * @var IPA_Admin            $this
 */

defined( 'ABSPATH' ) || exit;

$notice_type = isset( $_GET['ipa_notice'] ) ? sanitize_key( wp_unslash( $_GET['ipa_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$notice_msg  = isset( $_GET['ipa_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['ipa_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$updated     = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tabs = array(
	'connection' => __( 'Connection', 'instagram-profile-archive' ),
	'sync'       => __( 'Sync Settings', 'instagram-profile-archive' ),
	'highlights' => __( 'Highlights', 'instagram-profile-archive' ),
	'profile'    => __( 'Profile Display', 'instagram-profile-archive' ),
	'logs'       => __( 'Sync Logs', 'instagram-profile-archive' ),
	'delete'     => __( 'Clean up', 'instagram-profile-archive' ),
	'tools'      => __( 'Tools', 'instagram-profile-archive' ),
);
?>
<div class="wrap ipa-admin-wrap">
	<h1><?php esc_html_e( 'YouPreserver', 'instagram-profile-archive' ); ?></h1>
	<p class="ipa-admin-subtitle"><?php esc_html_e( 'Preserve and display your Instagram profile on your WordPress site with YouPreserver.', 'instagram-profile-archive' ); ?></p>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'instagram-profile-archive' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $notice_type && $notice_msg ) : ?>
		<?php
		$notice_class = 'success';
		if ( 'error' === $notice_type ) {
			$notice_class = 'error';
		} elseif ( 'warning' === $notice_type ) {
			$notice_class = 'warning';
		}
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<p><?php echo esc_html( $notice_msg ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ipa-dashboard-cards">
		<div class="ipa-card ipa-card-status ipa-status-<?php echo esc_attr( $stats['connection_status'] ); ?>">
			<span class="ipa-card-label"><?php esc_html_e( 'Connection', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-card-value"><?php echo esc_html( $stats['connection_label'] ); ?></strong>
		</div>
		<div class="ipa-card">
			<span class="ipa-card-label"><?php esc_html_e( 'Archived Posts', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-card-value"><?php echo esc_html( (string) $stats['total_posts'] ); ?></strong>
		</div>
		<div class="ipa-card">
			<span class="ipa-card-label"><?php esc_html_e( 'Last Sync', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-card-value"><?php echo esc_html( $stats['last_sync'] ); ?></strong>
		</div>
		<div class="ipa-card">
			<span class="ipa-card-label"><?php esc_html_e( 'Archived Highlights', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-card-value"><?php echo esc_html( (string) ( $stats['total_highlights'] ?? 0 ) ); ?></strong>
		</div>
		<div class="ipa-card">
			<span class="ipa-card-label"><?php esc_html_e( 'Highlights Import', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-card-value"><?php echo esc_html( $stats['last_highlights_sync'] ?? '' ); ?></strong>
		</div>
		<div class="ipa-card">
			<span class="ipa-card-label"><?php esc_html_e( 'Auto Sync', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-card-value"><?php echo esc_html( $stats['auto_sync'] ); ?></strong>
		</div>
		<div class="ipa-card">
			<span class="ipa-card-label"><?php esc_html_e( 'Token Status', 'instagram-profile-archive' ); ?></span>
			<strong class="ipa-card-value"><?php echo esc_html( $stats['token_status']['label'] ?? '' ); ?></strong>
		</div>
	</div>

	<nav class="nav-tab-wrapper ipa-nav-tabs">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $this->get_tab_url( $slug ) ); ?>" class="nav-tab <?php echo $this->get_current_tab() === $slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="ipa-tab-content">
		<?php
		switch ( $this->get_current_tab() ) {
			case 'sync':
				include IPA_PLUGIN_DIR . 'templates/admin-tab-sync.php';
				break;
			case 'highlights':
				include IPA_PLUGIN_DIR . 'templates/admin-tab-highlights.php';
				break;
			case 'profile':
				include IPA_PLUGIN_DIR . 'templates/admin-tab-profile.php';
				break;
			case 'logs':
				include IPA_PLUGIN_DIR . 'templates/admin-tab-logs.php';
				break;
			case 'delete':
				include IPA_PLUGIN_DIR . 'templates/admin-tab-delete.php';
				break;
			case 'tools':
				include IPA_PLUGIN_DIR . 'templates/admin-tab-tools.php';
				break;
			default:
				include IPA_PLUGIN_DIR . 'templates/admin-tab-connection.php';
				break;
		}
		?>
	</div>

	<div class="ipa-admin-footer">
		<p>
			<?php
			printf(
				/* translators: %s: page URL */
				esc_html__( 'Public archive page: %s', 'instagram-profile-archive' ),
				'<a href="' . esc_url( $page_url ) . '" target="_blank" rel="noopener">' . esc_html( $page_url ) . '</a>'
			);
			?>
		</p>
		<p class="ipa-admin-credit">
			<?php
			printf(
				/* translators: 1: author name, 2: author website URL, 3: website label */
				esc_html__( 'Built by %1$s · %2$s', 'instagram-profile-archive' ),
				'<a href="' . esc_url( IPA_AUTHOR_URL ) . '" target="_blank" rel="noopener">' . esc_html( IPA_AUTHOR_NAME ) . '</a>',
				'<a href="' . esc_url( IPA_AUTHOR_URL ) . '" target="_blank" rel="noopener">roktimsaha.com</a>'
			);
			?>
		</p>
	</div>
</div>
