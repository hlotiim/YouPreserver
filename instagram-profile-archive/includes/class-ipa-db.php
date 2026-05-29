<?php
/**
 * Database operations.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IPA_DB
 */
class IPA_DB {

	const DB_VERSION = '2.2.0';

	const CURSOR_FULL_SYNC = 'full_sync';

	/**
	 * Run table creation / upgrade.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'ipa_db_version', '0' );

		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			self::create_tables();
		}

		self::maybe_encrypt_legacy_credentials();
		self::ensure_uploads_protected();
		self::maybe_add_is_pinned_column();
		self::maybe_create_highlights_table();
	}

	/**
	 * Add is_pinned column for Instagram pinned post ordering.
	 *
	 * @return void
	 */
	public static function maybe_add_is_pinned_column() {
		global $wpdb;

		$table = self::media_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$column = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'is_pinned'" );
		if ( ! empty( $column ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER child_count" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE {$table} ADD KEY is_pinned (is_pinned)" );
	}

	/**
	 * Create highlights table when upgrading from older DB versions.
	 *
	 * @return void
	 */
	public static function maybe_create_highlights_table() {
		global $wpdb;

		$table = self::highlights_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists === $table ) {
			return;
		}

		self::create_tables();
	}

	/**
	 * One-time migration: encrypt any plaintext access token / app secret previously stored.
	 *
	 * @return void
	 */
	public static function maybe_encrypt_legacy_credentials() {
		if ( get_option( 'ipa_credentials_encrypted_at' ) ) {
			return;
		}

		if ( ! class_exists( 'IPA_Security' ) || ! class_exists( 'IPA_Settings' ) ) {
			return;
		}

		foreach ( IPA_Settings::get_encrypted_keys() as $key ) {
			$option_name = 'ipa_' . $key;
			$value       = get_option( $option_name, '' );
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			if ( 0 === strpos( $value, IPA_Security::ENC_PREFIX ) ) {
				continue;
			}
			$encrypted = IPA_Security::encrypt( $value );
			update_option( $option_name, $encrypted, false );
		}

		update_option( 'ipa_credentials_encrypted_at', current_time( 'mysql', true ), false );
	}

	/**
	 * Drop a .htaccess into the plugin uploads directory that blocks direct PHP execution.
	 *
	 * @return void
	 */
	public static function ensure_uploads_protected() {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return;
		}

		$base = trailingslashit( $upload_dir['basedir'] ) . IPA_UPLOAD_DIR;
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
		}

		$htaccess = trailingslashit( $base ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules  = "# YouPreserver — block script execution.\n";
			$rules .= "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|asp)$\">\n";
			$rules .= "Require all denied\n";
			$rules .= "</FilesMatch>\n";
			$rules .= "Options -ExecCGI -Indexes\n";
			@file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$index = trailingslashit( $base ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$media_table     = self::media_table();
		$logs_table      = self::logs_table();
		$cursors_table   = self::cursors_table();
		$highlights_table = self::highlights_table();

		$sql_media = "CREATE TABLE {$media_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ig_media_id VARCHAR(100) NOT NULL,
			parent_ig_media_id VARCHAR(100) DEFAULT NULL,
			media_type VARCHAR(50) DEFAULT NULL,
			media_product_type VARCHAR(50) DEFAULT NULL,
			caption LONGTEXT DEFAULT NULL,
			permalink LONGTEXT DEFAULT NULL,
			instagram_media_url LONGTEXT DEFAULT NULL,
			thumbnail_url LONGTEXT DEFAULT NULL,
			local_file_id BIGINT(20) UNSIGNED DEFAULT NULL,
			local_file_url LONGTEXT DEFAULT NULL,
			local_file_path LONGTEXT DEFAULT NULL,
			local_thumbnail_id BIGINT(20) UNSIGNED DEFAULT NULL,
			local_thumbnail_url LONGTEXT DEFAULT NULL,
			local_thumbnail_path LONGTEXT DEFAULT NULL,
			posted_at DATETIME DEFAULT NULL,
			synced_at DATETIME DEFAULT NULL,
			is_carousel_parent TINYINT(1) NOT NULL DEFAULT 0,
			has_children TINYINT(1) NOT NULL DEFAULT 0,
			child_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
			is_pinned TINYINT(1) NOT NULL DEFAULT 0,
			raw_json LONGTEXT DEFAULT NULL,
			sync_status VARCHAR(50) DEFAULT 'synced',
			download_status VARCHAR(50) DEFAULT 'pending',
			download_error LONGTEXT DEFAULT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			created_at DATETIME DEFAULT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ig_media_id (ig_media_id),
			KEY parent_ig_media_id (parent_ig_media_id),
			KEY media_type (media_type),
			KEY posted_at (posted_at),
			KEY status (status)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sync_id VARCHAR(100) DEFAULT NULL,
			sync_type VARCHAR(50) DEFAULT NULL,
			status VARCHAR(50) DEFAULT NULL,
			message LONGTEXT DEFAULT NULL,
			technical_message LONGTEXT DEFAULT NULL,
			total_found INT(11) UNSIGNED NOT NULL DEFAULT 0,
			total_new INT(11) UNSIGNED NOT NULL DEFAULT 0,
			total_updated INT(11) UNSIGNED NOT NULL DEFAULT 0,
			total_skipped INT(11) UNSIGNED NOT NULL DEFAULT 0,
			total_failed INT(11) UNSIGNED NOT NULL DEFAULT 0,
			api_calls_used INT(11) UNSIGNED NOT NULL DEFAULT 0,
			started_at DATETIME DEFAULT NULL,
			completed_at DATETIME DEFAULT NULL,
			created_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY sync_id (sync_id),
			KEY status (status),
			KEY started_at (started_at)
		) {$charset_collate};";

		$sql_cursors = "CREATE TABLE {$cursors_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			cursor_key VARCHAR(100) NOT NULL,
			cursor_value LONGTEXT DEFAULT NULL,
			last_media_id VARCHAR(100) DEFAULT NULL,
			last_posted_at DATETIME DEFAULT NULL,
			created_at DATETIME DEFAULT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cursor_key (cursor_key)
		) {$charset_collate};";

		$sql_highlights = "CREATE TABLE {$highlights_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ig_highlight_id VARCHAR(100) NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			cover_instagram_url LONGTEXT DEFAULT NULL,
			local_cover_id BIGINT(20) UNSIGNED DEFAULT NULL,
			local_cover_url LONGTEXT DEFAULT NULL,
			local_cover_path LONGTEXT DEFAULT NULL,
			item_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
			sort_order INT(11) UNSIGNED NOT NULL DEFAULT 0,
			stories_json LONGTEXT DEFAULT NULL,
			raw_json LONGTEXT DEFAULT NULL,
			synced_at DATETIME DEFAULT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			created_at DATETIME DEFAULT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ig_highlight_id (ig_highlight_id),
			KEY sort_order (sort_order),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql_media );
		dbDelta( $sql_logs );
		dbDelta( $sql_cursors );
		dbDelta( $sql_highlights );

		update_option( 'ipa_db_version', self::DB_VERSION );
	}

	/**
	 * @return string
	 */
	public static function media_table() {
		global $wpdb;
		return $wpdb->prefix . 'ipa_media';
	}

	/**
	 * @return string
	 */
	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'ipa_sync_logs';
	}

	/**
	 * @return string
	 */
	public static function cursors_table() {
		global $wpdb;
		return $wpdb->prefix . 'ipa_sync_cursors';
	}

	/**
	 * @return string
	 */
	public static function highlights_table() {
		global $wpdb;
		return $wpdb->prefix . 'ipa_highlights';
	}

	/**
	 * Upsert media row by ig_media_id.
	 *
	 * @param array<string, mixed> $data Media data.
	 * @return int|false Row ID or false.
	 */
	public static function upsert_media( $data ) {
		global $wpdb;

		if ( empty( $data['ig_media_id'] ) ) {
			return false;
		}

		$existing = self::get_media_by_ig_id( $data['ig_media_id'] );
		$now      = current_time( 'mysql', true );

		if ( $existing ) {
			$data['updated_at'] = $now;
			unset( $data['created_at'] );

			$wpdb->update(
				self::media_table(),
				$data,
				array( 'ig_media_id' => $data['ig_media_id'] )
			);

			return (int) $existing->id;
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$data['synced_at']  = $data['synced_at'] ?? $now;

		$wpdb->insert( self::media_table(), $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update media by Instagram media ID.
	 *
	 * @param string               $ig_media_id Instagram media ID.
	 * @param array<string, mixed> $data        Data.
	 * @return bool
	 */
	public static function update_media( $ig_media_id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		return false !== $wpdb->update(
			self::media_table(),
			$data,
			array( 'ig_media_id' => $ig_media_id )
		);
	}

	/**
	 * Get media by Instagram ID.
	 *
	 * @param string $ig_media_id Instagram media ID.
	 * @return object|null
	 */
	public static function get_media_by_ig_id( $ig_media_id ) {
		global $wpdb;

		$table = self::media_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ig_media_id = %s LIMIT 1",
				$ig_media_id
			)
		);
	}

	/**
	 * Count parent media items.
	 *
	 * @param string $filter Filter.
	 * @return int
	 */
	public static function count_media( $filter = 'all' ) {
		global $wpdb;

		$table = self::media_table();
		$where = self::build_frontend_where( $filter );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	}

	/**
	 * @return int
	 */
	public static function count_active_media() {
		return self::count_media( 'all' );
	}

	/**
	 * @return int
	 */
	public static function get_total_archived_posts() {
		return self::count_media( 'posts' );
	}

	/**
	 * Build WHERE clause for frontend queries.
	 *
	 * @param string $filter Filter.
	 * @return string
	 */
	private static function build_frontend_where( $filter ) {
		$where = "status = 'active' AND (parent_ig_media_id IS NULL OR parent_ig_media_id = '')";

		switch ( $filter ) {
			case 'posts':
				$where .= ' AND ' . self::get_posts_only_sql();
				break;
			case 'reels':
				$where .= ' AND ' . self::get_reels_only_sql();
				break;
			case 'videos':
				$where .= " AND media_type IN ('VIDEO', 'REELS')";
				break;
			case 'images':
				$where .= " AND media_type = 'IMAGE'";
				break;
			case 'carousel':
				$where .= " AND media_type = 'CAROUSEL_ALBUM'";
				break;
		}

		return $where;
	}

	/**
	 * SQL fragment for non-reel profile grid posts.
	 *
	 * @return string
	 */
	public static function get_posts_only_sql() {
		return "(media_product_type IS NULL OR media_product_type = '' OR media_product_type NOT IN ('REELS', 'STORY'))
			AND (media_type IS NULL OR media_type = '' OR media_type NOT IN ('REELS'))";
	}

	/**
	 * SQL fragment for reels tab content.
	 *
	 * @return string
	 */
	public static function get_reels_only_sql() {
		return "(media_product_type = 'REELS' OR media_type = 'REELS')";
	}

	/**
	 * Build ORDER BY clause for frontend media queries.
	 *
	 * @param string $filter Filter slug.
	 * @return string
	 */
	public static function get_frontend_order_sql( $filter ) {
		if ( 'posts' !== $filter ) {
			return 'posted_at DESC, id DESC';
		}

		$pinned_ids = array_values(
			array_filter(
				array_map( 'intval', (array) get_option( 'ipa_pinned_post_ids', array() ) )
			)
		);

		if ( ! empty( $pinned_ids ) && (bool) get_option( 'ipa_manual_pins', false ) ) {
			$field_parts = implode( ', ', $pinned_ids );
			return "is_pinned DESC, FIELD(id, {$field_parts}) ASC, posted_at DESC, id DESC";
		}

		return 'is_pinned DESC, posted_at DESC, id DESC';
	}

	/**
	 * Apply manual pinned post selection (max 3 profile grid posts).
	 *
	 * @param array<int, int> $db_ids Database row IDs in display order.
	 * @return int Number of posts pinned.
	 */
	public static function set_manual_pinned_posts( array $db_ids ) {
		global $wpdb;

		$table   = self::media_table();
		$db_ids  = array_values( array_unique( array_filter( array_map( 'intval', $db_ids ) ) ) );
		$db_ids  = array_slice( $db_ids, 0, 3 );
		$valid   = array();

		foreach ( $db_ids as $id ) {
			$row = self::get_media_by_db_id( $id );
			if ( ! $row || 'active' !== ( $row->status ?? '' ) ) {
				continue;
			}
			if ( ! empty( $row->parent_ig_media_id ) ) {
				continue;
			}
			if ( ipa_is_reel_media( $row ) ) {
				continue;
			}
			$valid[] = $id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"UPDATE {$table} SET is_pinned = 0 WHERE parent_ig_media_id IS NULL OR parent_ig_media_id = ''"
		);

		foreach ( $valid as $id ) {
			$wpdb->update(
				$table,
				array( 'is_pinned' => 1 ),
				array( 'id' => $id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		update_option( 'ipa_pinned_post_ids', $valid );
		update_option( 'ipa_manual_pins', ! empty( $valid ) ? 1 : 0 );

		if ( function_exists( 'ipa_clear_frontend_cache' ) ) {
			ipa_clear_frontend_cache();
		}

		return count( $valid );
	}

	/**
	 * Get parent post rows (excluding reels) for the pinned posts admin UI.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function get_admin_posts_for_pins( $limit = 100 ) {
		global $wpdb;

		$table  = self::media_table();
		$limit  = max( 1, (int) $limit );
		$posts  = self::get_posts_only_sql();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status = 'active'
					AND (parent_ig_media_id IS NULL OR parent_ig_media_id = '')
					AND {$posts}
				ORDER BY is_pinned DESC, posted_at DESC, id DESC
				LIMIT %d",
				$limit
			)
		) ?: array();
	}

	/**
	 * Clear pinned flags on all parent media rows.
	 *
	 * @return void
	 */
	public static function clear_all_pinned_flags() {
		global $wpdb;

		$table = self::media_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET is_pinned = 0 WHERE parent_ig_media_id IS NULL OR parent_ig_media_id = ''" );
	}

	/**
	 * Get parent media for frontend.
	 *
	 * @param int    $limit  Limit.
	 * @param int    $offset Offset.
	 * @param string $filter Filter.
	 * @return array<int, object>
	 */
	public static function get_frontend_media( $limit = 60, $offset = 0, $filter = 'all' ) {
		global $wpdb;

		$table  = self::media_table();
		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );
		$where  = self::build_frontend_where( $filter );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order = self::get_frontend_order_sql( $filter );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		if ( ! is_array( $items ) ) {
			return array();
		}

		foreach ( $items as $item ) {
			if ( (int) $item->is_carousel_parent || (int) $item->has_children ) {
				$item->children = self::get_media_children( $item->ig_media_id );
			} else {
				$item->children = array();
			}
		}

		return $items;
	}

	/**
	 * Get carousel children.
	 *
	 * @param string $parent_ig_media_id Parent ID.
	 * @return array<int, object>
	 */
	public static function get_media_children( $parent_ig_media_id ) {
		global $wpdb;

		$table = self::media_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE parent_ig_media_id = %s AND status = 'active' ORDER BY id ASC",
				$parent_ig_media_id
			)
		) ?: array();
	}

	/**
	 * Insert sync log.
	 *
	 * @param array<string, mixed> $data Log data.
	 * @return int
	 */
	public static function insert_log( $data ) {
		global $wpdb;

		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql', true );
		$data['started_at'] = $data['started_at'] ?? current_time( 'mysql', true );

		$wpdb->insert( self::logs_table(), $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update sync log.
	 *
	 * @param int                  $log_id Log ID.
	 * @param array<string, mixed> $data   Data.
	 * @return bool
	 */
	public static function update_log( $log_id, $data ) {
		global $wpdb;

		return false !== $wpdb->update(
			self::logs_table(),
			$data,
			array( 'id' => (int) $log_id )
		);
	}

	/**
	 * Get sync logs.
	 *
	 * @param int $limit  Limit.
	 * @param int $offset Offset.
	 * @return array<int, object>
	 */
	public static function get_sync_logs( $limit = 20, $offset = 0 ) {
		global $wpdb;

		$table = self::logs_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY started_at DESC LIMIT %d OFFSET %d",
				max( 1, (int) $limit ),
				max( 0, (int) $offset )
			)
		) ?: array();
	}

	/**
	 * Count sync logs.
	 *
	 * @return int
	 */
	public static function count_sync_logs() {
		global $wpdb;

		$table = self::logs_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * @return object|null
	 */
	public static function get_latest_sync_log() {
		global $wpdb;

		$table = self::logs_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$log = $wpdb->get_row( "SELECT * FROM {$table} ORDER BY started_at DESC LIMIT 1" );

		return $log ?: null;
	}

	/**
	 * @return void
	 */
	public static function clear_sync_logs() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::logs_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return void
	 */
	public static function clear_media_data() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::media_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get cursor row.
	 *
	 * @param string $key Cursor key.
	 * @return object|null
	 */
	public static function get_cursor( $key ) {
		global $wpdb;

		$table = self::cursors_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE cursor_key = %s LIMIT 1",
				$key
			)
		);
	}

	/**
	 * Save cursor.
	 *
	 * @param string      $key           Key.
	 * @param string|null $value         Value.
	 * @param string|null $last_media_id Last media ID.
	 * @param string|null $last_posted_at Last posted at.
	 * @return void
	 */
	public static function save_cursor( $key, $value, $last_media_id = null, $last_posted_at = null ) {
		global $wpdb;

		$table    = self::cursors_table();
		$existing = self::get_cursor( $key );
		$now      = current_time( 'mysql', true );

		$data = array(
			'cursor_key'     => $key,
			'cursor_value'   => $value,
			'last_media_id'  => $last_media_id,
			'last_posted_at' => $last_posted_at,
			'updated_at'     => $now,
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'cursor_key' => $key ) );
			return;
		}

		$data['created_at'] = $now;
		$wpdb->insert( $table, $data );
	}

	/**
	 * Delete cursor.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	public static function delete_cursor( $key ) {
		global $wpdb;

		$wpdb->delete(
			self::cursors_table(),
			array( 'cursor_key' => $key ),
			array( '%s' )
		);
	}

	/**
	 * Get all media for CSV export.
	 *
	 * @return array<int, object>
	 */
	public static function get_all_media_for_export() {
		global $wpdb;

		$table = self::media_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY posted_at DESC" ) ?: array();
	}

	/**
	 * Get attachment IDs currently linked to archived records.
	 *
	 * @return array<int>
	 */
	public static function get_referenced_attachment_ids() {
		global $wpdb;

		$ids = self::get_all_attachment_ids();

		$highlights_table = self::highlights_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cover_ids = $wpdb->get_col(
			"SELECT local_cover_id FROM {$highlights_table} WHERE local_cover_id IS NOT NULL AND local_cover_id > 0"
		);

		if ( is_array( $cover_ids ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $cover_ids ) );
		}

		$profile_image_id = (int) get_option( 'ipa_profile_image_id', 0 );
		if ( $profile_image_id > 0 ) {
			$ids[] = $profile_image_id;
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Remove duplicate archive rows that share the same Instagram media ID.
	 *
	 * @return int Number of rows removed.
	 */
	public static function dedupe_media_records() {
		global $wpdb;

		$table = self::media_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$groups = $wpdb->get_results(
			"SELECT ig_media_id, GROUP_CONCAT(id ORDER BY id ASC) AS row_ids, COUNT(*) AS row_count
			FROM {$table}
			GROUP BY ig_media_id
			HAVING row_count > 1"
		);

		$removed = 0;
		foreach ( $groups ?: array() as $group ) {
			$row_ids = array_map( 'intval', explode( ',', (string) $group->row_ids ) );
			array_shift( $row_ids );
			foreach ( $row_ids as $row_id ) {
				$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Clean duplicate and orphaned IPA media library attachments.
	 *
	 * @return array{deleted:int, duplicates:int, orphans:int}
	 */
	public static function cleanup_duplicate_attachments() {
		global $wpdb;

		$referenced = array_flip( self::get_referenced_attachment_ids() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$attachment_rows = $wpdb->get_results(
			"SELECT p.ID AS attachment_id,
				MAX(CASE WHEN pm.meta_key = '_ipa_ig_media_id' THEN pm.meta_value END) AS ig_media_id,
				MAX(CASE WHEN pm.meta_key = '_ipa_download_type' THEN pm.meta_value END) AS download_type
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'attachment'
				AND pm.meta_key IN ('_ipa_ig_media_id', '_ipa_download_type')
			GROUP BY p.ID
			HAVING ig_media_id IS NOT NULL AND ig_media_id <> ''"
		);

		$groups   = array();
		$to_delete = array();

		foreach ( $attachment_rows ?: array() as $row ) {
			$key = (string) $row->ig_media_id . '|' . ( (string) ( $row->download_type ?: 'media' ) );
			$groups[ $key ][] = (int) $row->attachment_id;
		}

		foreach ( $groups as $attachment_ids ) {
			if ( count( $attachment_ids ) <= 1 ) {
				continue;
			}

			$keeper = 0;
			foreach ( $attachment_ids as $attachment_id ) {
				if ( isset( $referenced[ $attachment_id ] ) ) {
					$keeper = $attachment_id;
					break;
				}
			}

			if ( ! $keeper ) {
				$keeper = max( $attachment_ids );
			}

			foreach ( $attachment_ids as $attachment_id ) {
				if ( $attachment_id !== $keeper ) {
					$to_delete[] = $attachment_id;
				}
			}
		}

		$duplicate_ids = array_values( array_unique( $to_delete ) );
		$orphan_ids    = array();

		foreach ( $attachment_rows ?: array() as $row ) {
			$attachment_id = (int) $row->attachment_id;
			if ( isset( $referenced[ $attachment_id ] ) || in_array( $attachment_id, $duplicate_ids, true ) ) {
				continue;
			}

			$key = (string) $row->ig_media_id . '|' . ( (string) ( $row->download_type ?: 'media' ) );
			if ( 1 === count( $groups[ $key ] ?? array() ) ) {
				$orphan_ids[] = $attachment_id;
			}
		}

		$orphan_ids = array_values( array_unique( $orphan_ids ) );
		$all_delete = array_values( array_unique( array_merge( $duplicate_ids, $orphan_ids ) ) );

		foreach ( $all_delete as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		return array(
			'deleted'    => count( $all_delete ),
			'duplicates' => count( $duplicate_ids ),
			'orphans'    => count( $orphan_ids ),
		);
	}

	/**
	 * Get attachment IDs linked to archived media.
	 *
	 * @return array<int>
	 */
	public static function get_all_attachment_ids() {
		global $wpdb;

		$table = self::media_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			"SELECT local_file_id FROM {$table} WHERE local_file_id IS NOT NULL AND local_file_id > 0
			UNION
			SELECT local_thumbnail_id FROM {$table} WHERE local_thumbnail_id IS NOT NULL AND local_thumbnail_id > 0"
		);

		return array_map( 'intval', array_filter( $ids ) );
	}

	/**
	 * Upsert a highlight row.
	 *
	 * @param array<string, mixed> $data Highlight data.
	 * @return int|false
	 */
	public static function upsert_highlight( $data ) {
		global $wpdb;

		if ( empty( $data['ig_highlight_id'] ) ) {
			return false;
		}

		$table    = self::highlights_table();
		$existing = self::get_highlight_by_ig_id( $data['ig_highlight_id'] );
		$now      = current_time( 'mysql', true );

		if ( $existing ) {
			$data['updated_at'] = $now;
			unset( $data['created_at'] );
			$wpdb->update(
				$table,
				$data,
				array( 'ig_highlight_id' => $data['ig_highlight_id'] )
			);
			return (int) $existing->id;
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$wpdb->insert( $table, $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get highlight by Instagram ID.
	 *
	 * @param string $ig_highlight_id Highlight ID.
	 * @return object|null
	 */
	public static function get_highlight_by_ig_id( $ig_highlight_id ) {
		global $wpdb;

		$table = self::highlights_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ig_highlight_id = %s LIMIT 1",
				$ig_highlight_id
			)
		);
	}

	/**
	 * Get active highlights for frontend display.
	 *
	 * @return array<int, object>
	 */
	public static function get_active_highlights() {
		global $wpdb;

		$table = self::highlights_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'active' ORDER BY sort_order ASC, id ASC"
		) ?: array();
	}

	/**
	 * Count active highlights.
	 *
	 * @return int
	 */
	public static function count_active_highlights() {
		global $wpdb;

		$table = self::highlights_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
	}

	/**
	 * Mark highlights not in the synced list as inactive.
	 *
	 * @param array<int, string> $active_ids Active highlight IDs.
	 * @return void
	 */
	public static function deactivate_highlights_not_in( $active_ids ) {
		global $wpdb;

		$table = self::highlights_table();
		$active_ids = array_values(
			array_filter(
				array_map( 'strval', $active_ids )
			)
		);

		if ( empty( $active_ids ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$table} SET status = 'inactive', updated_at = '" . esc_sql( current_time( 'mysql', true ) ) . "'" );
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $active_ids ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'inactive', updated_at = %s WHERE ig_highlight_id NOT IN ({$placeholders})",
				array_merge( array( current_time( 'mysql', true ) ), $active_ids )
			)
		);
	}

	/**
	 * Get parent media rows for the admin delete tab.
	 *
	 * @param int $limit  Limit.
	 * @param int $offset Offset.
	 * @return array<int, object>
	 */
	public static function get_admin_parent_media( $limit = 50, $offset = 0 ) {
		global $wpdb;

		$table = self::media_table();
		$limit = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status = 'active'
					AND (parent_ig_media_id IS NULL OR parent_ig_media_id = '')
				ORDER BY posted_at DESC, id DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		) ?: array();
	}

	/**
	 * Count parent media rows for the admin delete tab.
	 *
	 * @return int
	 */
	public static function count_admin_parent_media() {
		global $wpdb;

		$table = self::media_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}
			WHERE status = 'active'
				AND (parent_ig_media_id IS NULL OR parent_ig_media_id = '')"
		);
	}

	/**
	 * Get a media row by database ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public static function get_media_by_db_id( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}

		$table = self::media_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Get a highlight row by database ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public static function get_highlight_by_db_id( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}

		$table = self::highlights_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Delete archived media rows by database IDs, including carousel children.
	 *
	 * @param array<int> $ids                Parent row IDs.
	 * @param bool       $delete_attachments Also delete linked media library files.
	 * @return int Number of parent posts deleted.
	 */
	public static function delete_media_by_db_ids( $ids, $delete_attachments = true ) {
		$deleted = 0;

		foreach ( array_values( array_unique( array_map( 'intval', (array) $ids ) ) ) as $id ) {
			if ( $id <= 0 ) {
				continue;
			}

			$row = self::get_media_by_db_id( $id );
			if ( ! $row || 'active' !== (string) ( $row->status ?? '' ) ) {
				continue;
			}

			if ( self::delete_media_tree( $row, $delete_attachments ) ) {
				++$deleted;
			}
		}

		if ( $deleted > 0 ) {
			ipa_clear_frontend_cache();
		}

		return $deleted;
	}

	/**
	 * Delete all archived parent media rows.
	 *
	 * @param bool $delete_attachments Also delete linked media library files.
	 * @return int
	 */
	public static function delete_all_archived_media( $delete_attachments = true ) {
		global $wpdb;

		$table = self::media_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			"SELECT id FROM {$table}
			WHERE status = 'active'
				AND (parent_ig_media_id IS NULL OR parent_ig_media_id = '')"
		);

		return self::delete_media_by_db_ids( $ids ?: array(), $delete_attachments );
	}

	/**
	 * Delete highlight rows by database IDs.
	 *
	 * @param array<int> $ids                Row IDs.
	 * @param bool       $delete_attachments Also delete linked media library files.
	 * @return int
	 */
	public static function delete_highlights_by_db_ids( $ids, $delete_attachments = true ) {
		global $wpdb;

		$table   = self::highlights_table();
		$deleted = 0;

		foreach ( array_values( array_unique( array_map( 'intval', (array) $ids ) ) ) as $id ) {
			if ( $id <= 0 ) {
				continue;
			}

			$row = self::get_highlight_by_db_id( $id );
			if ( ! $row ) {
				continue;
			}

			if ( $delete_attachments ) {
				self::delete_highlight_row_attachments( $row );
			}

			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
			++$deleted;
		}

		if ( $deleted > 0 ) {
			ipa_clear_frontend_cache();
		}

		return $deleted;
	}

	/**
	 * Delete all active highlights.
	 *
	 * @param bool $delete_attachments Also delete linked media library files.
	 * @return int
	 */
	public static function delete_all_highlights( $delete_attachments = true ) {
		global $wpdb;

		$table = self::highlights_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( "SELECT id FROM {$table} WHERE status = 'active'" );

		return self::delete_highlights_by_db_ids( $ids ?: array(), $delete_attachments );
	}

	/**
	 * Delete a media row, its children, and optional attachments.
	 *
	 * @param object $row                Media row.
	 * @param bool   $delete_attachments Delete attachments.
	 * @return bool
	 */
	private static function delete_media_tree( $row, $delete_attachments = true ) {
		global $wpdb;

		if ( ! $row || empty( $row->ig_media_id ) ) {
			return false;
		}

		$rows   = array( $row );
		$rows   = array_merge( $rows, self::get_media_children( (string) $row->ig_media_id ) );
		$table  = self::media_table();

		if ( $delete_attachments ) {
			foreach ( $rows as $media_row ) {
				self::delete_media_row_attachments( $media_row );
			}
		}

		foreach ( $rows as $media_row ) {
			$wpdb->delete( $table, array( 'id' => (int) $media_row->id ), array( '%d' ) );
		}

		return true;
	}

	/**
	 * @param object $row Media row.
	 * @return void
	 */
	private static function delete_media_row_attachments( $row ) {
		foreach ( array( 'local_file_id', 'local_thumbnail_id' ) as $field ) {
			$attachment_id = (int) ( $row->{$field} ?? 0 );
			if ( $attachment_id > 0 ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}
	}

	/**
	 * @param object $row Highlight row.
	 * @return void
	 */
	private static function delete_highlight_row_attachments( $row ) {
		$cover_id = (int) ( $row->local_cover_id ?? 0 );
		if ( $cover_id > 0 ) {
			wp_delete_attachment( $cover_id, true );
		}

		if ( ! class_exists( 'IPA_Media_Downloader' ) ) {
			return;
		}

		$downloader = new IPA_Media_Downloader();
		$ig_id      = (string) ( $row->ig_highlight_id ?? '' );
		$safe_id    = preg_replace( '/[^a-zA-Z0-9_-]/', '', $ig_id );
		$stories    = json_decode( (string) ( $row->stories_json ?? '' ), true );

		if ( ! is_array( $stories ) ) {
			return;
		}

		foreach ( $stories as $story ) {
			if ( ! is_array( $story ) ) {
				continue;
			}

			$story_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $story['id'] ?? '' ) );
			if ( '' === $story_id ) {
				continue;
			}

			$keys = array(
				array( 'highlight_' . $safe_id . '_' . $story_id, 'media' ),
				array( 'highlight_' . $safe_id . '_' . $story_id . '_thumb', 'thumb' ),
			);

			foreach ( $keys as $key_pair ) {
				$attachment_id = $downloader->get_existing_attachment( $key_pair[0], $key_pair[1] );
				if ( $attachment_id > 0 ) {
					wp_delete_attachment( $attachment_id, true );
				}
			}
		}
	}

	/**
	 * Delete all plugin data.
	 *
	 * @param bool $delete_attachments Delete attachments.
	 * @return void
	 */
	public static function delete_all_data( $delete_attachments = false ) {
		if ( $delete_attachments ) {
			$attachment_ids = array_unique( self::get_all_attachment_ids() );
			foreach ( $attachment_ids as $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}

		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . self::media_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'TRUNCATE TABLE ' . self::logs_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'TRUNCATE TABLE ' . self::cursors_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'TRUNCATE TABLE ' . self::highlights_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		delete_transient( 'ipa_frontend_data_mock' );
		delete_transient( 'ipa_frontend_data_live' );
	}
}
