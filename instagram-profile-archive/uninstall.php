<?php
/**
 * Uninstall handler.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$delete_data = get_option( 'ipa_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

$page_id = (int) get_option( 'ipa_instagram_page_id', 0 );

global $wpdb;

require_once dirname( __FILE__ ) . '/includes/class-ipa-settings.php';
require_once dirname( __FILE__ ) . '/includes/class-ipa-db.php';

$delete_attachments = (bool) get_option( 'ipa_delete_attachments_on_uninstall', false );
IPA_DB::delete_all_data( $delete_attachments );

$tables = array(
	$wpdb->prefix . 'ipa_media',
	$wpdb->prefix . 'ipa_sync_logs',
	$wpdb->prefix . 'ipa_sync_cursors',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$option_keys = array_keys( IPA_Settings::get_defaults() );
$options     = array_merge(
	array(
		'ipa_instagram_page_id',
		'ipa_db_version',
		'ipa_delete_attachments_on_uninstall',
	),
	array_map(
		static function ( $key ) {
			return 'ipa_' . $key;
		},
		$option_keys
	)
);

foreach ( $options as $option ) {
	delete_option( $option );
}

if ( $page_id > 0 ) {
	wp_delete_post( $page_id, true );
}

delete_transient( 'ipa_frontend_data_mock' );
delete_transient( 'ipa_frontend_data_live' );
