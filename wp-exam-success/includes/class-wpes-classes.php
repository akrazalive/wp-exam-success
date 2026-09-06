<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for "classes" (subjects), e.g. Reading, Algebra II.
 * A class is the umbrella a recurring set of sessions belongs to.
 */
class WPES_Classes {

	public static function get( $id ) {
		global $wpdb;
		$table = WPES_DB::classes_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function get_all( $status = null ) {
		global $wpdb;
		$table = WPES_DB::classes_table();
		if ( $status ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY name ASC", $status ) );
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
	}

	public static function create( $name, $description = '', $status = 'active' ) {
		global $wpdb;
		$table = WPES_DB::classes_table();
		$slug  = self::unique_slug( sanitize_title( $name ) );
		$now   = WPES_DB::now_gmt();

		$wpdb->insert(
			$table,
			array(
				'name'        => sanitize_text_field( $name ),
				'slug'        => $slug,
				'description' => wp_kses_post( $description ),
				'status'      => sanitize_key( $status ),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	public static function update( $id, $name, $description = '', $status = 'active' ) {
		global $wpdb;
		$table = WPES_DB::classes_table();

		return $wpdb->update(
			$table,
			array(
				'name'        => sanitize_text_field( $name ),
				'description' => wp_kses_post( $description ),
				'status'      => sanitize_key( $status ),
				'updated_at'  => WPES_DB::now_gmt(),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function count_all( $status = null ) {
		global $wpdb;
		$table = WPES_DB::classes_table();
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function archive( $id ) {
		$class = self::get( $id );
		if ( ! $class ) {
			return false;
		}
		return self::update( $id, $class->name, $class->description, 'archived' );
	}

	public static function delete( $id ) {
		global $wpdb;
		// Refuse to delete a class that still has sessions — protects
		// against orphaning bookings. Admin must archive instead.
		$sessions_table = WPES_DB::sessions_table();
		$has_sessions   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE class_id = %d", $id ) );
		if ( $has_sessions > 0 ) {
			return new WP_Error( 'wpes_class_has_sessions', __( 'This class has existing sessions and cannot be deleted. Archive it instead.', 'wp-exam-success' ) );
		}
		return $wpdb->delete( WPES_DB::classes_table(), array( 'id' => $id ), array( '%d' ) );
	}

	private static function unique_slug( $base_slug ) {
		global $wpdb;
		$table = WPES_DB::classes_table();
		$slug  = $base_slug;
		$i     = 2;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) ) ) {
			$slug = $base_slug . '-' . $i;
			$i++;
		}
		return $slug;
	}
}
