<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce integration: package products, cart validation,
 * session reservation / confirmation / release lifecycle.
 *
 * Updated to work with the frontend booking calendar flow:
 *  - Unlimited packages (>99 sessions) allow any number of sessions (min 8).
 *  - Cart item data now comes from a JSON blob in $_POST['wpes_sessions'].
 *  - Eligible classes check is removed (per original brief: "custom fields
 *    for eligible classes should be ignored altogether").
 *  - Uses WPES_Sessions::get_remaining() and WPES_Sessions::has_capacity().
 */
class WPES_WooCommerce {

	public static function init() {
		// Product edit screen: package settings.
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_package_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_package_fields' ) );

		// Cart: validate + reserve session picks.
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_remove_cart_item', array( __CLASS__, 'release_on_cart_remove' ), 10, 2 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'release_on_cart_remove' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_sessions' ), 10, 2 );

		// Order: persist session selection onto the order line item, confirm/release bookings.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'link_bookings_to_order' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'link_bookings_to_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'confirm_bookings_for_order' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'confirm_bookings_for_order' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'send_order_sessions_email' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'send_order_sessions_email' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'release_bookings_for_order' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'release_bookings_for_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'release_bookings_for_order' ) );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'mark_bookings_on_hold_for_order' ) );

		// Order details page: show associated booking summary.
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order_booking_summary' ) );

		// Redirect from Cart to Checkout
		add_action( 'woocommerce_cart_redirect_after_add', array( __CLASS__, 'redirect_to_checkout' ) );

		// Show Order Item Meta.
		add_action( 'woocommerce_order_item_meta_start', array( __CLASS__, 'show_order_item_meta' ), 10, 3 );

		// Clear previous cart
		add_action( 'woocommerce_add_to_cart', [ __CLASS__, 'clear_previous_cart' ], 10, 6 );
	}

	/* ---------------------------------------------------------------
	 * Constants
	 * ------------------------------------------------------------- */

	const UNLIMITED_THRESHOLD    = 99;
	const UNLIMITED_MIN_SESSIONS = 8;


	/**
	 * Keep only the just-added package in the cart.
	 *
	 * Previously this emptied the cart and re-called add_to_cart(), which
	 * re-ran add_cart_item_data() and created a second set of pending bookings
	 * (N sessions → 2N rows). Instead we remove other line items via
	 * remove_cart_item() so their reservations are released, and keep the
	 * item that was just reserved.
	 */
	public static function clear_previous_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		remove_action( 'woocommerce_add_to_cart', array( __CLASS__, 'clear_previous_cart' ), 10 );

		$cart = WC()->cart;
		if ( $cart && $cart->get_cart_contents_count() > $quantity ) {
			foreach ( array_keys( $cart->get_cart() ) as $key ) {
				if ( $key !== $cart_item_key ) {
					$cart->remove_cart_item( $key );
				}
			}
		}

		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'clear_previous_cart' ), 10, 6 );
	}
	
	/* ---------------------------------------------------------------
	 * Product edit screen
	 * ------------------------------------------------------------- */

	public static function render_package_fields() {
		global $post;
		echo '<div class="options_group wpes-package-fields">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_wpes_is_package',
				'label'       => __( 'Session Package', 'wp-exam-success' ),
				'description' => __( 'This product grants a fixed number of class sessions the customer selects at purchase.', 'wp-exam-success' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wpes_sessions_required',
				'label'             => __( 'Number of Sessions', 'wp-exam-success' ),
				'description'       => __( 'How many sessions the customer must choose for this package. Enter a value > 99 for an unlimited package (minimum 8).', 'wp-exam-success' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
			)
		);

		echo '</div>';
	}

	public static function redirect_to_checkout() {

		// Check if cart empty, if yes, go to page_id 381
		if ( WC()->cart->is_empty() ) {
			wp_redirect( get_permalink( 381 ) );
			exit;
		}

		// Otherwise, redirect to checkout
		wp_redirect( wc_get_checkout_url() );
		exit;
	}

	public static function save_package_fields( $post_id ) {
		$is_package = isset( $_POST['_wpes_is_package'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wpes_is_package', $is_package );

		if ( isset( $_POST['_wpes_sessions_required'] ) ) {
			$required = max( 1, (int) $_POST['_wpes_sessions_required'] );
			update_post_meta( $post_id, '_wpes_sessions_required', $required );
			self::sync_package_description( $post_id, $required );
		}
	}

	public static function show_order_item_meta( $item_id, $item, $order ) {

		$session_data = $item->get_meta( '_wpes_session_ids' );

		// Check if item has session data.
		if ( ! empty( $session_data ) ) {
			echo '<div class="wpes-order-item-meta">';
			
			foreach( $session_data as $session_id ) {
				$session = WPES_Sessions::get_with_capacity( $session_id );
				if ( $session ) {
					echo '<div class="single-session-detail-row">' . $session->class_name . '<br>' . $session->title . '<span>' . $session->level . '</span>' . '</div>';
				}
			}

			echo '</div>';
		}
	}

	/**
	 * Keep product short description in sync with session count meta.
	 *
	 * @param int $post_id  Product ID.
	 * @param int $required Sessions required.
	 */
	public static function sync_package_description( $post_id, $required ) {
		if ( 'yes' !== get_post_meta( $post_id, '_wpes_is_package', true ) ) {
			return;
		}

		$unlimited = $required > self::UNLIMITED_THRESHOLD;
		if ( $unlimited ) {
			$desc = sprintf(
				/* translators: %d: minimum sessions */
				__( 'Unlimited session package — select at least %d sessions.', 'wp-exam-success' ),
				self::UNLIMITED_MIN_SESSIONS
			);
		} else {
			$desc = sprintf(
				/* translators: %d: number of sessions */
				_n( 'Select %d session from our schedule.', 'Select %d sessions from our schedule.', $required, 'wp-exam-success' ),
				$required
			);
		}

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_excerpt' => $desc,
			)
		);
	}

	public static function is_package_product( $product_id ) {
		return 'yes' === get_post_meta( $product_id, '_wpes_is_package', true );
	}

	/**
	 * Get package metadata in a normalized shape.
	 *
	 * @param int $product_id
	 * @return array { required: int, min: int, unlimited: bool }
	 */
	public static function get_package_meta( $product_id ) {
		$required  = (int) get_post_meta( $product_id, '_wpes_sessions_required', true );
		$unlimited = $required > self::UNLIMITED_THRESHOLD;

		return array(
			'required'  => $unlimited ? 0 : $required,
			'min'       => $unlimited ? self::UNLIMITED_MIN_SESSIONS : $required,
			'unlimited' => $unlimited,
		);
	}

	/* ---------------------------------------------------------------
	 * Cart: validate + reserve session picks
	 * ------------------------------------------------------------- */

	/**
	 * Validate the customer's session selection before adding to cart.
	 *
	 * The frontend posts a JSON blob in $_POST['wpes_sessions'] containing
	 * the selected session objects. We decode it, validate counts, capacity,
	 * and session status.
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! self::is_package_product( $product_id ) ) {
			return $passed;
		}

		$meta     = self::get_package_meta( $product_id );
		$selected = self::get_selected_sessions_from_request();

		if ( empty( $selected ) ) {
			wc_add_notice( __( 'Please select at least one session for this package.', 'wp-exam-success' ), 'error' );
			return false;
		}

		$count = count( $selected );

		if ( $meta['unlimited'] ) {
			if ( $count < $meta['min'] ) {
				wc_add_notice(
					sprintf(
						/* translators: %d: minimum sessions */
						__( 'Please select at least %d sessions for this package.', 'wp-exam-success' ),
						$meta['min']
					),
					'error'
				);
				return false;
			}
		} else {
			if ( $count !== $meta['required'] ) {
				wc_add_notice(
					sprintf(
						/* translators: 1: selected count, 2: required count */
						__( 'Please select exactly %2$d sessions for this package (you selected %1$d).', 'wp-exam-success' ),
						$count,
						$meta['required']
					),
					'error'
				);
				return false;
			}
		}

		$session_ids = array_column( $selected, 'id' );
		$session_ids = array_map( 'intval', $session_ids );
		$session_ids = array_unique( $session_ids );

		foreach ( $session_ids as $session_id ) {
			$session = WPES_Sessions::get( $session_id );

			if ( ! $session || 'scheduled' !== $session->status ) {
				wc_add_notice(
					__( 'One of the selected sessions is no longer available. Please review your selection.', 'wp-exam-success' ),
					'error'
				);
				return false;
			}

			if ( ! WPES_Sessions::has_capacity( $session_id ) ) {
				wc_add_notice(
					__( 'One of the selected sessions has just reached capacity. Please choose another.', 'wp-exam-success' ),
					'error'
				);
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Attach session data to the cart item and create pending bookings.
	 *
	 * The frontend sends $_POST['wpes_sessions'] as a JSON-encoded string.
	 * We decode it, create pending bookings for each session, and store
	 * both the session data and booking IDs in the cart item.
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! self::is_package_product( $product_id ) ) {
			return $cart_item_data;
		}

		// Already reserved (e.g. cart_item_data passed into a nested add_to_cart).
		if ( ! empty( $cart_item_data['wpes_booking_ids'] ) ) {
			return $cart_item_data;
		}

		$selected = self::get_selected_sessions_from_request();
		if ( empty( $selected ) ) {
			return $cart_item_data;
		}

		$session_ids = array_column( $selected, 'id' );
		$session_ids = array_map( 'intval', $session_ids );
		$session_ids = array_unique( $session_ids );

		$booking_ids = array();

		foreach ( $session_ids as $sid ) {
			$bid = WPES_Bookings::reserve( $sid, array( 'product_id' => $product_id ) );
			if ( ! is_wp_error( $bid ) ) {
				$booking_ids[] = $bid;
			}
		}

		$cart_item_data['wpes_session_ids'] = $session_ids;
		$cart_item_data['wpes_sessions']    = $selected; // full objects for display
		$cart_item_data['wpes_booking_ids'] = $booking_ids;
		$cart_item_data['wpes_unique_key']  = md5( microtime() . wp_rand() );

		return $cart_item_data;
	}

	/**
	 * Decode the JSON session blob from the frontend request.
	 *
	 * @return array Array of session objects (each with id, title, etc.).
	 */
	protected static function get_selected_sessions_from_request() {
		if ( empty( $_POST['wpes_sessions'] ) ) {
			return array();
		}

		$raw = sanitize_text_field( wp_unslash( $_POST['wpes_sessions'] ) );
		$decoded = json_decode( rawurldecode( $_POST['wpes_sessions'] ), true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return $decoded;
	}

	/**
	 * Release pending bookings when a cart item is removed.
	 */
	public static function release_on_cart_remove( $cart_item_key, $cart ) {
		$item = $cart->removed_cart_contents[ $cart_item_key ] ?? ( $cart->cart_contents[ $cart_item_key ] ?? null );
		if ( empty( $item['wpes_booking_ids'] ) ) {
			return;
		}
		foreach ( (array) $item['wpes_booking_ids'] as $booking_id ) {
			WPES_Bookings::cancel( $booking_id );
		}
	}

	/**
	 * Display selected sessions in the cart / mini-cart.
	 */
	public static function display_cart_item_sessions( $item_data, $cart_item ) {
		if ( empty( $cart_item['wpes_sessions'] ) ) {
			return $item_data;
		}

		foreach ( (array) $cart_item['wpes_sessions'] as $session ) {
			if ( empty( $session['title'] ) || empty( $session['local_time'] ) ) {
				continue;
			}

			$label = sprintf(
				'%s (%s)',
				esc_html( $session['title'] ),
				esc_html( $session['level'] ?? '' )
			);

			// Store UTC in data-utc so the mini-cart can localize via JS if desired.
			$utc = ! empty( $session['starts_at_gmt'] )
				? esc_attr( gmdate( 'c', strtotime( $session['starts_at_gmt'] ) ) )
				: '';

			// Format Date Time to Human Readable Format
			$local_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $session['local_time'] ) );

			$item_data[] = array(
				'key'   => $label,
				'value' => '<span class="wpes-local-time" data-utc="' . $utc . '">' . esc_html( $local_time ) . '</span>',
			);
		}

		return $item_data;
	}

	/* ---------------------------------------------------------------
	 * Order: persist selection to order items, confirm/release bookings
	 * ------------------------------------------------------------- */

	public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['wpes_booking_ids'] ) ) {
			return;
		}

		$item->add_meta_data( '_wpes_session_ids', $values['wpes_session_ids'] );
		$item->add_meta_data( '_wpes_booking_ids', $values['wpes_booking_ids'] );

		// Order ID is often still 0 during this hook; link what we can and
		// finish linking in link_bookings_to_order() once the order is saved.
		$order_id = $order ? (int) $order->get_id() : 0;
		if ( ! $order_id ) {
			return;
		}

		self::update_bookings_for_order_item( $values['wpes_booking_ids'], $order, 0 );
	}

	/**
	 * After checkout, the order has a real ID — attach it (and line item IDs)
	 * to every WPES booking reserved in the cart.
	 *
	 * @param int|\WC_Order $order Order ID or order object.
	 */
	public static function link_bookings_to_order( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$booking_ids = $item->get_meta( '_wpes_booking_ids' );
			if ( empty( $booking_ids ) ) {
				continue;
			}
			self::update_bookings_for_order_item( $booking_ids, $order, (int) $item->get_id() );
		}
	}

	/**
	 * Write order_id / order_item_id / customer fields onto booking rows.
	 *
	 * @param array     $booking_ids Booking IDs.
	 * @param \WC_Order $order       Order.
	 * @param int       $item_id     Order item ID (0 if not yet known).
	 */
	protected static function update_bookings_for_order_item( $booking_ids, $order, $item_id = 0 ) {
		global $wpdb;
		$table    = WPES_DB::bookings_table();
		$order_id = (int) $order->get_id();
		if ( ! $order_id ) {
			return;
		}

		foreach ( (array) $booking_ids as $booking_id ) {
			$data   = array(
				'order_id'       => $order_id,
				'customer_email' => $order->get_billing_email(),
				'customer_name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'user_id'        => $order->get_customer_id() ?: null,
				'updated_at'     => WPES_DB::now_gmt(),
			);
			$format = array( '%d', '%s', '%s', '%d', '%s' );

			if ( $item_id ) {
				$data['order_item_id'] = $item_id;
				$format[]              = '%d';
			}

			$wpdb->update(
				$table,
				$data,
				array( 'id' => (int) $booking_id ),
				$format,
				array( '%d' )
			);
		}
	}

	public static function confirm_bookings_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Ensure order linkage is present even if checkout hooks were skipped.
		self::link_bookings_to_order( $order );

		foreach ( $order->get_items() as $item ) {
			$booking_ids = $item->get_meta( '_wpes_booking_ids' );
			if ( empty( $booking_ids ) ) {
				continue;
			}
			foreach ( (array) $booking_ids as $booking_id ) {
				WPES_Bookings::confirm( $booking_id );
			}
		}
	}

	public static function release_bookings_for_order( $order_id ) {
		WPES_Bookings::cancel_by_order( $order_id );
	}

	/**
	 * Order placed on-hold (e.g. offline/bank transfer payment pending):
	 * mark bookings as 'on-hold' so they hold their seat and show up in
	 * the admin "On-Hold" tab, without confirming them yet.
	 */
	public static function mark_bookings_on_hold_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		self::link_bookings_to_order( $order );
		WPES_Bookings::mark_on_hold_by_order( $order_id );
	}

	/**
	 * Show a small booking summary block on the WooCommerce order edit
	 * screen so admins can see which WPES bookings are tied to this order.
	 */
	public static function render_order_booking_summary( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$bookings = WPES_Bookings::for_order( $order->get_id() );
		if ( empty( $bookings ) ) {
			return;
		}

		$labels = array(
			'pending'   => __( 'Pending', 'wp-exam-success' ),
			'on-hold'   => __( 'On-Hold', 'wp-exam-success' ),
			'confirmed' => __( 'Confirmed', 'wp-exam-success' ),
			'cancelled' => __( 'Cancelled', 'wp-exam-success' ),
			'expired'   => __( 'Expired', 'wp-exam-success' ),
		);

		echo '<div class="wpes-order-booking-summary" style="clear:both;margin-top:1em;">';
		echo '<h3>' . esc_html__( 'Exam Success Bookings', 'wp-exam-success' ) . '</h3>';
		echo '<ul style="margin:0;">';
		foreach ( $bookings as $booking ) {
			$session = WPES_Sessions::get( $booking->session_id );
			$status  = $labels[ $booking->status ] ?? ucfirst( $booking->status );

			echo '<li>';
			printf(
				/* translators: 1: booking ID, 2: session/class title, 3: booking status */
				esc_html__( 'Booking #%1$s — %2$s — Status: %3$s', 'wp-exam-success' ),
				esc_html( $booking->id ),
				esc_html( $session ? $session->title : __( '(session deleted)', 'wp-exam-success' ) ),
				esc_html( $status )
			);
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Send session details email after order is paid.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function send_order_sessions_email( $order_id ) {
		if ( get_post_meta( $order_id, '_wpes_sessions_email_sent', true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$has_sessions = false;
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_meta( '_wpes_booking_ids' ) ) {
				$has_sessions = true;
				break;
			}
		}

		if ( ! $has_sessions ) {
			return;
		}

		if ( WPES_Emailer::send_order_sessions( $order ) ) {
			update_post_meta( $order_id, '_wpes_sessions_email_sent', 'yes' );
		}
	}
}