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

	/**
	 * Accept-Link invitation sent to a suitable teacher once a session
	 * reaches the minimum participant count.
	 *
	 * @param object $teacher      Row from wpes_teachers.
	 * @param object $session      Row from wpes_sessions.
	 * @param object|null $class   Row from wpes_classes.
	 * @param string $accept_url   The unique, time-limited Accept-Link.
	 * @param int    $valid_hours  Validity window, for the copy.
	 * @return bool
	 */
	public static function send_teacher_invite( $teacher, $session, $class, $accept_url, $valid_hours ) {
		$class_name = $class ? $class->name : __( 'a class', 'wp-exam-success' );
		$title      = $session->title ?: $class_name;
		$when       = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';

		$body  = '<p>' . sprintf(
			/* translators: %s: teacher name */
			esc_html__( 'Hi %s,', 'wp-exam-success' ),
			esc_html( $teacher->name )
		) . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: 1: class/session title, 2: date and time */
			esc_html__( 'A session for %1$s has reached its minimum number of participants and needs a teacher: %2$s.', 'wp-exam-success' ),
			esc_html( $title ),
			esc_html( $when )
		) . '</p>';
		$body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $accept_url ) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Accept This Session', 'wp-exam-success' ) . '</a></p>';
		$body .= '<p style="font-size:13px;color:#666;">' . sprintf(
			/* translators: %d: number of hours */
			esc_html__( 'This invitation was also sent to other suitable teachers — whoever accepts first is assigned. It expires in %d hours.', 'wp-exam-success' ),
			(int) $valid_hours
		) . '</p>';

		return self::send(
			$teacher->email,
			sprintf(
				/* translators: %s: class/session title */
				__( 'Session invitation — %s', 'wp-exam-success' ),
				$title
			),
			$body
		);
	}

	/**
	 * Sent to every confirmed attendee once a session locks in (minimum
	 * reached + teacher assigned) — distinct from the initial order
	 * confirmation, since this can happen well after purchase.
	 *
	 * @param object $session
	 * @param object|null $class
	 * @param object $teacher
	 * @return int Number of attendees successfully emailed.
	 */
	public static function send_session_confirmed_to_attendees( $session, $class, $teacher ) {
		$attendees = WPES_Bookings::get_attendees_for_session( $session->id, array( 'confirmed' ) );
		if ( empty( $attendees ) ) {
			return 0;
		}

		$class_name = $class ? $class->name : __( 'your class', 'wp-exam-success' );
		$title      = $session->title ?: $class_name;
		$when       = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';
		$sent       = 0;

		foreach ( $attendees as $attendee ) {
			if ( empty( $attendee->customer_email ) ) {
				continue;
			}

			$body  = '<p>' . sprintf(
				/* translators: %s: customer name */
				esc_html__( 'Hi %s,', 'wp-exam-success' ),
				esc_html( $attendee->customer_name ?: __( 'there', 'wp-exam-success' ) )
			) . '</p>';
			$body .= '<p>' . sprintf(
				/* translators: 1: session/class title, 2: date and time, 3: teacher name */
				esc_html__( 'Good news — your session for %1$s on %2$s is confirmed, with %3$s as your teacher.', 'wp-exam-success' ),
				esc_html( $title ),
				esc_html( $when ),
				esc_html( $teacher->name )
			) . '</p>';
			$body .= '<p style="margin-top:24px;"><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" style="color:#2563eb;">' . esc_html__( 'View your sessions dashboard', 'wp-exam-success' ) . '</a></p>';

			if ( self::send(
				$attendee->customer_email,
				sprintf(
					/* translators: %s: session/class title */
					__( 'Your session is confirmed — %s', 'wp-exam-success' ),
					$title
				),
				$body
			) ) {
				$sent++;
			}
		}

		return $sent;
	}

	/**
	 * Sent to a customer whose booked session was cancelled for not
	 * reaching the minimum participant count — informs them and points
	 * at their replacement credit (Developer Spec §11).
	 *
	 * @param object $booking Row from wpes_bookings (now cancelled).
	 * @param object $session Row from wpes_sessions (now cancelled, failed_min).
	 * @param object|null $class
	 * @param int $credit_id
	 * @return bool
	 */
	public static function send_session_cancelled_replacement( $booking, $session, $class, $credit_id ) {
		if ( empty( $booking->customer_email ) ) {
			return false;
		}

		$class_name = $class ? $class->name : __( 'your class', 'wp-exam-success' );
		$title      = $session->title ?: $class_name;
		$when       = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';
		$account_url = wc_get_page_permalink( 'myaccount' );

		$body  = '<p>' . sprintf(
			/* translators: %s: customer name */
			esc_html__( 'Hi %s,', 'wp-exam-success' ),
			esc_html( $booking->customer_name ?: __( 'there', 'wp-exam-success' ) )
		) . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: 1: session/class title, 2: date and time */
			esc_html__( 'Unfortunately your session for %1$s on %2$s did not reach the minimum number of participants and will not take place.', 'wp-exam-success' ),
			esc_html( $title ),
			esc_html( $when )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'No further action is needed on your part regarding payment — this session is covered by the package you already purchased, and you have not been charged separately for it.', 'wp-exam-success' ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'You have 1 replacement credit available.', 'wp-exam-success' ) . '</strong> ' . esc_html__( 'Use it to pick a different session at no additional cost.', 'wp-exam-success' ) . '</p>';
		$body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $account_url ) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Choose a Replacement Session', 'wp-exam-success' ) . '</a></p>';

		return self::send(
			$booking->customer_email,
			sprintf(
				/* translators: %s: session/class title */
				__( 'Session cancelled — replacement available for %s', 'wp-exam-success' ),
				$title
			),
			$body
		);
	}

	/**
	 * Admin alert: no teacher accepted a session's Accept-Link within the
	 * configured validity window.
	 *
	 * @param object $session
	 * @param object|null $class
	 * @return bool
	 */
	public static function send_admin_no_teacher_response( $session, $class ) {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return false;
		}

		$class_name = $class ? $class->name : __( 'Unknown class', 'wp-exam-success' );
		$title      = $session->title ?: $class_name;
		$when       = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';
		$edit_url   = admin_url( 'admin.php?page=wpes-sessions' );

		$body  = '<p>' . sprintf(
			/* translators: 1: session/class title, 2: date and time */
			esc_html__( 'No teacher accepted the invitation for %1$s on %2$s within the configured time window.', 'wp-exam-success' ),
			esc_html( $title ),
			esc_html( $when )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'This session still has no assigned teacher and needs a manual decision.', 'wp-exam-success' ) . '</p>';
		$body .= '<p style="margin-top:20px;"><a href="' . esc_url( $edit_url ) . '" style="color:#2563eb;">' . esc_html__( 'Assign a teacher manually', 'wp-exam-success' ) . '</a></p>';

		return self::send(
			$admin_email,
			sprintf(
				/* translators: %s: session/class title */
				__( 'Action required: no teacher assigned — %s', 'wp-exam-success' ),
				$title
			),
			$body
		);
	}
}
