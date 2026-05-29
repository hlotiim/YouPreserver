<?php
/**
 * Instagram access token management.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Token
 */
class IPA_Token {

	/**
	 * @return bool
	 */
	public function is_token_configured() {
		return ! empty( ipa_get_setting( 'ig_user_id' ) ) && ! empty( ipa_get_setting( 'access_token' ) );
	}

	/**
	 * @return bool
	 */
	public function is_token_expired() {
		$expires = ipa_get_setting( 'token_expires_at', '' );

		if ( empty( $expires ) ) {
			return false;
		}

		return strtotime( $expires ) < time();
	}

	/**
	 * @param int $days Days threshold.
	 * @return bool
	 */
	public function is_token_expiring_soon( $days = 7 ) {
		$expires = ipa_get_setting( 'token_expires_at', '' );

		if ( empty( $expires ) ) {
			return false;
		}

		$threshold = time() + ( DAY_IN_SECONDS * max( 1, (int) $days ) );

		return strtotime( $expires ) <= $threshold;
	}

	/**
	 * @param int|null $seconds Seconds until expiry.
	 * @return string
	 */
	public function get_expiry_date_from_seconds( $seconds ) {
		if ( empty( $seconds ) ) {
			return '';
		}

		return gmdate( 'Y-m-d', time() + (int) $seconds );
	}

	/**
	 * @return array<string, string>
	 */
	public function get_token_status() {
		if ( ! $this->is_token_configured() ) {
			return array(
				'status' => 'missing',
				'label'  => __( 'Missing token', 'instagram-profile-archive' ),
			);
		}

		if ( $this->is_token_expired() ) {
			return array(
				'status' => 'expired',
				'label'  => __( 'Expired', 'instagram-profile-archive' ),
			);
		}

		if ( $this->is_token_expiring_soon() ) {
			return array(
				'status' => 'expiring',
				'label'  => __( 'Expiring soon', 'instagram-profile-archive' ),
			);
		}

		$expires = ipa_get_setting( 'token_expires_at', '' );

		return array(
			'status' => 'valid',
			'label'  => $expires
				? sprintf(
					/* translators: %s: expiry date */
					__( 'Valid until %s', 'instagram-profile-archive' ),
					$expires
				)
				: __( 'Connected', 'instagram-profile-archive' ),
		);
	}

	/**
	 * Save token response data.
	 *
	 * @param string   $token      Token.
	 * @param int|null $expires_in Expires in seconds.
	 * @return void
	 */
	public function save_token_data( $token, $expires_in = null ) {
		if ( ! empty( $token ) ) {
			IPA_Settings::set_secure( 'access_token', sanitize_text_field( $token ) );

			if ( class_exists( 'IPA_Security' ) ) {
				IPA_Security::audit(
					'access_token_updated',
					array(
						'expires_in' => $expires_in,
					)
				);
			}
		}

		update_option( 'ipa_token_last_refreshed_at', current_time( 'mysql', true ) );

		if ( null !== $expires_in && $expires_in > 0 ) {
			update_option( 'ipa_token_expires_at', $this->get_expiry_date_from_seconds( $expires_in ) );
		}
	}

	/**
	 * Refresh long-lived token.
	 *
	 * @return true|WP_Error
	 */
	public function refresh_token() {
		$token = ipa_get_setting( 'access_token', '' );

		if ( empty( $token ) ) {
			return new WP_Error(
				'ipa_no_token',
				__( 'Access token is missing. Please add a valid Instagram access token.', 'instagram-profile-archive' )
			);
		}

		$body = IPA_OAuth::request_graph_token(
			'https://graph.instagram.com/refresh_access_token',
			array(
				'grant_type'   => 'ig_refresh_token',
				'access_token' => $token,
			)
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$new_token   = $body['access_token'] ?? '';
		$expires_in  = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : null;
		$token_type  = $body['token_type'] ?? 'bearer';

		if ( empty( $new_token ) ) {
			return new WP_Error(
				'ipa_token_refresh_failed',
				__( 'Token refresh response did not include a new access token.', 'instagram-profile-archive' )
			);
		}

		$this->save_token_data( $new_token, $expires_in );
		update_option( 'ipa_token_type', sanitize_text_field( $token_type ) );

		return true;
	}
}
