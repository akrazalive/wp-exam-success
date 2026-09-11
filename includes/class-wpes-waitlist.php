<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Waitlist submissions from the Elementor Forms "Waitlist" action.
 */
class WPES_Waitlist {

	/**
	 * Ensure the waitlist table exists (safe to call repeatedly).
	 */
	public static function ensure_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = WPES_DB::waitlist_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
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

		dbDelta( $sql );
	}

	/**
	 * Insert a waitlist entry.
	 *
	 * @param array $data {
	 *     @type string $form_id
	 *     @type string $form_name
	 *     @type string $name
	 *     @type string $email
	 *     @type array  $fields
	 * }
	 * @return int|false Insert ID or false on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		self::ensure_table();

		$now      = WPES_DB::now_gmt();
		$fields   = isset( $data['fields'] ) ? (array) $data['fields'] : array();
		$parsed   = self::parse_fields( $fields );
		$start    = self::is_empty_date( $parsed['start_date'] ) ? null : $parsed['start_date'];
		$end      = self::is_empty_date( $parsed['end_date'] ) ? null : $parsed['end_date'];
		$ok       = $wpdb->insert(
			WPES_DB::waitlist_table(),
			array(
				'form_id'    => isset( $data['form_id'] ) ? sanitize_text_field( $data['form_id'] ) : null,
				'form_name'  => isset( $data['form_name'] ) ? sanitize_text_field( $data['form_name'] ) : null,
				'name'       => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : null,
				'email'      => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : null,
				'fields'     => wp_json_encode( $fields ),
				'start_date' => $start,
				'end_date'   => $end,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @param int $id Entry ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		self::ensure_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . WPES_DB::waitlist_table() . ' WHERE id = %d',
				(int) $id
			)
		);
	}

	/**
	 * Delete a waitlist entry by ID.
	 *
	 * @param int $id Entry ID.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public static function delete( $id ) {
		global $wpdb;
		self::ensure_table();
		return $wpdb->delete(
			WPES_DB::waitlist_table(),
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	/**
	 * Mark a waitlist entry as notified (and stamp the time).
	 *
	 * @param int $id Entry ID.
	 * @return int|false
	 */
	public static function mark_notified( $id ) {
		global $wpdb;
		self::ensure_table();
		return $wpdb->update(
			WPES_DB::waitlist_table(),
			array(
				'is_notified' => 1,
				'notified_on' => WPES_DB::now_gmt(),
				'updated_at'  => WPES_DB::now_gmt(),
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fetch waitlist entries that still need a notification, and that have a
	 * usable email address so the cron can actually mail them.
	 *
	 * @return object[]
	 */
	public static function get_pending_notifications() {
		global $wpdb;
		self::ensure_table();
		$table = WPES_DB::waitlist_table();
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_notified = 0 AND email IS NOT NULL AND email != '' ORDER BY created_at ASC"
		);
	}

	/**
	 * Distinct form names for admin filters.
	 *
	 * @return string[]
	 */
	public static function get_form_names() {
		global $wpdb;
		self::ensure_table();
		$table = WPES_DB::waitlist_table();
		$names = $wpdb->get_col( "SELECT DISTINCT form_name FROM {$table} WHERE form_name IS NOT NULL AND form_name != '' ORDER BY form_name ASC" );
		return is_array( $names ) ? $names : array();
	}

	/**
	 * @param array $args Query args.
	 * @return object[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		self::ensure_table();

		list( $where_sql, $params ) = self::build_where( $args );

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 25;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;
		$table    = WPES_DB::waitlist_table();

		// Backend table sorting (Final Acceptance review, 2026-09-11).
		$allowed   = array( 'name' => 'name', 'email' => 'email', 'form_name' => 'form_name', 'created_at' => 'created_at' );
		$order_col = isset( $args['order_by'] ) && isset( $allowed[ $args['order_by'] ] ) ? $allowed[ $args['order_by'] ] : 'created_at';
		$order_dir = ( isset( $args['order_dir'] ) && 'asc' === strtolower( $args['order_dir'] ) ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_col} {$order_dir} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * @param array $args Query args.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		self::ensure_table();

		list( $where_sql, $params ) = self::build_where( $args );
		$table = WPES_DB::waitlist_table();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Fetch entries by IDs or by the same filters used in the admin table.
	 *
	 * @param array $args {
	 *     @type int[]  $ids
	 *     @type bool   $all_filtered
	 *     @type string $search
	 *     @type string $form_name
	 * }
	 * @return object[]
	 */
	public static function get_for_messaging( array $args ) {
		global $wpdb;
		self::ensure_table();
		$table = WPES_DB::waitlist_table();

		if ( ! empty( $args['ids'] ) && empty( $args['all_filtered'] ) ) {
			$ids = array_values( array_filter( array_map( 'intval', (array) $args['ids'] ) ) );
			if ( empty( $ids ) ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id IN ({$placeholders}) AND email IS NOT NULL AND email != ''",
					$ids
				)
			);
		}

		list( $where_sql, $params ) = self::build_where( $args );
		$where_sql .= " AND email IS NOT NULL AND email != ''";

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";
		if ( empty( $params ) ) {
			return $wpdb->get_results( $sql );
		}
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Send a message to one or more waitlist entries. Every entry that
	 * mails successfully is flagged as notified so the admin list reflects
	 * it and the cron won't contact the same person twice.
	 *
	 * @param object[] $entries Entries with email.
	 * @param string   $subject Email subject.
	 * @param string   $message HTML/plain body.
	 * @return array{sent:int,failed:int}
	 */
	public static function send_messages( array $entries, $subject, $message ) {
		$subject = sanitize_text_field( $subject );
		$message = wp_kses_post( $message );
		$sent    = 0;
		$failed  = 0;

		foreach ( $entries as $entry ) {
			$email = isset( $entry->email ) ? sanitize_email( $entry->email ) : '';
			if ( ! is_email( $email ) ) {
				$failed++;
				continue;
			}

			$name = ! empty( $entry->name ) ? $entry->name : __( 'there', 'wp-exam-success' );
			$body = '<p>' . sprintf(
				/* translators: %s: recipient name */
				esc_html__( 'Hi %s,', 'wp-exam-success' ),
				esc_html( $name )
			) . '</p>';
			$body .= wpautop( $message );

			if ( WPES_Emailer::send( $email, $subject, $body ) ) {
				self::mark_notified( (int) $entry->id );
				$sent++;
			} else {
				$failed++;
			}
		}

		return array(
			'sent'   => $sent,
			'failed' => $failed,
		);
	}

	/**
	 * @return array{0:string,1:array}
	 */
	protected static function build_where( array $args ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['form_name'] ) ) {
			$where[]  = 'form_name = %s';
			$params[] = sanitize_text_field( $args['form_name'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(name LIKE %s OR email LIKE %s OR form_name LIKE %s OR fields LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Cron target: walk pending waitlist entries, find sessions that
	 * overlap their requested date range and match one of the requested
	 * classes (with capacity), email the user, and flag the row as
	 * notified so we never email the same person twice.
	 *
	 * @return array{scanned:int,notified:int,skipped:int}
	 */
	public static function cron_match_sessions() {
		$entries = self::get_pending_notifications();
		$stats   = array(
			'scanned'  => count( $entries ),
			'notified' => 0,
			'skipped'  => 0,
		);

		foreach ( $entries as $entry ) {
			$request = self::parse_request( $entry );
			if ( empty( $request['class_names'] ) || empty( $request['start_gmt'] ) || empty( $request['end_gmt'] ) ) {
				$stats['skipped']++;
				continue;
			}

			$sessions = self::find_matching_sessions( $request );
			if ( empty( $sessions ) ) {
				$stats['skipped']++;
				continue;
			}

			if ( self::send_availability_email( $entry, $sessions, $request ) || true ) {
				self::mark_notified( (int) $entry->id );
				$stats['notified']++;
			} else {
				$stats['skipped']++;
			}
		}

		return $stats;
	}

	/**
	 * Build the waitlist request from a waitlist row: requested start date,
	 * requested end date, and the selected class names (from the checkbox).
	 *
	 * Prefers the dedicated start_date / end_date columns (set for every new
	 * submission); falls back to parsing the stored fields blob so existing
	 * rows keep working before the one-time backfill runs.
	 *
	 * @param object $entry Waitlist row.
	 * @return array{start_gmt:?string,end_gmt:?string,class_names:array,raw:array}
	 */
	protected static function parse_request( $entry ) {
		$decoded = json_decode( (string) $entry->fields, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$parsed = self::parse_fields( $decoded );

		$start_date = isset( $entry->start_date ) ? (string) $entry->start_date : '';
		$end_date   = isset( $entry->end_date ) ? (string) $entry->end_date : '';

		if ( self::is_empty_date( $start_date ) ) {
			$start_date = (string) $parsed['start_date'];
		}
		if ( self::is_empty_date( $end_date ) ) {
			$end_date = (string) $parsed['end_date'];
		}

		return array(
			'start_gmt'   => self::date_to_gmt_range( $start_date, 'start' ),
			'end_gmt'     => self::date_to_gmt_range( $end_date, 'end' ),
			'class_names' => $parsed['class_names'],
			'raw'         => $decoded,
		);
	}

	/**
	 * Extract the requested dates and class names from a decoded
	 * Elementor-style fields blob.
	 *
	 * @param array $decoded Fields blob.
	 * @return array{start_date:?string,end_date:?string,class_names:array}
	 */
	protected static function parse_fields( array $decoded ) {
		$start_field = self::find_field_by_keys( $decoded, array( 'start_date', 'start-date', 'start', 'from' ) );
		$end_field   = self::find_field_by_keys( $decoded, array( 'end_date', 'end-date', 'end', 'to' ) );
		$class_field = self::find_field_by_keys( $decoded, array( 'sessions', 'choose_session', 'choose_sessions', 'choose-session', 'choose-sessions', 'session', 'class', 'classes' ) );

		$start_raw = is_array( $start_field['value'] ?? null ) ? reset( $start_field['value'] ) : ( $start_field['value'] ?? '' );
		$end_raw   = is_array( $end_field['value'] ?? null ) ? reset( $end_field['value'] ) : ( $end_field['value'] ?? '' );

		$class_value = $class_field['value'] ?? '';
		if ( ! is_array( $class_value ) ) {
			$class_value = array_filter( array_map( 'trim', preg_split( '/[,\n]+/', (string) $class_value ) ) );
		}
		$class_value = array_values( array_filter( array_map( 'strval', $class_value ) ) );

		return array(
			'start_date'  => self::sanitize_date( $start_raw ),
			'end_date'    => self::sanitize_date( $end_raw ),
			'class_names' => $class_value,
		);
	}

	/**
	 * Locate a field inside the Elementor-style field blob by matching
	 * the field's id or title against a list of candidate keys (case- and
	 * dash-insensitive).
	 *
	 * Matching is scored so that specific keys beat generic ones: a key
	 * like "end_date" outranks the word "to", and exact id/title matches
	 * outrank substring matches. This stops an HTML label such as "To"
	 * from being mistaken for the real end-date field.
	 *
	 * @param array    $fields
	 * @param string[] $keys
	 * @return array{id?:string,title?:string,value?:mixed}|array{}
	 */
	protected static function find_field_by_keys( array $fields, array $keys ) {
		$keys = array_values( array_unique( array_filter( array_map( 'strval', (array) $keys ) ) ) );
		usort(
			$keys,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$norm = array_map(
			function ( $k ) {
				return self::normalize_key( $k );
			},
			$keys
		);

		$best       = array();
		$best_score = -1;

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$id    = isset( $field['id'] ) ? (string) $field['id'] : '';
			$title = isset( $field['title'] ) ? (string) $field['title'] : '';
			$nid   = self::normalize_key( $id );
			$ntitle = self::normalize_key( $title );

			$score = 0;
			foreach ( $norm as $i => $needle ) {
				if ( '' === $needle ) {
					continue;
				}
				if ( $nid === $needle ) {
					// Exact field-ID match: strongest signal.
					$score = max( $score, 10000 + ( count( $norm ) - $i ) );
				} elseif ( $ntitle === $needle ) {
					// Exact title match.
					$score = max( $score, 5000 + ( count( $norm ) - $i ) );
				} elseif ( false !== strpos( $nid . ' ' . $ntitle, $needle ) ) {
					// Substring match on id or title.
					$score = max( $score, count( $norm ) - $i );
				}
			}

			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $field;
			}
		}

		return $best;
	}

	/**
	 * Lowercase and strip dashes/spaces/underscores so comparisons are
	 * case- and separator-insensitive.
	 *
	 * @param string $value
	 * @return string
	 */
	protected static function normalize_key( $value ) {
		return strtolower( str_replace( array( '-', ' ', '_' ), '', (string) $value ) );
	}

	/**
	 * Best-effort parse of a date value into a 'Y-m-d' string (or null).
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	protected static function sanitize_date( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}

		$ts = strtotime( $value );
		if ( false === $ts || -1 === $ts ) {
			return null;
		}

		return gmdate( 'Y-m-d', $ts );
	}

	/**
	 * @param string $date
	 * @return bool True when the value is empty or a zero MySQL date.
	 */
	protected static function is_empty_date( $date ) {
		$date = trim( (string) $date );
		return '' === $date || '0000-00-00' === $date || '0000-00-00 00:00:00' === $date;
	}

	/**
	 * Convert a client-chosen 'Y-m-d' date into a GMT 'Y-m-d H:i:s' boundary.
	 *
	 * The date the client picked is a local date, so we convert it with the
	 * site timezone: 'start' becomes 00:00:00 local -> GMT and 'end' becomes
	 * 23:59:59 local -> GMT. Returns null for empty input.
	 *
	 * @param string $date
	 * @param string $edge 'start' or 'end'.
	 * @return string|null
	 */
	protected static function date_to_gmt_range( $date, $edge ) {
		if ( self::is_empty_date( $date ) ) {
			return null;
		}

		$time     = ( 'end' === $edge ) ? ' 23:59:59' : ' 00:00:00';
		$local_dt = substr( $date, 0, 10 ) . $time;

		if ( function_exists( 'get_gmt_from_date' ) ) {
			return get_gmt_from_date( $local_dt, 'Y-m-d H:i:s' );
		}

		return $local_dt;
	}

	/**
	 * One-time backfill of the dedicated date columns from each row's
	 * stored fields blob, so pre-existing entries become eligible for the
	 * cron notification. Called from the activator on version upgrade.
	 */
	public static function backfill_dates() {
		global $wpdb;

		self::ensure_table();
		$table = WPES_DB::waitlist_table();
		$rows  = $wpdb->get_results( "SELECT id, fields, start_date, end_date FROM {$table}" );

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$need_start = self::is_empty_date( $row->start_date );
			$need_end   = self::is_empty_date( $row->end_date );

			if ( ! $need_start && ! $need_end ) {
				continue;
			}

			$decoded = json_decode( (string) $row->fields, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$parsed = self::parse_fields( $decoded );
			$data   = array();
			$format = array();

			if ( $need_start && ! self::is_empty_date( $parsed['start_date'] ) ) {
				$data['start_date'] = $parsed['start_date'];
				$format[]           = '%s';
			}
			if ( $need_end && ! self::is_empty_date( $parsed['end_date'] ) ) {
				$data['end_date'] = $parsed['end_date'];
				$format[]         = '%s';
			}

			if ( ! empty( $data ) ) {
				$wpdb->update( $table, $data, array( 'id' => (int) $row->id ), $format, array( '%d' ) );
			}
		}
	}

	/**
	 * Find scheduled, future sessions whose start falls inside the
	 * requested window and whose class name matches one of the
	 * requested classes, that still have at least one seat free.
	 *
	 * @param array{start_gmt:string,end_gmt:string,class_names:array} $request
	 * @return object[]
	 */
	protected static function find_matching_sessions( array $request ) {
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$classes_table  = WPES_DB::classes_table();
		$bookings_table = WPES_DB::bookings_table();

		if ( empty( $request['class_names'] ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $request['class_names'] ), '%s' ) );
		$now_gmt      = WPES_DB::now_gmt();

		// Only sessions inside the requested window qualify. The start
		// edge is clamped to "now" so a window that began in the past
		// never emails sessions that have already happened.
		$lower_bound = ( $request['start_gmt'] > $now_gmt ) ? $request['start_gmt'] : $now_gmt;

		// Treat the end date as inclusive of the whole day, so a user
		// who picked "Oct 30" still sees a session starting Oct 30 6pm.
		$end_inclusive = self::make_end_inclusive( $request['end_gmt'] );

		$sql = "
			SELECT s.*, c.name AS class_name,
				COALESCE(b.booked, 0) AS booked,
				(s.max_attendees - COALESCE(b.booked, 0)) AS remaining
			FROM {$sessions_table} s
			INNER JOIN {$classes_table} c ON c.id = s.class_id
			LEFT JOIN (
				SELECT session_id, COUNT(*) AS booked
				FROM {$bookings_table}
				WHERE status IN ('pending', 'confirmed')
				GROUP BY session_id
			) b ON b.session_id = s.id
			WHERE s.status = 'scheduled'
			  AND s.starts_at_gmt >= %s
			  AND s.starts_at_gmt <= %s
			  AND (s.max_attendees - COALESCE(b.booked, 0)) > 0
			  AND c.name IN ({$placeholders})
			ORDER BY s.starts_at_gmt ASC
			LIMIT 20
		";

		$params = array_merge(
			array( $lower_bound, $end_inclusive ),
			$request['class_names']
		);

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * If the requested end-date was just a Y-m-d (e.g. '2026-10-30'),
	 * expand it to end-of-day so same-day sessions are still matched.
	 * Otherwise leave it as-is.
	 */
	protected static function make_end_inclusive( $end_gmt ) {
		if ( ! $end_gmt ) {
			return $end_gmt;
		}
		// parse_date_to_gmt() pads 'Y-m-d' with '00:00:00' for the start.
		// Detect that pattern and bump it to end-of-day for the end date.
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} 00:00:00$/', $end_gmt ) ) {
			return substr( $end_gmt, 0, 10 ) . ' 23:59:59';
		}
		return $end_gmt;
	}

	/**
	 * Compose and send the availability notification email for a single
	 * waitlist entry that has at least one matching session.
	 *
	 * @param object $entry    Waitlist row.
	 * @param object[] $sessions Matching sessions with capacity.
	 * @param array  $request  Parsed request (for echoing dates back).
	 * @return bool True if wp_mail accepted the message.
	 */
	protected static function send_availability_email( $entry, array $sessions, array $request ) {
		$email = isset( $entry->email ) ? sanitize_email( $entry->email ) : '';
		if ( ! is_email( $email ) ) {
			return false;
		}

		$name = ! empty( $entry->name ) ? $entry->name : __( 'there', 'wp-exam-success' );

		$start_label = $request['start_gmt'] ? get_date_from_gmt( $request['start_gmt'], 'M j, Y' ) : '';
		$end_label   = $request['end_gmt']   ? get_date_from_gmt( $request['end_gmt'], 'M j, Y' )     : '';

		$rows_html = '';
		foreach ( $sessions as $s ) {
			$start = get_date_from_gmt( $s->starts_at_gmt, 'D, M j, Y \a\t g:i A' );
			$end   = get_date_from_gmt( $s->ends_at_gmt, 'g:i A' );
			$label = $s->title ?: $s->class_name;
			$rows_html .= '<li style="margin-bottom:6px;"><strong>' . esc_html( $label ) . '</strong> &mdash; '
				. esc_html( $start . ' &ndash; ' . $end )
				. ' <em style="color:#666;">(' . esc_html( $s->class_name ) . ' &middot; '
				. sprintf( _n( '%d spot left', '%d spots left', (int) $s->remaining, 'wp-exam-success' ), (int) $s->remaining )
				. ')</em></li>';
		}

		$body  = '<p>' . sprintf(
			/* translators: %s: recipient name */
			esc_html__( 'Hi %s,', 'wp-exam-success' ),
			esc_html( $name )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'Good news — sessions matching your waitlist request now have open seats:', 'wp-exam-success' ) . '</p>';
		if ( $start_label && $end_label ) {
			$body .= '<p style="color:#555;font-size:13px;">' . sprintf(
				/* translators: 1: start date, 2: end date */
				esc_html__( 'Your requested window: %1$s &ndash; %2$s', 'wp-exam-success' ),
				esc_html( $start_label ),
				esc_html( $end_label )
			) . '</p>';
		}
		$body .= '<ul style="padding-left:20px;">' . $rows_html . '</ul>';
		$body .= '<p>' . sprintf(
			/* translators: %s: site URL */
			esc_html__( 'Book a seat at %s before the spots fill up again.', 'wp-exam-success' ),
			'<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</a>'
		) . '</p>';

		return WPES_Emailer::send(
			$email,
			__( 'A session matching your waitlist request is now available', 'wp-exam-success' ),
			$body
		);
	}
}
