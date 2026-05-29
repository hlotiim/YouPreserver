<?php
/**
 * Helper functions.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get plugin settings with defaults.
 *
 * @return array<string, mixed>
 */
function ipa_get_settings() {
	return IPA_Settings::get_all();
}

/**
 * Get a single setting value.
 *
 * @param string $key     Setting key without ipa_ prefix.
 * @param mixed  $default Default value.
 * @return mixed
 */
function ipa_get_setting( $key, $default = '' ) {
	return IPA_Settings::get( $key, $default );
}

/**
 * Format datetime for display.
 *
 * @param string|null $datetime Datetime string.
 * @return string
 */
/**
 * Escape a value for use in an img src attribute (https or inline SVG data URIs).
 *
 * @param string $url Image URL.
 * @return string
 */
function ipa_esc_img_src( $url ) {
	$url = (string) $url;

	if ( 0 === strpos( $url, 'data:image/' ) ) {
		return esc_attr( $url );
	}

	return esc_url( $url );
}

/**
 * Clear cached frontend display data.
 *
 * @return void
 */
function ipa_clear_frontend_cache() {
	delete_transient( 'ipa_frontend_data_mock' );
	delete_transient( 'ipa_frontend_data_live' );
}

/**
 * Disable mock mode so the frontend reads archived media from the database.
 *
 * @return void
 */
function ipa_disable_mock_mode() {
	update_option( 'ipa_enable_mock_mode', 0 );
	ipa_clear_frontend_cache();
}

/**
 * Download and store the Instagram profile image locally.
 *
 * @param string $url       Remote profile image URL.
 * @param string $ig_user_id Instagram user ID.
 * @return bool
 */
function ipa_save_local_profile_image( $url, $ig_user_id ) {
	$url        = esc_url_raw( (string) $url );
	$ig_user_id = sanitize_text_field( (string) $ig_user_id );

	if ( empty( $url ) || empty( $ig_user_id ) ) {
		return false;
	}

	$downloader = new IPA_Media_Downloader();
	$result     = $downloader->download_thumbnail( $url, 'profile_' . $ig_user_id, true );

	if ( is_wp_error( $result ) ) {
		update_option( 'ipa_profile_image_url', $url );
		return false;
	}

	update_option( 'ipa_profile_image_id', (int) $result['attachment_id'] );
	update_option( 'ipa_profile_image_url', esc_url_raw( $result['url'] ) );
	ipa_clear_frontend_cache();

	return true;
}

/**
 * Sync profile display fields and local avatar from the Instagram API.
 *
 * @param string $ig_user_id   Instagram user ID.
 * @param string $access_token Access token.
 * @return void
 */
function ipa_sync_profile_from_api( $ig_user_id, $access_token ) {
	$api_version = ipa_get_setting( 'api_version', 'v23.0' );
	if ( 0 !== strpos( $api_version, 'v' ) ) {
		$api_version = 'v' . ltrim( $api_version, 'v' );
	}

	$url = add_query_arg(
		array(
			'fields'       => 'id,username,name,biography,website,profile_picture_url',
			'access_token' => $access_token,
		),
		'https://graph.instagram.com/' . $api_version . '/' . rawurlencode( $ig_user_id )
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout'   => 20,
			'sslverify' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		return;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['id'] ) ) {
		return;
	}

	if ( ! empty( $body['username'] ) ) {
		update_option( 'ipa_username', sanitize_text_field( $body['username'] ) );
		update_option(
			'ipa_instagram_profile_url',
			esc_url_raw( 'https://www.instagram.com/' . rawurlencode( $body['username'] ) . '/' )
		);
	}

	if ( ! empty( $body['name'] ) && ! ipa_get_setting( 'display_name', '' ) ) {
		update_option( 'ipa_display_name', sanitize_text_field( $body['name'] ) );
	}

	if ( ! empty( $body['biography'] ) && ! ipa_get_setting( 'bio', '' ) ) {
		update_option( 'ipa_bio', sanitize_textarea_field( $body['biography'] ) );
	}

	if ( ! empty( $body['website'] ) && ! ipa_get_setting( 'external_link', '' ) ) {
		update_option( 'ipa_external_link', esc_url_raw( $body['website'] ) );
	}

	if ( ! empty( $body['profile_picture_url'] ) && ipa_get_setting( 'download_media_locally', true ) ) {
		ipa_save_local_profile_image( $body['profile_picture_url'], $ig_user_id );
	} elseif ( ! empty( $body['profile_picture_url'] ) && ! ipa_get_setting( 'profile_image_url', '' ) ) {
		update_option( 'ipa_profile_image_url', esc_url_raw( $body['profile_picture_url'] ) );
	}

	ipa_sync_gallery_page_title();
	ipa_clear_frontend_cache();
}

/**
 * Import story highlights from an extension export ZIP.
 *
 * @param string $zip_path Absolute path to ZIP.
 * @param bool   $replace_existing Replace highlights not in the import.
 * @return array<string, int>|WP_Error
 */
function ipa_import_highlights_zip( $zip_path, $replace_existing = true ) {
	if ( ! class_exists( 'IPA_Highlights_Import' ) ) {
		return new WP_Error( 'ipa_missing_highlights_import', __( 'Highlights import is unavailable.', 'instagram-profile-archive' ) );
	}

	$importer = new IPA_Highlights_Import();
	return $importer->import_zip( $zip_path, $replace_existing );
}

/**
 * Create a chunked highlights import job from a ZIP file.
 *
 * @param string $zip_path         Absolute path to ZIP.
 * @param bool   $replace_existing Replace highlights not in the import.
 * @param bool   $is_uploaded_file Whether the path is from PHP's upload handler.
 * @return array<string, mixed>|WP_Error
 */
function ipa_highlights_import_create_job( $zip_path, $replace_existing = true, $is_uploaded_file = false ) {
	if ( ! class_exists( 'IPA_Highlights_Import' ) ) {
		return new WP_Error( 'ipa_missing_highlights_import', __( 'Highlights import is unavailable.', 'instagram-profile-archive' ) );
	}

	$importer = new IPA_Highlights_Import();
	return $importer->create_job_from_zip( $zip_path, $replace_existing, $is_uploaded_file );
}

/**
 * Process the next step of a highlights import job.
 *
 * @param string $job_id Job identifier.
 * @return array<string, mixed>|WP_Error
 */
function ipa_highlights_import_step( $job_id ) {
	if ( ! class_exists( 'IPA_Highlights_Import' ) ) {
		return new WP_Error( 'ipa_missing_highlights_import', __( 'Highlights import is unavailable.', 'instagram-profile-archive' ) );
	}

	$importer = new IPA_Highlights_Import();
	return $importer->process_job_step( $job_id );
}

/**
 * Cancel a highlights import job.
 *
 * @param string $job_id Job identifier.
 * @return true|WP_Error
 */
function ipa_highlights_import_cancel( $job_id ) {
	if ( ! class_exists( 'IPA_Highlights_Import' ) ) {
		return new WP_Error( 'ipa_missing_highlights_import', __( 'Highlights import is unavailable.', 'instagram-profile-archive' ) );
	}

	$importer = new IPA_Highlights_Import();
	return $importer->cancel_job( $job_id );
}

function ipa_is_reel_media( $item ) {
	$media_type         = strtoupper( (string) ( is_object( $item ) ? ( $item->media_type ?? '' ) : ( $item['media_type'] ?? '' ) ) );
	$media_product_type = strtoupper( (string) ( is_object( $item ) ? ( $item->media_product_type ?? '' ) : ( $item['media_product_type'] ?? '' ) ) );

	return 'REELS' === $media_type || 'REELS' === $media_product_type;
}

/**
 * Detect pinned post IDs from the first page of Instagram media API results.
 *
 * Instagram returns pinned posts first; they break strict reverse-chronological order.
 *
 * @param array<int, array<string, mixed>> $items API media items.
 * @return array<int, string>
 */
function ipa_detect_pinned_media_ids( $items ) {
	$pinned_ids = array();
	$max_pins   = 3;

	if ( count( $items ) < 2 ) {
		return $pinned_ids;
	}

	for ( $i = 0; $i < min( $max_pins, count( $items ) - 1 ); $i++ ) {
		$current_ts = strtotime( (string) ( $items[ $i ]['timestamp'] ?? '' ) );
		$next_ts    = strtotime( (string) ( $items[ $i + 1 ]['timestamp'] ?? '' ) );

		if ( $current_ts && $next_ts && $current_ts < $next_ts ) {
			$pinned_ids[] = (string) ( $items[ $i ]['id'] ?? '' );
		} else {
			break;
		}
	}

	return array_values( array_filter( $pinned_ids ) );
}

/**
 * Split formatted media arrays into posts and reels collections.
 *
 * @param array<int, array<string, mixed>> $media Media items.
 * @return array{posts: array<int, array<string, mixed>>, reels: array<int, array<string, mixed>>}
 */
function ipa_split_posts_and_reels( $media ) {
	$posts = array();
	$reels = array();

	foreach ( $media as $item ) {
		if ( ipa_is_reel_media( $item ) ) {
			$reels[] = $item;
		} else {
			$posts[] = $item;
		}
	}

	return array(
		'posts' => $posts,
		'reels' => $reels,
	);
}

/**
 * Build modal payload entries for frontend JSON.
 *
 * @param array<int, array<string, mixed>> $media Media items.
 * @return array<int, array<string, mixed>>
 */
function ipa_build_modal_payload( $media ) {
	$modal_payload = array();

	foreach ( $media as $index => $item ) {
		$slides = array();
		if ( ! empty( $item['children'] ) ) {
			foreach ( $item['children'] as $child ) {
				$child_url = ! empty( $child['local_file_url'] ) ? $child['local_file_url'] : ( $child['thumbnail_url'] ?? '' );
				if ( ! empty( $child['mock_color'] ) && empty( $child_url ) ) {
					$child_url = ipa_get_placeholder_svg( $child['mock_color'], '' );
				}
				$slides[] = array(
					'type'  => $child['media_type'],
					'url'   => $child_url,
					'thumb' => $child['thumbnail_url'] ?? $child_url,
				);
			}
		} else {
			$url = ! empty( $item['local_file_url'] ) ? $item['local_file_url'] : ( $item['thumbnail_url'] ?? '' );
			if ( ! empty( $item['mock_color'] ) && empty( $url ) ) {
				$url = ipa_get_placeholder_svg( $item['mock_color'], (string) ( $index + 1 ) );
			}
			$slides[] = array(
				'type'  => $item['media_type'],
				'url'   => $url,
				'thumb' => $item['thumbnail_url'] ?? $url,
			);
		}

		$modal_payload[] = array(
			'caption'   => $item['caption'] ?? '',
			'permalink' => $item['permalink'] ?? '',
			'posted_at' => $item['posted_at'] ?? '',
			'slides'    => $slides,
		);
	}

	return $modal_payload;
}

function ipa_get_grid_palette_color( $index ) {
	$colors = array( '#F57600', '#E86800', '#FF9A2E', '#C95600', '#FFB366' );

	return $colors[ absint( $index ) % count( $colors ) ];
}

/**
 * Build the curated 9-cell featured grid used in the UI preview.
 *
 * @param int $media_count Number of available media items.
 * @return array<int, array<string, mixed>>
 */
function ipa_build_featured_grid_cells( $media_count ) {
	$media_count = max( 0, (int) $media_count );
	$cells       = array(
		array(
			'type' => 'placeholder',
			'mark' => 'dot',
		),
	);

	if ( $media_count > 0 ) {
		$cells[] = array(
			'type'   => 'post',
			'index'  => 0,
			'pinned' => true,
		);
	} else {
		$cells[] = array(
			'type' => 'placeholder',
			'mark' => 'dot',
		);
	}

	$cells[] = array(
		'type' => 'placeholder',
		'mark' => 'dot',
	);

	for ( $i = 1; $i <= 3; $i++ ) {
		if ( $i < $media_count ) {
			$cells[] = array(
				'type'  => 'post',
				'index' => $i,
			);
		} else {
			$cells[] = array(
				'type' => 'placeholder',
				'mark' => 'dot',
			);
		}
	}

	$cells[] = array(
		'type' => 'placeholder',
		'mark' => 'loading',
	);

	if ( $media_count > 4 ) {
		$cells[] = array(
			'type'  => 'post',
			'index' => 4,
		);
	} else {
		$cells[] = array(
			'type' => 'placeholder',
			'mark' => 'dot',
		);
	}

	$cells[] = array(
		'type' => 'placeholder',
		'mark' => 'dot',
	);

	return $cells;
}

function ipa_format_datetime( $datetime ) {
	if ( empty( $datetime ) ) {
		return '';
	}

	$timestamp = strtotime( $datetime );
	if ( ! $timestamp ) {
		return '';
	}

	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * Get mock media items for UI development.
 *
 * @return array<int, array<string, mixed>>
 */
function ipa_get_mock_media_items() {
	$items = array(
		array(
			'id'               => 1,
			'ig_media_id'      => 'mock_001',
			'media_type'       => 'IMAGE',
			'caption'          => 'Golden hour vibes over the city skyline.',
			'permalink'        => 'https://www.instagram.com/p/mock001/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-20 18:30:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#F57600',
		),
		array(
			'id'               => 2,
			'ig_media_id'      => 'mock_002',
			'media_type'       => 'VIDEO',
			'caption'          => 'Behind the scenes of today\'s shoot.',
			'permalink'        => 'https://www.instagram.com/p/mock002/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-19 14:15:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#405DE6',
		),
		array(
			'id'               => 3,
			'ig_media_id'      => 'mock_003',
			'media_type'       => 'CAROUSEL_ALBUM',
			'caption'          => 'Weekend gallery — swipe through the moments.',
			'permalink'        => 'https://www.instagram.com/p/mock003/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-18 10:00:00',
			'is_carousel_parent' => 1,
			'children'         => array(
				array(
					'id'             => 31,
					'ig_media_id'    => 'mock_003_1',
					'media_type'     => 'IMAGE',
					'local_file_url' => '',
					'mock_color'     => '#F57600',
				),
				array(
					'id'             => 32,
					'ig_media_id'    => 'mock_003_2',
					'media_type'     => 'IMAGE',
					'local_file_url' => '',
					'mock_color'     => '#F57600',
				),
				array(
					'id'             => 33,
					'ig_media_id'    => 'mock_003_3',
					'media_type'     => 'IMAGE',
					'local_file_url' => '',
					'mock_color'     => '#F57600',
				),
			),
			'mock_color'       => '#F57600',
		),
		array(
			'id'               => 4,
			'ig_media_id'      => 'mock_004',
			'media_type'       => 'IMAGE',
			'caption'          => 'Minimal workspace setup for deep focus.',
			'permalink'        => 'https://www.instagram.com/p/mock004/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-17 09:45:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#FD1D1D',
		),
		array(
			'id'               => 5,
			'ig_media_id'      => 'mock_005',
			'media_type'       => 'VIDEO',
			'caption'          => 'Quick reel from the morning run.',
			'permalink'        => 'https://www.instagram.com/p/mock005/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-16 07:20:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#F77737',
		),
		array(
			'id'               => 6,
			'ig_media_id'      => 'mock_006',
			'media_type'       => 'IMAGE',
			'caption'          => 'Coffee and code — the perfect pairing.',
			'permalink'        => 'https://www.instagram.com/p/mock006/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-15 16:00:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#FCAF45',
		),
		array(
			'id'               => 7,
			'ig_media_id'      => 'mock_007',
			'media_type'       => 'CAROUSEL_ALBUM',
			'caption'          => 'Travel diary — three cities in one week.',
			'permalink'        => 'https://www.instagram.com/p/mock007/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-14 12:30:00',
			'is_carousel_parent' => 1,
			'children'         => array(
				array(
					'id'             => 71,
					'ig_media_id'    => 'mock_007_1',
					'media_type'     => 'IMAGE',
					'local_file_url' => '',
					'mock_color'     => '#0095F6',
				),
				array(
					'id'             => 72,
					'ig_media_id'    => 'mock_007_2',
					'media_type'     => 'VIDEO',
					'local_file_url' => '',
					'mock_color'     => '#262626',
				),
			),
			'mock_color'       => '#0095F6',
		),
		array(
			'id'               => 8,
			'ig_media_id'      => 'mock_008',
			'media_type'       => 'IMAGE',
			'caption'          => 'New project announcement coming soon.',
			'permalink'        => 'https://www.instagram.com/p/mock008/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-13 20:10:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#667eea',
		),
		array(
			'id'               => 9,
			'ig_media_id'      => 'mock_009',
			'media_type'       => 'VIDEO',
			'caption'          => 'Studio session highlights.',
			'permalink'        => 'https://www.instagram.com/p/mock009/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-12 11:00:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#F57600',
		),
		array(
			'id'               => 10,
			'ig_media_id'      => 'mock_010',
			'media_type'       => 'IMAGE',
			'caption'          => 'Sunset reflections on the water.',
			'permalink'        => 'https://www.instagram.com/p/mock010/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-11 19:45:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#F57600',
		),
		array(
			'id'               => 11,
			'ig_media_id'      => 'mock_011',
			'media_type'       => 'IMAGE',
			'caption'          => 'Team lunch — grateful for great people.',
			'permalink'        => 'https://www.instagram.com/p/mock011/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-10 13:20:00',
			'is_carousel_parent' => 0,
			'children'         => array(),
			'mock_color'       => '#4facfe',
		),
		array(
			'id'               => 12,
			'ig_media_id'      => 'mock_012',
			'media_type'       => 'CAROUSEL_ALBUM',
			'caption'          => 'Archive preview — your posts will appear here after sync.',
			'permalink'        => 'https://www.instagram.com/p/mock012/',
			'local_file_url'   => '',
			'thumbnail_url'    => '',
			'posted_at'        => '2026-05-09 08:00:00',
			'is_carousel_parent' => 1,
			'children'         => array(
				array(
					'id'             => 121,
					'ig_media_id'    => 'mock_012_1',
					'media_type'     => 'IMAGE',
					'local_file_url' => '',
					'mock_color'     => '#43e97b',
				),
				array(
					'id'             => 122,
					'ig_media_id'    => 'mock_012_2',
					'media_type'     => 'IMAGE',
					'local_file_url' => '',
					'mock_color'     => '#38f9d7',
				),
			),
			'mock_color'       => '#43e97b',
		),
	);

	return $items;
}

/**
 * Generate inline SVG data URI placeholder.
 *
 * @param string $color Hex color.
 * @param string $label Optional label.
 * @return string
 */
function ipa_get_placeholder_svg( $color, $label = '' ) {
	$color   = sanitize_hex_color( $color ) ?: '#cccccc';
	$label   = esc_html( $label );
	$svg     = '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="480" viewBox="0 0 360 480">'
		. '<defs><linearGradient id="g" x1="0%" y1="0%" x2="0%" y2="100%">'
		. '<stop offset="0%" style="stop-color:' . esc_attr( $color ) . ';stop-opacity:1" />'
		. '<stop offset="100%" style="stop-color:#262626;stop-opacity:0.75" />'
		. '</linearGradient></defs>'
		. '<rect width="360" height="480" fill="url(#g)"/>';
	if ( $label ) {
		$svg .= '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="system-ui,sans-serif" font-size="18" opacity="0.9">' . $label . '</text>';
	}
	$svg .= '</svg>';

	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/**
 * Format username with @ prefix for display.
 *
 * @param string $username Username.
 * @return string
 */
function ipa_format_username( $username ) {
	$username = ltrim( trim( (string) $username ), '@' );
	return $username ? '@' . $username : '';
}

/**
 * Resolve the public Instagram profile URL for a username.
 *
 * @param string $username       Instagram username.
 * @param string $configured_url Optional saved profile URL from settings.
 * @return string
 */
function ipa_get_instagram_profile_url( $username = '', $configured_url = '' ) {
	$url = trim( (string) $configured_url );
	if ( $url && wp_http_validate_url( $url ) ) {
		return esc_url_raw( $url );
	}

	$username = ltrim( trim( (string) $username ), '@' );
	if ( '' === $username ) {
		return '';
	}

	return esc_url_raw( 'https://www.instagram.com/' . rawurlencode( $username ) . '/' );
}

/**
 * Format external link for display (strip protocol/www).
 *
 * @param string $url URL.
 * @return string
 */
function ipa_format_external_link_display( $url ) {
	$display = preg_replace( '#^https?://#i', '', (string) $url );
	$display = preg_replace( '#^www\.#i', '', $display );
	return rtrim( $display, '/' );
}

/**
 * Format profile bio with line breaks and @mention links.
 *
 * @param string $bio Bio text.
 * @return string Safe HTML.
 */
function ipa_format_bio_html( $bio ) {
	if ( empty( $bio ) ) {
		return '';
	}

	$lines  = preg_split( '/\r\n|\r|\n/', (string) $bio );
	$parts  = array();

	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			$parts[] = '';
			continue;
		}

		$escaped = esc_html( $line );
		$linked  = preg_replace_callback(
			'/@([\w.]+)/',
			static function ( $matches ) {
				$username = $matches[1];
				$url      = 'https://www.instagram.com/' . rawurlencode( $username ) . '/';

				return sprintf(
					'<a href="%1$s" class="ipa-bio-mention" target="_blank" rel="noopener noreferrer">@%2$s</a>',
					esc_url( $url ),
					esc_html( $username )
				);
			},
			$escaped
		);

		$parts[] = $linked;
	}

	return implode( '<br>', $parts );
}

/**
 * Default highlight items for mock UI.
 *
 * @return array<int, array<string, mixed>>
 */
function ipa_get_mock_highlights() {
	$labels = array( 'Travel', 'Work', 'Life', 'World', 'Places' );
	$items  = array();

	foreach ( $labels as $index => $label ) {
		$color = ipa_get_grid_palette_color( $index );
		$items[] = array(
			'id'         => 'mock_highlight_' . ( $index + 1 ),
			'title'      => $label,
			'cover_url'  => ipa_get_placeholder_svg( $color, '' ),
			'item_count' => 1,
			'slides'     => array(
				array(
					'type'  => 'IMAGE',
					'url'   => ipa_get_placeholder_svg( $color, (string) ( $index + 1 ) ),
					'thumb' => ipa_get_placeholder_svg( $color, '' ),
				),
			),
			'caption'   => $label,
			'permalink' => '',
			'posted_at' => '',
		);
	}

	return $items;
}

/**
 * Normalize highlight slides for the modal viewer.
 *
 * @param array<string, mixed> $highlight Highlight row.
 * @return array<int, array<string, string>>
 */
function ipa_get_highlight_modal_slides( $highlight ) {
	$slides = array();

	foreach ( (array) ( $highlight['slides'] ?? array() ) as $slide ) {
		$url = (string) ( $slide['url'] ?? '' );
		if ( '' === $url ) {
			continue;
		}

		$slides[] = array(
			'type'  => strtoupper( (string) ( $slide['type'] ?? 'IMAGE' ) ),
			'url'   => $url,
			'thumb' => (string) ( $slide['thumb'] ?? $url ),
		);
	}

	return $slides;
}

/**
 * Build modal payload for highlight stories.
 *
 * @param array<int, array<string, mixed>> $highlights Highlights.
 * @return array<int, array<string, mixed>>
 */
function ipa_build_highlights_modal_payload( $highlights ) {
	$payload = array();

	foreach ( $highlights as $highlight ) {
		$slides = ipa_get_highlight_modal_slides( $highlight );
		if ( empty( $slides ) ) {
			continue;
		}

		$payload[] = array(
			'caption'   => (string) ( $highlight['title'] ?? $highlight['caption'] ?? '' ),
			'permalink' => (string) ( $highlight['permalink'] ?? '' ),
			'posted_at' => (string) ( $highlight['posted_at'] ?? '' ),
			'slides'    => $slides,
		);
	}

	return $payload;
}

/**
 * Default highlight items for profile UI.
 *
 * @deprecated Use ipa_get_mock_highlights() or synced highlights.
 * @return array<int, array{icon: string, label: string}>
 */
function ipa_get_default_highlights() {
	return array(
		array(
			'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18"/><path d="M3 12h18"/><path d="M5.5 7.5c1.8 1.2 3.8 1.5 6.5 1.5s4.7-.3 6.5-1.5"/><path d="M5.5 16.5c1.8-1.2 3.8-1.5 6.5-1.5s4.7.3 6.5 1.5"/>',
			'label' => '.',
		),
		array(
			'icon'  => '<path d="M4 19h16"/><path d="M6 19c0-2.8 2.7-5 6-5s6 2.2 6 5"/><circle cx="12" cy="8" r="3.5"/><path d="M12 2.5v2M12 12.5v2M7.05 3.55l1.41 1.41M15.54 12.04l1.41 1.41M4.5 8h2M17.5 8h2"/>',
			'label' => '.',
		),
		array(
			'icon'  => '<path d="M4 7h3l1.5-2.5h7L16 7h4v12H4z"/><circle cx="12" cy="13" r="3.5"/><circle cx="17.5" cy="9.5" r="0.75" fill="currentColor" stroke="none"/>',
			'label' => '.',
		),
		array(
			'icon'  => '<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="9" ry="3.5"/><path d="M12 3a15.3 15.3 0 010 18"/>',
			'label' => '.',
		),
		array(
			'icon'  => '<path d="M12 21s-6-4.35-6-10a6 6 0 1112 0c0 5.65-6 10-6 10z"/><circle cx="12" cy="11" r="2.5"/>',
			'label' => '.',
		),
	);
}

/**
 * Get the public archive page slug.
 *
 * @return string
 */
function ipa_get_archive_page_slug() {
	return defined( 'IPA_ARCHIVE_PAGE_SLUG' ) ? IPA_ARCHIVE_PAGE_SLUG : 'gallery';
}

/**
 * Resolve the profile name used in the gallery page title.
 *
 * @return string
 */
function ipa_get_gallery_page_name() {
	$display_name = trim( (string) ipa_get_setting( 'display_name', '' ) );
	if ( $display_name ) {
		return $display_name;
	}

	$username = ltrim( trim( (string) ipa_get_setting( 'username', '' ) ), '@' );
	if ( $username ) {
		return ucwords( str_replace( array( '_', '.' ), ' ', $username ) );
	}

	return '';
}

/**
 * Build the public gallery page title and meta title.
 *
 * @return string
 */
function ipa_get_gallery_page_title() {
	$name = ipa_get_gallery_page_name();
	if ( $name ) {
		return sprintf(
			/* translators: %s: profile display name */
			__( 'Gallery (Photos of %s)', 'instagram-profile-archive' ),
			$name
		);
	}

	return __( 'Gallery', 'instagram-profile-archive' );
}

/**
 * Trim meta description text to a safe length without breaking mid-word.
 *
 * @param string $text        Description text.
 * @param int    $max_length  Maximum character length.
 * @return string
 */
function ipa_trim_meta_description( $text, $max_length = 320 ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
	if ( '' === $text ) {
		return '';
	}

	if ( mb_strlen( $text ) <= $max_length ) {
		return $text;
	}

	$trimmed    = mb_substr( $text, 0, $max_length - 1 );
	$last_space = mb_strrpos( $trimmed, ' ' );
	if ( false !== $last_space && $last_space > (int) ( $max_length * 0.6 ) ) {
		$trimmed = mb_substr( $trimmed, 0, $last_space );
	}

	return rtrim( $trimmed, '.,;:-' ) . '…';
}

/**
 * Build SEO, AEO, and GEO meta description for the gallery page.
 *
 * @return string
 */
function ipa_get_gallery_page_meta_description() {
	$name       = ipa_get_gallery_page_name();
	$username   = ltrim( trim( (string) ipa_get_setting( 'username', '' ) ), '@' );
	$category   = trim( (string) ipa_get_setting( 'category', '' ) );
	$site_label = wp_strip_all_tags( get_bloginfo( 'name', 'display' ) );
	if ( '' === $site_label ) {
		$site_label = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	$posts      = (int) IPA_DB::get_total_archived_posts();
	$highlights = (int) IPA_DB::count_active_highlights();

	if ( $name && $username ) {
		$lead = $category
			? sprintf(
				/* translators: 1: display name, 2: site name, 3: profile category, 4: instagram username */
				__( 'View %1$s\'s Instagram photos on %2$s — %3$s gallery archive for @%4$s.', 'instagram-profile-archive' ),
				$name,
				$site_label,
				$category,
				$username
			)
			: sprintf(
				/* translators: 1: display name, 2: site name, 3: instagram username */
				__( 'View %1$s\'s Instagram photos on %2$s — the gallery archive for @%3$s.', 'instagram-profile-archive' ),
				$name,
				$site_label,
				$username
			);

		if ( $posts > 0 ) {
			$detail = $highlights > 0
				? sprintf(
					/* translators: 1: post count, 2: highlight count */
					__( 'Browse %1$d archived posts, reels, captions, and %2$d story highlights preserved in a searchable Instagram profile gallery.', 'instagram-profile-archive' ),
					$posts,
					$highlights
				)
				: sprintf(
					/* translators: %d: number of archived posts */
					__( 'Browse %d archived posts, reels, and captions preserved in a searchable Instagram profile gallery.', 'instagram-profile-archive' ),
					$posts
				);
		} else {
			$detail = $highlights > 0
				? sprintf(
					/* translators: %d: number of story highlights */
					__( 'Browse archived posts, reels, captions, and %d story highlights preserved in a searchable Instagram profile gallery.', 'instagram-profile-archive' ),
					$highlights
				)
				: __( 'Browse archived posts, reels, captions, and story highlights preserved in a searchable Instagram profile gallery.', 'instagram-profile-archive' );
		}

		return ipa_trim_meta_description( $lead . ' ' . $detail );
	}

	if ( $name ) {
		return ipa_trim_meta_description(
			sprintf(
				/* translators: 1: display name, 2: site name */
				__( 'View %1$s\'s Instagram photo gallery on %2$s. Browse archived posts, reels, and story highlights preserved in a profile-style archive.', 'instagram-profile-archive' ),
				$name,
				$site_label
			)
		);
	}

	return ipa_trim_meta_description(
		__( 'Browse an Instagram-style photo gallery with archived posts, reels, and story highlights preserved on this site.', 'instagram-profile-archive' )
	);
}

/**
 * Keep the WordPress gallery page post title in sync with profile settings.
 *
 * @return void
 */
function ipa_sync_gallery_page_title() {
	$page_id = (int) get_option( 'ipa_instagram_page_id', 0 );
	if ( $page_id <= 0 ) {
		return;
	}

	$page = get_post( $page_id );
	if ( ! $page instanceof WP_Post ) {
		return;
	}

	$title = ipa_get_gallery_page_title();
	if ( $page->post_title === $title ) {
		return;
	}

	wp_update_post(
		array(
			'ID'         => $page_id,
			'post_title' => $title,
		)
	);
}

/**
 * Get the public archive page URL.
 *
 * @return string
 */
function ipa_get_archive_page_url() {
	$page_id = (int) get_option( 'ipa_instagram_page_id', 0 );
	if ( $page_id > 0 ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}

	return home_url( '/' . ipa_get_archive_page_slug() . '/' );
}

/**
 * Get a thumbnail URL for an archived media row in admin.
 *
 * @param object $row Media row.
 * @return string
 */
function ipa_get_admin_media_thumb_url( $row ) {
	if ( ! empty( $row->local_thumbnail_url ) ) {
		return (string) $row->local_thumbnail_url;
	}
	if ( ! empty( $row->local_file_url ) ) {
		return (string) $row->local_file_url;
	}
	if ( ! empty( $row->thumbnail_url ) ) {
		return (string) $row->thumbnail_url;
	}
	return (string) ( $row->instagram_media_url ?? '' );
}

/**
 * Get a short admin label for a media row type.
 *
 * @param object $row Media row.
 * @return string
 */
function ipa_get_admin_media_type_label( $row ) {
	if ( ipa_is_reel_media( $row ) ) {
		return __( 'Reel', 'instagram-profile-archive' );
	}
	if ( ! empty( $row->is_carousel_parent ) || 'CAROUSEL_ALBUM' === strtoupper( (string) ( $row->media_type ?? '' ) ) ) {
		return __( 'Carousel', 'instagram-profile-archive' );
	}
	if ( in_array( strtoupper( (string) ( $row->media_type ?? '' ) ), array( 'VIDEO', 'REELS' ), true ) ) {
		return __( 'Video', 'instagram-profile-archive' );
	}
	return __( 'Post', 'instagram-profile-archive' );
}

/**
 * Check if current page is the Instagram archive page.
 *
 * @return bool
 */
function ipa_is_archive_page() {
	$page_id = (int) get_option( 'ipa_instagram_page_id', 0 );
	if ( $page_id && is_page( $page_id ) ) {
		return true;
	}

	global $post;
	if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'instagram_profile_archive' ) ) {
		return true;
	}

	return false;
}
