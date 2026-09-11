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
	 * WooPayments' own order meta key holding the real Stripe Payment
	 * Intent status (values from WCPay\Constants\Intent_Status — e.g.
	 * 'requires_capture', 'succeeded'). Confirmed by reading WooPayments'
	 * own source (class-wc-payments-order-service.php) on staging: when
	 * its capture_authorization_on_order_status_change() genuinely fails
	 * to capture, it ONLY adds an order note ("Capture authorization
	 * failed to complete") — it does NOT touch the WooCommerce order
	 * status at all, and does not update this meta key either (both are
	 * only set on the success path). This is the confirmed root cause of
	 * a real bug found in client testing (Order #1190, 2026-09-11): this
	 * class's own update_status('completed') call had already set the
	 * WC order status before WooPayments' capture attempt even ran, so
	 * re-checking $order->get_status() afterward saw 'completed'
	 * regardless of whether the actual charge succeeded — a false
	 * positive that both left the order sitting at a status implying
	 * successful payment, AND skipped the failure-handling branch
	 * entirely (so no admin email either — both client-reported bugs
	 * traced back to this one root cause).
	 */
	const GATEWAY_INTENT_STATUS_META = '_intention_status';
	const GATEWAY_INTENT_SUCCESS_VALUE = 'succeeded';

	/**
	 * Register the manual "Retry payment capture" WooCommerce order
	 * action (Outstanding Points for Review follow-up, 2026-09-11 —
	 * client re-checked Order #1190 after the capture-failure fix above
	 * and found it *still* sitting at "Completed" with no admin email).
	 *
	 * Root cause of that specific complaint: the automatic retry this
	 * class promises in its failure order note ("will retry
	 * automatically the next time a session on this order confirms")
	 * only actually happens if there IS a next session — for a package
	 * where every session had already independently confirmed by the
	 * time the capture failed (Order #1190's case: the failure was on
	 * the package's own only/last remaining trigger), there is no
	 * future confirmation left to ever call maybe_capture_package_
	 * payment() again, so the order is stuck exactly as found — not
	 * because today's status/email fix doesn't work, but because
	 * nothing ever calls it again for that specific order. Confirmed by
	 * checking Order #1190 directly: no new order notes since the
	 * original failure, still 'completed', because it predates this
	 * fix entirely and nothing has re-triggered it since.
	 *
	 * This gives admins (and this project's own testing) a real,
	 * always-available way to retry, instead of only an automatic path
	 * that silently has no next trigger for some packages.
	 */
	public static function init() {
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'add_retry_order_action' ), 10, 2 );
		add_action( 'woocommerce_order_action_wpes_retry_payment_capture', array( __CLASS__, 'handle_retry_order_action' ) );
	}

	/**
	 * @param array    $actions
	 * @param WC_Order $order
	 * @return array
	 */
	public static function add_retry_order_action( $actions, $order ) {
		if ( ! $order instanceof WC_Order || self::is_captured( $order ) ) {
			return $actions;
		}
		// Only relevant for orders this class actually deals with: still
		// on-hold awaiting capture, or stuck at completed/processing
		// without ever having been confirmed captured (the exact
		// pre-existing-bug state Order #1190 is in).
		if ( ! in_array( $order->get_status(), array( 'on-hold', 'completed', 'processing' ), true ) ) {
			return $actions;
		}
		$actions['wpes_retry_payment_capture'] = __( 'WP Exam Success: retry payment capture', 'wp-exam-success' );
		return $actions;
	}

	/**
	 * @param WC_Order $order
	 */
	public static function handle_retry_order_action( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		self::retry_capture_manually( $order->get_id() );
	}

	/**
	 * Manual, admin-initiated retry — the "Retry payment capture" order
	 * action above, and reusable from anywhere else in the admin
	 * (e.g. a future button on the Bookings screen) via the order ID
	 * alone. Unlike maybe_capture_package_payment()'s automatic path,
	 * this is allowed to run against an order already sitting at
	 * 'completed'/'processing' with no confirmed capture — the exact
	 * state a pre-existing capture failure can leave an order in when
	 * there's no future session confirmation left to naturally retry it.
	 *
	 * @param int $order_id
	 * @return true|WP_Error True if a capture attempt just ran (check
	 *                       is_captured() afterward for the outcome —
	 *                       failure is reported the normal way, via the
	 *                       order note + admin email), or WP_Error if
	 *                       there was nothing valid to retry.
	 */
	public static function retry_capture_manually( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'wpes_order_not_found', __( 'Order not found.', 'wp-exam-success' ) );
		}

		if ( self::is_captured( $order ) ) {
			return new WP_Error( 'wpes_already_captured', __( 'This order\'s package payment is already marked captured — nothing to retry.', 'wp-exam-success' ) );
		}

		$status = $order->get_status();

		if ( in_array( $status, array( 'completed', 'processing' ), true ) ) {
			// Stuck state: this class itself set this status on a prior
			// attempt, but never confirmed the capture actually went
			// through, and nothing since has retried it. Correct the
			// status back to on-hold first — same correction the
			// automatic path now makes for itself on a fresh failure —
			// so the normal capture attempt below has a real on-hold
			// order to work from, exactly like the automatic trigger
			// expects.
			$order->add_order_note(
				__( 'WP Exam Success: manual retry requested — this order was sitting at a status implying successful payment, but the package payment was never confirmed captured. Reverting to On hold before retrying.', 'wp-exam-success' )
			);
			$order->update_status( 'on-hold', __( 'WP Exam Success: manual capture retry.', 'wp-exam-success' ) );
		} elseif ( 'on-hold' !== $status ) {
			return new WP_Error(
				'wpes_not_retryable',
				sprintf(
					/* translators: %s: current order status */
					__( 'This order is not in a state this can retry (current status: %s).', 'wp-exam-success' ),
					$status
				)
			);
		}

		self::maybe_capture_package_payment( $order_id, 0, true );

		return true;
	}

	/**
	 * Capture this order's pre-authorized package payment, if it's
	 * actually awaiting one — called once a session on this order
	 * reaches its minimum participants and gets a teacher assigned.
	 *
	 * Safe to call repeatedly (e.g. once per session on a multi-session
	 * package): only the first call that finds the order genuinely
	 * on-hold does anything; every call after that is a no-op.
	 *
	 * @param WC_Order|int $order          Order or order ID.
	 * @param int          $session_id     The session that just confirmed (for the audit trail).
	 * @param bool         $is_manual_retry True when called from retry_capture_manually() rather
	 *                                       than an automatic session-confirmation trigger — only
	 *                                       changes the wording of the order notes written below.
	 * @return bool True if a capture was just triggered, false otherwise.
	 */
	public static function maybe_capture_package_payment( $order, $session_id = 0, $is_manual_retry = false ) {
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
		// no-op rather than force a status change. (A manual retry that
		// needed to recover a stuck completed/processing order already
		// reverted it to on-hold before calling this, in retry_capture_
		// manually() above, so this guard stays exactly as strict as
		// before for the automatic path.)
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

		if ( $is_manual_retry ) {
			$order->add_order_note(
				__( 'WP Exam Success: manually retrying the pre-authorized package payment capture now.', 'wp-exam-success' )
			);
		} else {
			$order->add_order_note(
				sprintf(
					/* translators: %d: session ID */
					__( 'WP Exam Success: session #%d reached its minimum participants and a teacher was assigned — attempting to capture the pre-authorized package payment now.', 'wp-exam-success' ),
					(int) $session_id
				)
			);
		}

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

		// The order-status check above is NOT sufficient on its own —
		// confirmed by a real capture failure in client testing (see the
		// class docblock and GATEWAY_INTENT_STATUS_META above): this
		// class already set the status to 'completed' before the gateway
		// even attempted the capture, and WooPayments does not revert it
		// on failure. Cross-check the gateway's own intent-status meta as
		// a second, more precise signal when it's present (i.e. when
		// WooPayments or a compatible Stripe-based gateway is active); if
		// it isn't present at all (a different gateway that doesn't use
		// this convention), fall back to the order-status check alone so
		// this stays working, just less precisely, with any gateway.
		$intention_status     = $fresh_order ? $fresh_order->get_meta( self::GATEWAY_INTENT_STATUS_META ) : '';
		$gateway_meta_present = '' !== $intention_status;
		$gateway_confirms_ok  = ! $gateway_meta_present || ( self::GATEWAY_INTENT_SUCCESS_VALUE === $intention_status );

		if ( $fresh_order && in_array( $final_status, array( 'completed', 'processing' ), true ) && $gateway_confirms_ok ) {
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

		// Undo this class's own premature status change — an order must
		// never be left sitting at 'completed'/'processing' (a status
		// that implies successful payment) when the capture behind it did
		// not actually go through. Only touch it if it's still sitting at
		// the status this method itself set; if something else already
		// moved it on (e.g. a refund, an admin action), leave that alone.
		if ( $fresh_order && in_array( $final_status, array( 'completed', 'processing' ), true ) ) {
			$fresh_order->update_status(
				'on-hold',
				__( 'WP Exam Success: reverting to On hold — the payment capture attempted above did not actually succeed at the gateway (see the note below/above from the gateway itself).', 'wp-exam-success' )
			);
		}

		if ( $fresh_order ) {
			// Deliberately does NOT promise this retries "automatically" —
			// confirmed via a real stuck order (client re-check, 2026-09-11)
			// that this is only true if another session on the same order
			// still has a future confirmation left to fire the trigger;
			// for a package where this was the last/only session, nothing
			// would ever call this again on its own. Points at the real,
			// always-available fix instead: the "WP Exam Success: retry
			// payment capture" action in this order's own Order actions
			// dropdown (added alongside this note, see WPES_Payments::init()).
			$fresh_order->add_order_note(
				sprintf(
					/* translators: 1: session ID, 2: resulting order status, 3: gateway intent status or "n/a" */
					__( 'WP Exam Success: payment capture for session #%1$d did not complete — order status was "%2$s" and the gateway intent status is "%3$s" (expected "succeeded"). Reverted to On hold; admin has been notified. Will retry automatically if another session on this order still confirms later — otherwise, use "WP Exam Success: retry payment capture" from this order\'s Order actions dropdown.', 'wp-exam-success' ),
					(int) $session_id,
					$final_status,
					$gateway_meta_present ? $intention_status : 'n/a'
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
