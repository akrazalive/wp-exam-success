<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A pending booking (reserved at add-to-cart, before payment) holds a
 * seat for a limited window. If the customer abandons checkout, that
 * seat must come back into the pool automatically — otherwise popular
 * sessions "fill up" with phantom reservations. This cron sweeps them
 * every 5 minutes.
 */
class WPES_Cron {

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( 'wpes_cleanup_expired_reservations', array( 'WPES_Bookings', 'release_expired' ) );
		add_action( 'wpes_waitlist_match_sessions', array( 'WPES_Waitlist', 'cron_match_sessions' ) );

		if ( ! wp_next_scheduled( 'wpes_cleanup_expired_reservations' ) ) {
			wp_schedule_event( time(), 'wpes_five_minutes', 'wpes_cleanup_expired_reservations' );
		}
		if ( ! wp_next_scheduled( 'wpes_waitlist_match_sessions' ) ) {
			wp_schedule_event( time(), 'wpes_twelve_hours', 'wpes_waitlist_match_sessions' );
		}
	}

	public static function add_schedule( $schedules ) {
		$schedules['wpes_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 Minutes (WP Exam Success)', 'wp-exam-success' ),
		);
		$schedules['wpes_twelve_hours'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 Hours (WP Exam Success)', 'wp-exam-success' ),
		);
		return $schedules;
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpes_cleanup_expired_reservations' );
		wp_clear_scheduled_hook( 'wpes_waitlist_match_sessions' );
	}
}
