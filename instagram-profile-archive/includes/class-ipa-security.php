<?php
/**
 * Security utilities: encryption, signed CSRF tokens, rate limiting, masking.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Security
 */
class IPA_Security {

	const ENC_PREFIX        = 'ipa_enc_v1:';
	const STATE_PREFIX      = 'ipa_state_v1:';
	const RL_TRANSIENT_BASE = 'ipa_rl_';
	const USED_STATE_BASE   = 'ipa_used_state_';

	/**
	 * Encrypt a plaintext value using AES-256-CBC with a key derived from WP salts.
	 *
	 * @param string $plaintext Plain value.
	 * @return string Encrypted token with prefix, or empty string when nothing to encrypt.
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $plaintext;
		}

		$key        = self::get_encryption_key();
		$iv         = openssl_random_pseudo_bytes( 16 );
		$ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return $plaintext;
		}

		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );

		return self::ENC_PREFIX . base64_encode( $iv . $mac . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a previously encrypted value. Returns plaintext input unchanged for backward compatibility.
	 *
	 * @param string $value Encrypted or plain value.
	 * @return string
	 */
	public static function decrypt( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return '';
		}

		if ( 0 !== strpos( $value, self::ENC_PREFIX ) ) {
			return $value;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( substr( $value, strlen( self::ENC_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < ( 16 + 32 + 1 ) ) {
			return '';
		}

		$iv         = substr( $raw, 0, 16 );
		$mac        = substr( $raw, 16, 32 );
		$ciphertext = substr( $raw, 48 );
		$key        = self::get_encryption_key();

		$expected_mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
		if ( ! hash_equals( $expected_mac, $mac ) ) {
			return '';
		}

		$plain = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Derive a 32-byte symmetric key from WordPress secret constants.
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function get_encryption_key() {
		$material = '';
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT' ) as $constant ) {
			if ( defined( $constant ) ) {
				$material .= (string) constant( $constant );
			}
		}

		if ( '' === $material ) {
			$fallback = get_option( 'ipa_encryption_fallback_key', '' );
			if ( '' === $fallback ) {
				$fallback = wp_generate_password( 64, true, true );
				update_option( 'ipa_encryption_fallback_key', $fallback, false );
			}
			$material = $fallback;
		}

		return hash( 'sha256', $material . '|ipa_v1', true );
	}

	/**
	 * Generate a tamper-proof signed state token (CSRF + replay defence).
	 *
	 * @param int $user_id WordPress user ID initiating the flow.
	 * @return string Opaque state string.
	 */
	public static function generate_signed_state( $user_id ) {
		$payload = wp_json_encode(
			array(
				'u' => (int) $user_id,
				't' => time(),
				'n' => wp_generate_password( 16, false ),
			)
		);

		$key = self::get_encryption_key();
		$sig = hash_hmac( 'sha256', $payload, $key, true );

		return self::STATE_PREFIX . self::base64url_encode( $sig . $payload );
	}

	/**
	 * Verify a signed state token and consume the nonce so it cannot be replayed.
	 *
	 * @param string $state    State token.
	 * @param int    $max_age  Max age in seconds.
	 * @return array<string, mixed>|false
	 */
	public static function verify_signed_state( $state, $max_age = 900 ) {
		$state = (string) $state;
		if ( 0 !== strpos( $state, self::STATE_PREFIX ) ) {
			return false;
		}

		$raw = self::base64url_decode( substr( $state, strlen( self::STATE_PREFIX ) ) );
		if ( false === $raw || strlen( $raw ) < 33 ) {
			return false;
		}

		$sig     = substr( $raw, 0, 32 );
		$payload = substr( $raw, 32 );
		$key     = self::get_encryption_key();

		$expected = hash_hmac( 'sha256', $payload, $key, true );
		if ( ! hash_equals( $expected, $sig ) ) {
			return false;
		}

		$data = json_decode( $payload, true );
		if ( ! is_array( $data ) || empty( $data['t'] ) || empty( $data['n'] ) ) {
			return false;
		}

		if ( ( time() - (int) $data['t'] ) > (int) $max_age ) {
			return false;
		}

		$used_key = self::USED_STATE_BASE . md5( $data['n'] );
		if ( get_transient( $used_key ) ) {
			return false;
		}
		set_transient( $used_key, 1, max( 2 * $max_age, HOUR_IN_SECONDS ) );

		return $data;
	}

	/**
	 * URL-safe base64 encode.
	 *
	 * @param string $data Binary data.
	 * @return string
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * URL-safe base64 decode.
	 *
	 * @param string $data Encoded data.
	 * @return string|false
	 */
	private static function base64url_decode( $data ) {
		$padded = $data . str_repeat( '=', ( 4 - ( strlen( $data ) % 4 ) ) % 4 );
		return base64_decode( strtr( $padded, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Rate limit a sensitive action. Returns true when allowed.
	 *
	 * @param string $bucket       Logical bucket name (e.g. 'oauth_callback').
	 * @param int    $max_attempts Max attempts in the window.
	 * @param int    $window       Window length in seconds.
	 * @return bool
	 */
	public static function rate_limit_allow( $bucket, $max_attempts = 10, $window = 300 ) {
		$key      = self::RL_TRANSIENT_BASE . $bucket . '_' . md5( self::get_client_fingerprint() );
		$attempts = (int) get_transient( $key );

		if ( $attempts >= $max_attempts ) {
			return false;
		}

		set_transient( $key, $attempts + 1, $window );

		return true;
	}

	/**
	 * Build a stable per-client fingerprint (IP + user ID) for rate limiting.
	 *
	 * @return string
	 */
	private static function get_client_fingerprint() {
		$ip      = '';
		$headers = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$candidate = explode( ',', (string) $_SERVER[ $header ] );
				$candidate = trim( $candidate[0] );
				$candidate = filter_var( $candidate, FILTER_VALIDATE_IP );
				if ( $candidate ) {
					$ip = $candidate;
					break;
				}
			}
		}

		return $ip . '|' . get_current_user_id();
	}

	/**
	 * Mask a secret string for display/logs (e.g. tokens, secrets).
	 *
	 * @param string $value Secret value.
	 * @return string
	 */
	public static function mask_secret( $value ) {
		$value = (string) $value;
		$len   = strlen( $value );

		if ( 0 === $len ) {
			return '';
		}

		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}

		return substr( $value, 0, 4 ) . str_repeat( '*', max( 4, $len - 8 ) ) . substr( $value, -4 );
	}

	/**
	 * Recursively mask known sensitive keys inside an array.
	 *
	 * @param mixed $data Input data.
	 * @return mixed
	 */
	public static function mask_sensitive_data( $data ) {
		if ( is_array( $data ) ) {
			$sensitive = array(
				'access_token',
				'client_secret',
				'app_secret',
				'token',
				'code',
				'refresh_token',
			);

			foreach ( $data as $key => $value ) {
				if ( is_string( $key ) && in_array( strtolower( $key ), $sensitive, true ) ) {
					$data[ $key ] = self::mask_secret( (string) $value );
				} else {
					$data[ $key ] = self::mask_sensitive_data( $value );
				}
			}
		}

		return $data;
	}

	/**
	 * Send security-related HTTP headers for plugin admin responses.
	 *
	 * @return void
	 */
	public static function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}

	/**
	 * Securely compare two strings (timing-safe).
	 *
	 * @param string $known Known value.
	 * @param string $user  User-supplied value.
	 * @return bool
	 */
	public static function secure_equals( $known, $user ) {
		return hash_equals( (string) $known, (string) $user );
	}

	/**
	 * Add a security audit log entry.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function audit( $event, $context = array() ) {
		$log = get_option( 'ipa_audit_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'time'    => current_time( 'mysql', true ),
			'event'   => sanitize_key( $event ),
			'user'    => get_current_user_id(),
			'ip'      => self::get_client_ip(),
			'context' => self::mask_sensitive_data( $context ),
		);

		// Keep the most recent 100 entries.
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, -100 );
		}

		update_option( 'ipa_audit_log', $log, false );
	}

	/**
	 * @return string
	 */
	public static function get_client_ip() {
		foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$candidate = explode( ',', (string) $_SERVER[ $header ] );
				$candidate = trim( $candidate[0] );
				$candidate = filter_var( $candidate, FILTER_VALIDATE_IP );
				if ( $candidate ) {
					return $candidate;
				}
			}
		}
		return '0.0.0.0';
	}
}
