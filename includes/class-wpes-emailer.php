<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML email helpers used by magic login, order confirmation, etc.
 */
class WPES_Emailer {

	/**
	 * Wrap message body in a branded HTML template.
	 *
	 * @param string $message_body HTML content.
	 * @return string
	 */
	public static function wrap_template( $message_body ) {
		$site_name = get_bloginfo( 'name' );

		return '
		<div style="font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e8e8e8; border-radius: 8px; overflow: hidden;">
			<div style="background: #1a365d; color: #fff; padding: 20px 24px;">
				<h2 style="margin: 0; font-size: 20px; font-weight: 600;">' . esc_html( $site_name ) . '</h2>
			</div>
			<div style="padding: 24px; line-height: 1.6; color: #333;">' . $message_body . '</div>
			<div style="padding: 16px 24px; background: #f7f7f7; font-size: 12px; color: #888;">
				' . esc_html( sprintf( __( 'This is an automated message from %s.', 'wp-exam-success' ), $site_name ) ) . '
			</div>
		</div>';
	}

	/**
	 * Send an HTML email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject line.
	 * @param string $body    HTML body (without wrapper).
	 * @return bool
	 */
	public static function send( $to, $subject, $body ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		return wp_mail( $to, $subject, self::wrap_template( $body ), $headers );
	}

	/**
	 * Send magic login link email.
	 *
	 * @param WP_User $user User object.
	 * @param string  $url  Login URL.
	 * @return bool
	 */
	public static function send_magic_login( $user, $url ) {
		$body  = '<p>' . sprintf(
			/* translators: %s: user display name */
			esc_html__( 'Hi %s,', 'wp-exam-success' ),
			esc_html( $user->display_name ?: $user->user_email )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'Click the button below to securely access your sessions dashboard. This link expires in 30 minutes.', 'wp-exam-success' ) . '</p>';
		$body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Access My Sessions', 'wp-exam-success' ) . '</a></p>';
		$body .= '<p style="font-size:13px;color:#666;">' . esc_html__( 'If you did not request this link, you can safely ignore this email.', 'wp-exam-success' ) . '</p>';

		return self::send(
			$user->user_email,
			sprintf(
				/* translators: %s: site name */
				__( 'Your secure login link — %s', 'wp-exam-success' ),
				get_bloginfo( 'name' )
			),
			$body
		);
	}

	/**
	 * Send order confirmation with booked session details.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public static function send_order_sessions( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$sessions_html = '';
		foreach ( $order->get_items() as $item ) {
			$booking_ids = $item->get_meta( '_wpes_booking_ids' );
			if ( empty( $booking_ids ) ) {
				continue;
			}

			$sessions_html .= '<h3 style="margin:20px 0 8px;font-size:16px;">' . esc_html( $item->get_name() ) . '</h3>';
			$sessions_html .= '<ul style="margin:0;padding-left:20px;">';

			foreach ( (array) $booking_ids as $booking_id ) {
				global $wpdb;
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT s.title, s.starts_at_gmt, s.ends_at_gmt, c.name AS class_name
						 FROM " . WPES_DB::bookings_table() . " b
						 INNER JOIN " . WPES_DB::sessions_table() . " s ON s.id = b.session_id
						 INNER JOIN " . WPES_DB::classes_table() . " c ON c.id = s.class_id
						 WHERE b.id = %d",
						$booking_id
					)
				);
				if ( ! $row ) {
					continue;
				}
				$start = get_date_from_gmt( $row->starts_at_gmt, 'l, F j, Y \a\t g:i A' );
				$end   = get_date_from_gmt( $row->ends_at_gmt, 'g:i A' );
				$label = $row->title ?: $row->class_name;
				$sessions_html .= '<li style="margin-bottom:6px;"><strong>' . esc_html( $label ) . '</strong> — ' . esc_html( $start . ' – ' . $end ) . '</li>';
			}
			$sessions_html .= '</ul>';
		}

		if ( '' === $sessions_html ) {
			return false;
		}

		$body  = '<p>' . sprintf(
			/* translators: %s: customer first name */
			esc_html__( 'Hi %s,', 'wp-exam-success' ),
			esc_html( $order->get_billing_first_name() ?: __( 'there', 'wp-exam-success' ) )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'Thank you for your purchase! Here are the sessions you booked:', 'wp-exam-success' ) . '</p>';
		$body .= $sessions_html;
		$body .= '<p style="margin-top:24px;"><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" style="color:#2563eb;">' . esc_html__( 'View your sessions dashboard', 'wp-exam-success' ) . '</a></p>';

		return self::send(
			$order->get_billing_email(),
			sprintf(
				/* translators: %s: order number */
				__( 'Your booked sessions — Order #%s', 'wp-exam-success' ),
				$order->get_order_number()
			),
			$body
		);
	}
}
