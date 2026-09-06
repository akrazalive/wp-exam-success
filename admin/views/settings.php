<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array<string,bool> $filters */
/** @var array $booking */
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

					<button type="submit" class="btn btn-primary" id="wpesBookingSettingsSaveBtn">
						<?php esc_html_e( 'Save Settings', 'wp-exam-success' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>
</div>
