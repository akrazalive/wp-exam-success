<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for teachers, plus the many-to-many link to the classes (subjects)
 * a teacher is suitable to teach. Mirrors WPES_Classes structurally.
 *
 * Teachers are not WordPress user accounts — they are invited/accepted by
 * email (Accept-Link), so no login or role is required for the core
 * workflow. `email` is the only contact channel and must stay unique-ish
 * in practice, though not DB-enforced unique (an admin may need to fix a
 * typo without a hard collision).
 */
class WPES_Teachers {

	public static function get( $id ) {
		global $wpdb;
		$table = WPES_DB::teachers_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function get_all( $status = null ) {
		global $wpdb;
		$table = WPES_DB::teachers_table();
		if ( $status ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY name ASC", $status ) );
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
	}

	/**
	 * Create a teacher and set their suitable classes.
	 *
	 * @param string $name
	 * @param string $email
	 * @param string $status
	 * @param int[]  $class_ids
	 * @return int|WP_Error New teacher ID.
	 */
	public static function create( $name, $email, $status = 'active', array $class_ids = array() ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'wpes_invalid_email', __( 'Please enter a valid email address.', 'wp-exam-success' ) );
		}

		$table = WPES_DB::teachers_table();
		$now   = WPES_DB::now_gmt();

		$wpdb->insert(
			$table,
			array(
				'name'       => sanitize_text_field( $name ),
				'email'      => $email,
				'status'     => sanitize_key( $status ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		$teacher_id = $wpdb->insert_id;
		self::assign_classes( $teacher_id, $class_ids );

		return $teacher_id;
	}

	/**
	 * @param int    $id
	 * @param string $name
	 * @param string $email
	 * @param string $status
	 * @param int[]  $class_ids
	 * @return true|WP_Error
	 */
	public static function update( $id, $name, $email, $status = 'active', array $class_ids = array() ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'wpes_invalid_email', __( 'Please enter a valid email address.', 'wp-exam-success' ) );
		}

		$table = WPES_DB::teachers_table();

		$wpdb->update(
			$table,
			array(
				'name'       => sanitize_text_field( $name ),
				'email'      => $email,
				'status'     => sanitize_key( $status ),
				'updated_at' => WPES_DB::now_gmt(),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::assign_classes( (int) $id, $class_ids );

		return true;
	}

	public static function count_all( $status = null ) {
		global $wpdb;
		$table = WPES_DB::teachers_table();
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function archive( $id ) {
		$teacher = self::get( $id );
		if ( ! $teacher ) {
			return false;
		}
		global $wpdb;
		return $wpdb->update(
			WPES_DB::teachers_table(),
			array( 'status' => 'archived', 'updated_at' => WPES_DB::now_gmt() ),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Refuse to delete a teacher still assigned to a session — protects
	 * against orphaning a session's assigned_teacher_id. Admin must
	 * archive instead (mirrors WPES_Classes::delete()).
	 */
	public static function delete( $id ) {
		global $wpdb;
		$sessions_table = WPES_DB::sessions_table();
		$has_sessions   = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE assigned_teacher_id = %d", $id )
		);
		if ( $has_sessions > 0 ) {
			return new WP_Error( 'wpes_teacher_has_sessions', __( 'This teacher is assigned to existing sessions and cannot be deleted. Archive them instead.', 'wp-exam-success' ) );
		}

		$wpdb->delete( WPES_DB::teacher_classes_table(), array( 'teacher_id' => (int) $id ), array( '%d' ) );
		return $wpdb->delete( WPES_DB::teachers_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Replace the full set of classes a teacher is suitable for.
	 *
	 * @param int   $teacher_id
	 * @param int[] $class_ids
	 */
	public static function assign_classes( $teacher_id, array $class_ids ) {
		global $wpdb;
		$table      = WPES_DB::teacher_classes_table();
		$teacher_id = (int) $teacher_id;

		$wpdb->delete( $table, array( 'teacher_id' => $teacher_id ), array( '%d' ) );

		$class_ids = array_unique( array_filter( array_map( 'intval', $class_ids ) ) );
		if ( empty( $class_ids ) ) {
			return;
		}

		$now = WPES_DB::now_gmt();
		foreach ( $class_ids as $class_id ) {
			$wpdb->insert(
				$table,
				array(
					'teacher_id' => $teacher_id,
					'class_id'   => $class_id,
					'created_at' => $now,
				),
				array( '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * @param int $teacher_id
	 * @return int[] Class IDs this teacher is suitable for.
	 */
	public static function get_class_ids_for_teacher( $teacher_id ) {
		global $wpdb;
		$table = WPES_DB::teacher_classes_table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT class_id FROM {$table} WHERE teacher_id = %d", (int) $teacher_id ) );
		return array_map( 'intval', $ids );
	}

	/**
	 * Active teachers suitable for a given class (subject) — the invite
	 * pool for that class's sessions.
	 *
	 * @param int $class_id
	 * @return object[]
	 */
	public static function get_teachers_for_class( $class_id ) {
		global $wpdb;
		$teachers_table = WPES_DB::teachers_table();
		$link_table     = WPES_DB::teacher_classes_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.* FROM {$teachers_table} t
				 INNER JOIN {$link_table} tc ON tc.teacher_id = t.id
				 WHERE tc.class_id = %d AND t.status = 'active'
				 ORDER BY t.name ASC",
				(int) $class_id
			)
		);
	}

	/**
	 * Names of the classes a teacher is suitable for, comma-joined —
	 * for display in the admin list.
	 *
	 * @param int $teacher_id
	 * @return string
	 */
	public static function get_class_names_for_teacher( $teacher_id ) {
		global $wpdb;
		$classes_table = WPES_DB::classes_table();
		$link_table    = WPES_DB::teacher_classes_table();

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.name FROM {$classes_table} c
				 INNER JOIN {$link_table} tc ON tc.class_id = c.id
				 WHERE tc.teacher_id = %d
				 ORDER BY c.name ASC",
				(int) $teacher_id
			)
		);
		return implode( ', ', $names );
	}

	/**
	 * Count teachers matching a search term (for DataTables).
	 *
	 * @param string $search
	 * @param string $status
	 * @return int
	 */
	public static function count_query( $search = '', $status = '' ) {
		global $wpdb;
		list( $where_sql, $params ) = self::build_where( $search, $status );
		$table = WPES_DB::teachers_table();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * List teachers for DataTables (with pagination).
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
		$table    = WPES_DB::teachers_table();

		$sql      = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY name ASC LIMIT %d OFFSET %d";
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
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $status );
		}
		if ( '' !== $search ) {
			$where[]  = '(name LIKE %s OR email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		return array( implode( ' AND ', $where ), $params );
	}
}
