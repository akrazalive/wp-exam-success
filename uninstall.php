<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * By default, uninstalling the plugin leaves all booking/session data
 * in place — this is transactional business data (who booked what,
 * meeting-link send history) and should never vanish silently just
 * because a plugin was deactivated/deleted.
 *
 * An admin who explicitly wants a clean wipe can enable this by
 * setting the option 'wpes_delete_data_on_uninstall' to 'yes' via
 * the Settings screen (or wp-cli) before deleting the plugin.
 */
if ( 'yes' !== get_option( 'wpes_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'wpes_meeting_link_log',
	$wpdb->prefix . 'wpes_bookings',
	$wpdb->prefix . 'wpes_sessions',
	$wpdb->prefix . 'wpes_package_classes',
	$wpdb->prefix . 'wpes_magic_tokens',
	$wpdb->prefix . 'wpes_waitlist',
	$wpdb->prefix . 'wpes_classes',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'wpes_db_version' );
delete_option( 'wpes_delete_data_on_uninstall' );

wp_clear_scheduled_hook( 'wpes_cleanup_expired_reservations' );
