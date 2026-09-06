<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Passwordless magic-link login for customers accessing their sessions.
 */
class WPES_Magic_Login {

	const TOKEN_TTL_MINUTES = 30;
	const NONCE_ACTION      = 'wpes_magic_login';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_login_token' ), 5 );
		add_action( 'wp_ajax_wpes_request_magic_link', array( __CLASS__, 'ajax_request_link' ) );
		add_action( 'wp_ajax_nopriv_wpes_request_magic_link', array( __CLASS__, 'ajax_request_link' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		wp_enqueue_style( 'bulma', 'https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css', array(), '1.0.2' );
		wp_enqueue_style( 'wpes-access', WPES_PLUGIN_URL . 'public/css/wpes-access.css', array( 'bulma' ), WPES_VERSION );
		wp_enqueue_script( 'wpes-access', WPES_PLUGIN_URL . 'public/js/wpes-access.js', array( 'jquery' ), filemtime( WPES_PLUGIN_DIR . 'public/js/wpes-access.js' ), true );
		wp_localize_script(
			'wpes-access',
			'wpesAccess',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
				'accountUrl' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
				'isLoggedIn' => is_user_logged_in(),
				'i18n'       => array(
					'sendLink'     => __( 'Send my Secure Link', 'wp-exam-success' ),
					'sending'      => __( 'Sending…', 'wp-exam-success' ),
					'sent'         => __( 'Check your email for a secure login link. It expires in 30 minutes.', 'wp-exam-success' ),
					'notFound'     => __( 'We could not find an account with that email address.', 'wp-exam-success' ),
					'invalidEmail' => __( 'Please enter a valid email address.', 'wp-exam-success' ),
					'error'        => __( 'Something went wrong. Please try again.', 'wp-exam-success' ),
					'title'        => __( 'Access My Sessions', 'wp-exam-success' ),
					'subtitle'     => __( 'Enter your email and we will send you a secure login link.', 'wp-exam-success' ),
				),
			)
		);
	}

	public static function ajax_request_link() {
		// check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wp-exam-success' ) ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'We could not find an account with that email address.', 'wp-exam-success' ) ) );
		}

		$token = self::create_token( $user->ID );
		if ( is_wp_error( $token ) ) {
			wp_send_json_error( array( 'message' => $token->get_error_message() ) );
		}

		$url = add_query_arg( 'wpes_login', rawurlencode( $token ), wc_get_page_permalink( 'myaccount' ) );
		WPES_Emailer::send_magic_login( $user, $url );

		wp_send_json_success( array( 'message' => __( 'Check your email for a secure login link. It expires in 30 minutes.', 'wp-exam-success' ) ) );
	}

	/**
	 * Create and store a magic login token.
	 *
	 * @param int $user_id User ID.
	 * @return string|WP_Error Raw token string.
	 */
	public static function create_token( $user_id ) {
		global $wpdb;

		$token      = wp_generate_password( 48, false );
		$token_hash = hash( 'sha256', $token );
		$expires    = gmdate( 'Y-m-d H:i:s', time() + ( self::TOKEN_TTL_MINUTES * MINUTE_IN_SECONDS ) );
		$now        = WPES_DB::now_gmt();

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'wpes_magic_tokens',
			array(
				'user_id'    => $user_id,
				'token_hash' => $token_hash,
				'expires_at' => $expires,
				'created_at' => $now,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'wpes_token_failed', __( 'Could not create login link.', 'wp-exam-success' ) );
		}

		return $token;
	}

	/**
	 * Validate token from URL and log the user in.
	 */
	public static function handle_login_token() {
		if ( empty( $_GET['wpes_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['wpes_login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$hash  = hash( 'sha256', $token );

		global $wpdb;
		$table = $wpdb->prefix . 'wpes_magic_tokens';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE token_hash = %s AND expires_at > %s",
				$hash,
				WPES_DB::now_gmt()
			)
		);

		if ( ! $row ) {
			wc_add_notice( __( 'This login link has expired or is invalid. Please request a new one.', 'wp-exam-success' ), 'error' );
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		$user = get_user_by( 'id', (int) $row->user_id );
		if ( ! $user ) {
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		$wpdb->delete( $table, array( 'id' => $row->id ), array( '%d' ) );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}
}
