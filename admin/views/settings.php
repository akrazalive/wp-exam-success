<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array<string,bool> $filters */
/** @var array $booking */
/** @var array $email_templates */
/** @var array $status_messages */

$email_items = array(
	'teacher_invite'                => array(
		'label'  => __( 'Teacher Invitation Email', 'wp-exam-success' ),
		'tokens' => '{teacher_name}, {session_title}, {session_datetime}, {invite_hours}, {accept_button}',
	),
	'session_confirmed'             => array(
		'label'  => __( 'Session Confirmed Email (to attendees)', 'wp-exam-success' ),
		'tokens' => '{customer_name}, {session_title}, {session_datetime}, {teacher_name}, {account_link}',
	),
	'session_cancelled_replacement' => array(
		'label'  => __( 'Session Cancelled / Replacement Credit Email', 'wp-exam-success' ),
		'tokens' => '{customer_name}, {session_title}, {session_datetime}, {replacement_button}',
	),
	'admin_no_teacher_response'     => array(
		'label'  => __( 'Admin Alert: No Teacher Responded', 'wp-exam-success' ),
		'tokens' => '{session_title}, {session_datetime}, {assign_link}',
	),
	'admin_capture_failed'          => array(
		'label'  => __( 'Admin Alert: Payment Capture Failed', 'wp-exam-success' ),
		'tokens' => '{order_id}, {session_id}, {order_status}, {order_link}',
	),
);

$message_items = array(
	'teacher_accepted'              => array(
		'label'  => __( 'Teacher Accept-Link: Success', 'wp-exam-success' ),
		'tokens' => '{session_datetime}',
	),
	'teacher_already_assigned'      => array(
		'label'  => __( 'Teacher Accept-Link: Already Assigned', 'wp-exam-success' ),
		'tokens' => '{session_datetime}',
	),
	'teacher_minimum_no_longer_met' => array(
		'label'  => __( 'Teacher Accept-Link: Minimum No Longer Met', 'wp-exam-success' ),
		'tokens' => '{session_datetime}',
	),
	'teacher_link_invalid'          => array(
		'label'  => __( 'Teacher Accept-Link: Invalid / Expired', 'wp-exam-success' ),
		'tokens' => '{session_datetime}',
	),
);
$labels = array(
	'class_id'     => __( 'Skill', 'wp-exam-success' ),
	'level'        => __( 'Level', 'wp-exam-success' ),
	'day'          => __( 'Day', 'wp-exam-success' ),
	'time'         => __( 'Time', 'wp-exam-success' ),
	'availability' => __( 'Availability (More Filters)', 'wp-exam-success' ),
);
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<h1 class="wpes-page-title"><?php esc_html_e( 'Settings', 'wp-exam-success' ); ?></h1>

		<div class="card shadow-sm mt-3" style="max-width: 640px;">
			<div class="card-body">
				<h2 class="h5 mb-2"><?php esc_html_e( 'Frontend Booking Filters', 'wp-exam-success' ); ?></h2>
				<p class="text-muted small mb-3">
					<?php esc_html_e( 'Choose which filters appear on the Schedule & Booking page. Disabled filters are hidden; enabled filters continue to work as usual.', 'wp-exam-success' ); ?>
				</p>

				<form id="wpesSettingsForm">
					<?php foreach ( $labels as $key => $label ) : ?>
						<div class="form-check form-switch mb-2">
							<input
								class="form-check-input"
								type="checkbox"
								role="switch"
								id="wpesFilter_<?php echo esc_attr( $key ); ?>"
								name="filters[<?php echo esc_attr( $key ); ?>]"
								value="1"
								<?php checked( ! empty( $filters[ $key ] ) ); ?>
							/>
							<label class="form-check-label" for="wpesFilter_<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $label ); ?>
							</label>
						</div>
					<?php endforeach; ?>

					<button type="submit" class="btn btn-primary mt-3" id="wpesSettingsSaveBtn">
						<?php esc_html_e( 'Save Settings', 'wp-exam-success' ); ?>
					</button>
				</form>
			</div>
		</div>

		<div class="card shadow-sm mt-3" style="max-width: 640px;">
			<div class="card-body">
				<h2 class="h5 mb-2"><?php esc_html_e( 'Teacher Assignment', 'wp-exam-success' ); ?></h2>
				<p class="text-muted small mb-3">
					<?php esc_html_e( 'Controls the automatic minimum-participant check and teacher Accept-Link invitations.', 'wp-exam-success' ); ?>
				</p>

				<form id="wpesBookingSettingsForm">
					<div class="mb-3">
						<label class="form-label" for="wpesMinParticipants"><?php esc_html_e( 'Minimum Participants', 'wp-exam-success' ); ?></label>
						<input type="number" min="1" class="form-control" style="max-width: 140px;" id="wpesMinParticipants" name="booking[min_participants]" value="<?php echo esc_attr( $booking['min_participants'] ); ?>" />
						<div class="form-text"><?php esc_html_e( 'A session invites teachers once it reaches this many confirmed (paid) attendees.', 'wp-exam-success' ); ?></div>
					</div>

					<div class="form-check form-switch mb-3">
						<input class="form-check-input" type="checkbox" role="switch" id="wpesAutoTeacherAssignment" name="booking[auto_teacher_assignment]" value="1" <?php checked( $booking['auto_teacher_assignment'] ); ?> />
						<label class="form-check-label" for="wpesAutoTeacherAssignment"><?php esc_html_e( 'Automatic Teacher Assignment', 'wp-exam-success' ); ?></label>
						<div class="form-text"><?php esc_html_e( 'When off, sessions still track their minimum-participant status but no Accept-Link emails go out — assign teachers manually from the Sessions screen instead.', 'wp-exam-success' ); ?></div>
					</div>

					<div class="mb-3">
						<label class="form-label" for="wpesTeacherInviteHours"><?php esc_html_e( 'Teacher Invitation Validity (hours)', 'wp-exam-success' ); ?></label>
						<input type="number" min="1" class="form-control" style="max-width: 140px;" id="wpesTeacherInviteHours" name="booking[teacher_invite_hours]" value="<?php echo esc_attr( $booking['teacher_invite_hours'] ); ?>" />
						<div class="form-text"><?php esc_html_e( 'How long an Accept-Link stays valid before the administrator is notified instead.', 'wp-exam-success' ); ?></div>
					</div>

					<div class="mb-3">
						<label class="form-label" for="wpesFinalCheckHours"><?php esc_html_e( 'Final Check Before Session (hours)', 'wp-exam-success' ); ?></label>
						<input type="number" min="1" class="form-control" style="max-width: 140px;" id="wpesFinalCheckHours" name="booking[final_check_hours_before]" value="<?php echo esc_attr( $booking['final_check_hours_before'] ); ?>" />
						<div class="form-text"><?php esc_html_e( 'A last automatic check of the minimum-participant/teacher status this many hours before a session starts.', 'wp-exam-success' ); ?></div>
					</div>

					<div class="mb-3">
						<label class="form-label" for="wpesAdminNotificationEmail"><?php esc_html_e( 'Admin Notification Email', 'wp-exam-success' ); ?></label>
						<input type="email" class="form-control" style="max-width: 320px;" id="wpesAdminNotificationEmail" name="booking[admin_notification_email]" value="<?php echo esc_attr( $booking['admin_notification_email'] ); ?>" placeholder="e.g. bookings@yourcompany.com" />
						<div class="form-text">
							<?php esc_html_e( 'This field is the address actually used for both admin alert emails: "no teacher responded" and "payment capture failed". Leave blank and this site\'s normal WordPress admin email is used instead — that\'s the entire setup, no other configuration is needed.', 'wp-exam-success' ); ?>
							<br />
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: the literal filter name, wrapped in a <code> tag */
									__( 'Advanced/optional: a developer can still override this with the %s PHP filter, which always wins over the field above if used — this is not required for normal use.', 'wp-exam-success' ),
									'<code>wpes_admin_notification_email</code>'
								),
								array( 'code' => array() )
							);
							?>
						</div>
					</div>

					<button type="submit" class="btn btn-primary" id="wpesBookingSettingsSaveBtn">
						<?php esc_html_e( 'Save Settings', 'wp-exam-success' ); ?>
					</button>
				</form>
			</div>
		</div>

		<div class="card shadow-sm mt-3" style="max-width: 900px;">
			<div class="card-body">
				<h2 class="h5 mb-2"><?php esc_html_e( 'Email & Message Templates', 'wp-exam-success' ); ?></h2>
				<p class="text-muted small mb-3">
					<?php esc_html_e( 'Subject/title and body text for every email and Accept-Link outcome page this plugin sends. Leave a field blank and save to reset it back to the default text shown below. Tokens like {session_title} are replaced automatically when the email/message is sent — text ending in _button or _link inserts the actual working button/link and cannot be typed by hand.', 'wp-exam-success' ); ?>
				</p>

				<form id="wpesMessageSettingsForm">
					<h3 class="h6 mt-4 mb-3"><?php esc_html_e( 'Emails', 'wp-exam-success' ); ?></h3>
					<?php foreach ( $email_items as $key => $item ) : ?>
						<div class="border rounded p-3 mb-3">
							<h4 class="h6 mb-2"><?php echo esc_html( $item['label'] ); ?></h4>
							<div class="mb-2">
								<label class="form-label small" for="wpesEmailSubject_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Subject', 'wp-exam-success' ); ?></label>
								<input type="text" class="form-control form-control-sm" id="wpesEmailSubject_<?php echo esc_attr( $key ); ?>" name="email_templates[<?php echo esc_attr( $key ); ?>][subject]" value="<?php echo esc_attr( $email_templates[ $key ]['subject'] ); ?>" />
							</div>
							<div class="mb-1">
								<label class="form-label small" for="wpesEmailBody_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Body (HTML allowed)', 'wp-exam-success' ); ?></label>
								<textarea class="form-control form-control-sm" style="font-family:monospace;font-size:12px;" rows="4" id="wpesEmailBody_<?php echo esc_attr( $key ); ?>" name="email_templates[<?php echo esc_attr( $key ); ?>][body]"><?php echo esc_textarea( $email_templates[ $key ]['body'] ); ?></textarea>
							</div>
							<div class="form-text"><?php echo esc_html( sprintf(
								/* translators: %s: comma-separated list of available tokens */
								__( 'Available tokens: %s', 'wp-exam-success' ),
								$item['tokens']
							) ); ?></div>
						</div>
					<?php endforeach; ?>

					<h3 class="h6 mt-4 mb-3"><?php esc_html_e( 'Teacher Accept-Link Response Pages', 'wp-exam-success' ); ?></h3>
					<?php foreach ( $message_items as $key => $item ) : ?>
						<div class="border rounded p-3 mb-3">
							<h4 class="h6 mb-2"><?php echo esc_html( $item['label'] ); ?></h4>
							<div class="mb-2">
								<label class="form-label small" for="wpesMsgTitle_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Page Title', 'wp-exam-success' ); ?></label>
								<input type="text" class="form-control form-control-sm" id="wpesMsgTitle_<?php echo esc_attr( $key ); ?>" name="status_messages[<?php echo esc_attr( $key ); ?>][title]" value="<?php echo esc_attr( $status_messages[ $key ]['title'] ); ?>" />
							</div>
							<div class="mb-1">
								<label class="form-label small" for="wpesMsgBody_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Message', 'wp-exam-success' ); ?></label>
								<textarea class="form-control form-control-sm" style="font-family:monospace;font-size:12px;" rows="2" id="wpesMsgBody_<?php echo esc_attr( $key ); ?>" name="status_messages[<?php echo esc_attr( $key ); ?>][body]"><?php echo esc_textarea( $status_messages[ $key ]['body'] ); ?></textarea>
							</div>
							<div class="form-text"><?php echo esc_html( sprintf(
								/* translators: %s: comma-separated list of available tokens */
								__( 'Available tokens: %s', 'wp-exam-success' ),
								$item['tokens']
							) ); ?></div>
						</div>
					<?php endforeach; ?>

					<button type="submit" class="btn btn-primary" id="wpesMessageSettingsSaveBtn">
						<?php esc_html_e( 'Save Settings', 'wp-exam-success' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>
</div>
