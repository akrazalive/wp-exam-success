<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend "Schedule & Booking" experience.
 */
class WPES_Public {

	const NONCE_ACTION           = 'wpes_public';
	const UNLIMITED_THRESHOLD    = 99;
	const UNLIMITED_MIN_SESSIONS = 8;

	public static function init() {
		add_shortcode( 'wpes_booking_calendar', array( __CLASS__, 'render_shortcode' ) );

		add_action( 'wp_ajax_wpes_get_calendar', array( __CLASS__, 'ajax_get_calendar' ) );
		add_action( 'wp_ajax_nopriv_wpes_get_calendar', array( __CLASS__, 'ajax_get_calendar' ) );

		add_action( 'wp_ajax_wpes_get_packages', array( __CLASS__, 'ajax_get_packages' ) );
		add_action( 'wp_ajax_nopriv_wpes_get_packages', array( __CLASS__, 'ajax_get_packages' ) );

		add_action( 'init', array( __CLASS__, 'handle_teacher_accept' ), 5 );

		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Handle a teacher clicking their Accept-Link (?wpes_teacher_accept=<token>).
	 * No login required — the token itself is the credential, the same
	 * way WPES_Magic_Login's customer link works.
	 */
	public static function handle_teacher_accept() {
		if ( empty( $_GET['wpes_teacher_accept'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['wpes_teacher_accept'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$outcome = WPES_Teacher_Invites::handle_accept( $token );

		switch ( $outcome['result'] ) {
			case 'accepted':
				$session = $outcome['session'];
				$when    = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';
				wp_die(
					sprintf(
						/* translators: %s: session date and time */
						esc_html__( "You're confirmed to teach this session on %s. A confirmation email is on its way.", 'wp-exam-success' ),
						esc_html( $when )
					),
					esc_html__( 'Session Accepted', 'wp-exam-success' ),
					array( 'response' => 200 )
				);
				break;

			case 'already_assigned':
				wp_die(
					esc_html__( 'This session has already been assigned to another teacher. Thank you for responding.', 'wp-exam-success' ),
					esc_html__( 'Already Assigned', 'wp-exam-success' ),
					array( 'response' => 200 )
				);
				break;

			case 'minimum_no_longer_met':
				wp_die(
					esc_html__( 'Thank you for responding — since this invitation was sent, enough participants have cancelled that this session no longer meets the minimum required to proceed. No assignment is needed at this time.', 'wp-exam-success' ),
					esc_html__( 'No Longer Needed', 'wp-exam-success' ),
					array( 'response' => 200 )
				);
				break;

			default:
				wp_die(
					esc_html__( 'This invitation link is invalid or has expired.', 'wp-exam-success' ),
					esc_html__( 'Link Expired', 'wp-exam-success' ),
					array( 'response' => 200 )
				);
				break;
		}
	}

	public static function body_class( $classes ) {
		$classes[] = 'theme-light'; // Default to light theme.
		return $classes;
	}

	public static function render_shortcode( $atts = array() ) {
		self::enqueue_assets();

		$classes = class_exists( 'WPES_Classes' ) ? WPES_Classes::get_all( 'active' ) : array();
		$filters = class_exists( 'WPES_Admin' ) ? WPES_Admin::get_frontend_filters() : array(
			'class_id'     => true,
			'level'        => true,
			'day'          => true,
			'time'         => true,
			'availability' => true,
		);

		ob_start();
		include WPES_PLUGIN_DIR . 'public/partials/booking-calendar.php';
		return ob_get_clean();
	}

	protected static function enqueue_assets() {
		wp_enqueue_script( 'moment', 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js', array(), '2.30.1', true );
		wp_enqueue_script( 'moment-timezone', 'https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.45/moment-timezone-with-data.min.js', array( 'moment' ), '0.5.45', true );
		wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js', array(), '11', true );

		wp_enqueue_style( 'litepicker', 'https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css', array(), '2.0.12' );
		wp_enqueue_script( 'litepicker', 'https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js', array(), '2.0.12', true );

		wp_enqueue_style( 'wpes-schedule-booking', WPES_PLUGIN_URL . 'public/css/wpes-public.css', array(), filemtime( WPES_PLUGIN_DIR . 'public/css/wpes-public.css' ) );
		wp_enqueue_script( 'wpes-schedule-booking', WPES_PLUGIN_URL . 'public/js/wpes-public.js', array( 'jquery', 'moment-timezone', 'sweetalert2', 'litepicker' ), filemtime( WPES_PLUGIN_DIR . 'public/js/wpes-public.js' ), true );

		$filters = class_exists( 'WPES_Admin' ) ? WPES_Admin::get_frontend_filters() : array();

		wp_localize_script(
			'wpes-schedule-booking',
			'wpesBooking',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'unlimitedAt'  => self::UNLIMITED_THRESHOLD,
				'unlimitedMin' => self::UNLIMITED_MIN_SESSIONS,
				'cartUrl'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'checkoutUrl'  => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
				'filters'      => $filters,
				'i18n'         => array(
					'available'         => __( 'Available', 'wp-exam-success' ),
					'remainingOf'       => __( '%1$d of %2$d remaining', 'wp-exam-success' ),
					'fullyBooked'       => __( 'Fully Booked', 'wp-exam-success' ),
					'full'              => __( 'Fully Booked', 'wp-exam-success' ),
					'noSessions'        => __( 'No sessions this week matching your filters.', 'wp-exam-success' ),
					'chooseSelect'      => __( 'Select a package to continue', 'wp-exam-success' ),
					'selected'          => __( 'Selected', 'wp-exam-success' ),
					'choosePackage'     => __( 'Choose Package', 'wp-exam-success' ),
					'sessionsOf'        => __( '%1$d of %2$d sessions selected', 'wp-exam-success' ),
					'sessionsUnlimited' => __( '%1$d sessions selected (min %2$d)', 'wp-exam-success' ),
					'checkout'          => __( 'Checkout', 'wp-exam-success' ),
					'changePackage'     => __( 'Change package', 'wp-exam-success' ),
					'loadError'         => __( 'Could not load the schedule. Please try again.', 'wp-exam-success' ),
					'loading'           => __( 'Loading sessions…', 'wp-exam-success' ),
					'filtering'         => __( 'Applying filters…', 'wp-exam-success' ),
					'sessionDetails'    => __( 'Session Details', 'wp-exam-success' ),
					'classDescription'  => __( 'About this class', 'wp-exam-success' ),
					'sessionDescription'=> __( 'About this session', 'wp-exam-success' ),
					'close'             => __( 'Close', 'wp-exam-success' ),
					'tzUpdated'         => __( 'Your timezone has been updated to %s based on your location.', 'wp-exam-success' ),
					'tzUpdatedTitle'    => __( 'Timezone updated', 'wp-exam-success' ),
				),
			)
		);
	}

	public static function ajax_get_calendar() {
		// check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$start_gmt = isset( $_POST['start_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['start_gmt'] ) ) : '';
		$end_gmt   = isset( $_POST['end_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['end_gmt'] ) ) : '';
		$class_id  = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$level     = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';

		if ( ! self::is_valid_gmt_datetime( $start_gmt ) || ! self::is_valid_gmt_datetime( $end_gmt ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date range.', 'wp-exam-success' ) ), 400 );
		}

		$sessions = WPES_Sessions::get_calendar(
			array(
				'start_gmt' => $start_gmt,
				'end_gmt'   => $end_gmt,
				'class_id'  => $class_id,
				'level'     => $level,
			)
		);

		$out = array();
		foreach ( $sessions as $session ) {
			$max       = (int) $session->max_attendees;
			$remaining = max( 0, (int) $session->remaining );

			$out[] = array(
				'id'                 => (int) $session->id,
				'class_id'           => (int) $session->class_id,
				'class_name'         => $session->class_name,
				'class_description'  => $session->class_description ?? '',
				'description'        => $session->description ?? '',
				'icon_index'         => ( (int) $session->class_id ) % 6,
				'title'              => $session->title ? $session->title : $session->class_name,
				'level'              => $session->level,
				'starts_at_gmt'      => ! empty( $session->starts_at_gmt ) ? ( new DateTime( $session->starts_at_gmt, new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d\TH:i:s\Z' ) : '',
				'ends_at_gmt'        => ! empty( $session->ends_at_gmt ) ? ( new DateTime( $session->ends_at_gmt, new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d\TH:i:s\Z' ) : '',
				'max_attendees'      => $max,
				'remaining'          => $remaining,
				'status'             => $session->status,
			);
		}

		wp_send_json_success( array( 'sessions' => $out ) );
	}

	protected static function is_valid_gmt_datetime( $value ) {
		if ( empty( $value ) ) {
			return false;
		}
		return false !== strtotime( $value );
	}

	public static function ajax_get_packages() {
		// check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! function_exists( 'wc_get_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Store is unavailable right now.', 'wp-exam-success' ) ), 500 );
		}

		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => -1,
				'category' => array( 'packages' ),
				'orderby'  => 'menu_order',
				'order'    => 'ASC',
			)
		);

		$out = array();
		foreach ( $products as $product ) {
			if ( 'yes' !== get_post_meta( $product->get_id(), '_wpes_is_package', true ) ) {
				continue;
			}

			$required  = (int) get_post_meta( $product->get_id(), '_wpes_sessions_required', true );
			$unlimited = $required > self::UNLIMITED_THRESHOLD;

			$out[] = array(
				'id'          => $product->get_id(),
				'name'        => $product->get_name(),
				'price_html'  => $product->get_price_html(),
				'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
				'required'    => $unlimited ? 0 : $required,
				'min'         => $unlimited ? self::UNLIMITED_MIN_SESSIONS : $required,
				'unlimited'   => $unlimited,
			);
		}

		wp_send_json_success( array( 'packages' => $out ) );
	}
}
