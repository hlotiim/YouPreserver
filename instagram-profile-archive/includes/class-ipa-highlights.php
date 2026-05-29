<?php
/**
 * Story highlights formatting for frontend display.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_Highlights
 */
class IPA_Highlights {

	/**
	 * Format DB highlights for frontend display.
	 *
	 * @param array<int, object> $rows DB rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function format_for_frontend( $rows ) {
		$formatted = array();

		foreach ( $rows as $row ) {
			$cover  = ! empty( $row->local_cover_url ) ? (string) $row->local_cover_url : (string) ( $row->cover_instagram_url ?? '' );
			$slides = array();

			if ( ! empty( $row->stories_json ) ) {
				$decoded = json_decode( (string) $row->stories_json, true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $story ) {
						$url = (string) ( $story['url'] ?? '' );
						if ( '' === $url ) {
							continue;
						}
						$slides[] = array(
							'type'  => strtoupper( (string) ( $story['media_type'] ?? 'IMAGE' ) ),
							'url'   => $url,
							'thumb' => (string) ( $story['thumb'] ?? $url ),
						);
					}
				}
			}

			if ( '' === $cover ) {
				continue;
			}

			$formatted[] = array(
				'id'         => (string) $row->ig_highlight_id,
				'title'      => (string) $row->title,
				'cover_url'  => $cover,
				'item_count' => (int) $row->item_count,
				'slides'     => $slides,
				'caption'    => (string) $row->title,
				'permalink'  => '',
				'posted_at'  => '',
			);
		}

		return $formatted;
	}
}
