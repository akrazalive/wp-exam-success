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

		$tpl           = WPES_Admin::get_email_templates()['teacher_invite'];
		$accept_button = '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $accept_url ) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Accept This Session', 'wp-exam-success' ) . '</a></p>';

		$tokens = array(
			'{teacher_name}'     => esc_html( $teacher->name ),
			'{session_title}'    => esc_html( $title ),
			'{session_datetime}' => esc_html( $when ),
			'{invite_hours}'     => (int) $valid_hours,
			'{accept_button}'    => $accept_button,
		);

		return self::send(
			$teacher->email,
			strtr( $tpl['subject'], $tokens ),
			strtr( $tpl['body'], $tokens )
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
		// Includes 'on-hold' as defense-in-depth — by the time this is
		// called from finalize_session_confirmation(), that session's
		// on-hold bookings have already been promoted to 'confirmed', but
		// this stays correct even if called from elsewhere in the future.
		$attendees = WPES_Bookings::get_attendees_for_session( $session->id, array( 'confirmed', 'on-hold' ) );
		if ( empty( $attendees ) ) {
			return 0;
		}

		$class_name = $class ? $class->name : __( 'your class', 'wp-exam-success' );
		$title      = $session->title ?: $class_name;
		$when       = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';
		$sent       = 0;

		$tpl          = WPES_Admin::get_email_templates()['session_confirmed'];
		$account_link = '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" style="color:#2563eb;">' . esc_html__( 'View your sessions dashboard', 'wp-exam-success' ) . '</a>';

		foreach ( $attendees as $attendee ) {
			if ( empty( $attendee->customer_email ) ) {
				continue;
			}

			$tokens = array(
				'{customer_name}'    => esc_html( $attendee->customer_name ?: __( 'there', 'wp-exam-success' ) ),
				'{session_title}'    => esc_html( $title ),
				'{session_datetime}' => esc_html( $when ),
				'{teacher_name}'     => esc_html( $teacher->name ),
				'{account_link}'     => $account_link,
			);

			if ( self::send(
				$attendee->customer_email,
				strtr( $tpl['subject'], $tokens ),
				strtr( $tpl['body'], $tokens )
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

		$class_name  = $class ? $class->name : __( 'your class', 'wp-exam-success' );
		$title       = $session->title ?: $class_name;
		$when        = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';
		$account_url = wc_get_page_permalink( 'myaccount' );

		$tpl                = WPES_Admin::get_email_templates()['session_cancelled_replacement'];
		$replacement_button = '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $account_url ) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Choose a Replacement Session', 'wp-exam-success' ) . '</a></p>';

		$tokens = array(
			'{customer_name}'     => esc_html( $booking->customer_name ?: __( 'there', 'wp-exam-success' ) ),
			'{session_title}'     => esc_html( $title ),
			'{session_datetime}'  => esc_html( $when ),
			'{replacement_button}' => $replacement_button,
		);

		return self::send(
			$booking->customer_email,
			strtr( $tpl['subject'], $tokens ),
			strtr( $tpl['body'], $tokens )
		);
	}

	/**
	 * Recipient for every WP Exam Success admin/operational alert
	 * (no-teacher-response, capture-failed, etc.). Defaults to the site's
	 * "Administration Email Address" the same way this always has, but is
	 * now configurable — found during Final Acceptance Testing (item 2,
	 * 2026-09-10) that staging's admin_email is a generic placeholder
	 * inbox (contact@staging.exam-success.de) nobody actually monitors,
	 * which looks identical to "the notification was never sent" from
	 * the outside. wp_mail() itself was never the problem; this just
	 * lets the destination be pointed wherever someone is actually
	 * watching, on staging or live, without editing code.
	 *
	 * Resolution order: the "Admin Notification Email" field on the
	 * Settings screen (WPES_Admin::get_booking_settings()) if one has
	 * been entered there; otherwise the site's normal admin_email. The
	 * wpes_admin_notification_email filter this originally shipped with
	 * (2026-09-10, before the Settings field existed) still runs last
	 * and always wins if a developer has hooked it — kept for backward
	 * compatibility and for anyone who genuinely prefers a code-level
	 * override over the UI field.
	 *
	 * @return string
	 */
	public static function get_admin_notification_email() {
		$settings = class_exists( 'WPES_Admin' ) ? WPES_Admin::get_booking_settings() : array();
		$base     = ! empty( $settings['admin_notification_email'] ) ? $settings['admin_notification_email'] : get_option( 'admin_email' );

		return apply_filters( 'wpes_admin_notification_email', $base );
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
		$admin_email = self::get_admin_notification_email();
		if ( ! $admin_email ) {
			return false;
		}

		$class_name = $class ? $class->name : __( 'Unknown class', 'wp-exam-success' );
		$title      = $session->title ?: $class_name;
		$when       = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y \a\t g:i A' ) . ' (' . wp_timezone_string() . ')';
		$edit_url   = admin_url( 'admin.php?page=wpes-sessions' );

		$tpl         = WPES_Admin::get_email_templates()['admin_no_teacher_response'];
		$assign_link = '<p style="margin-top:20px;"><a href="' . esc_url( $edit_url ) . '" style="color:#2563eb;">' . esc_html__( 'Assign a teacher manually', 'wp-exam-success' ) . '</a></p>';

		$tokens = array(
			'{session_title}'    => esc_html( $title ),
			'{session_datetime}' => esc_html( $when ),
			'{assign_link}'      => $assign_link,
		);

		return self::send(
			$admin_email,
			strtr( $tpl['subject'], $tokens ),
			strtr( $tpl['body'], $tokens )
		);
	}

	/**
	 * Admin alert: a package payment capture attempt did not result in
	 * the order actually reaching completed/processing status (Developer
	 * Spec §6 payment-capture safety — Pre-Acceptance Review item 2).
	 * The plugin will retry automatically the next time a session on this
	 * order confirms, but a human should check the gateway directly if
	 * that keeps failing.
	 *
	 * @param int    $order_id
	 * @param int    $session_id
	 * @param string $resulting_status Order status found after the attempt.
	 * @return bool
	 */
	public static function send_admin_capture_failed( $order_id, $session_id, $resulting_status ) {
		$admin_email = self::get_admin_notification_email();
		if ( ! $admin_email ) {
			return false;
		}

		$order_url = function_exists( 'wc_get_order' ) && ( $order = wc_get_order( $order_id ) )
			? $order->get_edit_order_url()
			: admin_url( 'post.php?post=' . (int) $order_id . '&action=edit' );

		$tpl        = WPES_Admin::get_email_templates()['admin_capture_failed'];
		$order_link = '<p style="margin-top:20px;"><a href="' . esc_url( $order_url ) . '" style="color:#2563eb;">' . esc_html__( 'View the order', 'wp-exam-success' ) . '</a></p>';

		$tokens = array(
			'{order_id}'     => (int) $order_id,
			'{session_id}'   => (int) $session_id,
			'{order_status}' => esc_html( $resulting_status ),
			'{order_link}'   => $order_link,
		);

		return self::send(
			$admin_email,
			strtr( $tpl['subject'], $tokens ),
			strtr( $tpl['body'], $tokens )
		);
	}
}
