<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + recurrence generation for individual session occurrences.
 *
 * Design choice: recurring sessions are NOT computed on the fly from
 * an RRULE. Each occurrence is materialized as its own row at
 * creation time. This is deliberate:
 *   - each occurrence needs its own independent capacity count
 *   - each occurrence needs its own meeting link + send log
 *   - reporting/search stays a single flat SELECT instead of RRULE math
 * The tradeoff (bulk-editing a whole series requires updating N rows)
 * is handled by update_series() below.
 */
class WPES_Sessions {

	/**
	 * Available English CEFR levels.
	 *
	 * @return array<string, string> Level code => label
	 */
	public static function get_levels() {
		return apply_filters(
			'wpes_english_levels',
			array(
				'A1' => __( 'A1', 'wp-exam-success' ),
				'A2' => __( 'A2', 'wp-exam-success' ),
				'B1' => __( 'B1', 'wp-exam-success' ),
				'B2' => __( 'B2', 'wp-exam-success' ),
				'C1' => __( 'C1', 'wp-exam-success' ),
				'C2' => __( 'C2', 'wp-exam-success' ),
			)
		);
	}

	public static function get( $id ) {
		global $wpdb;
		$table = WPES_DB::sessions_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Create a single session, or a recurring series of them.
	 *
	 * @param array $args {
	 *   @type int    $class_id
	 *   @type string $title
	 *   @type string $starts_at_gmt   'Y-m-d H:i:s' in GMT
	 *   @type string $ends_at_gmt     'Y-m-d H:i:s' in GMT
	 *   @type string $source_timezone the tz the admin entered the time in, for reference only
	 *   @type int    $max_attendees
	 *   @type string $meeting_link
	 *   @type string $repeat          'none' | 'weekly'
	 *   @type int    $repeat_count    number of occurrences (including the first), only used if repeat != none
	 * }
	 * @return int[] array of inserted session IDs
	 */
	public static function create_series( array $args ) {
		global $wpdb;
		$table = WPES_DB::sessions_table();

		$repeat       = $args['repeat'] ?? 'none';
		$repeat_count = max( 1, (int) ( $args['repeat_count'] ?? 1 ) );
		$series_id    = ( 'none' === $repeat ) ? null : wp_generate_uuid4();

		$starts = new DateTime( $args['starts_at_gmt'], new DateTimeZone( 'UTC' ) );
		$ends   = new DateTime( $args['ends_at_gmt'], new DateTimeZone( 'UTC' ) );
		$duration_seconds = $ends->getTimestamp() - $starts->getTimestamp();

		if ( $duration_seconds <= 0 ) {
			return new WP_Error( 'wpes_invalid_times', __( 'Session end time must be after the start time.', 'wp-exam-success' ) );
		}

		$interval_days = ( 'weekly' === $repeat ) ? 7 : 0;
		$count         = ( 'none' === $repeat ) ? 1 : $repeat_count;

		$now         = WPES_DB::now_gmt();
		$inserted_ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$occurrence_start = clone $starts;
			if ( $interval_days > 0 && $i > 0 ) {
				$occurrence_start->modify( '+' . ( $i * $interval_days ) . ' days' );
			}
			$occurrence_end = clone $occurrence_start;
			$occurrence_end->modify( '+' . $duration_seconds . ' seconds' );

			$recurrence_label = 'none' === $repeat
				? null
				: sprintf(
					/* translators: 1: occurrence number, 2: total occurrences, 3: repeat frequency */
					__( 'Occurrence %1$d of %2$d (%3$s)', 'wp-exam-success' ),
					$i + 1,
					$count,
					$repeat
				);

			$wpdb->insert(
				$table,
				array(
					'class_id'         => (int) $args['class_id'],
					'series_id'        => $series_id,
					'title'            => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : null,
					'description'      => isset( $args['description'] ) ? wp_kses_post( $args['description'] ) : null,
					'level'            => isset( $args['level'] ) ? sanitize_text_field( $args['level'] ) : null,
					'starts_at_gmt'    => $occurrence_start->format( 'Y-m-d H:i:s' ),
					'ends_at_gmt'      => $occurrence_end->format( 'Y-m-d H:i:s' ),
					'source_timezone'  => sanitize_text_field( $args['source_timezone'] ?? 'UTC' ),
					'max_attendees'    => (int) $args['max_attendees'],
					'meeting_link'     => isset( $args['meeting_link'] ) ? esc_url_raw( $args['meeting_link'] ) : null,
					'status'           => 'scheduled',
					'recurrence_label' => $recurrence_label,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			$inserted_ids[] = $wpdb->insert_id;
		}

		return $inserted_ids;
	}

	public static function update( $id, array $fields ) {
		global $wpdb;
		$table       = WPES_DB::sessions_table();
		$allowed     = array( 'title', 'description', 'level', 'starts_at_gmt', 'ends_at_gmt', 'source_timezone', 'max_attendees', 'meeting_link', 'status', 'assigned_teacher_id' );
		$data        = array();
		$format      = array();

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				if ( 'description' === $key ) {
					$data[ $key ] = wp_kses_post( $fields[ $key ] );
				} elseif ( 'level' === $key ) {
					$data[ $key ] = sanitize_text_field( $fields[ $key ] );
				} elseif ( 'assigned_teacher_id' === $key ) {
					$data[ $key ] = $fields[ $key ] ? (int) $fields[ $key ] : null;
				} else {
					$data[ $key ] = $fields[ $key ];
				}
				$format[] = in_array( $key, array( 'max_attendees', 'assigned_teacher_id' ), true ) ? '%d' : '%s';
			}
		}
		if ( empty( $data ) ) {
			return false;
		}

		// Stamp when a teacher assignment actually changes, for the audit
		// trail, and keep confirmation_state consistent with whichever
		// path assigned it (manual admin edit here, or the automatic
		// Accept-Link acceptance in WPES_Teacher_Invites::handle_accept()).
		if ( array_key_exists( 'assigned_teacher_id', $data ) ) {
			$data['teacher_assigned_at'] = $data['assigned_teacher_id'] ? WPES_DB::now_gmt() : null;
			$data['confirmation_state']  = $data['assigned_teacher_id'] ? 'confirmed' : 'pending';
			$format[]                    = '%s';
			$format[]                    = '%s';
		}

		$data['updated_at'] = WPES_DB::now_gmt();
		$format[]            = '%s';

		return $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/** Cancel every future, not-yet-started occurrence in a series (e.g. "cancel all remaining Mondays"). */
	public static function cancel_series_future( $series_id ) {
		global $wpdb;
		$table = WPES_DB::sessions_table();
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'cancelled', updated_at = %s WHERE series_id = %s AND starts_at_gmt > %s",
				WPES_DB::now_gmt(),
				$series_id,
				WPES_DB::now_gmt()
			)
		);
	}

	/** Bulk archive (cancel) sessions by ID. */
	public static function bulk_archive( array $ids ) {
		global $wpdb;
		$table = WPES_DB::sessions_table();
		$ids   = array_map( 'intval', $ids );
		$ids   = array_filter( $ids );
		if ( empty( $ids ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( 'cancelled', WPES_DB::now_gmt() ), $ids );
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
				$params
			)
		);
	}

	/**
	 * Count sessions matching query args (for DataTables).
	 */
	public static function count_query( array $args = array() ) {
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$classes_table  = WPES_DB::classes_table();

		list( $where_sql, $params ) = self::build_query_where( $args, 's', 'c' );

		$sql = "SELECT COUNT(*) FROM {$sessions_table} s INNER JOIN {$classes_table} c ON c.id = s.class_id WHERE {$where_sql}";
		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Build WHERE clause shared by query() and count_query().
	 *
	 * @return array [ where_sql, params ]
	 */
	protected static function build_query_where( array $args, $s_alias = 's', $c_alias = 'c' ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['class_id'] ) ) {
			$where[]  = "{$s_alias}.class_id = %d";
			$params[] = (int) $args['class_id'];
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = "{$s_alias}.status = %s";
			$params[] = $args['status'];
		}
		if ( ! empty( $args['from_gmt'] ) ) {
			$where[]  = "{$s_alias}.starts_at_gmt >= %s";
			$params[] = $args['from_gmt'];
		}
		if ( ! empty( $args['to_gmt'] ) ) {
			$where[]  = "{$s_alias}.starts_at_gmt <= %s";
			$params[] = $args['to_gmt'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = "({$s_alias}.title LIKE %s OR {$c_alias}.name LIKE %s OR {$s_alias}.level LIKE %s)";
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['level'] ) ) {
			$where[]  = "{$s_alias}.level = %s";
			$params[] = $args['level'];
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * List sessions with computed capacity, for admin + frontend picker.
	 * $args: class_id, status, from_gmt, to_gmt, only_with_capacity (bool), search, per_page, page
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$bookings_table = WPES_DB::bookings_table();
		$classes_table  = WPES_DB::classes_table();
		$teachers_table = WPES_DB::teachers_table();

		list( $where_sql, $params ) = self::build_query_where( $args, 's', 'c' );

		// Client-reported bug (2026-09-12): see the matching note on the
		// subquery below — this "now" value fills the placeholder there,
		// and must be first in $params since that subquery appears before
		// $where_sql in the query text.
		$params = array_merge( array( WPES_DB::now_gmt() ), $params );

		if ( ! empty( $args['session_ids'] ) && is_array( $args['session_ids'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $args['session_ids'] ), '%d' ) );
			$where_sql   .= " AND s.id IN ({$placeholders})";
			foreach ( $args['session_ids'] as $sid ) {
				$params[] = (int) $sid;
			}
		}

		$having_sql = '';
		if ( ! empty( $args['only_with_capacity'] ) ) {
			$having_sql = 'HAVING (s.max_attendees - booked) > 0';
		}

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		// Backend table sorting (Final Acceptance review, 2026-09-11): only
		// honored when explicitly requested (the admin Sessions table) —
		// every other caller (frontend session picker, etc.) passes no
		// order_by and keeps the original starts_at_gmt ASC default.
		$allowed   = array(
			'class_name'    => 'c.name',
			'title'         => 's.title',
			'starts_at_gmt' => 's.starts_at_gmt',
			'teacher_name'  => 't.name',
			'status'        => 's.status',
		);
		$order_col = isset( $args['order_by'] ) && isset( $allowed[ $args['order_by'] ] ) ? $allowed[ $args['order_by'] ] : 's.starts_at_gmt';
		$order_dir = ( isset( $args['order_dir'] ) && 'desc' === strtolower( $args['order_dir'] ) ) ? 'DESC' : 'ASC';

		// Client-reported bug (2026-09-12): "booked" here previously
		// excluded 'on-hold' bookings (see get_calendar()'s docblock for
		// the full explanation) — fixed to match WPES_Bookings::reserve()'s
		// own definition of "occupies a seat" exactly, so this table's
		// numbers and the only_with_capacity picker (WPES_My_Account's
		// replacement-credit session list) can't disagree with what
		// checkout will actually allow.
		$sql = "
			SELECT s.*, c.name AS class_name, t.name AS teacher_name,
				COALESCE(b.booked, 0) AS booked,
				(s.max_attendees - COALESCE(b.booked, 0)) AS remaining
			FROM {$sessions_table} s
			INNER JOIN {$classes_table} c ON c.id = s.class_id
			LEFT JOIN {$teachers_table} t ON t.id = s.assigned_teacher_id
			LEFT JOIN (
				SELECT session_id, COUNT(*) AS booked
				FROM {$bookings_table}
				WHERE status IN ('confirmed','on-hold') OR ( status = 'pending' AND reserved_until > %s )
				GROUP BY session_id
			) b ON b.session_id = s.id
			WHERE {$where_sql}
			{$having_sql}
			ORDER BY {$order_col} {$order_dir}
			LIMIT %d OFFSET %d
		";

		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Get a single session with computed capacity (same row shape as query()).
	 *
	 * @param int $id Session ID.
	 * @return object|null
	 */
	public static function get_with_capacity( $id ) {
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$bookings_table = WPES_DB::bookings_table();
		$classes_table  = WPES_DB::classes_table();

		// Client-reported bug (2026-09-12): see get_calendar()'s docblock —
		// same on-hold undercount, fixed the same way here.
		$sql = "
			SELECT s.*, c.name AS class_name,
				COALESCE(b.booked, 0) AS booked,
				(s.max_attendees - COALESCE(b.booked, 0)) AS remaining
			FROM {$sessions_table} s
			INNER JOIN {$classes_table} c ON c.id = s.class_id
			LEFT JOIN (
				SELECT session_id, COUNT(*) AS booked
				FROM {$bookings_table}
				WHERE status IN ('confirmed','on-hold') OR ( status = 'pending' AND reserved_until > %s )
				GROUP BY session_id
			) b ON b.session_id = s.id
			WHERE s.id = %d
		";

		return $wpdb->get_row( $wpdb->prepare( $sql, WPES_DB::now_gmt(), (int) $id ) );
	}

	public static function get_calendar( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'start_gmt' => '',
			'end_gmt'   => '',
			'class_id'  => 0,
			'level'     => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$table     = WPES_DB::sessions_table();
		$bookings  = WPES_DB::bookings_table();
		$classes_t = $wpdb->prefix . 'wpes_classes';

		$where = array( "s.status = 'scheduled'", "c.status = 'active'" );
		$prepare = array();

		// Date range (UTC)
		if ( ! empty( $args['start_gmt'] ) && ! empty( $args['end_gmt'] ) ) {
			$where[]   = 's.starts_at_gmt >= %s AND s.starts_at_gmt < %s';
			$prepare[] = $args['start_gmt'];
			$prepare[] = $args['end_gmt'];
		}

		// Optional class filter
		if ( ! empty( $args['class_id'] ) ) {
			$where[]   = 's.class_id = %d';
			$prepare[] = $args['class_id'];
		}

		// Optional level filter
		if ( '' !== $args['level'] ) {
			$where[]   = 's.level = %s';
			$prepare[] = $args['level'];
		}

		$where_sql = implode( ' AND ', $where );

		// Client-reported bug (2026-09-12): "9/10 shown, but this session
		// just filled up" at checkout. Root cause: this calendar feed (the
		// public booking page's own data source) computed "remaining" from
		// confirmed_count + pending_count only — it never counted 'on-hold'
		// bookings (a package order authorized and awaiting its first
		// session's payment capture still occupies a real seat) — while
		// WPES_Bookings::reserve(), the actual gate at checkout, always
		// did. A session with on-hold bookings could show more availability
		// here than reserve() would actually allow, and did — the display
		// wasn't wrong about seats sold, it just wasn't counting all of
		// them. on_hold_count is now added and subtracted the same as
		// confirmed_count, so this matches reserve()'s definition exactly
		// (pending_count here still counts a cart hold regardless of
		// whether it has since expired, same as before this fix — the
		// under-count was specifically the missing on-hold bookings, not
		// pending's own accounting, so that part is left unchanged).
		$sql = "SELECT
				s.*,
				c.name AS class_name,
				c.description AS class_description,
				COALESCE(b.confirmed_count, 0) AS confirmed_count,
				COALESCE(b.pending_count, 0) AS pending_count,
				COALESCE(b.on_hold_count, 0) AS on_hold_count,
				(s.max_attendees - COALESCE(b.confirmed_count, 0) - COALESCE(b.pending_count, 0) - COALESCE(b.on_hold_count, 0)) AS remaining
			FROM {$table} s
			INNER JOIN {$classes_t} c ON c.id = s.class_id
			LEFT JOIN (
				SELECT session_id,
					SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
					SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
					SUM(CASE WHEN status = 'on-hold' THEN 1 ELSE 0 END) AS on_hold_count
				FROM {$bookings}
				GROUP BY session_id
			) b ON b.session_id = s.id
			WHERE {$where_sql}
			ORDER BY s.starts_at_gmt ASC";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$prepare ) );

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'hydrate_calendar_row' ), $rows );
	}

	/**
	 * Hydration for calendar rows (includes computed availability).
	 */
	protected static function hydrate_calendar_row( $row ) {
		$row = self::hydrate_row( $row );
		$row->confirmed_count = (int) $row->confirmed_count;
		$row->pending_count   = (int) $row->pending_count;
		$row->on_hold_count   = (int) $row->on_hold_count;
		$row->remaining       = (int) $row->remaining;
		return $row;
	}

	/**
	 * Basic hydration for a raw DB row.
	 */
	protected static function hydrate_row( $row ) {
		$row->id             = (int) $row->id;
		$row->class_id       = (int) $row->class_id;
		$row->max_attendees  = (int) $row->max_attendees;
		$row->starts_at_gmt  = $row->starts_at_gmt;
		$row->ends_at_gmt    = $row->ends_at_gmt;
		return $row;
	}

	/**
	 * Get the number of remaining spots for a session.
	 *
	 * @param int $session_id Session ID.
	 * @return int
	 */
	public static function get_remaining( $session_id ) {
		global $wpdb;

		$session = self::get( $session_id );
		if ( ! $session ) {
			return 0;
		}

		// Client-reported bug (2026-09-12): this feeds has_capacity(), the
		// pre-checkout validation gate (WPES_WooCommerce::validate_add_to_
		// cart()) — it previously excluded 'on-hold' bookings the same way
		// the calendar display did (see get_calendar()'s docblock above),
		// so a customer could pass this check only to have the stricter,
		// correct WPES_Bookings::reserve() row-locked count reject them a
		// moment later with "this session just filled up". Now matches
		// reserve()'s own definition of "occupies a seat" exactly:
		// confirmed/on-hold always count, pending only until its temporary
		// cart-hold expires.
		$occupied = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . WPES_DB::bookings_table() . "
				 WHERE session_id = %d
				   AND ( status IN ('confirmed','on-hold') OR ( status = 'pending' AND reserved_until > %s ) )",
				$session_id,
				WPES_DB::now_gmt()
			)
		);

		return max( 0, $session->max_attendees - $occupied );
	}

	/**
	 * Check if a session has capacity.
	 *
	 * @param int $session_id Session ID.
	 * @param int $needed     Number of spots needed. Default 1.
	 * @return bool
	 */
	public static function has_capacity( $session_id, $needed = 1 ) {
		return self::get_remaining( $session_id ) >= $needed;
	}

	public static function count_remaining_capacity( $session_id ) {
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$bookings_table = WPES_DB::bookings_table();

		// Client-reported bug (2026-09-12): see get_calendar()'s docblock —
		// same on-hold undercount, fixed the same way here.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.max_attendees,
					(SELECT COUNT(*) FROM {$bookings_table} WHERE session_id = s.id AND ( status IN ('confirmed','on-hold') OR ( status = 'pending' AND reserved_until > %s ) )) AS booked
				 FROM {$sessions_table} s WHERE s.id = %d",
				WPES_DB::now_gmt(),
				$session_id
			)
		);

		if ( ! $row ) {
			return 0;
		}

		return max( 0, (int) $row->max_attendees - (int) $row->booked );
	}

	/**
	 * Count upcoming, still-scheduled sessions whose confirmed attendee
	 * count has not yet reached the minimum participant setting —
	 * dashboard "at a glance" stat for the Developer Spec §9 minimum
	 * check. Mirrors the same 'confirmed' definition
	 * WPES_Teacher_Invites::count_confirmed_attendees() uses.
	 *
	 * @param int $min_participants
	 * @return int
	 */
	public static function count_below_minimum( $min_participants ) {
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$bookings_table = WPES_DB::bookings_table();

		$sql = "SELECT COUNT(*) FROM (
				SELECT s.id, COALESCE(b.confirmed_count, 0) AS confirmed_count
				FROM {$sessions_table} s
				LEFT JOIN (
					SELECT session_id, COUNT(*) AS confirmed_count
					FROM {$bookings_table}
					WHERE status = 'confirmed'
					GROUP BY session_id
				) b ON b.session_id = s.id
				WHERE s.status = 'scheduled' AND s.starts_at_gmt >= %s
			) below
			WHERE below.confirmed_count < %d";

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, WPES_DB::now_gmt(), max( 1, (int) $min_participants ) ) );
	}
}
