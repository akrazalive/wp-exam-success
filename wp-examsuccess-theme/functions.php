<?php
/**
 * Exam Success Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Exam Success
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_EXAM_SUCCESS_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'exam-success-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_EXAM_SUCCESS_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    // Fix (2026-09-08): this redirect fired unconditionally, even with an
    // empty cart. WooCommerce's own checkout page redirects back to the
    // cart page whenever the cart is empty -- so an empty cart produced
    // an infinite redirect loop (cart -> checkout -> cart -> ...),
    // reported as ERR_TOO_MANY_REDIRECTS on /cart/, including in a fresh
    // incognito session. Only skip straight to checkout when there is
    // actually something to check out.
    if (is_cart() && !is_checkout() && WC()->cart && ! WC()->cart->is_empty()) {
        wp_safe_redirect(wc_get_checkout_url(), 301);
        exit;
    }
});