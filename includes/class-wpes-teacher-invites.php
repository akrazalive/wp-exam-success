<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Automatic teacher invitation and Accept-Link acceptance.
 *
 * Once a session reaches the configured minimum participant count and
 * has no assigned teacher, every suitable teacher (WPES_Teachers::
 * get_teachers_for_class()) gets a unique, time-limited Accept-Link by
 * email. The first valid acceptance wins; this must be race-condition
 * safe the same way WPES_Bookings::reserve() is, so acceptance uses a
 * single conditional UPDATE ... WHERE assigned_teacher_id IS NULL
 * rather than a read-then-write check.
 */
class WPES_Teacher_Invites {

	/**
	 * Re-evaluate one session: if it has reached the minimum participant
	 * count, has no assigned teacher yet, and doesn't already have a
	 * live invitation round in flight, send Accept-Link invitations to
	 * every suitable teacher.
	 *
	 * Safe to call repeatedly (e.g. after every booking confirmation,
	 * and from the periodic sweep) — it is a no-op once a teacher is
	 * assigned or a round is already pending.
	 *
	 * @param int $session_id
	 * @return true|string true if invitations were (re)sent, otherwise
	 *                      a short reason code for why nothing happened.
	 */
	public static function maybe_invite_teachers( $session_id ) {
		$session_id = (int) $session_id;
		$session    = WPES_Sessions::get( $session_id );

		if ( ! $session || 'scheduled' !== $session->status ) {
			return 'session_unavailable';
		}
		if ( ! empty( $session->assigned_teacher_id ) ) {
			return 'already_assigned';
		}

		$settings = WPES_Admin::get_booking_settings();

		$confirmed = self::count_confirmed_attendees( $session_id );
		if ( $confirmed < $settings['min_participants'] ) {
			return 'minimum_not_reached';
		}

		if ( ! $settings['auto_teacher_assignment'] ) {
			return 'auto_assignment_disabled';
		}

		// Race-condition-safe claim of the invite round itself. Without
		// this lock, two near-simultaneous callers for the same session
		// (e.g. two bookings each confirming within milliseconds of each
		// other, both pushing the session past its minimum) can both pass
		// the has_pending_invites() check before either has inserted a
		// row, and each send a full duplicate round of invites to every
		// suitable teacher. Locking the session row for the duration of
		// the check-and-insert serializes that — the same discipline
		// AGENTS.md requires of any new path touching this row
		// (WPES_Bookings::reserve()'s FOR UPDATE lock).
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$wpdb->query( 'START TRANSACTION' );

		$locked = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, assigned_teacher_id FROM {$sessions_table} WHERE id = %d FOR UPDATE", $session_id )
		);

		if ( ! $locked || ! empty( $locked->assigned_teacher_id ) || self::has_pending_invites( $session_id ) ) {
			$wpdb->query( 'COMMIT' );
			if ( ! $locked ) {
				return 'session_unavailable';
			}
			return ! empty( $locked->assigned_teacher_id ) ? 'already_assigned' : 'invites_already_pending';
		}

		$teachers = WPES_Teachers::get_teachers_for_class( $session->class_id );
		if ( empty( $teachers ) ) {
			$wpdb->query( 'COMMIT' );
			return 'no_suitable_teachers';
		}

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $settings['teacher_invite_hours'] * HOUR_IN_SECONDS ) );
		$tokens     = array();

		foreach ( $teachers as $teacher ) {
			$tokens[ $teacher->id ] = self::create_invite( $session_id, $teacher->id, $expires_at );
		}

		$wpdb->query( 'COMMIT' );

		// Emails (external I/O) are sent only after the transaction
		// commits and the row lock releases — never hold a DB lock across
		// a network call to the mail server.
		$class = WPES_Classes::get( $session->class_id );
		$sent  = 0;
		foreach ( $teachers as $teacher ) {
			$accept_url = self::build_accept_url( $tokens[ $teacher->id ] );
			if ( WPES_Emailer::send_teacher_invite( $teacher, $session, $class, $accept_url, $settings['teacher_invite_hours'] ) ) {
				$sent++;
			}
		}

		return $sent > 0 ? true : 'send_failed';
	}

	/**
	 * @param int $session_id
	 * @param int $teacher_id
	 * @param string $expires_at_gmt
	 * @return string The raw (unhashed) token to embed in the email link.
	 */
	protected static function create_invite( $session_id, $teacher_id, $expires_at_gmt ) {
		global $wpdb;

		$token      = bin2hex( random_bytes( 20 ) );
		$token_hash = hash( 'sha256', $token );

		$wpdb->insert(
			WPES_DB::teacher_invites_table(),
			array(
				'session_id' => (int) $session_id,
				'teacher_id' => (int) $teacher_id,
				'token_hash' => $token_hash,
				'status'     => 'pending',
				'expires_at' => $expires_at_gmt,
				'created_at' => WPES_DB::now_gmt(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $token;
	}

	protected static function build_accept_url( $token ) {
		return add_query_arg( 'wpes_teacher_accept', rawurlencode( $token ), home_url( '/' ) );
	}

	/**
	 * @param int $session_id
	 * @return bool
	 */
	public static function has_pending_invites( $session_id ) {
		global $wpdb;
		$table = WPES_DB::teacher_invites_table();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE session_id = %d AND status = 'pending' AND expires_at > %s",
				(int) $session_id,
				WPES_DB::now_gmt()
			)
		);
		return $count > 0;
	}

	/**
	 * @param int $session_id
	 * @return int Confirmed (paid) attendee count for this session.
	 */
	public static function count_confirmed_attendees( $session_id ) {
		global $wpdb;
		$table = WPES_DB::bookings_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE session_id = %d AND status = 'confirmed'",
				(int) $session_id
			)
		);
	}

	/**
	 * Handle a teacher clicking their Accept-Link.
	 *
	 * @param string $token Raw token from the URL.
	 * @return array{result:string,session?:object,teacher?:object} result is one of:
	 *   'accepted', 'already_assigned', 'invalid_or_expired'.
	 */
	public static function handle_accept( $token ) {
		global $wpdb;

		$token_hash = hash( 'sha256', sanitize_text_field( $token ) );
		$table      = WPES_DB::teacher_invites_table();

		$invite = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE token_hash = %s AND status = 'pending' AND expires_at > %s",
				$token_hash,
				WPES_DB::now_gmt()
			)
		);

		if ( ! $invite ) {
			return array( 'result' => 'invalid_or_expired' );
		}

		$session = WPES_Sessions::get( $invite->session_id );
		$teacher = WPES_Teachers::get( $invite->teacher_id );

		if ( ! $session || ! $teacher ) {
			return array( 'result' => 'invalid_or_expired' );
		}

		$settings       = WPES_Admin::get_booking_settings();
		$sessions_table = WPES_DB::sessions_table();
		$bookings_table = WPES_DB::bookings_table();

		// Race-condition-safe claim: succeeds only if nobody has been
		// assigned to this session yet, AND the session still genuinely
		// meets the minimum participant count at this exact moment — the
		// final-confirmation check required by Developer Spec §9/§14. A
		// cancellation or refund between the invite being sent and this
		// acceptance (up to teacher_invite_hours later) must not be able
		// to let a session confirm below minimum. Both conditions live in
		// the same conditional UPDATE as the assignment itself — the same
		// discipline as WPES_Bookings::reserve()'s row lock, but via a
		// compare-and-set (plus this correlated subquery) rather than a
		// transaction, since it's a single row with no related rows to lock.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table}
				 SET assigned_teacher_id = %d, teacher_assigned_at = %s, confirmation_state = 'confirmed', updated_at = %s
				 WHERE id = %d AND assigned_teacher_id IS NULL
				   AND ( SELECT COUNT(*) FROM {$bookings_table} WHERE session_id = %d AND status = 'confirmed' ) >= %d",
				$teacher->id,
				WPES_DB::now_gmt(),
				WPES_DB::now_gmt(),
				$session->id,
				$session->id,
				$settings['min_participants']
			)
		);

		if ( 1 !== $updated ) {
			// Either someone else's acceptance won the race (or an admin
			// assigned manually), or the session has since dropped below
			// the minimum — re-check which, so the teacher sees the
			// accurate reason rather than a generic "already assigned".
			self::mark_invite( $invite->id, 'superseded' );

			$fresh = WPES_Sessions::get( $session->id );
			if ( $fresh && empty( $fresh->assigned_teacher_id ) && self::count_confirmed_attendees( $session->id ) < $settings['min_participants'] ) {
				return array(
					'result'  => 'minimum_no_longer_met',
					'session' => $fresh,
					'teacher' => $teacher,
				);
			}

			return array(
				'result'  => 'already_assigned',
				'session' => $fresh ?: $session,
				'teacher' => $teacher,
			);
		}

		self::mark_invite( $invite->id, 'accepted' );
		self::supersede_other_invites( $session->id, $invite->id );
		self::finalize_session_confirmation( $session->id );

		return array(
			'result'  => 'accepted',
			'session' => WPES_Sessions::get( $session->id ),
			'teacher' => $teacher,
		);
	}

	/**
	 * Everything that happens once a session is genuinely confirmed
	 * (minimum reached + teacher assigned) — shared by the automatic
	 * Accept-Link path above and the manual admin-assignment path
	 * (WPES_Admin::ajax_update_session()), so both behave identically:
	 * confirmation email to attendees, automatic meeting-link send if one
	 * is already set, and triggering payment capture on every distinct
	 * order that has a confirmed booking for this session.
	 *
	 * Safe to call more than once for the same session — re-sending the
	 * confirmation email on a repeat call is the only side effect that
	 * isn't itself idempotent, so callers should only invoke this at the
	 * moment a session actually transitions into 'confirmed', not on
	 * every unrelated save.
	 *
	 * @param int $session_id
	 */
	public static function finalize_session_confirmation( $session_id ) {
		$session = WPES_Sessions::get( $session_id );
		if ( ! $session || empty( $session->assigned_teacher_id ) ) {
			return;
		}

		// Central final gate (Pre-Acceptance Review item 1): regardless of
		// which path got a teacher assigned here — the automatic
		// Accept-Link (already guarded atomically in handle_accept(), so
		// this is redundant-but-safe for that path) or a manual admin
		// assignment (WPES_Admin::ajax_update_session(), which has its own
		// pre-check but this is the true last line of defense) — the
		// minimum must still genuinely hold right now, before any
		// confirmation email, meeting-link send, or payment capture fires.
		// If it doesn't (e.g. a cancellation landed between the
		// assignment and this call), undo the assignment rather than
		// silently skipping the side effects, so the session correctly
		// returns to "awaiting invite" instead of showing an assigned
		// teacher that was never actually confirmed.
		$settings = WPES_Admin::get_booking_settings();
		if ( self::count_confirmed_attendees( $session_id ) < $settings['min_participants'] ) {
			global $wpdb;
			$wpdb->update(
				WPES_DB::sessions_table(),
				array(
					'assigned_teacher_id' => null,
					'teacher_assigned_at' => null,
					'confirmation_state'  => 'pending',
					'updated_at'          => WPES_DB::now_gmt(),
				),
				array( 'id' => $session_id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		$teacher = WPES_Teachers::get( $session->assigned_teacher_id );
		$class   = WPES_Classes::get( $session->class_id );

		if ( $teacher ) {
			WPES_Emailer::send_session_confirmed_to_attendees( $session, $class, $teacher );
		}

		if ( ! empty( $session->meeting_link ) ) {
			WPES_Meeting_Links::send_for_session( $session->id, 'initial' );
		}

		// Capture each distinct order that has a confirmed booking for
		// this session — a session is shared across potentially many
		// different customers' packages, and each package's payment is
		// captured independently (Developer Spec §6).
		if ( class_exists( 'WPES_Payments' ) ) {
			$bookings = WPES_Bookings::get_attendees_for_session( $session_id, array( 'confirmed' ) );
			$order_ids = array();
			foreach ( $bookings as $booking ) {
				if ( ! empty( $booking->order_id ) ) {
					$order_ids[ (int) $booking->order_id ] = true;
				}
			}
			foreach ( array_keys( $order_ids ) as $order_id ) {
				WPES_Payments::maybe_capture_package_payment( $order_id, $session_id );
			}
		}
	}

	protected static function mark_invite( $invite_id, $status ) {
		global $wpdb;
		$wpdb->update(
			WPES_DB::teacher_invites_table(),
			array( 'status' => $status, 'responded_at' => WPES_DB::now_gmt() ),
			array( 'id' => (int) $invite_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	protected static function supersede_other_invites( $session_id, $except_invite_id ) {
		global $wpdb;
		$table = WPES_DB::teacher_invites_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'superseded' WHERE session_id = %d AND status = 'pending' AND id != %d",
				(int) $session_id,
				(int) $except_invite_id
			)
		);
	}

	/**
	 * Cron target: expire stale pending invites, and notify the admin for
	 * any session left with no teacher once its whole invite round has
	 * expired.
	 */
	public static function sweep_expired_invites() {
		global $wpdb;
		$table          = WPES_DB::teacher_invites_table();
		$sessions_table = WPES_DB::sessions_table();
		$now            = WPES_DB::now_gmt();

		// Sessions with at least one invite about to expire in this sweep,
		// captured before the UPDATE so we know who to check afterwards.
		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT session_id FROM {$table} WHERE status = 'pending' AND expires_at <= %s",
				$now
			)
		);

		if ( empty( $session_ids ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired' WHERE status = 'pending' AND expires_at <= %s",
				$now
			)
		);

		foreach ( $session_ids as $session_id ) {
			$session_id = (int) $session_id;

			// If any invite for this session is still pending (a later,
			// longer-lived one), this session's round isn't over yet.
			if ( self::has_pending_invites( $session_id ) ) {
				continue;
			}

			$session = WPES_Sessions::get( $session_id );
			if ( ! $session || ! empty( $session->assigned_teacher_id ) ) {
				continue; // Already resolved — nothing to notify about.
			}

			$class = WPES_Classes::get( $session->class_id );
			WPES_Emailer::send_admin_no_teacher_response( $session, $class );
		}
	}

	/**
	 * Count invites matching filters (for the admin DataTable).
	 *
	 * @param string $search
	 * @param string $status
	 * @return int
	 */
	public static function count_query( $search = '', $status = '' ) {
		global $wpdb;
		list( $where_sql, $params ) = self::build_where( $search, $status );

		$table          = WPES_DB::teacher_invites_table();
		$teachers_table = WPES_DB::teachers_table();
		$sessions_table = WPES_DB::sessions_table();

		$sql = "SELECT COUNT(*) FROM {$table} i
			INNER JOIN {$teachers_table} t ON t.id = i.teacher_id
			INNER JOIN {$sessions_table} s ON s.id = i.session_id
			WHERE {$where_sql}";

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * List teacher invites for the admin "Teacher Invites" screen —
	 * every invite ever sent, across every session, so the admin no
	 * longer has to open each session individually to see who was asked
	 * and how they responded.
	 *
	 * @param array $args { search, status, per_page, page }
	 * @return object[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		list( $where_sql, $params ) = self::build_where( $args['search'] ?? '', $args['status'] ?? '' );

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 25;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$table          = WPES_DB::teacher_invites_table();
		$teachers_table = WPES_DB::teachers_table();
		$sessions_table = WPES_DB::sessions_table();
		$classes_table  = WPES_DB::classes_table();

		$sql = "SELECT i.*, t.name AS teacher_name, t.email AS teacher_email,
				s.title AS session_title, s.starts_at_gmt AS session_starts_at_gmt,
				c.name AS class_name
			FROM {$table} i
			INNER JOIN {$teachers_table} t ON t.id = i.teacher_id
			INNER JOIN {$sessions_table} s ON s.id = i.session_id
			LEFT JOIN {$classes_table} c ON c.id = s.class_id
			WHERE {$where_sql}
			ORDER BY i.created_at DESC
			LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * @return array{0:string,1:array}
	 */
	protected static function build_where( $search, $status ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $status ) {
			$where[]  = 'i.status = %s';
			$params[] = sanitize_key( $status );
		}
		if ( '' !== $search ) {
			$where[]  = '(t.name LIKE %s OR t.email LIKE %s OR s.title LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Cron target: the final check before each session starts (Developer
	 * Spec §11, Overview PDF's "final time-based check ... default 24
	 * hours before start"). A still-unconfirmed session this close to
	 * starting is resolved one of two ways:
	 *   - enough confirmed attendees, just no teacher yet -> one more
	 *     invite attempt (safety net for e.g. auto-assignment having been
	 *     off when the minimum was first reached);
	 *   - still below the minimum -> the session cannot take place, hand
	 *     off to WPES_Replacements for the cancellation/credit flow.
	 */
	public static function run_final_checks() {
		$settings = WPES_Admin::get_booking_settings();
		$horizon  = gmdate( 'Y-m-d H:i:s', time() + ( $settings['final_check_hours_before'] * HOUR_IN_SECONDS ) );

		$sessions = WPES_Sessions::query(
			array(
				'status'   => 'scheduled',
				'to_gmt'   => $horizon,
				'per_page' => 200,
			)
		);

		foreach ( $sessions as $session ) {
			if ( ! empty( $session->assigned_teacher_id ) ) {
				continue;
			}

			$confirmed = self::count_confirmed_attendees( $session->id );
			if ( $confirmed >= $settings['min_participants'] ) {
				self::maybe_invite_teachers( $session->id );
			} else {
				WPES_Replacements::process_session_failure( $session );
			}
		}
	}
}
