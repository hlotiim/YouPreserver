<?php
/**
 * Import story highlights from a Chrome extension export ZIP.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Highlights_Import
 */
class IPA_Highlights_Import {

	const EXPORT_FORMAT     = 'ipa-highlights-export';
	const SUPPORTED_VERSION = 1;
	const MANIFEST_NAMES    = array( 'highlights.json', 'manifest.json' );
	const MAX_HIGHLIGHTS    = 100;
	const MAX_STORIES_PER_HIGHLIGHT = 500;
	const MAX_ZIP_BYTES     = 524288000; // 500 MB.
	const JOB_TRANSIENT_PREFIX = 'ipa_hl_job_';
	const JOB_TTL           = 7200; // 2 hours.
	const BATCH_SIZE        = 1;
	const BATCH_TIME_LIMIT  = 25; // Seconds per AJAX step.
	const BATCH_MAX_HIGHLIGHTS = 5;

	/**
	 * @var IPA_Media_Downloader
	 */
	private $downloader;

	/**
	 * @var array<string, int>
	 */
	private $attachment_cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->downloader = new IPA_Media_Downloader();
	}

	/**
	 * Import highlights from an uploaded ZIP archive (single request).
	 *
	 * @param string $zip_path          Absolute path to ZIP file.
	 * @param bool   $replace_existing  Deactivate highlights missing from the import.
	 * @return array<string, int>|WP_Error
	 */
	public function import_zip( $zip_path, $replace_existing = true ) {
		$job = $this->create_job_from_zip( $zip_path, $replace_existing );
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$job_id = $job['job_id'];

		while ( true ) {
			$result = $this->process_job_step( $job_id, self::BATCH_SIZE );
			if ( is_wp_error( $result ) ) {
				$this->cancel_job( $job_id );
				return $result;
			}

			if ( ! empty( $result['done'] ) ) {
				return array(
					'highlights' => (int) ( $result['imported'] ?? 0 ),
					'stories'    => (int) ( $result['story_count'] ?? 0 ),
				);
			}
		}
	}

	/**
	 * Stage an uploaded ZIP and create a resumable import job.
	 *
	 * @param string $uploaded_tmp_path Path to PHP upload temp file.
	 * @param bool   $replace_existing  Replace highlights not in the import.
	 * @param bool   $is_uploaded_file  Whether the path is from PHP's upload handler.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_job_from_zip( $uploaded_tmp_path, $replace_existing = true, $is_uploaded_file = false ) {
		$this->cleanup_stale_jobs();

		if ( ! is_readable( $uploaded_tmp_path ) ) {
			return new WP_Error( 'ipa_zip_unreadable', __( 'Could not read the uploaded ZIP file.', 'instagram-profile-archive' ) );
		}

		$file_size = filesize( $uploaded_tmp_path );
		if ( false !== $file_size && $file_size > self::MAX_ZIP_BYTES ) {
			return new WP_Error(
				'ipa_zip_too_large',
				__( 'The highlights ZIP file is too large.', 'instagram-profile-archive' )
			);
		}

		$job_id  = wp_generate_password( 32, false, false );
		$job_dir = trailingslashit( get_temp_dir() ) . 'ipa-hl-' . $job_id;
		if ( ! wp_mkdir_p( $job_dir ) ) {
			return new WP_Error( 'ipa_zip_extract_failed', __( 'Could not create a temporary directory for import.', 'instagram-profile-archive' ) );
		}

		$zip_path = trailingslashit( $job_dir ) . 'upload.zip';
		$stored   = false;
		if ( $is_uploaded_file ) {
			$stored = @move_uploaded_file( $uploaded_tmp_path, $zip_path ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		if ( ! $stored && ! copy( $uploaded_tmp_path, $zip_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			$this->remove_directory( $job_dir );
			return new WP_Error( 'ipa_zip_unreadable', __( 'Could not store the uploaded ZIP file.', 'instagram-profile-archive' ) );
		}

		$job = array(
			'user_id'          => get_current_user_id(),
			'phase'            => 'extract',
			'job_dir'          => $job_dir,
			'zip_path'         => $zip_path,
			'extract_dir'      => '',
			'base_dir'         => '',
			'username'         => '',
			'replace_existing' => (bool) $replace_existing,
			'index'            => 0,
			'total'            => 0,
			'imported'         => 0,
			'story_count'      => 0,
			'seen_ids'         => array(),
			'created_at'       => time(),
		);

		$this->save_job( $job_id, $job );

		return array(
			'job_id'   => $job_id,
			'total'    => 0,
			'username' => '',
		);
	}

	/**
	 * Import the next batch of highlights for a job.
	 *
	 * @param string $job_id     Job identifier.
	 * @param int    $batch_size Highlights to process this step.
	 * @return array<string, mixed>|WP_Error
	 */
	public function process_job_step( $job_id, $batch_size = self::BATCH_SIZE ) {
		@set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$job = $this->get_job( $job_id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		if ( 'extract' === ( $job['phase'] ?? '' ) ) {
			return $this->process_extract_phase( $job_id, $job );
		}

		$manifest = $this->load_manifest_for_job( $job );
		if ( is_wp_error( $manifest ) ) {
			$this->cancel_job( $job_id );
			return $manifest;
		}

		$highlights = array_values( array_filter( (array) ( $manifest['highlights'] ?? array() ), 'is_array' ) );
		$logs       = array();
		$processed  = 0;
		$deadline   = microtime( true ) + self::BATCH_TIME_LIMIT;

		while ( $job['index'] < $job['total'] ) {
			if ( $processed > 0 && ( microtime( true ) >= $deadline || $processed >= self::BATCH_MAX_HIGHLIGHTS ) ) {
				break;
			}
			$highlight = $highlights[ $job['index'] ] ?? null;
			++$job['index'];

			if ( ! is_array( $highlight ) ) {
				++$processed;
				continue;
			}

			$title  = sanitize_text_field( (string) ( $highlight['title'] ?? __( 'Untitled highlight', 'instagram-profile-archive' ) ) );
			$result = $this->import_highlight_entry( $job['base_dir'], $highlight, $job['index'] - 1 );

			if ( is_wp_error( $result ) ) {
				$logs[] = array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: 1: highlight title, 2: error message */
						__( 'Skipped "%1$s": %2$s', 'instagram-profile-archive' ),
						$title,
						$result->get_error_message()
					),
				);
			} else {
				$job['seen_ids'][] = $result['ig_id'];
				$job['imported']   += 1;
				$job['story_count'] += (int) $result['stories'];
				$logs[]            = array(
					'type'    => 'success',
					'message' => sprintf(
						/* translators: 1: highlight title, 2: story count */
						__( 'Imported "%1$s" (%2$d stories)', 'instagram-profile-archive' ),
						$title,
						(int) $result['stories']
					),
				);
			}

			++$processed;
		}

		$done = $job['index'] >= $job['total'];

		if ( $done ) {
			if ( 0 === $job['imported'] ) {
				$this->cancel_job( $job_id );
				return new WP_Error(
					'ipa_highlights_import_empty',
					__( 'No highlights could be imported from this ZIP. Check that cover images and media files are included.', 'instagram-profile-archive' )
				);
			}

			$this->finalize_job( $job );
			$this->delete_job( $job_id );
			if ( ! empty( $job['job_dir'] ) ) {
				$this->remove_directory( $job['job_dir'] );
			} elseif ( ! empty( $job['extract_dir'] ) ) {
				$this->remove_directory( $job['extract_dir'] );
			}

			$summary = sprintf(
				/* translators: 1: highlight count, 2: story count */
				__( 'Imported %1$d highlights with %2$d stories.', 'instagram-profile-archive' ),
				(int) $job['imported'],
				(int) $job['story_count']
			);

			return array(
				'done'         => true,
				'phase'        => 'import',
				'index'        => $job['index'],
				'total'        => $job['total'],
				'imported'     => $job['imported'],
				'story_count'  => $job['story_count'],
				'percent'      => 100,
				'logs'         => $logs,
				'message'      => $summary,
			);
		}

		$this->save_job( $job_id, $job );

		return array(
			'done'        => false,
			'phase'       => 'import',
			'index'       => $job['index'],
			'total'       => $job['total'],
			'imported'    => $job['imported'],
			'story_count' => $job['story_count'],
			'percent'     => $job['total'] > 0 ? (int) floor( ( $job['index'] / $job['total'] ) * 100 ) : 0,
			'logs'        => $logs,
		);
	}

	/**
	 * Cancel an in-progress import and remove temp files.
	 *
	 * @param string $job_id Job identifier.
	 * @return true|WP_Error
	 */
	public function cancel_job( $job_id ) {
		$job = $this->get_job( $job_id, false );
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		if ( ! empty( $job['extract_dir'] ) ) {
			$this->remove_directory( $job['extract_dir'] );
		}

		if ( ! empty( $job['job_dir'] ) ) {
			$this->remove_directory( $job['job_dir'] );
		}

		$this->delete_job( $job_id );

		return true;
	}

	/**
	 * Extract and validate the staged ZIP for a job.
	 *
	 * @param string               $job_id Job ID.
	 * @param array<string, mixed> $job    Job data.
	 * @return array<string, mixed>|WP_Error
	 */
	private function process_extract_phase( $job_id, $job ) {
		$prepared = $this->prepare_zip_archive( (string) ( $job['zip_path'] ?? '' ), (string) ( $job['job_dir'] ?? '' ) );
		if ( is_wp_error( $prepared ) ) {
			$this->cancel_job( $job_id );
			return $prepared;
		}

		$manifest   = $prepared['manifest'];
		$highlights = array_values( array_filter( (array) ( $manifest['highlights'] ?? array() ), 'is_array' ) );

		$job['phase']         = 'import';
		$job['extract_dir']   = $prepared['extract_dir'];
		$job['base_dir']      = $prepared['base_dir'];
		$job['manifest_path'] = $prepared['manifest_path'];
		$job['username']      = sanitize_text_field( (string) ( $manifest['username'] ?? '' ) );
		$job['total']         = count( $highlights );
		$job['index']         = 0;

		$this->save_job( $job_id, $job );

		if ( ! empty( $job['zip_path'] ) && is_file( $job['zip_path'] ) ) {
			@unlink( $job['zip_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return array(
			'done'        => false,
			'phase'       => 'extract',
			'index'       => 0,
			'total'       => $job['total'],
			'imported'    => 0,
			'story_count' => 0,
			'percent'     => 100,
			'logs'        => array(
				array(
					'type'    => 'info',
					'message' => sprintf(
						/* translators: %d: number of highlights */
						__( 'ZIP extracted. Found %d highlights to import.', 'instagram-profile-archive' ),
						$job['total']
					),
				),
			),
		);
	}

	/**
	 * @param string $zip_path  ZIP path.
	 * @param string $parent_dir Optional parent directory for extracted files.
	 * @return array{extract_dir:string, base_dir:string, manifest_path:string, manifest:array<string,mixed>}|WP_Error
	 */
	private function prepare_zip_archive( $zip_path, $parent_dir = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'ipa_zip_missing',
				__( 'The PHP ZipArchive extension is required to import highlights.', 'instagram-profile-archive' )
			);
		}

		if ( ! is_readable( $zip_path ) ) {
			return new WP_Error( 'ipa_zip_unreadable', __( 'Could not read the uploaded ZIP file.', 'instagram-profile-archive' ) );
		}

		$file_size = filesize( $zip_path );
		if ( false !== $file_size && $file_size > self::MAX_ZIP_BYTES ) {
			return new WP_Error(
				'ipa_zip_too_large',
				__( 'The highlights ZIP file is too large.', 'instagram-profile-archive' )
			);
		}

		$extract_dir = $parent_dir
			? trailingslashit( $parent_dir ) . 'extracted'
			: trailingslashit( get_temp_dir() ) . 'ipa-highlights-' . wp_generate_password( 12, false );
		if ( ! wp_mkdir_p( $extract_dir ) ) {
			return new WP_Error( 'ipa_zip_extract_failed', __( 'Could not create a temporary directory for import.', 'instagram-profile-archive' ) );
		}

		$zip    = new ZipArchive();
		$opened = $zip->open( $zip_path );
		if ( true !== $opened ) {
			$this->remove_directory( $extract_dir );
			return new WP_Error( 'ipa_zip_invalid', __( 'The uploaded file is not a valid ZIP archive.', 'instagram-profile-archive' ) );
		}

		if ( ! $zip->extractTo( $extract_dir ) ) {
			$zip->close();
			$this->remove_directory( $extract_dir );
			return new WP_Error( 'ipa_zip_extract_failed', __( 'Could not extract the highlights ZIP file.', 'instagram-profile-archive' ) );
		}
		$zip->close();

		$manifest_path = $this->locate_manifest( $extract_dir );
		if ( ! $manifest_path ) {
			$this->remove_directory( $extract_dir );
			return new WP_Error(
				'ipa_manifest_missing',
				__( 'The ZIP must contain highlights.json at the root (exported by the YouPreserver Highlights Chrome extension).', 'instagram-profile-archive' )
			);
		}

		$manifest_raw = file_get_contents( $manifest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$manifest     = json_decode( (string) $manifest_raw, true );
		if ( ! is_array( $manifest ) ) {
			$this->remove_directory( $extract_dir );
			return new WP_Error( 'ipa_manifest_invalid', __( 'highlights.json is not valid JSON.', 'instagram-profile-archive' ) );
		}

		$validation = $this->validate_manifest( $manifest );
		if ( is_wp_error( $validation ) ) {
			$this->remove_directory( $extract_dir );
			return $validation;
		}

		return array(
			'extract_dir'   => $extract_dir,
			'base_dir'      => dirname( $manifest_path ),
			'manifest_path' => $manifest_path,
			'manifest'      => $manifest,
		);
	}

	/**
	 * @param array<string, mixed> $job Job data.
	 * @return array<string, mixed>|WP_Error
	 */
	private function load_manifest_for_job( $job ) {
		$manifest_path = ! empty( $job['manifest_path'] ) ? (string) $job['manifest_path'] : $this->locate_manifest( $job['extract_dir'] );
		if ( ! $manifest_path || ! is_readable( $manifest_path ) ) {
			return new WP_Error( 'ipa_manifest_missing', __( 'Import files are no longer available.', 'instagram-profile-archive' ) );
		}

		$manifest_raw = file_get_contents( $manifest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$manifest     = json_decode( (string) $manifest_raw, true );
		if ( ! is_array( $manifest ) ) {
			return new WP_Error( 'ipa_manifest_invalid', __( 'highlights.json is not valid JSON.', 'instagram-profile-archive' ) );
		}

		return $manifest;
	}

	/**
	 * @param array<string, mixed> $job Job data.
	 * @return void
	 */
	private function finalize_job( $job ) {
		if ( ! empty( $job['replace_existing'] ) && ! empty( $job['seen_ids'] ) ) {
			IPA_DB::deactivate_highlights_not_in( $job['seen_ids'] );
		}

		if ( ! empty( $job['username'] ) && ! ipa_get_setting( 'username', '' ) ) {
			update_option( 'ipa_username', $job['username'] );
		}

		update_option( 'ipa_last_highlights_import_at', current_time( 'mysql', true ) );
		update_option(
			'ipa_last_highlights_import_message',
			sprintf(
				/* translators: 1: highlight count, 2: story count */
				__( 'Imported %1$d highlights with %2$d stories.', 'instagram-profile-archive' ),
				(int) $job['imported'],
				(int) $job['story_count']
			)
		);

		ipa_clear_frontend_cache();

		IPA_Security::audit(
			'highlights_imported',
			array(
				'highlights' => (int) $job['imported'],
				'stories'    => (int) $job['story_count'],
				'username'   => $job['username'] ?? '',
			)
		);
	}

	/**
	 * @param string $job_id Job ID.
	 * @param bool   $verify_user Verify current user owns the job.
	 * @return array<string, mixed>|WP_Error
	 */
	private function get_job( $job_id, $verify_user = true ) {
		$job_id = sanitize_key( (string) $job_id );
		if ( '' === $job_id ) {
			return new WP_Error( 'ipa_job_invalid', __( 'Invalid import job.', 'instagram-profile-archive' ) );
		}

		$job = get_transient( self::JOB_TRANSIENT_PREFIX . $job_id );
		if ( ! is_array( $job ) ) {
			return new WP_Error( 'ipa_job_missing', __( 'This import job has expired or was already completed.', 'instagram-profile-archive' ) );
		}

		if ( $verify_user && (int) ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'ipa_job_forbidden', __( 'You do not have permission to access this import job.', 'instagram-profile-archive' ) );
		}

		return $job;
	}

	/**
	 * @param string               $job_id Job ID.
	 * @param array<string, mixed> $job    Job data.
	 * @return void
	 */
	private function save_job( $job_id, $job ) {
		set_transient( self::JOB_TRANSIENT_PREFIX . sanitize_key( $job_id ), $job, self::JOB_TTL );
	}

	/**
	 * @param string $job_id Job ID.
	 * @return void
	 */
	private function delete_job( $job_id ) {
		delete_transient( self::JOB_TRANSIENT_PREFIX . sanitize_key( $job_id ) );
	}

	/**
	 * Remove expired import jobs and temp directories.
	 *
	 * @return void
	 */
	private function cleanup_stale_jobs() {
		global $wpdb;

		$like = '_transient_' . self::JOB_TRANSIENT_PREFIX . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		foreach ( $rows ?: array() as $row ) {
			$job = maybe_unserialize( $row->option_value );
			if ( ! is_array( $job ) ) {
				continue;
			}

			$created = (int) ( $job['created_at'] ?? 0 );
			if ( $created > 0 && ( time() - $created ) > self::JOB_TTL ) {
				if ( ! empty( $job['extract_dir'] ) ) {
					$this->remove_directory( $job['extract_dir'] );
				}
				if ( ! empty( $job['job_dir'] ) ) {
					$this->remove_directory( $job['job_dir'] );
				}
				$transient_name = str_replace( '_transient_', '', $row->option_name );
				delete_transient( $transient_name );
			}
		}
	}

	/**
	 * Import one highlight entry.
	 *
	 * @param string               $base_dir  Extract base directory.
	 * @param array<string, mixed> $highlight Highlight data.
	 * @param int                  $order     Sort order fallback.
	 * @return array{ig_id:string, stories:int}|WP_Error
	 */
	private function import_highlight_entry( $base_dir, $highlight, $order ) {
		$ig_id = sanitize_text_field( (string) ( $highlight['id'] ?? '' ) );
		if ( '' === $ig_id ) {
			return new WP_Error( 'ipa_highlight_missing_id', __( 'Missing highlight ID.', 'instagram-profile-archive' ) );
		}

		$cover_path = $this->resolve_archive_path( $base_dir, (string) ( $highlight['cover'] ?? '' ) );
		if ( ! $cover_path ) {
			return new WP_Error( 'ipa_highlight_missing_cover', __( 'Cover image not found in ZIP.', 'instagram-profile-archive' ) );
		}

		$cover = $this->import_local_media( $cover_path, 'highlight_cover_' . $this->sanitize_key( $ig_id ), 'IMAGE', 'thumb' );
		if ( is_wp_error( $cover ) ) {
			return $cover;
		}

		$stories = array();
		foreach ( (array) ( $highlight['stories'] ?? array() ) as $story ) {
			if ( ! is_array( $story ) || count( $stories ) >= self::MAX_STORIES_PER_HIGHLIGHT ) {
				continue;
			}

			$story_id = sanitize_text_field( (string) ( $story['id'] ?? '' ) );
			if ( '' === $story_id ) {
				continue;
			}

			$media_type = strtoupper( (string) ( $story['media_type'] ?? 'IMAGE' ) );
			$file_rel   = (string) ( $story['file'] ?? '' );
			$thumb_rel  = (string) ( $story['thumb'] ?? $file_rel );
			$file_path  = $this->resolve_archive_path( $base_dir, $file_rel );
			if ( ! $file_path ) {
				continue;
			}

			$media = $this->import_local_media(
				$file_path,
				'highlight_' . $this->sanitize_key( $ig_id ) . '_' . $this->sanitize_key( $story_id ),
				$media_type,
				'media'
			);
			if ( is_wp_error( $media ) ) {
				continue;
			}

			$thumb_url = $media['url'];
			if ( $thumb_rel !== $file_rel ) {
				$thumb_path = $this->resolve_archive_path( $base_dir, $thumb_rel );
				if ( $thumb_path ) {
					$thumb = $this->import_local_media(
						$thumb_path,
						'highlight_' . $this->sanitize_key( $ig_id ) . '_' . $this->sanitize_key( $story_id ) . '_thumb',
						'IMAGE',
						'thumb'
					);
					if ( ! is_wp_error( $thumb ) ) {
						$thumb_url = $thumb['url'];
					}
				}
			}

			$stories[] = array(
				'id'         => $story_id,
				'media_type' => $media_type,
				'url'        => esc_url_raw( $media['url'] ),
				'thumb'      => esc_url_raw( $thumb_url ),
				'posted_at'  => sanitize_text_field( (string) ( $story['posted_at'] ?? '' ) ),
			);
		}

		IPA_DB::upsert_highlight(
			array(
				'ig_highlight_id'     => $ig_id,
				'title'               => sanitize_text_field( (string) ( $highlight['title'] ?? '' ) ),
				'cover_instagram_url' => '',
				'local_cover_id'      => (int) $cover['attachment_id'],
				'local_cover_url'     => esc_url_raw( $cover['url'] ),
				'local_cover_path'    => (string) ( $cover['path'] ?? '' ),
				'item_count'          => count( $stories ),
				'sort_order'          => isset( $highlight['sort_order'] ) ? (int) $highlight['sort_order'] : (int) $order,
				'stories_json'        => wp_json_encode( $stories ),
				'raw_json'            => wp_json_encode( $highlight ),
				'status'              => 'active',
				'synced_at'           => current_time( 'mysql', true ),
			)
		);

		return array(
			'ig_id'    => $ig_id,
			'stories'  => count( $stories ),
		);
	}

	/**
	 * @param array<string, mixed> $manifest Manifest data.
	 * @return true|WP_Error
	 */
	private function validate_manifest( $manifest ) {
		if ( (int) ( $manifest['version'] ?? 0 ) !== self::SUPPORTED_VERSION ) {
			return new WP_Error(
				'ipa_manifest_version',
				__( 'Unsupported highlights export version. Please update the Chrome extension and export again.', 'instagram-profile-archive' )
			);
		}

		if ( self::EXPORT_FORMAT !== ( $manifest['format'] ?? '' ) ) {
			return new WP_Error(
				'ipa_manifest_format',
				__( 'This ZIP is not a valid YouPreserver Highlights export.', 'instagram-profile-archive' )
			);
		}

		if ( empty( $manifest['highlights'] ) || ! is_array( $manifest['highlights'] ) ) {
			return new WP_Error(
				'ipa_manifest_empty',
				__( 'The highlights export does not contain any highlights.', 'instagram-profile-archive' )
			);
		}

		if ( count( $manifest['highlights'] ) > self::MAX_HIGHLIGHTS ) {
			return new WP_Error(
				'ipa_manifest_too_many',
				sprintf(
					/* translators: %d: max highlights */
					__( 'This export contains too many highlights (max %d).', 'instagram-profile-archive' ),
					self::MAX_HIGHLIGHTS
				)
			);
		}

		return true;
	}

	/**
	 * @param string $base_dir Extract root.
	 * @return string|false
	 */
	private function locate_manifest( $base_dir ) {
		foreach ( self::MANIFEST_NAMES as $name ) {
			$direct = trailingslashit( $base_dir ) . $name;
			if ( is_file( $direct ) ) {
				return $direct;
			}
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
				continue;
			}
			if ( in_array( $file->getFilename(), self::MANIFEST_NAMES, true ) ) {
				return $file->getPathname();
			}
		}

		return false;
	}

	/**
	 * @param string $base_dir Base directory.
	 * @param string $relative Relative path from manifest.
	 * @return string|false
	 */
	private function resolve_archive_path( $base_dir, $relative ) {
		$relative = str_replace( '\\', '/', trim( $relative ) );
		if ( '' === $relative || 0 === strpos( $relative, '/' ) ) {
			return false;
		}

		$parts = array();
		foreach ( explode( '/', $relative ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				return false;
			}
			$parts[] = $part;
		}

		if ( empty( $parts ) ) {
			return false;
		}

		$path      = trailingslashit( $base_dir ) . implode( '/', $parts );
		$real_base = realpath( $base_dir );
		$real_path = realpath( $path );

		if ( false === $real_base || false === $real_path || 0 !== strpos( $real_path, $real_base ) ) {
			return false;
		}

		if ( ! is_file( $real_path ) || ! is_readable( $real_path ) ) {
			return false;
		}

		$checked = wp_check_filetype( $real_path );
		$allowed = array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'm4v' );
		if ( empty( $checked['ext'] ) || ! in_array( strtolower( $checked['ext'] ), $allowed, true ) ) {
			return false;
		}

		return $real_path;
	}

	/**
	 * @param string $source_path Source file.
	 * @param string $ig_media_id Media key.
	 * @param string $media_type  Media type.
	 * @param string $download_type media|thumb.
	 * @return array<string, mixed>|WP_Error
	 */
	private function import_local_media( $source_path, $ig_media_id, $media_type, $download_type ) {
		$existing = $this->get_cached_attachment( $ig_media_id, $download_type );
		if ( $existing ) {
			$file_path = get_attached_file( $existing );
			return array(
				'attachment_id' => $existing,
				'url'           => wp_get_attachment_url( $existing ),
				'path'          => $file_path ?: '',
			);
		}

		if ( ! $this->downloader->validate_file_type( $source_path, $media_type ) ) {
			return new WP_Error( 'ipa_invalid_media', __( 'Unsupported media file in highlights export.', 'instagram-profile-archive' ) );
		}

		$checked  = wp_check_filetype( $source_path );
		$filename = $this->downloader->build_filename(
			$ig_media_id,
			'source.' . ( $checked['ext'] ?? 'jpg' ),
			$media_type,
			$download_type
		);

		$attachment_id = $this->downloader->sideload_to_media_library(
			$source_path,
			$filename,
			sprintf( 'YouPreserver Highlight %s', $ig_media_id )
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		update_post_meta( $attachment_id, '_ipa_ig_media_id', sanitize_text_field( $ig_media_id ) );
		update_post_meta( $attachment_id, '_ipa_media_type', sanitize_text_field( $media_type ) );
		update_post_meta( $attachment_id, '_ipa_download_type', sanitize_text_field( $download_type ) );

		$cache_key = $ig_media_id . '|' . $download_type;
		$this->attachment_cache[ $cache_key ] = (int) $attachment_id;

		return array(
			'attachment_id' => (int) $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'path'          => get_attached_file( $attachment_id ) ?: '',
		);
	}

	/**
	 * @param string $ig_media_id   Media key.
	 * @param string $download_type media|thumb.
	 * @return int
	 */
	private function get_cached_attachment( $ig_media_id, $download_type ) {
		$cache_key = $ig_media_id . '|' . $download_type;
		if ( array_key_exists( $cache_key, $this->attachment_cache ) ) {
			return (int) $this->attachment_cache[ $cache_key ];
		}

		$attachment_id = $this->downloader->get_existing_attachment( $ig_media_id, $download_type );
		$this->attachment_cache[ $cache_key ] = (int) $attachment_id;

		return (int) $attachment_id;
	}

	/**
	 * @param string $value Raw key.
	 * @return string
	 */
	private function sanitize_key( $value ) {
		return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value );
	}

	/**
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo ) {
				continue;
			}
			if ( $file->isDir() ) {
				@rmdir( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
			} else {
				@unlink( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
