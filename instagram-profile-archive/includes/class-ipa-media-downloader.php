<?php
/**
 * Media downloader.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Media_Downloader
 */
class IPA_Media_Downloader {

	/**
	 * Download media file.
	 *
	 * @param string $url         Remote URL.
	 * @param string $ig_media_id Instagram media ID.
	 * @param string $media_type  Media type.
	 * @param bool   $force       Force re-download.
	 * @return array<string, mixed>|WP_Error
	 */
	public function download_media( $url, $ig_media_id, $media_type = 'IMAGE', $force = false ) {
		return $this->perform_download( $url, $ig_media_id, $media_type, $force, 'media' );
	}

	/**
	 * Download thumbnail.
	 *
	 * @param string $url         Remote URL.
	 * @param string $ig_media_id Instagram media ID.
	 * @param bool   $force       Force.
	 * @return array<string, mixed>|WP_Error
	 */
	public function download_thumbnail( $url, $ig_media_id, $force = false ) {
		return $this->perform_download( $url, $ig_media_id, 'IMAGE', $force, 'thumb' );
	}

	/**
	 * Backward-compatible alias.
	 *
	 * @param string $url         URL.
	 * @param string $ig_media_id Media ID.
	 * @param string $media_type  Type.
	 * @param bool   $force       Force.
	 * @return array<string, mixed>|WP_Error
	 */
	public function download( $url, $ig_media_id, $media_type = 'IMAGE', $force = false ) {
		return $this->download_media( $url, $ig_media_id, $media_type, $force );
	}

	/**
	 * @param string $url         URL.
	 * @param string $ig_media_id Media ID.
	 * @param string $media_type  Type.
	 * @param bool   $force       Force.
	 * @param string $download_type media|thumb.
	 * @return array<string, mixed>|WP_Error
	 */
	private function perform_download( $url, $ig_media_id, $media_type, $force, $download_type ) {
		if ( ! $this->validate_url( $url ) ) {
			return new WP_Error( 'ipa_invalid_url', __( 'Invalid media URL.', 'instagram-profile-archive' ) );
		}

		$existing = $this->get_existing_attachment( $ig_media_id, $download_type );
		if ( ! $force && $existing ) {
			$file_path = get_attached_file( $existing );
			return array(
				'attachment_id' => $existing,
				'url'           => wp_get_attachment_url( $existing ),
				'path'          => $file_path ?: '',
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = download_url( $url, 300 );

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$filename = $this->build_filename( $ig_media_id, $url, $media_type, $download_type );

		if ( ! $this->validate_file_type( $tmp_file, $media_type ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'ipa_invalid_file_type', __( 'Downloaded file type is not allowed.', 'instagram-profile-archive' ) );
		}

		$result = $this->sideload_to_media_library(
			$tmp_file,
			$filename,
			sprintf( 'YouPreserver %s', $ig_media_id )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $result, '_ipa_ig_media_id', sanitize_text_field( $ig_media_id ) );
		update_post_meta( $result, '_ipa_media_type', sanitize_text_field( $media_type ) );
		update_post_meta( $result, '_ipa_download_type', sanitize_text_field( $download_type ) );

		$this->remove_other_ipa_attachments( $ig_media_id, $download_type, (int) $result );

		return array(
			'attachment_id' => $result,
			'url'           => wp_get_attachment_url( $result ),
			'path'          => get_attached_file( $result ) ?: '',
		);
	}

	/**
	 * @param string $ig_media_id Media ID.
	 * @param string $type        media|thumb.
	 * @return int
	 */
	public function get_existing_attachment( $ig_media_id, $type = 'media' ) {
		$posts = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_ipa_ig_media_id',
						'value' => $ig_media_id,
					),
					array(
						'key'   => '_ipa_download_type',
						'value' => $type,
					),
				),
			)
		);

		return ! empty( $posts[0] ) ? (int) $posts[0] : 0;
	}

	/**
	 * Delete older duplicate attachments for the same Instagram media key.
	 *
	 * @param string $ig_media_id     Instagram media ID.
	 * @param string $download_type   media|thumb.
	 * @param int    $keep_attachment Attachment ID to keep.
	 * @return void
	 */
	public function remove_other_ipa_attachments( $ig_media_id, $download_type, $keep_attachment ) {
		$keep_attachment = (int) $keep_attachment;
		if ( $keep_attachment <= 0 ) {
			return;
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_ipa_ig_media_id',
						'value' => $ig_media_id,
					),
					array(
						'key'   => '_ipa_download_type',
						'value' => $download_type,
					),
				),
			)
		);

		foreach ( $attachments as $attachment_id ) {
			if ( (int) $attachment_id === $keep_attachment ) {
				continue;
			}
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}

	/**
	 * @param string $ig_media_id Media ID.
	 * @param string $url         URL.
	 * @param string $media_type  Type.
	 * @param string $suffix      Suffix.
	 * @return string
	 */
	public function build_filename( $ig_media_id, $url, $media_type, $suffix = 'media' ) {
		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$ext      = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
		$allowed  = array( 'jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', 'm4v' );

		if ( ! in_array( $ext, $allowed, true ) ) {
			$ext = in_array( $media_type, array( 'VIDEO', 'REELS' ), true ) ? 'mp4' : 'jpg';
		}

		if ( 'thumb' === $suffix ) {
			return 'ig-' . sanitize_file_name( $ig_media_id ) . '-thumb.' . $ext;
		}

		return 'ig-' . sanitize_file_name( $ig_media_id ) . '.' . $ext;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public function validate_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		if ( empty( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );

		// Block private/reserved IPs (SSRF protection).
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		// Restrict to Meta/Instagram CDN hostnames.
		$allowed_suffixes = array(
			'cdninstagram.com',
			'fbcdn.net',
			'instagram.com',
			'facebook.com',
		);

		foreach ( $allowed_suffixes as $suffix ) {
			if ( $host === $suffix || substr( $host, -strlen( '.' . $suffix ) ) === '.' . $suffix ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $file_path   Path.
	 * @param string $media_type  Type.
	 * @return bool
	 */
	public function validate_file_type( $file_path, $media_type ) {
		$checked = wp_check_filetype( $file_path );

		if ( empty( $checked['ext'] ) ) {
			return false;
		}

		$max_image_bytes = 25 * 1024 * 1024;   // 25 MB.
		$max_video_bytes = 250 * 1024 * 1024;  // 250 MB.

		$size = file_exists( $file_path ) ? filesize( $file_path ) : 0;
		if ( false === $size ) {
			$size = 0;
		}

		$image_exts = array( 'jpg', 'jpeg', 'png', 'webp' );
		$video_exts = array( 'mp4', 'mov', 'm4v' );

		if ( in_array( $media_type, array( 'VIDEO', 'REELS' ), true ) ) {
			if ( ! in_array( $checked['ext'], $video_exts, true ) ) {
				return false;
			}
			if ( $size > $max_video_bytes ) {
				return false;
			}
			return true;
		}

		if ( ! in_array( $checked['ext'], $image_exts, true ) ) {
			return false;
		}
		if ( $size > $max_image_bytes ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $tmp_file  Temp file.
	 * @param string $filename  Filename.
	 * @param string $post_title Title.
	 * @return int|WP_Error
	 */
	public function sideload_to_media_library( $tmp_file, $filename, $post_title ) {
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		$upload_dir = wp_upload_dir();
		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );

		$attachment_id = media_handle_sideload( $file_array, 0, $post_title );

		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attachment_id;
		}

		return (int) $attachment_id;
	}

	/**
	 * @param array<string, mixed> $dirs Upload dirs.
	 * @return array<string, mixed>
	 */
	public function filter_upload_dir( $dirs ) {
		$subdir = $this->get_upload_subdir();
		$dirs['subdir'] = '/' . $subdir;
		$dirs['path']   = $dirs['basedir'] . '/' . $subdir;
		$dirs['url']    = $dirs['baseurl'] . '/' . $subdir;
		return $dirs;
	}

	/**
	 * @return string
	 */
	public function get_upload_subdir() {
		return IPA_UPLOAD_DIR . '/' . gmdate( 'Y/m' );
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function delete_downloaded_attachment( $attachment_id ) {
		if ( $attachment_id <= 0 ) {
			return false;
		}
		return (bool) wp_delete_attachment( $attachment_id, true );
	}
}
