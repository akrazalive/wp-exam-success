<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Package payment: authorize the full amount at checkout, capture it once
 * — the moment the first session in the package actually confirms
 * (Developer Spec §6, §7 steps 3/12/13, §14 acceptance criteria).
 *
 * This class does NOT talk to any payment gateway directly. Per the
 * spec's explicit requirement ("no custom payment system may be
 * developed"), it only manages the WooCommerce order's own status
 * lifecycle: an order sitting 'on-hold' with WooPayments' manual-capture
 * setting enabled holds an authorized-but-uncaptured charge, and
 * transitioning that order to 'completed' is what WooCommerce's own
 * payment gateway infrastructure hooks to actually capture it (verified
 * directly against the installed WooPayments plugin's source on
 * staging: it binds `capture_authorization_on_order_status_change()` to
 * `woocommerce_order_status_completed`). Any other WooCommerce gateway
 * that supports manual capture the same way benefits automatically,
 * with no plugin code changes.
 *
 * Inert by design until manual capture is actually enabled on the
 * gateway: without it, orders never sit 'on-hold' awaiting a charge, so
 * maybe_capture_package_payment() always no-ops (see the status guard).
 */
class WPES_Payments {

	const CAPTURED_META = '_wpes_captured';
	const CAPTURED_BY_SESSION_META = '_wpes_captured_by_session';

	/**
	 * Capture this order's pre-authorized package payment, if it's
	 * actually awaiting one — called once a session on this order
	 * reaches its minimum participants and gets a teacher assigned.
	 *
	 * Safe to call repeatedly (e.g. once per session on a multi-session
	 * package): only the first call that finds the order genuinely
	 * on-hold does anything; every call after that is a no-op.
	 *
	 * @param WC_Order|int $order      Order or order ID.
	 * @param int          $session_id The session that just confirmed (for the audit trail).
	 * @return bool True if a capture was just triggered, false otherwise.
	 */
	public static function maybe_capture_package_payment( $order, $session_id = 0 ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		// Already captured for this package — every other session in it
		// rides on this same payment (Developer Spec §6, "Remaining
		// sessions" / §7 step 13).
		if ( 'yes' === $order->get_meta( self::CAPTURED_META ) ) {
			return false;
		}

		// Not currently awaiting a capture. Covers both "manual capture
		// isn't enabled on the gateway" (order already went straight to
		// processing/completed at checkout, nothing to do here) and "this
		// order was already resolved another way" — either way, safe to
		// no-op rather than force a status change.
		if ( 'on-hold' !== $order->get_status() ) {
			return false;
		}

		$order->update_meta_data( self::CAPTURED_META, 'yes' );
		$order->update_meta_data( self::CAPTURED_BY_SESSION_META, (int) $session_id );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: %d: session ID */
				__( 'WP Exam Success: session #%d reached its minimum participants and a teacher was assigned — capturing the pre-authorized package payment now.', 'wp-exam-success' ),
				(int) $session_id
			)
		);

		// This status transition is the actual trigger. It deliberately
		// does not call any gateway-specific capture method — see the
		// class docblock. If the underlying capture fails at the gateway
		// (e.g. the 7-day WooPayments authorization window lapsed), the
		// gateway adds its own order note explaining why; there is no
		// automatic re-attempt or admin alert for that failure case yet.
		$order->update_status(
			'completed',
			__( 'WP Exam Success: package payment captured — first session confirmed.', 'wp-exam-success' )
		);

		return true;
	}

	/**
	 * @param WC_Order|int $order
	 * @return bool Whether this order's package payment has been captured.
	 */
	public static function is_captured( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}
		return $order instanceof WC_Order && 'yes' === $order->get_meta( self::CAPTURED_META );
	}
}
