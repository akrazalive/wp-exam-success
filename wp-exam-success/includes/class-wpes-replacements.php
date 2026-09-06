<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replacement session / credit flow (Developer Spec §11, Overview PDF
 * red path): a session that never reaches its minimum participant count
 * by the final pre-session check is cancelled, and every customer
 * booked into it gets one replacement credit to pick a different
 * session — no additional charge, since it's covered by the package
 * already purchased.
 */
class WPES_Replacements {

	/**
	 * Handle a session that failed to reach its minimum by the final
	 * check. Idempotent — the atomic status guard means only one caller
	 * ever wins even if this fires twice for the same session.
	 *
	 * @param object $session Row from wpes_sessions (status must still be 'scheduled').
	 * @return bool True if this call is the one that processed the failure.
	 */
	public static function process_session_failure( $session ) {
		global $wpdb;

		$sessions_table = WPES_DB::sessions_table();
		$updated        = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table}
				 SET status = 'cancelled', confirmation_state = 'failed_min', updated_at = %s
				 WHERE id = %d AND status = 'scheduled'",
				WPES_DB::now_gmt(),
				$session->id
			)
		);

		if ( 1 !== $updated ) {
			return false; // Already processed (or no longer 'scheduled') — someone else's call wins.
		}

		$class     = WPES_Classes::get( $session->class_id );
		$bookings  = WPES_Bookings::get_attendees_for_session( $session->id, array( 'confirmed' ) );

		foreach ( $bookings as $booking ) {
			WPES_Bookings::cancel( $booking->id );
			$credit_id = self::issue_credit( $booking, $session );
			if ( $credit_id ) {
				WPES_Emailer::send_session_cancelled_replacement( $booking, $session, $class, $credit_id );
			}
		}

		return true;
	}

	/**
	 * @param object $booking Row from wpes_bookings (the now-cancelled booking).
	 * @param object $session Row from wpes_sessions (the failed session).
	 * @return int|false New credit ID.
	 */
	public static function issue_credit( $booking, $session ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			WPES_DB::replacement_credits_table(),
			array(
				'order_id'          => $booking->order_id ?: null,
				'order_item_id'     => $booking->order_item_id ?: null,
				'product_id'        => $booking->product_id ?: null,
				'source_booking_id' => $booking->id,
				'source_session_id' => $session->id,
				'user_id'           => $booking->user_id ?: null,
				'customer_name'     => $booking->customer_name,
				'customer_email'    => $booking->customer_email,
				'status'            => 'available',
				'created_at'        => WPES_DB::now_gmt(),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Available (unredeemed) credits for a customer, for the My Sessions
	 * screen.
	 *
	 * @param int    $user_id
	 * @param string $email
	 * @return object[]
	 */
	public static function get_available_credits( $user_id, $email ) {
		global $wpdb;
		$credits_table  = WPES_DB::replacement_credits_table();
		$sessions_table = WPES_DB::sessions_table();
		$classes_table  = WPES_DB::classes_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rc.*, s.title AS source_title, s.starts_at_gmt AS source_starts_at_gmt, c.name AS source_class_name
				 FROM {$credits_table} rc
				 LEFT JOIN {$sessions_table} s ON s.id = rc.source_session_id
				 LEFT JOIN {$classes_table} c ON c.id = s.class_id
				 WHERE rc.status = 'available' AND ( rc.user_id = %d OR rc.customer_email = %s )
				 ORDER BY rc.created_at ASC",
				(int) $user_id,
				$email
			)
		);
	}

	/**
	 * @param int $credit_id
	 * @return object|null
	 */
	public static function get_credit( $credit_id ) {
		global $wpdb;
		$table = WPES_DB::replacement_credits_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $credit_id ) );
	}

	/**
	 * Redeem a credit against a newly chosen session — reuses the exact
	 * same reservation path a paying customer goes through
	 * (WPES_Bookings::reserve() + confirm()), just without a checkout, so
	 * capacity and race-condition safety come for free. No payment is
	 * ever created, per the spec.
	 *
	 * @param int    $credit_id
	 * @param int    $new_session_id
	 * @param int    $user_id  Requesting user, for ownership verification.
	 * @param string $email    Requesting user's email, for ownership verification.
	 * @return int|WP_Error New booking ID.
	 */
	public static function redeem_credit( $credit_id, $new_session_id, $user_id, $email ) {
		$credit = self::get_credit( $credit_id );

		if ( ! $credit || 'available' !== $credit->status ) {
			return new WP_Error( 'wpes_credit_unavailable', __( 'This replacement credit is no longer available.', 'wp-exam-success' ) );
		}

		$owns_credit = ( $user_id && (int) $credit->user_id === (int) $user_id )
			|| ( $email && strtolower( $credit->customer_email ) === strtolower( $email ) );

		if ( ! $owns_credit ) {
			return new WP_Error( 'wpes_credit_forbidden', __( 'This replacement credit does not belong to you.', 'wp-exam-success' ) );
		}

		$booking_id = WPES_Bookings::reserve(
			$new_session_id,
			array(
				'order_id'       => $credit->order_id,
				'order_item_id'  => $credit->order_item_id,
				'product_id'     => $credit->product_id,
				'user_id'        => $credit->user_id,
				'customer_name'  => $credit->customer_name,
				'customer_email' => $credit->customer_email,
			)
		);

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// No payment involved — confirm immediately, exactly like the
		// existing manual-enrollment path.
		WPES_Bookings::confirm( $booking_id );

		global $wpdb;
		$wpdb->update(
			WPES_DB::replacement_credits_table(),
			array(
				'status'          => 'used',
				'used_booking_id' => $booking_id,
				'used_session_id' => $new_session_id,
				'used_at'         => WPES_DB::now_gmt(),
			),
			array( 'id' => $credit->id ),
			array( '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		// The redemption itself may push the new session past its own
		// minimum — re-check it exactly as a normal booking would.
		WPES_Teacher_Invites::maybe_invite_teachers( (int) $new_session_id );

		return $booking_id;
	}
}
