<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu, asset loading, and AJAX endpoints.
 */
class WPES_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		WPES_Activator::maybe_upgrade();

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'wp_ajax_wpes_save_class', array( __CLASS__, 'ajax_save_class' ) );
		add_action( 'wp_ajax_wpes_delete_class', array( __CLASS__, 'ajax_delete_class' ) );
		add_action( 'wp_ajax_wpes_archive_class', array( __CLASS__, 'ajax_archive_class' ) );
		add_action( 'wp_ajax_wpes_save_teacher', array( __CLASS__, 'ajax_save_teacher' ) );
		add_action( 'wp_ajax_wpes_delete_teacher', array( __CLASS__, 'ajax_delete_teacher' ) );
		add_action( 'wp_ajax_wpes_archive_teacher', array( __CLASS__, 'ajax_archive_teacher' ) );
		add_action( 'wp_ajax_wpes_datatable_teachers', array( __CLASS__, 'ajax_datatable_teachers' ) );
		add_action( 'wp_ajax_wpes_get_teachers_for_class', array( __CLASS__, 'ajax_get_teachers_for_class' ) );
		add_action( 'wp_ajax_wpes_save_session', array( __CLASS__, 'ajax_save_session' ) );
		add_action( 'wp_ajax_wpes_update_session', array( __CLASS__, 'ajax_update_session' ) );
		add_action( 'wp_ajax_wpes_cancel_series', array( __CLASS__, 'ajax_cancel_series' ) );
		add_action( 'wp_ajax_wpes_bulk_archive_sessions', array( __CLASS__, 'ajax_bulk_archive_sessions' ) );
		add_action( 'wp_ajax_wpes_manual_enroll', array( __CLASS__, 'ajax_manual_enroll' ) );
		add_action( 'wp_ajax_wpes_send_meeting_link', array( __CLASS__, 'ajax_send_meeting_link' ) );
		add_action( 'wp_ajax_wpes_session_attendees', array( __CLASS__, 'ajax_session_attendees' ) );
		add_action( 'wp_ajax_wpes_datatable_classes', array( __CLASS__, 'ajax_datatable_classes' ) );
		add_action( 'wp_ajax_wpes_datatable_sessions', array( __CLASS__, 'ajax_datatable_sessions' ) );
		add_action( 'wp_ajax_wpes_datatable_bookings', array( __CLASS__, 'ajax_datatable_bookings' ) );
		add_action( 'wp_ajax_wpes_datatable_waitlist', array( __CLASS__, 'ajax_datatable_waitlist' ) );
		add_action( 'wp_ajax_wpes_datatable_teacher_invites', array( __CLASS__, 'ajax_datatable_teacher_invites' ) );
		add_action( 'wp_ajax_wpes_datatable_credits', array( __CLASS__, 'ajax_datatable_credits' ) );
		add_action( 'wp_ajax_wpes_waitlist_send_message', array( __CLASS__, 'ajax_waitlist_send_message' ) );
		add_action( 'wp_ajax_wpes_waitlist_delete', array( __CLASS__, 'ajax_waitlist_delete' ) );
		add_action( 'wp_ajax_wpes_get_session', array( __CLASS__, 'ajax_get_session' ) );
		add_action( 'wp_ajax_wpes_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Exam Success', 'wp-exam-success' ),
			__( 'Exam Success', 'wp-exam-success' ),
			self::CAP,
			'wpes-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-welcome-learn-more',
			56
		);
		add_submenu_page( 'wpes-dashboard', __( 'Dashboard', 'wp-exam-success' ), __( 'Dashboard', 'wp-exam-success' ), self::CAP, 'wpes-dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Classes', 'wp-exam-success' ), __( 'Classes', 'wp-exam-success' ), self::CAP, 'wpes-classes', array( __CLASS__, 'render_classes' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Teachers', 'wp-exam-success' ), __( 'Teachers', 'wp-exam-success' ), self::CAP, 'wpes-teachers', array( __CLASS__, 'render_teachers' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Teacher Invites', 'wp-exam-success' ), __( 'Teacher Invites', 'wp-exam-success' ), self::CAP, 'wpes-teacher-invites', array( __CLASS__, 'render_teacher_invites' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Sessions', 'wp-exam-success' ), __( 'Sessions', 'wp-exam-success' ), self::CAP, 'wpes-sessions', array( __CLASS__, 'render_sessions' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Bookings & Reporting', 'wp-exam-success' ), __( 'Bookings & Reporting', 'wp-exam-success' ), self::CAP, 'wpes-bookings', array( __CLASS__, 'render_bookings' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Replacement Credits', 'wp-exam-success' ), __( 'Replacement Credits', 'wp-exam-success' ), self::CAP, 'wpes-credits', array( __CLASS__, 'render_credits' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Waitlist', 'wp-exam-success' ), __( 'Waitlist', 'wp-exam-success' ), self::CAP, 'wpes-waitlist', array( __CLASS__, 'render_waitlist' ) );
		add_submenu_page( 'wpes-dashboard', __( 'Settings', 'wp-exam-success' ), __( 'Settings', 'wp-exam-success' ), self::CAP, 'wpes-settings', array( __CLASS__, 'render_settings' ) );
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'wpes-' ) === false && ! ( isset( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'wpes-' ) ) ) {
			return;
		}

		wp_enqueue_style( 'wpes-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3' );
		wp_enqueue_style( 'wpes-datatables', 'https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css', array(), '1.13.11' );
		wp_enqueue_style( 'wpes-dt-buttons', 'https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css', array(), '2.4.2' );
		wp_enqueue_style( 'wpes-notyf', 'https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css', array(), '3' );
		wp_enqueue_style( 'wpes-admin', WPES_PLUGIN_URL . 'admin/css/wpes-admin.css', array( 'wpes-bootstrap' ), filemtime( WPES_PLUGIN_DIR . 'admin/css/wpes-admin.css' ) );

		wp_enqueue_script( 'wpes-bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array(), '5.3.3', true );
		wp_enqueue_script( 'wpes-datatables-js', 'https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.11', true );
		wp_enqueue_script( 'wpes-datatables-bs5-js', 'https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js', array( 'wpes-datatables-js' ), '1.13.11', true );
		wp_enqueue_script( 'wpes-dt-buttons', 'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js', array( 'wpes-datatables-bs5-js' ), '2.4.2', true );
		wp_enqueue_script( 'wpes-dt-buttons-bs5', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js', array( 'wpes-dt-buttons' ), '2.4.2', true );
		wp_enqueue_script( 'wpes-dt-buttons-html5', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js', array( 'wpes-dt-buttons' ), '2.4.2', true );
		wp_enqueue_script( 'wpes-dt-buttons-print', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js', array( 'wpes-dt-buttons' ), '2.4.2', true );
		wp_enqueue_script( 'wpes-jszip', 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', array(), '3.10.1', true );
		wp_enqueue_script( 'wpes-pdfmake', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js', array(), '0.2.7', true );
		wp_enqueue_script( 'wpes-pdfmake-fonts', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.min.js', array( 'wpes-pdfmake' ), '0.2.7', true );
		wp_enqueue_script( 'wpes-notyf', 'https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js', array(), '3', true );

		wp_enqueue_script( 'wpes-admin', WPES_PLUGIN_URL . 'admin/js/wpes-admin.js', array( 'jquery', 'wpes-datatables-bs5-js', 'wpes-bootstrap-js', 'wpes-notyf' ), filemtime( WPES_PLUGIN_DIR . 'admin/js/wpes-admin.js' ), true );

		wp_localize_script(
			'wpes-admin',
			'WPES_Admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpes_admin_nonce' ),
				'site_tz'  => wp_timezone_string(),
				'i18n'     => array(
					'confirm_delete'        => __( 'Delete this class? This cannot be undone.', 'wp-exam-success' ),
					'confirm_archive'       => __( 'Archive this class? It will be hidden from the frontend.', 'wp-exam-success' ),
					'confirm_cancel_series' => __( 'Cancel all future occurrences in this series?', 'wp-exam-success' ),
					'confirm_resend'        => __( 'Resend the meeting link to all confirmed attendees?', 'wp-exam-success' ),
					'confirm_bulk_archive'  => __( 'Archive the selected sessions?', 'wp-exam-success' ),
					'saved'                 => __( 'Saved.', 'wp-exam-success' ),
					'error'                 => __( 'Something went wrong. Please try again.', 'wp-exam-success' ),
					'required'              => __( 'Please fill in all required fields.', 'wp-exam-success' ),
					'enrolled'              => __( 'Customer enrolled successfully.', 'wp-exam-success' ),
					'addSession'            => __( 'Add Session', 'wp-exam-success' ),
					'editSession'           => __( 'Edit Session', 'wp-exam-success' ),
					'messageSent'           => __( 'Message sent.', 'wp-exam-success' ),
					'selectRecipients'      => __( 'Select at least one waitlist entry, or choose “Send to all filtered”.', 'wp-exam-success' ),
					'confirmBulkSend'       => __( 'Send this message to all entries matching the current filters?', 'wp-exam-success' ),
					'viewFields'            => __( 'View fields', 'wp-exam-success' ),
					'sendMessage'           => __( 'Send message', 'wp-exam-success' ),
					'confirmDeleteWaitlist' => __( 'Delete this waitlist entry? This cannot be undone.', 'wp-exam-success' ),
					'deleted'               => __( 'Waitlist entry deleted.', 'wp-exam-success' ),
					'unassigned'            => __( 'Unassigned', 'wp-exam-success' ),
					'confirmDeleteTeacher'  => __( 'Delete this teacher? This cannot be undone.', 'wp-exam-success' ),
					'confirmArchiveTeacher' => __( 'Archive this teacher? They will no longer receive session invitations.', 'wp-exam-success' ),
				),
			)
		);

		if ( isset( $_GET['page'] ) && in_array( sanitize_key( wp_unslash( $_GET['page'] ) ), array( 'wpes-classes', 'wpes-sessions' ), true ) ) {
			wp_enqueue_editor();
		}
	}

	public static function render_dashboard() {
		include WPES_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function render_classes() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		include WPES_PLUGIN_DIR . 'admin/views/classes.php';
	}

	public static function render_teachers() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		$classes = WPES_Classes::get_all( 'active' );
		include WPES_PLUGIN_DIR . 'admin/views/teachers.php';
	}

	public static function render_sessions() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		$classes = WPES_Classes::get_all( 'active' );
		include WPES_PLUGIN_DIR . 'admin/views/sessions.php';
	}

	public static function render_bookings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		$classes = WPES_Classes::get_all();
		include WPES_PLUGIN_DIR . 'admin/views/bookings.php';
	}

	public static function render_waitlist() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		WPES_Waitlist::ensure_table();
		$form_names = WPES_Waitlist::get_form_names();
		include WPES_PLUGIN_DIR . 'admin/views/waitlist.php';
	}

	public static function render_teacher_invites() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		include WPES_PLUGIN_DIR . 'admin/views/teacher-invites.php';
	}

	public static function render_credits() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		include WPES_PLUGIN_DIR . 'admin/views/credits.php';
	}

	public static function render_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-exam-success' ) );
		}
		$filters = self::get_frontend_filters();
		$booking = self::get_booking_settings();
		include WPES_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Enabled frontend booking filters. Missing keys default to enabled.
	 *
	 * @return array<string,bool>
	 */
	public static function get_frontend_filters() {
		$defaults = array(
			'class_id'     => true,
			'level'        => true,
			'day'          => true,
			'time'         => true,
			'availability' => true,
		);
		$saved = get_option( 'wpes_frontend_filters', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$out = array();
		foreach ( $defaults as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $saved ) ? (bool) $saved[ $key ] : $default;
		}
		return $out;
	}

	public static function ajax_save_settings() {
		self::verify_request();

		// Two independent forms post to this one action — only touch the
		// option a given request actually submitted data for, so saving
		// one never silently wipes the other back to defaults.
		if ( isset( $_POST['filters'] ) ) {
			$keys    = array( 'class_id', 'level', 'day', 'time', 'availability' );
			$filters = array();
			foreach ( $keys as $key ) {
				$filters[ $key ] = ! empty( $_POST['filters'][ $key ] );
			}
			update_option( 'wpes_frontend_filters', $filters, false );
		}

		if ( isset( $_POST['booking'] ) ) {
			$posted  = (array) $_POST['booking'];
			$booking = array(
				'min_participants'          => max( 1, (int) ( $posted['min_participants'] ?? 5 ) ),
				'auto_teacher_assignment'   => ! empty( $posted['auto_teacher_assignment'] ),
				'teacher_invite_hours'      => max( 1, (int) ( $posted['teacher_invite_hours'] ?? 4 ) ),
				'final_check_hours_before'  => max( 1, (int) ( $posted['final_check_hours_before'] ?? 24 ) ),
			);
			update_option( 'wpes_booking_settings', $booking, false );
		}

		wp_send_json_success();
	}

	/**
	 * Global booking-workflow settings (minimum participants, teacher
	 * assignment). Missing keys default per the Developer Specification.
	 *
	 * @return array{min_participants:int,auto_teacher_assignment:bool,teacher_invite_hours:int,final_check_hours_before:int}
	 */
	public static function get_booking_settings() {
		$defaults = array(
			'min_participants'         => 5,
			'auto_teacher_assignment'  => true,
			'teacher_invite_hours'     => 4,
			'final_check_hours_before' => 24,
		);
		$saved = get_option( 'wpes_booking_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$out = array();
		foreach ( $defaults as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $saved ) ? $saved[ $key ] : $default;
		}
		$out['min_participants']         = max( 1, (int) $out['min_participants'] );
		$out['auto_teacher_assignment']  = (bool) $out['auto_teacher_assignment'];
		$out['teacher_invite_hours']     = max( 1, (int) $out['teacher_invite_hours'] );
		$out['final_check_hours_before'] = max( 1, (int) $out['final_check_hours_before'] );
		return $out;
	}

	private static function verify_request() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-exam-success' ) ), 403 );
		}
		check_ajax_referer( 'wpes_admin_nonce', 'nonce' );
	}

	public static function ajax_save_class() {
		self::verify_request();

		$id          = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$status      = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'active';

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Name is required.', 'wp-exam-success' ) ) );
		}

		if ( $id > 0 ) {
			WPES_Classes::update( $id, $name, $description, $status );
		} else {
			$id = WPES_Classes::create( $name, $description, $status );
		}

		wp_send_json_success( array( 'id' => $id ) );
	}

	public static function ajax_delete_class() {
		self::verify_request();
		$result = WPES_Classes::delete( (int) ( $_POST['id'] ?? 0 ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	public static function ajax_archive_class() {
		self::verify_request();
		WPES_Classes::archive( (int) ( $_POST['id'] ?? 0 ) );
		wp_send_json_success();
	}

	/* ---------------------------------------------------------------
	 * Teachers
	 * ------------------------------------------------------------- */

	public static function ajax_save_teacher() {
		self::verify_request();

		$id        = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$status    = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'active';
		$class_ids = isset( $_POST['class_ids'] ) ? array_map( 'intval', (array) $_POST['class_ids'] ) : array();

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Name is required.', 'wp-exam-success' ) ) );
		}
		if ( '' === $email ) {
			wp_send_json_error( array( 'message' => __( 'Email is required.', 'wp-exam-success' ) ) );
		}

		if ( $id > 0 ) {
			$result = WPES_Teachers::update( $id, $name, $email, $status, $class_ids );
		} else {
			$result = WPES_Teachers::create( $name, $email, $status, $class_ids );
			$id     = is_wp_error( $result ) ? 0 : $result;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'id' => $id ) );
	}

	public static function ajax_delete_teacher() {
		self::verify_request();
		$result = WPES_Teachers::delete( (int) ( $_POST['id'] ?? 0 ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	public static function ajax_archive_teacher() {
		self::verify_request();
		WPES_Teachers::archive( (int) ( $_POST['id'] ?? 0 ) );
		wp_send_json_success();
	}

	/**
	 * Suitable teachers for a class — used to populate the Session
	 * modal's Teacher dropdown when the Class selection changes.
	 */
	public static function ajax_get_teachers_for_class() {
		self::verify_request();
		$class_id = (int) ( $_POST['class_id'] ?? 0 );
		$teachers = $class_id ? WPES_Teachers::get_teachers_for_class( $class_id ) : array();

		$out = array();
		foreach ( $teachers as $teacher ) {
			$out[] = array( 'id' => (int) $teacher->id, 'name' => $teacher->name );
		}
		wp_send_json_success( array( 'teachers' => $out ) );
	}

	public static function ajax_datatable_teachers() {
		self::verify_request();
		$draw   = (int) ( $_POST['draw'] ?? 1 );
		$start  = (int) ( $_POST['start'] ?? 0 );
		$length = max( 1, (int) ( $_POST['length'] ?? 25 ) );
		$search = sanitize_text_field( wp_unslash( $_POST['search']['value'] ?? '' ) );
		$status = sanitize_key( $_POST['status_filter'] ?? '' );
		$page   = (int) floor( $start / $length ) + 1;

		$args = array(
			'search'   => $search,
			'status'   => $status,
			'per_page' => $length,
			'page'     => $page,
		);

		$total    = WPES_Teachers::count_query( $search, $status );
		$teachers = WPES_Teachers::query( $args );
		$data     = array();

		foreach ( $teachers as $teacher ) {
			$class_ids   = WPES_Teachers::get_class_ids_for_teacher( $teacher->id );
			$class_names = WPES_Teachers::get_class_names_for_teacher( $teacher->id );

			$actions  = '<button type="button" class="btn btn-sm btn-outline-primary wpes-edit-teacher" data-id="' . esc_attr( $teacher->id ) . '" data-name="' . esc_attr( $teacher->name ) . '" data-email="' . esc_attr( $teacher->email ) . '" data-status="' . esc_attr( $teacher->status ) . '" data-class-ids="' . esc_attr( wp_json_encode( $class_ids ) ) . '">' . esc_html__( 'Edit', 'wp-exam-success' ) . '</button> ';
			if ( 'archived' !== $teacher->status ) {
				$actions .= '<button type="button" class="btn btn-sm btn-outline-warning wpes-archive-teacher" data-id="' . esc_attr( $teacher->id ) . '">' . esc_html__( 'Archive', 'wp-exam-success' ) . '</button> ';
			}
			$actions .= '<button type="button" class="btn btn-sm btn-outline-danger wpes-delete-teacher" data-id="' . esc_attr( $teacher->id ) . '">' . esc_html__( 'Delete', 'wp-exam-success' ) . '</button>';

			$data[] = array(
				esc_html( $teacher->name ),
				esc_html( $teacher->email ),
				esc_html( $class_names ?: '—' ),
				'<span class="badge bg-' . ( 'active' === $teacher->status ? 'success' : 'secondary' ) . '">' . esc_html( ucfirst( $teacher->status ) ) . '</span>',
				$actions,
			);
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
				'data'            => $data,
			)
		);
	}

	public static function ajax_save_session() {
		self::verify_request();

		$class_id = (int) ( $_POST['class_id'] ?? 0 );
		$date     = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$start    = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) );
		$end      = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) );
		$tz_name  = sanitize_text_field( wp_unslash( $_POST['timezone'] ?? wp_timezone_string() ) );
		$max      = max( 1, (int) ( $_POST['max_attendees'] ?? 1 ) );
		$link     = esc_url_raw( wp_unslash( $_POST['meeting_link'] ?? '' ) );
		$title    = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$level    = sanitize_text_field( wp_unslash( $_POST['level'] ?? '' ) );
		$desc     = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
		$repeat   = sanitize_key( $_POST['repeat'] ?? 'none' );
		$count    = max( 1, (int) ( $_POST['repeat_count'] ?? 1 ) );

		if ( ! $class_id || ! $date || ! $start || ! $end ) {
			wp_send_json_error( array( 'message' => __( 'Class, date, and start/end time are required.', 'wp-exam-success' ) ) );
		}

		try {
			$tz = new DateTimeZone( $tz_name );
		} catch ( Exception $e ) {
			$tz = wp_timezone();
		}

		try {
			$starts_local = new DateTime( "{$date} {$start}", $tz );
			$ends_local   = new DateTime( "{$date} {$end}", $tz );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date/time.', 'wp-exam-success' ) ) );
		}

		$starts_local->setTimezone( new DateTimeZone( 'UTC' ) );
		$ends_local->setTimezone( new DateTimeZone( 'UTC' ) );

		$result = WPES_Sessions::create_series(
			array(
				'class_id'        => $class_id,
				'title'           => $title,
				'description'     => $desc,
				'level'           => $level,
				'starts_at_gmt'   => $starts_local->format( 'Y-m-d H:i:s' ),
				'ends_at_gmt'     => $ends_local->format( 'Y-m-d H:i:s' ),
				'source_timezone' => $tz_name,
				'max_attendees'   => $max,
				'meeting_link'    => $link,
				'repeat'          => $repeat,
				'repeat_count'    => $count,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'session_ids' => $result ) );
	}

	public static function ajax_update_session() {
		self::verify_request();
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing session ID.', 'wp-exam-success' ) ) );
		}

		$fields = array();
		foreach ( array( 'title', 'level', 'description', 'meeting_link', 'status' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$fields[ $key ] = 'description' === $key
					? wp_kses_post( wp_unslash( $_POST[ $key ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		if ( isset( $_POST['max_attendees'] ) ) {
			$fields['max_attendees'] = max( 1, (int) $_POST['max_attendees'] );
		}
		if ( isset( $_POST['assigned_teacher_id'] ) ) {
			$fields['assigned_teacher_id'] = (int) $_POST['assigned_teacher_id'];
		}

		$date    = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$start   = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) );
		$end     = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) );
		$tz_name = sanitize_text_field( wp_unslash( $_POST['timezone'] ?? wp_timezone_string() ) );

		if ( $date && $start && $end ) {
			try {
				$tz = new DateTimeZone( $tz_name );
			} catch ( Exception $e ) {
				$tz      = wp_timezone();
				$tz_name = wp_timezone_string();
			}

			try {
				$starts_local = new DateTime( "{$date} {$start}", $tz );
				$ends_local   = new DateTime( "{$date} {$end}", $tz );
			} catch ( Exception $e ) {
				wp_send_json_error( array( 'message' => __( 'Invalid date/time.', 'wp-exam-success' ) ) );
			}

			$starts_local->setTimezone( new DateTimeZone( 'UTC' ) );
			$ends_local->setTimezone( new DateTimeZone( 'UTC' ) );

			$fields['starts_at_gmt']   = $starts_local->format( 'Y-m-d H:i:s' );
			$fields['ends_at_gmt']     = $ends_local->format( 'Y-m-d H:i:s' );
			$fields['source_timezone'] = $tz_name;
		}

		// Was this save the moment the session actually became newly
		// assigned? Check before writing so a re-save of an unchanged
		// assignment doesn't re-fire the confirmation email/payment-
		// capture side effects.
		$newly_assigned = false;
		if ( array_key_exists( 'assigned_teacher_id', $fields ) && $fields['assigned_teacher_id'] ) {
			$before         = WPES_Sessions::get( $id );
			$newly_assigned = $before && empty( $before->assigned_teacher_id );
		}

		WPES_Sessions::update( $id, $fields );

		if ( $newly_assigned ) {
			WPES_Teacher_Invites::finalize_session_confirmation( $id );
		}

		wp_send_json_success();
	}

	public static function ajax_cancel_series() {
		self::verify_request();
		$series_id = sanitize_text_field( wp_unslash( $_POST['series_id'] ?? '' ) );
		if ( ! $series_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing series ID.', 'wp-exam-success' ) ) );
		}
		wp_send_json_success( array( 'affected' => WPES_Sessions::cancel_series_future( $series_id ) ) );
	}

	public static function ajax_bulk_archive_sessions() {
		self::verify_request();
		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No sessions selected.', 'wp-exam-success' ) ) );
		}
		wp_send_json_success( array( 'affected' => WPES_Sessions::bulk_archive( $ids ) ) );
	}

	public static function ajax_manual_enroll() {
		self::verify_request();
		$session_id = (int) ( $_POST['session_id'] ?? 0 );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$result     = WPES_Bookings::manual_enroll( $session_id, $email );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// manual_enroll() confirms the booking immediately (no order to
		// wait on) — it can push the session past its minimum the same
		// way a paid booking does, so re-check here too.
		WPES_Teacher_Invites::maybe_invite_teachers( $session_id );

		wp_send_json_success( array( 'booking_id' => $result ) );
	}

	public static function ajax_send_meeting_link() {
		self::verify_request();
		$result = WPES_Meeting_Links::send_for_session(
			(int) ( $_POST['session_id'] ?? 0 ),
			sanitize_key( $_POST['send_type'] ?? 'initial' )
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'recipient_count' => $result,
				'message'         => sprintf(
					__( 'Meeting link sent to %d attendee(s).', 'wp-exam-success' ),
					$result
				),
			)
		);
	}

	public static function ajax_session_attendees() {
		self::verify_request();
		$session_id = (int) ( $_POST['session_id'] ?? 0 );
		$attendees  = WPES_Bookings::get_attendees_for_session( $session_id, array( 'confirmed', 'pending' ) );
		$log        = WPES_Meeting_Links::get_log_for_session( $session_id );
		ob_start();
		include WPES_PLUGIN_DIR . 'admin/views/partials/attendees-modal.php';
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	public static function ajax_get_session() {
		self::verify_request();
		$session = WPES_Sessions::get( (int) ( $_POST['id'] ?? 0 ) );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'wp-exam-success' ) ) );
		}

		$tz_name = ! empty( $session->source_timezone ) ? $session->source_timezone : wp_timezone_string();
		try {
			$tz = new DateTimeZone( $tz_name );
		} catch ( Exception $e ) {
			$tz      = wp_timezone();
			$tz_name = wp_timezone_string();
		}

		try {
			$starts = new DateTime( $session->starts_at_gmt, new DateTimeZone( 'UTC' ) );
			$ends   = new DateTime( $session->ends_at_gmt, new DateTimeZone( 'UTC' ) );
			$starts->setTimezone( $tz );
			$ends->setTimezone( $tz );
			$session->local_date     = $starts->format( 'Y-m-d' );
			$session->local_start    = $starts->format( 'H:i' );
			$session->local_end      = $ends->format( 'H:i' );
			$session->local_timezone = $tz_name;
		} catch ( Exception $e ) {
			$session->local_date     = '';
			$session->local_start    = '';
			$session->local_end      = '';
			$session->local_timezone = $tz_name;
		}

		wp_send_json_success( array( 'session' => $session ) );
	}

	public static function ajax_datatable_classes() {
		self::verify_request();
		$draw   = (int) ( $_POST['draw'] ?? 1 );
		$start  = (int) ( $_POST['start'] ?? 0 );
		$length = (int) ( $_POST['length'] ?? 25 );
		$search = sanitize_text_field( wp_unslash( $_POST['search']['value'] ?? '' ) );
		$status = sanitize_key( $_POST['status_filter'] ?? '' );

		$all = WPES_Classes::get_all( $status ?: null );
		if ( $search ) {
			$all = array_filter(
				$all,
				function ( $c ) use ( $search ) {
					return false !== stripos( $c->name, $search ) || false !== stripos( $c->description, $search );
				}
			);
		}
		$total    = count( $all );
		$slice    = array_slice( array_values( $all ), $start, $length );
		$data     = array();

		foreach ( $slice as $class ) {
			$actions  = '<button type="button" class="btn btn-sm btn-outline-primary wpes-edit-class" data-id="' . esc_attr( $class->id ) . '" data-name="' . esc_attr( $class->name ) . '" data-description="' . esc_attr( $class->description ) . '" data-status="' . esc_attr( $class->status ) . '">' . esc_html__( 'Edit', 'wp-exam-success' ) . '</button> ';
			if ( 'archived' !== $class->status ) {
				$actions .= '<button type="button" class="btn btn-sm btn-outline-warning wpes-archive-class" data-id="' . esc_attr( $class->id ) . '">' . esc_html__( 'Archive', 'wp-exam-success' ) . '</button> ';
			}
			$actions .= '<button type="button" class="btn btn-sm btn-outline-danger wpes-delete-class" data-id="' . esc_attr( $class->id ) . '">' . esc_html__( 'Delete', 'wp-exam-success' ) . '</button>';
			$data[] = array(
				esc_html( $class->name ),
				wp_trim_words( wp_strip_all_tags( $class->description ), 12 ),
				'<span class="badge bg-' . ( 'active' === $class->status ? 'success' : 'secondary' ) . '">' . esc_html( ucfirst( $class->status ) ) . '</span>',
				$actions,
			);
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
				'data'            => $data,
			)
		);
	}

	public static function ajax_datatable_sessions() {
		self::verify_request();
		$draw     = (int) ( $_POST['draw'] ?? 1 );
		$start    = (int) ( $_POST['start'] ?? 0 );
		$length   = max( 1, (int) ( $_POST['length'] ?? 25 ) );
		$search   = sanitize_text_field( wp_unslash( $_POST['search']['value'] ?? '' ) );
		$class_id = (int) ( $_POST['class_filter'] ?? 0 );
		$status   = sanitize_key( $_POST['status_filter'] ?? '' );
		$page     = (int) floor( $start / $length ) + 1;

		$args = array(
			'search'   => $search,
			'class_id' => $class_id,
			'status'   => $status,
			'per_page' => $length,
			'page'     => $page,
		);

		$total    = WPES_Sessions::count_query( $args );
		$sessions = WPES_Sessions::query( $args );
		$data     = array();

		foreach ( $sessions as $session ) {
			$site_time = get_date_from_gmt( $session->starts_at_gmt, 'M j, Y g:i A' );
			$full      = ( (int) $session->remaining <= 0 );
			$link_html = ! empty( $session->meeting_link )
				? '<a href="' . esc_url( $session->meeting_link ) . '" target="_blank">' . esc_html__( 'Link set', 'wp-exam-success' ) . '</a>'
				: '<span class="text-danger">' . esc_html__( 'Not set', 'wp-exam-success' ) . '</span>';

			$actions  = '<input type="checkbox" class="wpes-session-check form-check-input me-1" value="' . esc_attr( $session->id ) . '" />';
			$actions .= '<button type="button" class="btn btn-sm btn-outline-secondary wpes-view-attendees" data-id="' . esc_attr( $session->id ) . '">' . esc_html__( 'Attendees', 'wp-exam-success' ) . '</button> ';
			$actions .= '<button type="button" class="btn btn-sm btn-outline-primary wpes-send-link" data-id="' . esc_attr( $session->id ) . '" data-type="initial">' . esc_html__( 'Send Link', 'wp-exam-success' ) . '</button> ';
			$actions .= '<button type="button" class="btn btn-sm btn-outline-primary wpes-send-link" data-id="' . esc_attr( $session->id ) . '" data-type="resend">' . esc_html__( 'Resend', 'wp-exam-success' ) . '</button> ';
			$actions .= '<button type="button" class="btn btn-sm btn-outline-success wpes-enroll-btn" data-id="' . esc_attr( $session->id ) . '">' . esc_html__( 'Enroll', 'wp-exam-success' ) . '</button> ';
			$actions .= '<button type="button" class="btn btn-sm btn-outline-primary wpes-edit-session" data-id="' . esc_attr( $session->id ) . '" data-title="' . esc_attr( $session->title ) . '" data-level="' . esc_attr( $session->level ) . '" data-max="' . esc_attr( $session->max_attendees ) . '" data-link="' . esc_url( $session->meeting_link ) . '" data-class-id="' . esc_attr( $session->class_id ) . '" data-teacher-id="' . esc_attr( $session->assigned_teacher_id ) . '">' . esc_html__( 'Edit', 'wp-exam-success' ) . '</button>';

			$teacher_html = ! empty( $session->teacher_name )
				? esc_html( $session->teacher_name )
				: '<span class="text-muted">' . esc_html__( 'Unassigned', 'wp-exam-success' ) . '</span>';

			$data[] = array(
				esc_html( $session->class_name ),
				esc_html( $session->title ?: '—' ),
				esc_html( $site_time ),
				'<span class="badge bg-' . ( $full ? 'danger' : 'success' ) . '">' . esc_html( $session->booked . ' / ' . $session->max_attendees ) . '</span>',
				$link_html,
				$teacher_html,
				'<span class="badge bg-' . ( 'scheduled' === $session->status ? 'success' : 'secondary' ) . '">' . esc_html( ucfirst( $session->status ) ) . '</span>',
				$actions,
			);
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
				'data'            => $data,
			)
		);
	}

	public static function ajax_datatable_bookings() {
		self::verify_request();
		$draw     = (int) ( $_POST['draw'] ?? 1 );
		$start    = (int) ( $_POST['start'] ?? 0 );
		$length   = max( 1, (int) ( $_POST['length'] ?? 25 ) );
		$search   = sanitize_text_field( wp_unslash( $_POST['search']['value'] ?? '' ) );
		$class_id = (int) ( $_POST['class_filter'] ?? 0 );
		$tab      = sanitize_key( $_POST['tab_filter'] ?? 'active' );
		$page     = (int) floor( $start / $length ) + 1;

		$args = array(
			'search'   => $search,
			'class_id' => $class_id,
			'per_page' => $length,
			'page'     => $page,
		);

		if ( 'pending' === $tab ) {
			$args['status'] = 'pending';
		} elseif ( 'on-hold' === $tab ) {
			$args['status'] = 'on-hold';
		} elseif ( 'cancelled' === $tab ) {
			$args['status'] = 'cancelled';
		} else {
			// Active: confirmed only — hide pending, on-hold, cancelled, and expired.
			$args['status'] = 'confirmed';
		}

		$total    = WPES_Bookings::count_report( $args );
		$bookings = WPES_Bookings::query_report( $args );
		$data     = array();

		$badges = array(
			'confirmed' => 'bg-success',
			'pending'   => 'bg-warning text-dark',
			'on-hold'   => 'bg-info text-dark',
			'cancelled' => 'bg-secondary',
			'expired'   => 'bg-secondary',
		);

		foreach ( $bookings as $booking ) {
			$badge    = $badges[ $booking->status ] ?? 'bg-secondary';
			$order_id = (int) $booking->order_id;
			if ( ! $order_id && ! empty( $booking->order_item_id ) && function_exists( 'wc_get_order_id_by_order_item_id' ) ) {
				$order_id = (int) wc_get_order_id_by_order_item_id( (int) $booking->order_item_id );
				if ( $order_id ) {
					// Backfill so future loads and order-based lookups work.
					global $wpdb;
					$wpdb->update(
						WPES_DB::bookings_table(),
						array( 'order_id' => $order_id, 'updated_at' => WPES_DB::now_gmt() ),
						array( 'id' => (int) $booking->id ),
						array( '%d', '%s' ),
						array( '%d' )
					);
				}
			}

			$order = '—';
			if ( $order_id ) {
				$wc_order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
				$url      = $wc_order ? $wc_order->get_edit_order_url() : admin_url( 'post.php?post=' . $order_id . '&action=edit' );
				$label    = $wc_order ? $wc_order->get_order_number() : (string) $order_id;
				$order    = '<a href="' . esc_url( $url ) . '">#' . esc_html( $label ) . '</a>';
			}

			$data[] = array(
				esc_html( $booking->customer_name ?: '—' ),
				esc_html( $booking->customer_email ?: '—' ),
				esc_html( $booking->class_name ),
				esc_html( $booking->session_title ?: $booking->class_name ),
				esc_html( get_date_from_gmt( $booking->starts_at_gmt, 'M j, Y g:i A' ) ),
				'<span class="badge ' . esc_attr( $badge ) . '">' . esc_html( ucfirst( $booking->status ) ) . '</span>',
				$order,
				esc_html( get_date_from_gmt( $booking->created_at, 'M j, Y g:i A' ) ),
			);
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
				'data'            => $data,
			)
		);
	}

	public static function ajax_datatable_waitlist() {
		self::verify_request();
		WPES_Waitlist::ensure_table();

		$draw      = (int) ( $_POST['draw'] ?? 1 );
		$start     = (int) ( $_POST['start'] ?? 0 );
		$length    = max( 1, (int) ( $_POST['length'] ?? 25 ) );
		$search    = sanitize_text_field( wp_unslash( $_POST['search']['value'] ?? '' ) );
		$form_name = sanitize_text_field( wp_unslash( $_POST['form_filter'] ?? '' ) );
		$page      = (int) floor( $start / $length ) + 1;

		$args = array(
			'search'    => $search,
			'form_name' => $form_name,
			'per_page'  => $length,
			'page'      => $page,
		);

		$total   = WPES_Waitlist::count( $args );
		$entries = WPES_Waitlist::query( $args );
		$data    = array();

		foreach ( $entries as $entry ) {
			$fields_preview = '—';
			$decoded        = json_decode( (string) $entry->fields, true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				$bits = array();
				foreach ( $decoded as $field ) {
					$title = ! empty( $field['title'] ) ? $field['title'] : ( $field['id'] ?? '' );
					$value = isset( $field['value'] ) ? $field['value'] : '';
					if ( is_array( $value ) ) {
						$value = implode( ', ', $value );
					}
					$bits[] = esc_html( $title ) . ': ' . esc_html( (string) $value );
				}
				$fields_preview = '<div class="small text-muted wpes-waitlist-fields">' . implode( '<br>', $bits ) . '</div>';
			}

			$is_notified    = isset( $entry->is_notified ) ? (int) $entry->is_notified : 0;
			$notified_on    = isset( $entry->notified_on ) ? (string) $entry->notified_on : '';

			if ( $is_notified && '' !== $notified_on && '0000-00-00 00:00:00' !== $notified_on ) {
				$notified_cell  = '<span class="wpes-notified-pill wpes-notified-pill--yes">' .
					'<span class="wpes-notified-dot" aria-hidden="true"></span>' .
					'<span class="wpes-notified-label">' . esc_html__( 'Notified', 'wp-exam-success' ) . '</span>' .
					'</span>' .
					'<div class="wpes-notified-on">' . esc_html( get_date_from_gmt( $notified_on, 'M j, Y g:i A' ) ) . '</div>';
			} else {
				$notified_cell  = '<span class="wpes-notified-pill wpes-notified-pill--no">' .
					'<span class="wpes-notified-dot" aria-hidden="true"></span>' .
					'<span class="wpes-notified-label">' . esc_html__( 'Pending', 'wp-exam-success' ) . '</span>' .
					'</span>';
			}

			$actions  = '<div class="wpes-waitlist-actions"><button type="button" class="btn btn-sm btn-outline-primary wpes-waitlist-message" data-id="' . esc_attr( $entry->id ) . '" data-email="' . esc_attr( $entry->email ) . '" data-name="' . esc_attr( $entry->name ) . '">' . esc_html__( 'Message', 'wp-exam-success' ) . '</button> ';
			$actions .= '<button type="button" class="btn btn-sm btn-outline-secondary wpes-waitlist-view" data-fields-b64="' . esc_attr( base64_encode( (string) $entry->fields ) ) . '">' . esc_html__( 'Details', 'wp-exam-success' ) . '</button> ';
			$actions .= '<button type="button" class="btn btn-sm btn-outline-danger wpes-waitlist-delete" data-id="' . esc_attr( $entry->id ) . '" data-name="' . esc_attr( $entry->name ) . '">' . esc_html__( 'Delete', 'wp-exam-success' ) . '</button></div>';

			$data[] = array(
				'<input type="checkbox" class="form-check-input wpes-waitlist-check" value="' . esc_attr( $entry->id ) . '" />',
				esc_html( $entry->name ?: '—' ),
				esc_html( $entry->email ?: '—' ),
				esc_html( $entry->form_name ?: '—' ),
				$fields_preview,
				esc_html( get_date_from_gmt( $entry->created_at, 'M j, Y g:i A' ) ),
				$notified_cell,
				$actions,
			);
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
				'data'            => $data,
			)
		);
	}

	public static function ajax_datatable_teacher_invites() {
		self::verify_request();
		$draw   = (int) ( $_POST['draw'] ?? 1 );
		$start  = (int) ( $_POST['start'] ?? 0 );
		$length = max( 1, (int) ( $_POST['length'] ?? 25 ) );
		$search = sanitize_text_field( wp_unslash( $_POST['search']['value'] ?? '' ) );
		$status = sanitize_key( $_POST['status_filter'] ?? '' );
		$page   = (int) floor( $start / $length ) + 1;

		$args = array(
			'search'   => $search,
			'status'   => $status,
			'per_page' => $length,
			'page'     => $page,
		);

		$total   = WPES_Teacher_Invites::count_query( $search, $status );
		$invites = WPES_Teacher_Invites::query( $args );
		$data    = array();

		$badges = array(
			'pending'    => 'bg-warning text-dark',
			'accepted'   => 'bg-success',
			'expired'    => 'bg-secondary',
			'superseded' => 'bg-secondary',
		);

		foreach ( $invites as $invite ) {
			$session_label = trim( ( $invite->class_name ? $invite->class_name . ' — ' : '' ) . ( $invite->session_title ?: __( '(untitled session)', 'wp-exam-success' ) ) );
			$badge         = $badges[ $invite->status ] ?? 'bg-secondary';

			$data[] = array(
				esc_html( $session_label ) . '<div class="small text-muted">' . esc_html( get_date_from_gmt( $invite->session_starts_at_gmt, 'M j, Y g:i A' ) ) . '</div>',
				esc_html( $invite->teacher_name ) . '<div class="small text-muted">' . esc_html( $invite->teacher_email ) . '</div>',
				'<span class="badge ' . esc_attr( $badge ) . '">' . esc_html( ucfirst( $invite->status ) ) . '</span>',
				esc_html( get_date_from_gmt( $invite->created_at, 'M j, Y g:i A' ) ),
				esc_html( get_date_from_gmt( $invite->expires_at, 'M j, Y g:i A' ) ),
				! empty( $invite->responded_at ) ? esc_html( get_date_from_gmt( $invite->responded_at, 'M j, Y g:i A' ) ) : '—',
			);
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
				'data'            => $data,
			)
		);
	}

	public static function ajax_datatable_credits() {
		self::verify_request();
		$draw   = (int) ( $_POST['draw'] ?? 1 );
		$start  = (int) ( $_POST['start'] ?? 0 );
		$length = max( 1, (int) ( $_POST['length'] ?? 25 ) );
		$search = sanitize_text_field( wp_unslash( $_POST['search']['value'] ?? '' ) );
		$status = sanitize_key( $_POST['status_filter'] ?? '' );
		$page   = (int) floor( $start / $length ) + 1;

		$args = array(
			'search'   => $search,
			'status'   => $status,
			'per_page' => $length,
			'page'     => $page,
		);

		$total   = WPES_Replacements::count_query( $search, $status );
		$credits = WPES_Replacements::query( $args );
		$data    = array();

		foreach ( $credits as $credit ) {
			$source_label = trim( ( $credit->source_class_name ? $credit->source_class_name . ' — ' : '' ) . ( $credit->source_title ?: '' ) );
			$source_cell  = $source_label
				? esc_html( $source_label ) . '<div class="small text-muted">' . esc_html( get_date_from_gmt( $credit->source_starts_at_gmt, 'M j, Y g:i A' ) ) . '</div>'
				: '—';

			if ( 'used' === $credit->status && ! empty( $credit->used_session_id ) ) {
				$used_label  = trim( ( $credit->used_class_name ? $credit->used_class_name . ' — ' : '' ) . ( $credit->used_title ?: '' ) );
				$redeemed_for = esc_html( $used_label ) . '<div class="small text-muted">' . esc_html( get_date_from_gmt( $credit->used_starts_at_gmt, 'M j, Y g:i A' ) ) . '</div>';
			} else {
				$redeemed_for = '<span class="text-muted">' . esc_html__( 'Not yet redeemed', 'wp-exam-success' ) . '</span>';
			}

			$data[] = array(
				esc_html( $credit->customer_name ?: '—' ) . '<div class="small text-muted">' . esc_html( $credit->customer_email ) . '</div>',
				$source_cell,
				'<span class="badge bg-' . ( 'available' === $credit->status ? 'success' : 'secondary' ) . '">' . esc_html( ucfirst( $credit->status ) ) . '</span>',
				esc_html( get_date_from_gmt( $credit->created_at, 'M j, Y g:i A' ) ),
				$redeemed_for,
			);
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
				'data'            => $data,
			)
		);
	}

	public static function ajax_waitlist_send_message() {
		self::verify_request();
		WPES_Waitlist::ensure_table();

		$subject      = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message      = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
		$all_filtered = ! empty( $_POST['all_filtered'] );
		$ids          = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$search       = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$form_name    = isset( $_POST['form_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['form_filter'] ) ) : '';

		if ( '' === $subject || '' === trim( wp_strip_all_tags( $message ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Subject and message are required.', 'wp-exam-success' ) ) );
		}

		$entries = WPES_Waitlist::get_for_messaging(
			array(
				'ids'          => $ids,
				'all_filtered' => $all_filtered,
				'search'       => $search,
				'form_name'    => $form_name,
			)
		);

		if ( empty( $entries ) ) {
			wp_send_json_error( array( 'message' => __( 'No recipients found with a valid email address.', 'wp-exam-success' ) ) );
		}

		$result = WPES_Waitlist::send_messages( $entries, $subject, $message );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: sent count, 2: failed count */
					__( 'Sent %1$d message(s). Failed: %2$d.', 'wp-exam-success' ),
					$result['sent'],
					$result['failed']
				),
				'sent'    => $result['sent'],
				'failed'  => $result['failed'],
			)
		);
	}

	public static function ajax_waitlist_delete() {
	self::verify_request();
	WPES_Waitlist::ensure_table();

	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid waitlist entry ID.', 'wp-exam-success' ) ) );
	}

	$entry = WPES_Waitlist::get( $id );
	if ( ! $entry ) {
		wp_send_json_error( array( 'message' => __( 'Waitlist entry not found.', 'wp-exam-success' ) ) );
	}

	$deleted = WPES_Waitlist::delete( $id );
	if ( false === $deleted ) {
		wp_send_json_error( array( 'message' => __( 'Could not delete the waitlist entry.', 'wp-exam-success' ) ) );
	}

	wp_send_json_success(
		array(
			'message' => __( 'Waitlist entry deleted.', 'wp-exam-success' ),
			'id'      => $id,
		)
	);
	}
}
