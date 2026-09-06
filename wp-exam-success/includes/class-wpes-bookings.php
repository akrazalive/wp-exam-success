<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking lifecycle: pending (reserved at add-to-cart) -> confirmed
 * (payment complete) -> cancelled/expired (released back to the pool).
 *
 * The one piece of code in this plugin that MUST be correct under
 * concurrency is reserve(): two customers hitting "add to cart" on the
 * last open seat at the same moment must not both succeed. We handle
 * this with an explicit transaction + `SELECT ... FOR UPDATE` on the
 * session row, which serializes concurrent reservations against the
 * same session on InnoDB.
 */
class WPES_Bookings {

	/**
	 * Attempt to reserve one seat in a session. Returns the new booking
	 * ID on success, or WP_Error( 'wpes_session_full' ) if no capacity
	 * remains at the moment of the lock.
	 */
	public static function reserve( $session_id, array $customer = array(), $reserved_minutes = 20 ) {
		global $wpdb;

		$sessions_table = WPES_DB::sessions_table();
		$bookings_table = WPES_DB::bookings_table();

		$wpdb->query( 'START TRANSACTION' );

		// Lock the session row so concurrent reservations against the
		// same session serialize instead of racing on the COUNT below.
		$session = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, max_attendees, status FROM {$sessions_table} WHERE id = %d FOR UPDATE", $session_id )
		);

		if ( ! $session || 'scheduled' !== $session->status ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'wpes_session_unavailable', __( 'This session is no longer available.', 'wp-exam-success' ) );
		}

		$booked = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table} WHERE session_id = %d AND status IN ('pending','confirmed','on-hold') AND (status IN ('confirmed','on-hold') OR reserved_until > %s)",
				$session_id,
				WPES_DB::now_gmt()
			)
		);

		if ( $booked >= (int) $session->max_attendees ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'wpes_session_full', __( 'This session just filled up. Please choose a different session.', 'wp-exam-success' ) );
		}

		$now            = WPES_DB::now_gmt();
		$reserved_until = gmdate( 'Y-m-d H:i:s', time() + ( $reserved_minutes * MINUTE_IN_SECONDS ) );

		$wpdb->insert(
			$bookings_table,
			array(
				'session_id'     => $session_id,
				'order_id'       => $customer['order_id'] ?? null,
				'order_item_id'  => $customer['order_item_id'] ?? null,
				'product_id'     => $customer['product_id'] ?? null,
				'user_id'        => $customer['user_id'] ?? null,
				'customer_name'  => $customer['customer_name'] ?? null,
				'customer_email' => $customer['customer_email'] ?? null,
				'status'         => 'pending',
				'reserved_until' => $reserved_until,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$booking_id = $wpdb->insert_id;
		$wpdb->query( 'COMMIT' );

		return $booking_id;
	}

	public static function confirm( $booking_id ) {
		global $wpdb;
		return $wpdb->update(
			WPES_DB::bookings_table(),
			array( 'status' => 'confirmed', 'reserved_until' => null, 'updated_at' => WPES_DB::now_gmt() ),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function confirm_by_order_item( $order_item_id ) {
		global $wpdb;
		return $wpdb->update(
			WPES_DB::bookings_table(),
			array( 'status' => 'confirmed', 'reserved_until' => null, 'updated_at' => WPES_DB::now_gmt() ),
			array( 'order_item_id' => $order_item_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function mark_on_hold_by_order( $order_id ) {
		global $wpdb;
		return $wpdb->update(
			WPES_DB::bookings_table(),
			array( 'status' => 'on-hold', 'reserved_until' => null, 'updated_at' => WPES_DB::now_gmt() ),
			array( 'order_id' => $order_id, 'status' => 'pending' ),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	public static function cancel( $booking_id ) {
		global $wpdb;
		return $wpdb->update(
			WPES_DB::bookings_table(),
			array( 'status' => 'cancelled', 'updated_at' => WPES_DB::now_gmt() ),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function cancel_by_order( $order_id ) {
		global $wpdb;
		return $wpdb->update(
			WPES_DB::bookings_table(),
			array( 'status' => 'cancelled', 'updated_at' => WPES_DB::now_gmt() ),
			array( 'order_id' => $order_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/** Cron target: release pending reservations whose hold window has lapsed (abandoned carts). */
	public static function release_expired() {
		global $wpdb;
		$table = WPES_DB::bookings_table();
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired', updated_at = %s WHERE status = 'pending' AND reserved_until < %s",
				WPES_DB::now_gmt(),
				WPES_DB::now_gmt()
			)
		);
	}

	public static function get_attendees_for_session( $session_id, $status = array( 'confirmed' ) ) {
		global $wpdb;
		$table        = WPES_DB::bookings_table();
		$placeholders = implode( ',', array_fill( 0, count( $status ), '%s' ) );
		$params       = array_merge( array( $session_id ), $status );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_id = %d AND status IN ({$placeholders}) ORDER BY created_at ASC",
				$params
			)
		);
	}

	public static function for_order( $order_id ) {
		global $wpdb;
		$table = WPES_DB::bookings_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ) );
	}

	/**
	 * Reporting query: bookings joined with session + class, filterable
	 * by class, date range, status, and free-text search on customer.
	 */
	public static function query_report( array $args = array() ) {
		global $wpdb;
		$bookings_table = WPES_DB::bookings_table();
		$sessions_table = WPES_DB::sessions_table();
		$classes_table  = WPES_DB::classes_table();

		list( $where_sql, $params ) = self::build_report_where( $args );

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$order_col = isset( $args['order_by'] ) ? sanitize_key( $args['order_by'] ) : 'created_at';
		$order_dir = ( isset( $args['order_dir'] ) && 'asc' === strtolower( $args['order_dir'] ) ) ? 'ASC' : 'DESC';
		$allowed   = array( 'created_at' => 'b.created_at', 'starts_at_gmt' => 's.starts_at_gmt', 'customer_name' => 'b.customer_name', 'status' => 'b.status' );
		$order_sql = $allowed[ $order_col ] ?? 'b.created_at';

		$sql = "
			SELECT b.*, s.title AS session_title, s.starts_at_gmt, s.ends_at_gmt, c.name AS class_name
			FROM {$bookings_table} b
			INNER JOIN {$sessions_table} s ON s.id = b.session_id
			INNER JOIN {$classes_table} c ON c.id = s.class_id
			WHERE {$where_sql}
			ORDER BY {$order_sql} {$order_dir}
			LIMIT %d OFFSET %d
		";

		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Count bookings for DataTables.
	 */
	public static function count_report( array $args = array() ) {
		global $wpdb;
		$bookings_table = WPES_DB::bookings_table();
		$sessions_table = WPES_DB::sessions_table();
		$classes_table  = WPES_DB::classes_table();

		list( $where_sql, $params ) = self::build_report_where( $args );

		$sql = "
			SELECT COUNT(*)
			FROM {$bookings_table} b
			INNER JOIN {$sessions_table} s ON s.id = b.session_id
			INNER JOIN {$classes_table} c ON c.id = s.class_id
			WHERE {$where_sql}
		";

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Manual enrollment — no order created.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $email      Customer email.
	 * @return int|WP_Error Booking ID.
	 */
	public static function manual_enroll( $session_id, $email ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'wpes_invalid_email', __( 'Invalid email address.', 'wp-exam-success' ) );
		}

		if ( ! WPES_Sessions::has_capacity( $session_id ) ) {
			return new WP_Error( 'wpes_session_full', __( 'This session is full.', 'wp-exam-success' ) );
		}

		$user = get_user_by( 'email', $email );
		$name = $user ? $user->display_name : '';

		$now = WPES_DB::now_gmt();
		$wpdb->insert(
			WPES_DB::bookings_table(),
			array(
				'session_id'     => $session_id,
				'user_id'        => $user ? $user->ID : null,
				'customer_name'  => $name,
				'customer_email' => $email,
				'status'         => 'confirmed',
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Build WHERE for report queries.
	 *
	 * @return array [ where_sql, params ]
	 */
	protected static function build_report_where( array $args ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['class_id'] ) ) {
			$where[]  = 's.class_id = %d';
			$params[] = (int) $args['class_id'];
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'b.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['status_in'] ) && is_array( $args['status_in'] ) ) {
			$statuses     = array_values( array_filter( array_map( 'sanitize_key', $args['status_in'] ) ) );
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$where[]      = "b.status IN ({$placeholders})";
			$params       = array_merge( $params, $statuses );
		}
		if ( ! empty( $args['status_not'] ) ) {
			$where[]  = 'b.status != %s';
			$params[] = $args['status_not'];
		}
		if ( ! empty( $args['status_not_in'] ) && is_array( $args['status_not_in'] ) ) {
			$statuses     = array_values( array_filter( array_map( 'sanitize_key', $args['status_not_in'] ) ) );
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$where[]      = "b.status NOT IN ({$placeholders})";
			$params       = array_merge( $params, $statuses );
		}
		if ( ! empty( $args['from_gmt'] ) ) {
			$where[]  = 's.starts_at_gmt >= %s';
			$params[] = $args['from_gmt'];
		}
		if ( ! empty( $args['to_gmt'] ) ) {
			$where[]  = 's.starts_at_gmt <= %s';
			$params[] = $args['to_gmt'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(b.customer_name LIKE %s OR b.customer_email LIKE %s OR c.name LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( implode( ' AND ', $where ), $params );
	}
}
