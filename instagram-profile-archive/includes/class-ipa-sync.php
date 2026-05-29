<?php
/**
 * Sync orchestration.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Sync
 */
class IPA_Sync {

	/**
	 * @var IPA_Logger
	 */
	private $logger;

	/**
	 * @var IPA_API
	 */
	private $api;

	/**
	 * @var IPA_Media_Downloader
	 */
	private $downloader;

	/**
	 * @var int
	 */
	private $log_id = 0;

	/**
	 * @var array<string, int>
	 */
	private $counters = array(
		'total_found'   => 0,
		'total_new'     => 0,
		'total_updated' => 0,
		'total_skipped' => 0,
		'total_failed'  => 0,
		'api_calls_used'=> 0,
	);

	/**
	 * @var string
	 */
	private $technical_message = '';

	/**
	 * @var array<int, string>
	 */
	private $pinned_ids = array();

	/**
	 * @var bool
	 */
	private $apply_pinned_flags = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger     = new IPA_Logger();
		$this->api        = new IPA_API();
		$this->downloader = new IPA_Media_Downloader();
	}

	/**
	 * Run manual sync.
	 *
	 * @return array<string, mixed>
	 */
	public function run_manual_sync() {
		return $this->sync_recent_media();
	}

	/**
	 * Run cron sync.
	 *
	 * @return array<string, mixed>
	 */
	public function run_cron_sync() {
		return $this->sync_recent_media( 'cron' );
	}

	/**
	 * Run full re-sync.
	 *
	 * @param bool $continue Continue from saved cursor.
	 * @return array<string, mixed>
	 */
	public function run_full_sync( $continue = false ) {
		$cursor = $continue ? $this->get_full_sync_cursor() : null;
		if ( ! $continue ) {
			$this->clear_full_sync_cursor();
		}

		return $this->sync_media_batch(
			$cursor ? $cursor->cursor_value : null,
			'full'
		);
	}

	/**
	 * Sync latest media only.
	 *
	 * @param string $sync_type Sync type.
	 * @return array<string, mixed>
	 */
	public function sync_recent_media( $sync_type = 'manual' ) {
		return $this->sync_media_batch( null, 'recent', $sync_type );
	}

	/**
	 * Sync a batch of media.
	 *
	 * @param string|null $after     Pagination cursor.
	 * @param string      $mode      recent|full.
	 * @param string      $sync_type Log sync type.
	 * @return array<string, mixed>
	 */
	public function sync_media_batch( $after = null, $mode = 'recent', $sync_type = 'manual' ) {
		if ( 'full' === $mode ) {
			$sync_type = $after ? 'full_continue' : 'full';
		}

		$this->reset_counters();
		$this->log_id = $this->logger->start_sync( $sync_type );

		$token = new IPA_Token();
		if ( ! $token->is_token_configured() ) {
			return $this->finish_with_error(
				__( 'Access token is missing. Please add a valid Instagram access token.', 'instagram-profile-archive' )
			);
		}

		if ( $token->is_token_expired() ) {
			return $this->finish_with_error(
				__( 'Your Instagram access token appears to be expired. Please refresh or replace it.', 'instagram-profile-archive' )
			);
		}

		$max_posts   = max( 1, (int) ipa_get_setting( 'max_posts_per_sync', 100 ) );
		$per_request = min( 50, max( 1, (int) ipa_get_setting( 'posts_per_request', 25 ) ) );
		$sync_only_new = (bool) ipa_get_setting( 'sync_only_new', true ) && 'recent' === $mode;
		$processed   = 0;
		$stop_sync   = false;
		$rate_limited = false;

		while ( $processed < $max_posts && ! $stop_sync ) {
			$response = $this->api->get_media( $per_request, $after );
			$this->counters['api_calls_used'] = $this->api->get_api_call_count();

			if ( is_wp_error( $response ) ) {
				if ( 'ipa_rate_limited' === $response->get_error_code() ) {
					$rate_limited = true;
					if ( 'full' === $mode && $after ) {
						$this->save_full_sync_cursor( $after );
					}
					return $this->finish_with_summary(
						'rate_limited',
						$response->get_error_message(),
						true
					);
				}

				return $this->finish_with_error( $response->get_error_message(), $response->get_error_data() );
			}

			$this->capture_rate_limit_headers( $response['headers'] ?? array() );

			$items = $response['data'] ?? array();
			if ( empty( $items ) ) {
				if ( 'full' === $mode ) {
					$this->clear_full_sync_cursor();
				}
				break;
			}

			if ( null === $after && ! (bool) get_option( 'ipa_manual_pins', false ) ) {
				IPA_DB::clear_all_pinned_flags();
				$this->pinned_ids         = ipa_detect_pinned_media_ids( $items );
				$this->apply_pinned_flags = true;
			}

			foreach ( $items as $item ) {
				if ( $processed >= $max_posts ) {
					$stop_sync = true;
					break;
				}

				++$this->counters['total_found'];

				$existing = $this->get_existing_media( $item['id'] ?? '' );
				if ( $sync_only_new && $existing ) {
					++$this->counters['total_skipped'];
					$stop_sync = true;
					break;
				}

				$result = $this->sync_media_item( $item, null, 'full' === $mode );

				if ( is_wp_error( $result ) ) {
					++$this->counters['total_failed'];
					continue;
				}

				if ( ! empty( $result['is_new'] ) ) {
					++$this->counters['total_new'];
				} elseif ( ! empty( $result['updated'] ) ) {
					++$this->counters['total_updated'];
				} else {
					++$this->counters['total_skipped'];
				}

				++$processed;
			}

			$after = $response['paging']['cursors']['after'] ?? '';
			if ( empty( $after ) || empty( $response['paging']['next'] ) ) {
				if ( 'full' === $mode ) {
					$this->clear_full_sync_cursor();
				}
				break;
			}

			if ( $processed >= $max_posts && 'full' === $mode ) {
				$this->save_full_sync_cursor( $after );
				break;
			}
		}

		return $this->finish_with_summary( 'success' );
	}

	/**
	 * Sync a single media item.
	 *
	 * @param array<string, mixed> $item      API item.
	 * @param string|null          $parent_id Parent Instagram media ID.
	 * @param bool                 $force     Force download.
	 * @return array<string, mixed>|WP_Error
	 */
	public function sync_media_item( $item, $parent_id = null, $force = false ) {
		if ( empty( $item['id'] ) ) {
			return new WP_Error( 'ipa_invalid_item', __( 'Invalid media item from API.', 'instagram-profile-archive' ) );
		}

		$existing = $this->get_existing_media( $item['id'] );
		$row_id   = $this->save_or_update_media( $item, $parent_id );

		if ( ! $row_id ) {
			return new WP_Error( 'ipa_save_failed', __( 'Failed to save media record.', 'instagram-profile-archive' ) );
		}

		$media_type = strtoupper( (string) ( $item['media_type'] ?? 'IMAGE' ) );
		$is_parent  = null === $parent_id && 'CAROUSEL_ALBUM' === $media_type;

		if ( $is_parent && ipa_get_setting( 'sync_carousels', true ) ) {
			$children = $item['children']['data'] ?? array();

			if ( empty( $children ) || ! isset( $children[0]['media_url'] ) ) {
				$children_response = $this->api->get_media_children( $item['id'] );
				$this->counters['api_calls_used'] = $this->api->get_api_call_count();

				if ( ! is_wp_error( $children_response ) ) {
					$children = $children_response['data'] ?? array();
				}
			}

			if ( ! empty( $children ) ) {
				$this->sync_carousel_children( $item['id'], $children );
			}
		}

		if ( null === $parent_id ) {
			$this->maybe_download_media( $item['id'], $force );
		} else {
			$this->maybe_download_media( $item['id'], $force, true );
		}

		return array(
			'id'      => $row_id,
			'is_new'  => ! $existing,
			'updated' => (bool) $existing,
		);
	}

	/**
	 * Sync carousel children.
	 *
	 * @param string               $parent_ig_media_id Parent ID.
	 * @param array<int, array<string, mixed>> $children_data Children.
	 * @return void
	 */
	public function sync_carousel_children( $parent_ig_media_id, $children_data ) {
		$child_count = count( $children_data );

		IPA_DB::update_media(
			$parent_ig_media_id,
			array(
				'is_carousel_parent' => 1,
				'has_children'       => 1,
				'child_count'        => $child_count,
			)
		);

		foreach ( $children_data as $child ) {
			$this->sync_media_item( $child, $parent_ig_media_id );
		}

		$first_child = IPA_DB::get_media_children( $parent_ig_media_id );
		if ( ! empty( $first_child[0] ) ) {
			$parent = IPA_DB::get_media_by_ig_id( $parent_ig_media_id );
			if ( $parent && empty( $parent->instagram_media_url ) ) {
				IPA_DB::update_media(
					$parent_ig_media_id,
					array(
						'instagram_media_url' => $first_child[0]->instagram_media_url,
						'thumbnail_url'       => $first_child[0]->thumbnail_url ?: $first_child[0]->instagram_media_url,
					)
				);
			}

			$this->maybe_download_media( $parent_ig_media_id );
		}
	}

	/**
	 * Save or update media row.
	 *
	 * @param array<string, mixed> $item      API item.
	 * @param string|null          $parent_id Parent ID.
	 * @return int|false
	 */
	public function save_or_update_media( $item, $parent_id = null ) {
		$ig_media_id = sanitize_text_field( $item['id'] ?? '' );
		if ( empty( $ig_media_id ) ) {
			return false;
		}

		$media_type = strtoupper( sanitize_text_field( $item['media_type'] ?? 'IMAGE' ) );
		$posted_at  = ! empty( $item['timestamp'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $item['timestamp'] ) ) : null;

		$data = array(
			'ig_media_id'           => $ig_media_id,
			'parent_ig_media_id'    => $parent_id ? sanitize_text_field( $parent_id ) : null,
			'media_type'            => $media_type,
			'media_product_type'    => sanitize_text_field( $item['media_product_type'] ?? '' ),
			'caption'               => ipa_get_setting( 'sync_captions', true ) ? ( $item['caption'] ?? '' ) : '',
			'permalink'             => esc_url_raw( $item['permalink'] ?? '' ),
			'instagram_media_url'   => esc_url_raw( $item['media_url'] ?? '' ),
			'thumbnail_url'         => esc_url_raw( $item['thumbnail_url'] ?? '' ),
			'posted_at'             => $posted_at,
			'synced_at'             => current_time( 'mysql', true ),
			'is_carousel_parent'    => ( null === $parent_id && 'CAROUSEL_ALBUM' === $media_type ) ? 1 : 0,
			'raw_json'              => wp_json_encode( $item ),
			'sync_status'           => 'synced',
			'status'                => 'active',
		);

		if ( null === $parent_id && $this->apply_pinned_flags ) {
			$data['is_pinned'] = in_array( $ig_media_id, $this->pinned_ids, true ) ? 1 : 0;
		}

		return IPA_DB::upsert_media( $data );
	}

	/**
	 * @param string $ig_media_id Media ID.
	 * @return object|null
	 */
	public function get_existing_media( $ig_media_id ) {
		return IPA_DB::get_media_by_ig_id( $ig_media_id );
	}

	/**
	 * @return string|null
	 */
	public function get_latest_synced_timestamp() {
		global $wpdb;

		$table = IPA_DB::media_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$latest = $wpdb->get_var( "SELECT MAX(posted_at) FROM {$table} WHERE parent_ig_media_id IS NULL OR parent_ig_media_id = ''" );

		return $latest ?: null;
	}

	/**
	 * @param array<string, mixed> $item Item.
	 * @return bool
	 */
	public function should_skip_existing( $item ) {
		return (bool) $this->get_existing_media( $item['id'] ?? '' );
	}

	/**
	 * Download media files for a stored record.
	 *
	 * @param string $ig_media_id Media ID.
	 * @param bool   $force       Force download.
	 * @param bool   $is_child    Is carousel child.
	 * @return void
	 */
	private function maybe_download_media( $ig_media_id, $force = false, $is_child = false ) {
		$row = IPA_DB::get_media_by_ig_id( $ig_media_id );
		if ( ! $row ) {
			return;
		}

		$media_type          = strtoupper( (string) $row->media_type );
		$download_images     = (bool) ipa_get_setting( 'download_media_locally', true );
		$download_videos     = (bool) ipa_get_setting( 'download_videos_locally', true );
		$is_video            = in_array( $media_type, array( 'VIDEO', 'REELS' ), true );
		$should_download     = $is_video ? $download_videos : $download_images;
		$update              = array();
		$download_error      = '';

		if ( ! $should_download ) {
			IPA_DB::update_media(
				$ig_media_id,
				array(
					'download_status' => 'skipped',
				)
			);
			return;
		}

		$needs_media = $force || empty( $row->local_file_id ) || ( ! empty( $row->local_file_path ) && ! file_exists( (string) $row->local_file_path ) );

		$media_url = $row->instagram_media_url;
		if ( empty( $media_url ) && 'CAROUSEL_ALBUM' === $media_type ) {
			$children = IPA_DB::get_media_children( $ig_media_id );
			if ( ! empty( $children[0]->instagram_media_url ) ) {
				$media_url = $children[0]->instagram_media_url;
			}
		}

		if ( $needs_media && ! empty( $media_url ) ) {
			$result = $this->downloader->download_media(
				$media_url,
				$ig_media_id,
				$media_type,
				$force
			);

			if ( is_wp_error( $result ) ) {
				$download_error = $result->get_error_message();
			} else {
				$new_attachment_id = (int) $result['attachment_id'];
				if ( ! empty( $row->local_file_id ) && (int) $row->local_file_id !== $new_attachment_id ) {
					$this->downloader->delete_downloaded_attachment( (int) $row->local_file_id );
				}
				$update['local_file_id']   = $new_attachment_id;
				$update['local_file_url']  = $result['url'];
				$update['local_file_path'] = $result['path'];
				$update['download_status'] = 'downloaded';
			}
		}

		if ( $is_video && ! empty( $row->thumbnail_url ) ) {
			$needs_thumb = $force || empty( $row->local_thumbnail_id );
			if ( $needs_thumb ) {
				$thumb_result = $this->downloader->download_thumbnail( $row->thumbnail_url, $ig_media_id, $force );
				if ( ! is_wp_error( $thumb_result ) ) {
					$new_thumb_id = (int) $thumb_result['attachment_id'];
					if ( ! empty( $row->local_thumbnail_id ) && (int) $row->local_thumbnail_id !== $new_thumb_id ) {
						$this->downloader->delete_downloaded_attachment( (int) $row->local_thumbnail_id );
					}
					$update['local_thumbnail_id']   = $new_thumb_id;
					$update['local_thumbnail_url']  = $thumb_result['url'];
					$update['local_thumbnail_path'] = $thumb_result['path'];
				}
			}
		}

		if ( ! empty( $download_error ) ) {
			$update['download_status'] = 'failed';
			$update['download_error']  = $download_error;
		} elseif ( empty( $update['download_status'] ) && ! empty( $update['local_file_id'] ) ) {
			$update['download_status'] = 'downloaded';
		}

		if ( ! empty( $update ) ) {
			IPA_DB::update_media( $ig_media_id, $update );
		}
	}

	/**
	 * @return object|null
	 */
	public function get_full_sync_cursor() {
		return IPA_DB::get_cursor( IPA_DB::CURSOR_FULL_SYNC );
	}

	/**
	 * @param string $cursor Cursor value.
	 * @return void
	 */
	public function save_full_sync_cursor( $cursor ) {
		IPA_DB::save_cursor( IPA_DB::CURSOR_FULL_SYNC, $cursor );
	}

	/**
	 * @return void
	 */
	public function clear_full_sync_cursor() {
		IPA_DB::delete_cursor( IPA_DB::CURSOR_FULL_SYNC );
	}

	/**
	 * @param array<string, mixed> $headers Response headers.
	 * @return void
	 */
	private function capture_rate_limit_headers( $headers ) {
		if ( empty( $headers ) ) {
			return;
		}

		$usage = $headers['x-business-use-case-usage'] ?? $headers['X-Business-Use-Case-Usage'] ?? '';
		if ( $usage ) {
			$this->technical_message = is_array( $usage ) ? wp_json_encode( $usage ) : (string) $usage;
		}
	}

	/**
	 * @return void
	 */
	private function reset_counters() {
		$this->counters = array(
			'total_found'    => 0,
			'total_new'      => 0,
			'total_updated'  => 0,
			'total_skipped'  => 0,
			'total_failed'   => 0,
			'api_calls_used' => 0,
		);
		$this->technical_message = '';
		$this->pinned_ids        = array();
		$this->apply_pinned_flags = false;
	}

	/**
	 * Refresh profile avatar and display fields after sync.
	 *
	 * @return void
	 */
	private function maybe_sync_profile_image() {
		$user_id = ipa_get_setting( 'ig_user_id', '' );
		$token   = ipa_get_setting( 'access_token', '' );

		if ( empty( $user_id ) || empty( $token ) ) {
			return;
		}

		ipa_sync_profile_from_api( $user_id, $token );
	}

	/**
	 * @param string $message Message.
	 * @param mixed  $technical Technical data.
	 * @return array<string, mixed>
	 */
	private function finish_with_error( $message, $technical = '' ) {
		$technical_message = is_array( $technical ) ? wp_json_encode( $technical ) : (string) $technical;

		$this->logger->fail_sync( $this->log_id, $message, $technical_message );
		ipa_clear_frontend_cache();

		return array(
			'success' => false,
			'message' => $message,
		);
	}

	/**
	 * @param string $status  Status.
	 * @param string $message Custom message.
	 * @param bool   $partial Partial sync.
	 * @return array<string, mixed>
	 */
	private function finish_with_summary( $status = 'success', $message = '', $partial = false ) {
		if ( empty( $message ) ) {
			$message = sprintf(
				/* translators: 1: new count, 2: updated count, 3: skipped count, 4: failed count */
				__( 'Sync complete: %1$d new, %2$d updated, %3$d skipped, %4$d failed.', 'instagram-profile-archive' ),
				$this->counters['total_new'],
				$this->counters['total_updated'],
				$this->counters['total_skipped'],
				$this->counters['total_failed']
			);

			if ( 0 === $this->counters['total_found'] ) {
				$message = __( 'No Instagram media was found for this account.', 'instagram-profile-archive' );
			}
		}

		$this->logger->complete_sync(
			$this->log_id,
			array_merge(
				$this->counters,
				array(
					'status'            => $status,
					'message'           => $message,
					'technical_message' => $this->technical_message,
				)
			)
		);

		if ( 'failed' !== $status && $this->counters['total_found'] > 0 ) {
			ipa_disable_mock_mode();
		}

		$this->maybe_sync_profile_image();

		ipa_clear_frontend_cache();

		$cursor = $this->get_full_sync_cursor();

		return array(
			'success'         => 'failed' !== $status,
			'message'         => $message,
			'has_more_full'   => (bool) $cursor,
			'partial'         => $partial,
		);
	}
}
