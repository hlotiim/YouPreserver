<?php

/**

 * Admin Profile Display tab.

 *

 * @package Instagram_Profile_Archive

 *

 * @var array<string, mixed> $settings

 */



defined( 'ABSPATH' ) || exit;

?>

<div class="ipa-panel">

	<h2><?php esc_html_e( 'Profile Display', 'instagram-profile-archive' ); ?></h2>

	<p class="description"><?php esc_html_e( 'Customize how your archived profile appears on the public page.', 'instagram-profile-archive' ); ?></p>



	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ipa-form">

		<?php wp_nonce_field( 'ipa_save_settings' ); ?>

		<input type="hidden" name="action" value="ipa_save_settings" />

		<input type="hidden" name="ipa_tab" value="profile" />



		<table class="form-table" role="presentation">

			<tr>

				<th scope="row"><label for="ipa_username"><?php esc_html_e( 'Username', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="text" id="ipa_username" name="ipa_username" value="<?php echo esc_attr( $settings['username'] ); ?>" class="regular-text" placeholder="yourprofile" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_display_name"><?php esc_html_e( 'Display Name', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="text" id="ipa_display_name" name="ipa_display_name" value="<?php echo esc_attr( $settings['display_name'] ); ?>" class="regular-text" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_category"><?php esc_html_e( 'Category', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="text" id="ipa_category" name="ipa_category" value="<?php echo esc_attr( $settings['category'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Entrepreneur', 'instagram-profile-archive' ); ?>" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_bio"><?php esc_html_e( 'Bio', 'instagram-profile-archive' ); ?></label></th>

				<td><textarea id="ipa_bio" name="ipa_bio" rows="4" class="large-text"><?php echo esc_textarea( $settings['bio'] ); ?></textarea></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_profile_image_url"><?php esc_html_e( 'Profile Image URL', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="url" id="ipa_profile_image_url" name="ipa_profile_image_url" value="<?php echo esc_url( $settings['profile_image_url'] ); ?>" class="large-text" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_external_link"><?php esc_html_e( 'Website Link', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="url" id="ipa_external_link" name="ipa_external_link" value="<?php echo esc_url( $settings['external_link'] ); ?>" class="large-text" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_secondary_link"><?php esc_html_e( 'Secondary Link', 'instagram-profile-archive' ); ?></label></th>

				<td>

					<input type="url" id="ipa_secondary_link" name="ipa_secondary_link" value="<?php echo esc_url( $settings['secondary_link'] ?? '' ); ?>" class="large-text" />

					<input type="text" id="ipa_secondary_link_label" name="ipa_secondary_link_label" value="<?php echo esc_attr( $settings['secondary_link_label'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Link label', 'instagram-profile-archive' ); ?>" style="margin-top:6px;" />

				</td>

			</tr>

			<tr>

				<th scope="row"><?php esc_html_e( 'Show Stats', 'instagram-profile-archive' ); ?></th>

				<td><label><input type="checkbox" name="ipa_show_stats" value="1" <?php checked( $settings['show_stats'] ); ?> /></label></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_posts_count_label"><?php esc_html_e( 'Posts Count', 'instagram-profile-archive' ); ?></label></th>

				<td>

					<input type="text" id="ipa_posts_count_label" name="ipa_posts_count_label" value="<?php echo esc_attr( $settings['posts_count_label'] ); ?>" class="small-text" />

					<p class="description"><?php esc_html_e( 'Leave empty to use archived post count.', 'instagram-profile-archive' ); ?></p>

				</td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_followers_count"><?php esc_html_e( 'Followers Count', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="text" id="ipa_followers_count" name="ipa_followers_count" value="<?php echo esc_attr( $settings['followers_count'] ?? '' ); ?>" class="small-text" placeholder="1,541" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_following_count"><?php esc_html_e( 'Following Count', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="text" id="ipa_following_count" name="ipa_following_count" value="<?php echo esc_attr( $settings['following_count'] ?? '' ); ?>" class="small-text" placeholder="366" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_button_text"><?php esc_html_e( 'Primary Button Text', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="text" id="ipa_button_text" name="ipa_button_text" value="<?php echo esc_attr( $settings['button_text'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Following', 'instagram-profile-archive' ); ?>" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_instagram_profile_url"><?php esc_html_e( 'Primary Button URL', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="url" id="ipa_instagram_profile_url" name="ipa_instagram_profile_url" value="<?php echo esc_url( $settings['instagram_profile_url'] ); ?>" class="large-text" placeholder="https://www.instagram.com/yourprofile/" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_message_button_text"><?php esc_html_e( 'Message Button Text', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="text" id="ipa_message_button_text" name="ipa_message_button_text" value="<?php echo esc_attr( $settings['message_button_text'] ?? 'Message' ); ?>" class="regular-text" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_message_button_url"><?php esc_html_e( 'Message Button URL', 'instagram-profile-archive' ); ?></label></th>

				<td><input type="url" id="ipa_message_button_url" name="ipa_message_button_url" value="<?php echo esc_url( $settings['message_button_url'] ?? '' ); ?>" class="large-text" /></td>

			</tr>

			<tr>

				<th scope="row"><label for="ipa_layout_width"><?php esc_html_e( 'Layout Width', 'instagram-profile-archive' ); ?></label></th>

				<td>

					<select id="ipa_layout_width" name="ipa_layout_width">

						<option value="390" <?php selected( $settings['layout_width'], '390' ); ?>>390px</option>

						<option value="430" <?php selected( $settings['layout_width'], '430' ); ?>>430px</option>

						<option value="480" <?php selected( $settings['layout_width'], '480' ); ?>>480px</option>

						<option value="full" <?php selected( $settings['layout_width'], 'full' ); ?>><?php esc_html_e( 'Full responsive', 'instagram-profile-archive' ); ?></option>

					</select>

				</td>

			</tr>

			<tr>

				<th scope="row"><?php esc_html_e( 'UI Sections', 'instagram-profile-archive' ); ?></th>

				<td>

					<label><input type="checkbox" name="ipa_show_bottom_nav" value="1" <?php checked( $settings['show_bottom_nav'] ?? true ); ?> /> <?php esc_html_e( 'Show bottom navigation bar', 'instagram-profile-archive' ); ?></label>

				</td>

			</tr>

			<tr>

				<th scope="row"><?php esc_html_e( 'Modal Options', 'instagram-profile-archive' ); ?></th>

				<td>

					<label><input type="checkbox" name="ipa_show_captions_modal" value="1" <?php checked( $settings['show_captions_modal'] ); ?> /> <?php esc_html_e( 'Show captions in modal', 'instagram-profile-archive' ); ?></label><br />

					<label><input type="checkbox" name="ipa_show_dates_modal" value="1" <?php checked( $settings['show_dates_modal'] ); ?> /> <?php esc_html_e( 'Show dates in modal', 'instagram-profile-archive' ); ?></label><br />

					<label><input type="checkbox" name="ipa_show_instagram_link_modal" value="1" <?php checked( $settings['show_instagram_link_modal'] ); ?> /> <?php esc_html_e( 'Show original Instagram link', 'instagram-profile-archive' ); ?></label>

				</td>

			</tr>

			<tr>

				<th scope="row"><?php esc_html_e( 'Appearance', 'instagram-profile-archive' ); ?></th>

				<td>

					<label><input type="checkbox" name="ipa_enable_dark_mode" value="1" <?php checked( $settings['enable_dark_mode'] ); ?> /> <?php esc_html_e( 'Enable dark mode', 'instagram-profile-archive' ); ?></label><br />

					<label><input type="checkbox" name="ipa_enable_sticky_header" value="1" <?php checked( $settings['enable_sticky_header'] ); ?> /> <?php esc_html_e( 'Enable sticky header', 'instagram-profile-archive' ); ?></label>

				</td>

			</tr>

		</table>



		<p class="submit">

			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'instagram-profile-archive' ); ?></button>

		</p>

	</form>

</div>

