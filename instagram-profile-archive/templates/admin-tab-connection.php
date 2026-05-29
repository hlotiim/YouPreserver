<?php
/**
 * Admin Connection tab.
 *
 * @package Instagram_Profile_Archive
 *
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

$secret_value      = ! empty( $settings['app_secret'] ) ? IPA_Settings::get_masked_secret_placeholder() : '';
$redirect_uri      = IPA_OAuth::get_rest_redirect_uri();
$is_connected      = ! empty( $settings['ig_user_id'] ) && ! empty( $settings['access_token'] );
$token_status      = ( new IPA_Token() )->get_token_status();
$has_stored_secret = ! empty( $settings['app_secret'] );
$oauth_debug       = get_option( 'ipa_last_oauth_debug', '' );
?>
<div class="ipa-panel">
	<h2><?php esc_html_e( 'Instagram API Connection', 'instagram-profile-archive' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Use the Instagram App ID and Instagram App Secret from your Meta app’s Instagram Login setup. Connect will fetch your User ID and access token automatically.', 'instagram-profile-archive' ); ?>
	</p>

	<div class="ipa-help-box">
		<strong><?php esc_html_e( 'Where to find the correct App ID', 'instagram-profile-archive' ); ?></strong>
		<ol>
			<li><?php esc_html_e( 'Open developers.facebook.com → Your App → Add Product → Instagram Platform.', 'instagram-profile-archive' ); ?></li>
			<li><?php esc_html_e( 'Choose “API setup with Instagram login” (not Facebook login).', 'instagram-profile-archive' ); ?></li>
			<li><?php esc_html_e( 'Go to Business login settings and copy the Instagram App ID and Instagram App Secret shown there.', 'instagram-profile-archive' ); ?></li>
			<li><?php esc_html_e( 'Do not use your Instagram username or the generic App ID from the top of the dashboard unless it matches the Instagram App ID in that section.', 'instagram-profile-archive' ); ?></li>
		</ol>
		<p class="description">
			<?php esc_html_e( '“Invalid platform app” means the App ID is not from an Instagram Login app, or Instagram Platform has not been added to the Meta app yet.', 'instagram-profile-archive' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'If login succeeds but long-lived token exchange fails, add your Instagram account as an Instagram Tester under App Roles, verify your Meta Business, and complete Access Verification in the Meta app dashboard.', 'instagram-profile-archive' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-form" id="ipa-connection-form">
		<?php wp_nonce_field( 'ipa_connection_form' ); ?>
		<input type="hidden" name="action" value="ipa_connection_form" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ipa_app_id"><?php esc_html_e( 'Instagram App ID', 'instagram-profile-archive' ); ?></label></th>
				<td>
					<input type="text" id="ipa_app_id" name="ipa_app_id" value="<?php echo esc_attr( $settings['app_id'] ); ?>" class="regular-text" autocomplete="off" inputmode="numeric" pattern="[0-9]+" placeholder="1234567890123456" />
					<p class="description"><?php esc_html_e( 'Numeric ID from Instagram → API setup with Instagram login → Business login settings.', 'instagram-profile-archive' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ipa_app_secret"><?php esc_html_e( 'Instagram App Secret', 'instagram-profile-archive' ); ?></label></th>
				<td>
					<div class="ipa-password-field">
						<input type="password" id="ipa_app_secret" name="ipa_app_secret" value="<?php echo esc_attr( $secret_value ); ?>" class="regular-text" autocomplete="new-password" placeholder="<?php echo $has_stored_secret ? esc_attr__( 'Leave blank to keep saved secret', 'instagram-profile-archive' ) : esc_attr__( 'Required before connecting', 'instagram-profile-archive' ); ?>" />
						<button type="button" class="button ipa-toggle-token" data-show-label="<?php esc_attr_e( 'Show', 'instagram-profile-archive' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide', 'instagram-profile-archive' ); ?>" aria-label="<?php esc_attr_e( 'Show/hide secret', 'instagram-profile-archive' ); ?>"><?php esc_html_e( 'Show', 'instagram-profile-archive' ); ?></button>
					</div>
					<?php if ( $has_stored_secret ) : ?>
						<p class="description"><?php esc_html_e( 'App Secret is already saved. Leave this field blank unless you want to replace it.', 'instagram-profile-archive' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ipa_api_version"><?php esc_html_e( 'API Version', 'instagram-profile-archive' ); ?></label></th>
				<td><input type="text" id="ipa_api_version" name="ipa_api_version" value="<?php echo esc_attr( $settings['api_version'] ); ?>" class="small-text" placeholder="v23.0" /></td>
			</tr>
			<tr>
				<th scope="row"><label><?php esc_html_e( 'OAuth Redirect URI', 'instagram-profile-archive' ); ?></label></th>
				<td>
					<code class="ipa-copy-uri" id="ipa-oauth-redirect-uri"><?php echo esc_html( $redirect_uri ); ?></code>
					<button type="button" class="button button-small ipa-copy-button" data-copy-label="<?php esc_attr_e( 'Copy', 'instagram-profile-archive' ); ?>" data-copied-label="<?php esc_attr_e( 'Copied', 'instagram-profile-archive' ); ?>" data-copy-target="ipa-oauth-redirect-uri"><?php esc_html_e( 'Copy', 'instagram-profile-archive' ); ?></button>
					<p class="description"><?php esc_html_e( 'Add this exact URL to Meta App Dashboard → Instagram → API setup with Instagram login → Business login settings → OAuth redirect URIs.', 'instagram-profile-archive' ); ?></p>
				</td>
			</tr>
		</table>

		<div class="ipa-connect-actions">
			<button type="submit" name="ipa_connection_action" value="connect" class="button button-primary ipa-btn-connect" data-loading-text="<?php esc_attr_e( 'Connecting…', 'instagram-profile-archive' ); ?>">
				<?php esc_html_e( 'Connect with Instagram', 'instagram-profile-archive' ); ?>
			</button>
			<button type="submit" name="ipa_connection_action" value="save" class="button button-secondary">
				<?php esc_html_e( 'Save App Settings', 'instagram-profile-archive' ); ?>
			</button>
		</div>
	</form>

	<?php if ( ! empty( $oauth_debug ) && ! $is_connected ) : ?>
		<div class="ipa-oauth-debug">
			<h3><?php esc_html_e( 'Last Connect attempt', 'instagram-profile-archive' ); ?></h3>
			<pre><?php echo esc_html( $oauth_debug ); ?></pre>
		</div>
	<?php endif; ?>

	<div class="ipa-connected-card<?php echo $is_connected ? ' ipa-connected-card-active' : ''; ?>">
		<h3><?php esc_html_e( 'Connected Account', 'instagram-profile-archive' ); ?></h3>

		<?php if ( $is_connected ) : ?>
			<table class="widefat striped ipa-connected-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Instagram User ID', 'instagram-profile-archive' ); ?></th>
						<td><code><?php echo esc_html( $settings['ig_user_id'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Username', 'instagram-profile-archive' ); ?></th>
						<td><?php echo esc_html( $settings['username'] ? ipa_format_username( $settings['username'] ) : '—' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Access Token', 'instagram-profile-archive' ); ?></th>
						<td><code><?php echo esc_html( IPA_Settings::get_masked_secret_placeholder() ); ?></code> <?php esc_html_e( '(stored securely)', 'instagram-profile-archive' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Token Status', 'instagram-profile-archive' ); ?></th>
						<td><?php echo esc_html( $token_status['label'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Token Expiry', 'instagram-profile-archive' ); ?></th>
						<td><?php echo esc_html( $settings['token_expires_at'] ?: '—' ); ?></td>
					</tr>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Not connected yet. Enter your App ID and App Secret above, then click Connect with Instagram.', 'instagram-profile-archive' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="ipa-connection-tools">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-inline-form">
			<?php wp_nonce_field( 'ipa_test_connection' ); ?>
			<input type="hidden" name="action" value="ipa_test_connection" />
			<button type="submit" class="button button-secondary" <?php disabled( ! $is_connected ); ?>><?php esc_html_e( 'Test Connection', 'instagram-profile-archive' ); ?></button>
			<span class="description"><?php esc_html_e( 'Verify the stored connection can fetch media.', 'instagram-profile-archive' ); ?></span>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-inline-form">
			<?php wp_nonce_field( 'ipa_refresh_token' ); ?>
			<input type="hidden" name="action" value="ipa_refresh_token" />
			<button type="submit" class="button button-secondary" <?php disabled( ! $is_connected ); ?>><?php esc_html_e( 'Refresh Token Now', 'instagram-profile-archive' ); ?></button>
			<span class="description"><?php esc_html_e( 'Refresh the long-lived access token.', 'instagram-profile-archive' ); ?></span>
		</form>

		<?php if ( $is_connected ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-inline-form">
				<?php wp_nonce_field( 'ipa_connect_instagram' ); ?>
				<input type="hidden" name="action" value="ipa_connect_instagram" />
				<input type="hidden" name="ipa_app_id" value="<?php echo esc_attr( $settings['app_id'] ); ?>" />
				<input type="hidden" name="ipa_app_secret" value="<?php echo esc_attr( IPA_Settings::get_masked_secret_placeholder() ); ?>" />
				<input type="hidden" name="ipa_api_version" value="<?php echo esc_attr( $settings['api_version'] ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Reconnect Instagram', 'instagram-profile-archive' ); ?></button>
				<span class="description"><?php esc_html_e( 'Connect again to replace the stored token.', 'instagram-profile-archive' ); ?></span>
			</form>
		<?php endif; ?>
	</div>
</div>
