<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central place for table names and small DB conventions, so nothing
 * in the plugin hardcodes a $wpdb->prefix string by hand.
 */
class WPES_DB {

	public static function classes_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_classes';
	}

	public static function sessions_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_sessions';
	}

	public static function bookings_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_bookings';
	}

	public static function meeting_log_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_meeting_link_log';
	}

	public static function package_classes_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_package_classes';
	}

	public static function waitlist_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_waitlist';
	}

	public static function teachers_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_teachers';
	}

	public static function teacher_classes_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_teacher_classes';
	}

	public static function teacher_invites_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_teacher_invites';
	}

	public static function replacement_credits_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpes_replacement_credits';
	}

	/** Always use this instead of `date()`/`current_time('mysql')` for stored timestamps — everything in the schema is UTC. */
	public static function now_gmt() {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
