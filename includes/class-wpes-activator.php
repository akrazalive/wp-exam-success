<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates / upgrades the custom tables this plugin relies on.
 */
class WPES_Activator {

	const DB_VERSION = '1.4.0';

	public static function activate() {
		self::create_tables();
		update_option( 'wpes_db_version', self::DB_VERSION );

		if ( ! wp_next_scheduled( 'wpes_cleanup_expired_reservations' ) ) {
			wp_schedule_event( time(), 'wpes_five_minutes', 'wpes_cleanup_expired_reservations' );
		}
		if ( ! wp_next_scheduled( 'wpes_waitlist_match_sessions' ) ) {
			wp_schedule_event( time(), 'wpes_twelve_hours', 'wpes_waitlist_match_sessions' );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( 'wpes_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
			WPES_Waitlist::backfill_dates();
			update_option( 'wpes_db_version', self::DB_VERSION );
		}
	}

	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$classes_table         = $wpdb->prefix . 'wpes_classes';
		$sessions_table        = $wpdb->prefix . 'wpes_sessions';
		$bookings_table        = $wpdb->prefix . 'wpes_bookings';
		$meeting_log_table     = $wpdb->prefix . 'wpes_meeting_link_log';
		$package_classes_table = $wpdb->prefix . 'wpes_package_classes';
		$magic_tokens_table    = $wpdb->prefix . 'wpes_magic_tokens';
		$waitlist_table        = $wpdb->prefix . 'wpes_waitlist';

		$sql_classes = "CREATE TABLE {$classes_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset_collate};";

		$sql_sessions = "CREATE TABLE {$sessions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT UNSIGNED NOT NULL,
			series_id VARCHAR(64) NULL,
			title VARCHAR(191) NULL,
			description TEXT NULL,
			level VARCHAR(10) NULL,
			starts_at_gmt DATETIME NOT NULL,
			ends_at_gmt DATETIME NOT NULL,
			source_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			max_attendees SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			meeting_link VARCHAR(500) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
			recurrence_label VARCHAR(191) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY series_id (series_id),
			KEY starts_at_gmt (starts_at_gmt),
			KEY status (status)
		) {$charset_collate};";

		$sql_bookings = "CREATE TABLE {$bookings_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NULL,
			order_item_id BIGINT UNSIGNED NULL,
			product_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NULL,
			customer_name VARCHAR(191) NULL,
			customer_email VARCHAR(191) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			reserved_until DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY order_id (order_id),
			KEY order_item_id (order_item_id),
			KEY status (status),
			KEY customer_email (customer_email),
			KEY user_id (user_id)
		) {$charset_collate};";

		$sql_meeting_log = "CREATE TABLE {$meeting_log_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id BIGINT UNSIGNED NOT NULL,
			sent_by BIGINT UNSIGNED NULL,
			sent_at DATETIME NOT NULL,
			recipient_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			send_type VARCHAR(20) NOT NULL DEFAULT 'initial',
			PRIMARY KEY  (id),
			KEY session_id (session_id)
		) {$charset_collate};";

		$sql_package_classes = "CREATE TABLE {$package_classes_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			class_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_class (product_id, class_id),
			KEY product_id (product_id),
			KEY class_id (class_id)
		) {$charset_collate};";

		$sql_magic_tokens = "CREATE TABLE {$magic_tokens_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			token_hash VARCHAR(64) NOT NULL,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY user_id (user_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		$sql_waitlist = "CREATE TABLE {$waitlist_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id VARCHAR(64) NULL,
			form_name VARCHAR(191) NULL,
			name VARCHAR(191) NULL,
			email VARCHAR(191) NULL,
			fields LONGTEXT NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			is_notified TINYINT(1) NOT NULL DEFAULT 0,
			notified_on DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY is_notified (is_notified),
			KEY start_date (start_date),
			KEY end_date (end_date)
		) {$charset_collate};";

		dbDelta( $sql_classes );
		dbDelta( $sql_sessions );
		dbDelta( $sql_bookings );
		dbDelta( $sql_meeting_log );
		dbDelta( $sql_package_classes );
		dbDelta( $sql_magic_tokens );
		dbDelta( $sql_waitlist );
	}
}
