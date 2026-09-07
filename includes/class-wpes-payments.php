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
 *
 * Concurrency: guarded by wpes_payment_captures' UNIQUE KEY on
 * order_id, so two sessions in the same package confirming almost
 * simultaneously can never both trigger a capture for the same order.
 *
 * Two requirements confirmed explicitly per client review (2026-09-07):
 *
 * 1. Gateway replaceability — this class contains zero gateway-specific
 *    code (no gateway ID checks, no gateway API calls; grepped clean
 *    across the whole plugin for any hardcoded reference to WooPayments,
 *    Stripe, PayPal, etc.). "Authorize at checkout" is entirely the
 *    gateway's own native behavior once its manual-capture setting is
 *    on — WooCommerce puts the order into 'on-hold', and the plugin's
 *    pre-existing WPES_WooCommerce::mark_bookings_on_hold_for_order()
 *    (already used for offline/bank-transfer on-hold orders before
 *    this feature existed) holds the seats, unmodified. Swapping the
 *    live gateway later, or changing which gateway supports manual
 *    capture, requires zero changes to this plugin.
 *
 * 2. Capture-once-per-package — for a multi-session package, every
 *    session's confirmation calls maybe_capture_package_payment() with
 *    that order's ID (see WPES_Teacher_Invites::finalize_session_
 *    confirmation()). The CAPTURED_META check above is the fast path
 *    that makes every session after the first a no-op; the
 *    wpes_payment_captures UNIQUE KEY is the actual concurrency
 *    guarantee for two sessions confirming close enough together that
 *    the meta read/write itself could race. Together they guarantee
 *    exactly one capture per order regardless of how many sessions it
 *    covers or how those sessions confirm.
 *
 * Capture-failure safety (Pre-Acceptance Review item 2, 2026-09-07):
 * CAPTURED_META is set ONLY after re-fetching the order fresh from the
 * DB and confirming its status actually is completed/processing — never
 * optimistically before the status transition. If the real status after
 * the attempt is anything else (the gateway's own capture hook rejected
 * it), the wpes_payment_captures claim is released so the very next
 * trigger for this order retries the capture from scratch, an order
 * note records what happened, and the admin gets an email. The internal
 * "captured" state can never say yes for a payment that actually failed.
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

		// Atomic claim: a package with several sessions can have more than
		// one reach "confirmed" within milliseconds of each other (e.g. two
		// Accept-Link clicks landing back-to-back, or a cron sweep and a
		// manual admin assignment overlapping), and every confirmation on
		// this order calls this method. The two checks above are a plain
		// read of order data and are not safe against that — this INSERT
		// is: the table's UNIQUE KEY on order_id means only the first of
		// any concurrent callers can ever win it, the same compare-and-set
		// discipline as WPES_Bookings::reserve() and
		// WPES_Teacher_Invites::handle_accept(), just via a dedicated
		// tracking table instead of a row lock (order storage varies
		// between HPOS and legacy postmeta, so this stays storage-agnostic).
		global $wpdb;
		$claimed = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . WPES_DB::payment_captures_table() . ' (order_id, session_id, created_at) VALUES (%d, %d, %s)',
				$order->get_id(),
				(int) $session_id,
				WPES_DB::now_gmt()
			)
		);

		if ( 1 !== $claimed ) {
			// Someone else's call already won the race for this order.
			return false;
		}

		$order_id = $order->get_id();

		$order->add_order_note(
			sprintf(
				/* translators: %d: session ID */
				__( 'WP Exam Success: session #%d reached its minimum participants and a teacher was assigned — attempting to capture the pre-authorized package payment now.', 'wp-exam-success' ),
				(int) $session_id
			)
		);

		// This status transition is the actual trigger. It deliberately
		// does not call any gateway-specific capture method — see the
		// class docblock.
		$order->update_status(
			'completed',
			__( 'WP Exam Success: attempting package payment capture — first session confirmed.', 'wp-exam-success' )
		);

		// Pre-Acceptance Review item 2: never trust that the status
		// transition above actually succeeded at the gateway — re-fetch
		// the order fresh from the DB rather than the in-memory object,
		// since a gateway's own capture hook (bound to
		// woocommerce_order_status_completed, same as WooPayments) can
		// change the status again within this same request if the actual
		// charge fails. Only mark CAPTURED_META once the order's real,
		// current status confirms the payment went through — never
		// optimistically before that.
		$fresh_order  = wc_get_order( $order_id );
		$final_status = $fresh_order ? $fresh_order->get_status() : 'unknown';

		if ( $fresh_order && in_array( $final_status, array( 'completed', 'processing' ), true ) ) {
			$fresh_order->update_meta_data( self::CAPTURED_META, 'yes' );
			$fresh_order->update_meta_data( self::CAPTURED_BY_SESSION_META, (int) $session_id );
			$fresh_order->save();
			return true;
		}

		// Capture did not actually succeed. Release the claim so this is
		// not permanently stuck — the next trigger for this order (another
		// session in the same package confirming later, or a manual admin
		// re-save) will attempt the capture again from scratch. Internal
		// state never says "captured" for a payment that wasn't, and the
		// admin is notified so a human can check the gateway directly if
		// automatic retries keep failing.
		$wpdb->delete( WPES_DB::payment_captures_table(), array( 'order_id' => $order_id ), array( '%d' ) );

		if ( $fresh_order ) {
			$fresh_order->add_order_note(
				sprintf(
					/* translators: 1: session ID, 2: resulting order status */
					__( 'WP Exam Success: payment capture for session #%1$d did not complete — order status is "%2$s" instead of completed/processing. Will retry automatically the next time a session on this order confirms; admin has been notified.', 'wp-exam-success' ),
					(int) $session_id,
					$final_status
				)
			);
		}

		if ( class_exists( 'WPES_Emailer' ) ) {
			WPES_Emailer::send_admin_capture_failed( $order_id, (int) $session_id, $final_status );
		}

		return false;
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
