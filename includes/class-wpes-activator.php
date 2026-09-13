<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates / upgrades the custom tables this plugin relies on.
 */
class WPES_Activator {

	const DB_VERSION = '1.9.0';

	public static function activate() {
		self::create_tables();
		update_option( 'wpes_db_version', self::DB_VERSION );

		if ( ! wp_next_scheduled( 'wpes_cleanup_expired_reservations' ) ) {
			wp_schedule_event( time(), 'wpes_five_minutes', 'wpes_cleanup_expired_reservations' );
		}
		if ( ! wp_next_scheduled( 'wpes_waitlist_match_sessions' ) ) {
			wp_schedule_event( time(), 'wpes_twelve_hours', 'wpes_waitlist_match_sessions' );
		}
		if ( ! wp_next_scheduled( 'wpes_teacher_invite_sweep' ) ) {
			wp_schedule_event( time(), 'wpes_five_minutes', 'wpes_teacher_invite_sweep' );
		}
		if ( ! wp_next_scheduled( 'wpes_session_final_check' ) ) {
			wp_schedule_event( time(), 'wpes_twelve_hours', 'wpes_session_final_check' );
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
		$teachers_table        = $wpdb->prefix . 'wpes_teachers';
		$teacher_classes_table = $wpdb->prefix . 'wpes_teacher_classes';
		$teacher_invites_table = $wpdb->prefix . 'wpes_teacher_invites';
		$replacement_credits_table = $wpdb->prefix . 'wpes_replacement_credits';
		$payment_captures_table = $wpdb->prefix . 'wpes_payment_captures';

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
			assigned_teacher_id BIGINT UNSIGNED NULL,
			teacher_assigned_at DATETIME NULL,
			confirmation_state VARCHAR(20) NOT NULL DEFAULT 'pending',
			is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY series_id (series_id),
			KEY starts_at_gmt (starts_at_gmt),
			KEY status (status),
			KEY assigned_teacher_id (assigned_teacher_id),
			KEY confirmation_state (confirmation_state),
			KEY is_active (is_active)
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

		$sql_teachers = "CREATE TABLE {$teachers_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			email VARCHAR(191) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY email (email)
		) {$charset_collate};";

		// Many-to-many: which classes (subjects) a teacher is suitable to teach.
		$sql_teacher_classes = "CREATE TABLE {$teacher_classes_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			teacher_id BIGINT UNSIGNED NOT NULL,
			class_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY teacher_class (teacher_id, class_id),
			KEY teacher_id (teacher_id),
			KEY class_id (class_id)
		) {$charset_collate};";

		// One row per teacher invited to a session via Accept-Link. A session
		// can have several pending rows at once (one per invited teacher);
		// the first to accept wins and the rest are marked 'superseded'.
		$sql_teacher_invites = "CREATE TABLE {$teacher_invites_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id BIGINT UNSIGNED NOT NULL,
			teacher_id BIGINT UNSIGNED NOT NULL,
			token_hash VARCHAR(64) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			responded_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY session_id (session_id),
			KEY teacher_id (teacher_id),
			KEY status (status),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		// One row per session that failed its minimum-participant check —
		// tracks the customer's entitlement to pick a different session at
		// no additional charge (Developer Spec §11).
		$sql_replacement_credits = "CREATE TABLE {$replacement_credits_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NULL,
			order_item_id BIGINT UNSIGNED NULL,
			product_id BIGINT UNSIGNED NULL,
			source_booking_id BIGINT UNSIGNED NOT NULL,
			source_session_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NULL,
			customer_name VARCHAR(191) NULL,
			customer_email VARCHAR(191) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'available',
			used_booking_id BIGINT UNSIGNED NULL,
			used_session_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			used_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY customer_email (customer_email),
			KEY status (status),
			KEY source_session_id (source_session_id)
		) {$charset_collate};";

		dbDelta( $sql_classes );
		dbDelta( $sql_sessions );
		dbDelta( $sql_bookings );
		dbDelta( $sql_meeting_log );
		dbDelta( $sql_package_classes );
		dbDelta( $sql_magic_tokens );
		dbDelta( $sql_waitlist );
		dbDelta( $sql_teachers );
		dbDelta( $sql_teacher_classes );
		dbDelta( $sql_teacher_invites );
		// One row per order whose package payment has been captured — the
		// UNIQUE KEY on order_id is the actual concurrency guard: two
		// sessions in the same package confirming within milliseconds of
		// each other both call WPES_Payments::maybe_capture_package_payment()
		// for the same order, and only the INSERT that wins the unique
		// constraint is allowed to proceed to capture (Developer Spec §6,
		// §14's "even with almost simultaneous ... requests" requirement).
		$sql_payment_captures = "CREATE TABLE {$payment_captures_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			session_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql_replacement_credits );
		dbDelta( $sql_payment_captures );
	}
}
