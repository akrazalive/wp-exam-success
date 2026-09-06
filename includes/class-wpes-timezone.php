<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IP-based timezone detection using WooCommerce geolocation.
 */
class WPES_Timezone {

	const IP_COOKIE = 'wpes_visitor_ip';

	public static function init() {
		add_action( 'wp_ajax_wpes_detect_timezone', array( __CLASS__, 'ajax_detect' ) );
		add_action( 'wp_ajax_nopriv_wpes_detect_timezone', array( __CLASS__, 'ajax_detect' ) );
	}

	/**
	 * AJAX: detect timezone from IP, re-detect on IP change.
	 */
	public static function ajax_detect() {
		// check_ajax_referer( WPES_Public::NONCE_ACTION, 'nonce' );

		$current_ip = self::get_client_ip();
		$stored_ip  = isset( $_COOKIE[ self::IP_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::IP_COOKIE ] ) ) : '';

		// AJAX responses often miss Set-Cookie; accept client-read cookie as fallback.
		if ( empty( $stored_ip ) && isset( $_POST['stored_ip'] ) ) {
			$stored_ip = sanitize_text_field( wp_unslash( $_POST['stored_ip'] ) );
		}

		$ip_changed = ( $stored_ip !== $current_ip );
		$timezone   = self::guess_timezone( $current_ip );

		// Best-effort server cookie; client also sets this from the JSON response.
		if ( $current_ip ) {
			setcookie( self::IP_COOKIE, $current_ip, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
		}

		wp_send_json_success(
			array(
				'timezone'   => $timezone,
				'ip_changed' => $ip_changed && ! empty( $stored_ip ),
				'ip'         => $current_ip,
				'cookie'     => array(
					'name'   => self::IP_COOKIE,
					'value'  => $current_ip,
					'maxAge' => YEAR_IN_SECONDS,
					'path'   => COOKIEPATH,
					'domain' => COOKIE_DOMAIN,
					'secure' => is_ssl(),
				),
			)
		);
	}

	/**
	 * Guess IANA timezone from IP using WooCommerce geolocation.
	 *
	 * @param string $ip IP address.
	 * @return string|null IANA timezone or null.
	 */
	public static function guess_timezone( $ip = '' ) {
		if ( ! class_exists( 'WC_Geolocation' ) ) {
			return null;
		}

		if ( empty( $ip ) ) {
			$ip = self::get_client_ip();
		}

		if ( empty( $ip ) || '127.0.0.1' === $ip || '::1' === $ip ) {
			return null;
		}

		$user_geo = WC_Geolocation::geolocate_ip( $ip );
		if ( ! empty( $user_geo['country'] ) ) {
			return self::timezone_from_country( $user_geo['country'] );
		}

		return null;
	}

	/**
	 * Rough country → timezone fallback.
	 *
	 * @param string $country ISO 3166-1 alpha-2.
	 * @return string|null
	 */
	protected static function timezone_from_country( $country ) {
		$map = array(
			// Western Europe
			'GB' => 'Europe/London',
			'IE' => 'Europe/Dublin',
			'PT' => 'Europe/Lisbon',
			'ES' => 'Europe/Madrid',
			'FR' => 'Europe/Paris',
			'BE' => 'Europe/Brussels',
			'NL' => 'Europe/Amsterdam',
			'LU' => 'Europe/Luxembourg',
			'DE' => 'Europe/Berlin',
			'AT' => 'Europe/Vienna',
			'CH' => 'Europe/Zurich',
			'IT' => 'Europe/Rome',
			'MC' => 'Europe/Monaco',
			'AD' => 'Europe/Andorra',
			'MT' => 'Europe/Malta',
			// Northern Europe
			'SE' => 'Europe/Stockholm',
			'NO' => 'Europe/Oslo',
			'DK' => 'Europe/Copenhagen',
			'FI' => 'Europe/Helsinki',
			'IS' => 'Atlantic/Reykjavik',
			// Eastern Europe
			'PL' => 'Europe/Warsaw',
			'CZ' => 'Europe/Prague',
			'SK' => 'Europe/Bratislava',
			'HU' => 'Europe/Budapest',
			'RO' => 'Europe/Bucharest',
			'BG' => 'Europe/Sofia',
			'GR' => 'Europe/Athens',
			'HR' => 'Europe/Zagreb',
			'SI' => 'Europe/Ljubljana',
			'RS' => 'Europe/Belgrade',
			'BA' => 'Europe/Sarajevo',
			'ME' => 'Europe/Podgorica',
			'MK' => 'Europe/Skopje',
			'AL' => 'Europe/Tirane',
			'UA' => 'Europe/Kyiv',
			'BY' => 'Europe/Minsk',
			'MD' => 'Europe/Chisinau',
			'LT' => 'Europe/Vilnius',
			'LV' => 'Europe/Riga',
			'EE' => 'Europe/Tallinn',
			// Americas
			'US' => 'America/New_York',
			'CA' => 'America/Toronto',
			'MX' => 'America/Mexico_City',
			'BR' => 'America/Sao_Paulo',
			'AR' => 'America/Argentina/Buenos_Aires',
			'CL' => 'America/Santiago',
			'CO' => 'America/Bogota',
			'PE' => 'America/Lima',
			'VE' => 'America/Caracas',
			'EC' => 'America/Guayaquil',
			'BO' => 'America/La_Paz',
			'PY' => 'America/Asuncion',
			'UY' => 'America/Montevideo',
			'CR' => 'America/Costa_Rica',
			'PA' => 'America/Panama',
			'GT' => 'America/Guatemala',
			'HN' => 'America/Tegucigalpa',
			'SV' => 'America/El_Salvador',
			'NI' => 'America/Managua',
			'DO' => 'America/Santo_Domingo',
			'PR' => 'America/Puerto_Rico',
			'JM' => 'America/Jamaica',
			'TT' => 'America/Port_of_Spain',
			'BB' => 'America/Barbados',
			'BS' => 'America/Nassau',
			'BZ' => 'America/Belize',
			'CU' => 'America/Havana',
			// Asia-Pacific
			'AU' => 'Australia/Sydney',
			'NZ' => 'Pacific/Auckland',
			'JP' => 'Asia/Tokyo',
			'KR' => 'Asia/Seoul',
			'CN' => 'Asia/Shanghai',
			'HK' => 'Asia/Hong_Kong',
			'TW' => 'Asia/Taipei',
			'SG' => 'Asia/Singapore',
			'MY' => 'Asia/Kuala_Lumpur',
			'TH' => 'Asia/Bangkok',
			'VN' => 'Asia/Ho_Chi_Minh',
			'PH' => 'Asia/Manila',
			'ID' => 'Asia/Jakarta',
			'IN' => 'Asia/Kolkata',
			'PK' => 'Asia/Karachi',
			'BD' => 'Asia/Dhaka',
			'LK' => 'Asia/Colombo',
			'NP' => 'Asia/Kathmandu',
			'MM' => 'Asia/Yangon',
			'KH' => 'Asia/Phnom_Penh',
			'LA' => 'Asia/Vientiane',
			'BN' => 'Asia/Brunei',
			'MN' => 'Asia/Ulaanbaatar',
			'KZ' => 'Asia/Almaty',
			'UZ' => 'Asia/Tashkent',
			// Middle East
			'AE' => 'Asia/Dubai',
			'SA' => 'Asia/Riyadh',
			'QA' => 'Asia/Qatar',
			'KW' => 'Asia/Kuwait',
			'BH' => 'Asia/Bahrain',
			'OM' => 'Asia/Muscat',
			'JO' => 'Asia/Amman',
			'LB' => 'Asia/Beirut',
			'IL' => 'Asia/Jerusalem',
			'TR' => 'Europe/Istanbul',
			'IQ' => 'Asia/Baghdad',
			'IR' => 'Asia/Tehran',
			'YE' => 'Asia/Aden',
			// Africa
			'ZA' => 'Africa/Johannesburg',
			'EG' => 'Africa/Cairo',
			'NG' => 'Africa/Lagos',
			'KE' => 'Africa/Nairobi',
			'GH' => 'Africa/Accra',
			'MA' => 'Africa/Casablanca',
			'TZ' => 'Africa/Dar_es_Salaam',
			'UG' => 'Africa/Kampala',
			'ET' => 'Africa/Addis_Ababa',
			'SN' => 'Africa/Dakar',
			'CI' => 'Africa/Abidjan',
			'CM' => 'Africa/Douala',
			'AO' => 'Africa/Luanda',
			'MU' => 'Indian/Mauritius',
			// Oceania
			'FJ' => 'Pacific/Fiji',
			'PG' => 'Pacific/Port_Moresby',
		);

		return $map[ strtoupper( $country ) ] ?? null;
	}

	/**
	 * Get visitor IP via WooCommerce.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		if ( class_exists( 'WC_Geolocation' ) ) {
			return WC_Geolocation::get_ip_address();
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
