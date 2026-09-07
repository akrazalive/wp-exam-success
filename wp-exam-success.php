<?php
/**
 * Plugin Name: WP Exam Success — Course Session Manager
 * Description: Sells course packages via WooCommerce where each package grants a fixed number of sessions, chosen by the customer from recurring, capacity-limited class sessions. Handles session scheduling, capacity locking, timezone-correct display, and meeting-link dispatch.
 * Version: 1.8.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: wp-exam-success
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPES_VERSION', '1.8.4' );
define( 'WPES_PLUGIN_FILE', __FILE__ );
define( 'WPES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bail politely if WooCommerce isn't active. This plugin is a layer
 * on top of WooCommerce, not a standalone store.
 */
function wpes_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'WP Exam Success requires WooCommerce to be installed and active.', 'wp-exam-success' ) .
		'</p></div>';
}

function wpes_is_woocommerce_active() {
	return in_array(
		'woocommerce/woocommerce.php',
		apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
		true
	) || ( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins', array() ) ) );
}

require_once WPES_PLUGIN_DIR . 'includes/class-wpes-activator.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-db.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-classes.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-teachers.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-sessions.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-bookings.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-meeting-links.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-emailer.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-teacher-invites.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-replacements.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-cron.php';
require_once WPES_PLUGIN_DIR . 'includes/class-wpes-waitlist.php';

register_activation_hook( __FILE__, array( 'WPES_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPES_Cron', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded, so we can reliably
 * check for WooCommerce and hook into its actions/filters.
 */
function wpes_bootstrap() {
	if ( ! wpes_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wpes_woocommerce_missing_notice' );
		return;
	}

	require_once WPES_PLUGIN_DIR . 'includes/class-wpes-payments.php';
	require_once WPES_PLUGIN_DIR . 'includes/class-wpes-woocommerce.php';
	require_once WPES_PLUGIN_DIR . 'includes/class-wpes-my-account.php';
	require_once WPES_PLUGIN_DIR . 'includes/class-wpes-magic-login.php';
	require_once WPES_PLUGIN_DIR . 'includes/class-wpes-timezone.php';
	require_once WPES_PLUGIN_DIR . 'admin/class-wpes-admin.php';
	require_once WPES_PLUGIN_DIR . 'public/class-wpes-public.php';

	WPES_Cron::init();
	WPES_WooCommerce::init();
	WPES_My_Account::init();
	WPES_Magic_Login::init();
	WPES_Timezone::init();

	WPES_Admin::init();
	WPES_Public::init();

	add_action( 'elementor_pro/forms/actions/register', 'wpes_register_elementor_waitlist_action' );
}
add_action( 'plugins_loaded', 'wpes_bootstrap' );

/**
 * Register the Elementor Forms "Waitlist" action when Elementor Pro is present.
 *
 * @param \ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar $registrar Registrar.
 */
function wpes_register_elementor_waitlist_action( $registrar ) {
	require_once WPES_PLUGIN_DIR . 'includes/elementor/class-wpes-elementor-waitlist-action.php';
	$registrar->register( new WPES_Elementor_Waitlist_Action() );
}
