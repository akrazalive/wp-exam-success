<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce My Account customisation — sessions-only dashboard.
 */
class WPES_My_Account {

	const REDEEM_NONCE_ACTION = 'wpes_redeem_credit';

	public static function init() {
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'filter_menu_items' ) );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_dashboard' ), 1 );
		remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard_content' );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_wpes_redeem_credit', array( __CLASS__, 'handle_redeem_credit' ) );
	}

	/**
	 * Remove default account tabs; keep dashboard + logout only.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function filter_menu_items( $items ) {
		unset( $items['orders'], $items['downloads'], $items['edit-address'], $items['edit-account'] );
		if ( isset( $items['dashboard'] ) ) {
			$items['dashboard'] = __( 'My Sessions', 'wp-exam-success' );
		}
		return $items;
	}

	public static function enqueue_assets() {
		if ( ! is_account_page() ) {
			return;
		}

		wp_enqueue_style( 'bulma', 'https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css', array(), '1.0.2' );
		wp_enqueue_style( 'wpes-my-account', WPES_PLUGIN_URL . 'public/css/wpes-my-account.css', array( 'bulma' ), WPES_VERSION );
	}

	/**
	 * Render upcoming / past sessions on the account dashboard.
	 */
	public static function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user       = wp_get_current_user();
		$sessions   = self::get_user_sessions( $user->ID, $user->user_email );
		$on_hold    = self::get_user_sessions( $user->ID, $user->user_email, 'on-hold' );
		$tab        = isset( $_GET['wpes_tab'] ) ? sanitize_key( wp_unslash( $_GET['wpes_tab'] ) ) : 'upcoming'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$now       = WPES_DB::now_gmt();
		$upcoming  = array();
		$past      = array();

		foreach ( $sessions as $session ) {
			if ( $session->starts_at_gmt >= $now ) {
				$upcoming[] = $session;
			} else {
				$past[] = $session;
			}
		}

		if ( 'on-hold' === $tab ) {
			$active_list = $on_hold;
		} elseif ( 'past' === $tab ) {
			$active_list = $past;
		} else {
			$active_list = $upcoming;
		}

		$base_url = wc_get_page_permalink( 'myaccount' );

		$replacement_credits = WPES_Replacements::get_available_credits( $user->ID, $user->user_email );
		$available_sessions  = ! empty( $replacement_credits )
			? WPES_Sessions::query( array( 'status' => 'scheduled', 'only_with_capacity' => true, 'per_page' => 200 ) )
			: array();

		include WPES_PLUGIN_DIR . 'public/partials/my-account-sessions.php';
	}

	/**
	 * Handle the "Choose a Replacement Session" form submit (plain POST
	 * via admin-post.php, no AJAX/JS required). Redirects back to My
	 * Sessions with a notice either way.
	 */
	public static function handle_redeem_credit() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		check_admin_referer( self::REDEEM_NONCE_ACTION, 'wpes_redeem_nonce' );

		$credit_id  = (int) ( $_POST['credit_id'] ?? 0 );
		$session_id = (int) ( $_POST['session_id'] ?? 0 );
		$user       = wp_get_current_user();
		$base_url   = wc_get_page_permalink( 'myaccount' );

		if ( ! $credit_id || ! $session_id ) {
			wp_safe_redirect( add_query_arg( 'wpes_notice', 'redeem_missing', $base_url ) );
			exit;
		}

		$result = WPES_Replacements::redeem_credit( $credit_id, $session_id, $user->ID, $user->user_email );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'wpes_notice', 'redeem_failed', $base_url ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'wpes_notice', 'redeem_success', $base_url ) );
		exit;
	}

	/**
	 * Fetch bookings for a user by user_id or email, filtered by status.
	 *
	 * @param int    $user_id User ID.
	 * @param string $email   User email.
	 * @param string $status  Booking status (default 'confirmed').
	 * @return array
	 */
	public static function get_user_sessions( $user_id, $email, $status = 'confirmed' ) {
		global $wpdb;

		$bookings_table = WPES_DB::bookings_table();
		$sessions_table = WPES_DB::sessions_table();
		$classes_table  = WPES_DB::classes_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, c.name AS class_name, c.description AS class_description, b.id AS booking_id
				 FROM {$bookings_table} b
				 INNER JOIN {$sessions_table} s ON s.id = b.session_id
				 INNER JOIN {$classes_table} c ON c.id = s.class_id
				 WHERE b.status = %s
				   AND (b.user_id = %d OR b.customer_email = %s)
				 ORDER BY s.starts_at_gmt ASC",
				$status,
				$user_id,
				$email
			)
		);
	}
}
