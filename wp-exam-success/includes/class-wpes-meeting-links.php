<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sending (and re-sending) the meeting link to everyone confirmed for
 * a session, with a permanent log entry per send so support staff can
 * see "did we actually email these people, and when".
 */
class WPES_Meeting_Links {

	/**
	 * @param int    $session_id
	 * @param string $send_type 'initial' | 'resend'
	 * @return int|WP_Error number of recipients emailed, or error
	 */
	public static function send_for_session( $session_id, $send_type = 'initial' ) {
		$session = WPES_Sessions::get( $session_id );
		if ( ! $session ) {
			return new WP_Error( 'wpes_session_not_found', __( 'Session not found.', 'wp-exam-success' ) );
		}
		if ( empty( $session->meeting_link ) ) {
			return new WP_Error( 'wpes_no_meeting_link', __( 'This session has no meeting link set yet.', 'wp-exam-success' ) );
		}

		$class      = WPES_Classes::get( $session->class_id );
		$attendees  = WPES_Bookings::get_attendees_for_session( $session_id, array( 'confirmed' ) );

		if ( empty( $attendees ) ) {
			return new WP_Error( 'wpes_no_attendees', __( 'This session has no confirmed attendees to email.', 'wp-exam-success' ) );
		}

		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$sent_count   = 0;

		foreach ( $attendees as $attendee ) {
			if ( empty( $attendee->customer_email ) ) {
				continue;
			}

			$subject = sprintf(
				/* translators: 1: class name, 2: site name */
				__( 'Your meeting link for %1$s — %2$s', 'wp-exam-success' ),
				$class ? $class->name : __( 'your session', 'wp-exam-success' ),
				$site_name
			);

			$body = self::build_email_body( $session, $class, $attendee );

			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$sent    = wp_mail( $attendee->customer_email, $subject, $body, $headers );

			if ( $sent ) {
				$sent_count++;
			}
		}

		global $wpdb;
		$wpdb->insert(
			WPES_DB::meeting_log_table(),
			array(
				'session_id'      => $session_id,
				'sent_by'         => get_current_user_id(),
				'sent_at'         => WPES_DB::now_gmt(),
				'recipient_count' => $sent_count,
				'send_type'       => $send_type,
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		return $sent_count;
	}

	private static function build_email_body( $session, $class, $attendee ) {
		$name       = $attendee->customer_name ? $attendee->customer_name : __( 'there', 'wp-exam-success' );
		$class_name = $class ? esc_html( $class->name ) : '';
		$title      = $session->title ? esc_html( $session->title ) : $class_name;
		$link       = esc_url( $session->meeting_link );

		// Render the time in UTC here as a fallback for email clients;
		// admins are told in the UI that recipients should confirm
		// against their own calendar app, which will localize an .ics
		// attachment if one is added in a future iteration.
		$when = esc_html( date_i18n( 'l, F j, Y \a\t g:i A', strtotime( $session->starts_at_gmt ) ) . ' UTC' );

		ob_start();
		?>
		<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'wp-exam-success' ), $name ) ); ?></p>
		<p><?php echo esc_html( sprintf( __( 'Here is your meeting link for %s:', 'wp-exam-success' ), $title ) ); ?></p>
		<p><a href="<?php echo $link; ?>"><?php echo $link; ?></a></p>
		<p><?php echo esc_html( sprintf( __( 'Session time: %s', 'wp-exam-success' ), $when ) ); ?></p>
		<?php
		return ob_get_clean();
	}

	public static function get_log_for_session( $session_id ) {
		global $wpdb;
		$table = WPES_DB::meeting_log_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE session_id = %d ORDER BY sent_at DESC", $session_id ) );
	}
}
