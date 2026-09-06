<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $classes */
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="wpes-page-title mb-0"><?php esc_html_e( 'Sessions', 'wp-exam-success' ); ?></h1>
			<button type="button" class="btn btn-primary" id="wpesAddSessionBtn" data-bs-toggle="modal" data-bs-target="#wpesSessionModal">
				<?php esc_html_e( '+ Add Session', 'wp-exam-success' ); ?>
			</button>
		</div>

		<p class="text-muted">
			<?php
			printf(
				esc_html__( 'Times below are shown in the site timezone (%s).', 'wp-exam-success' ),
				esc_html( wp_timezone_string() )
			);
			?>
		</p>

		<div class="row g-2 mb-3 align-items-center">
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesSessionClassFilter">
					<option value=""><?php esc_html_e( 'All classes', 'wp-exam-success' ); ?></option>
					<?php foreach ( $classes as $class ) : ?>
						<option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesSessionStatusFilter">
					<option value=""><?php esc_html_e( 'All statuses', 'wp-exam-success' ); ?></option>
					<option value="scheduled"><?php esc_html_e( 'Scheduled', 'wp-exam-success' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'wp-exam-success' ); ?></option>
				</select>
			</div>
			<div class="col-auto">
				<button type="button" class="btn btn-sm btn-outline-danger" id="wpesBulkArchiveBtn"><?php esc_html_e( 'Archive Selected', 'wp-exam-success' ); ?></button>
			</div>
		</div>

		<table id="wpesSessionsTable" class="table table-striped table-bordered w-100">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Class', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Title', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Starts (site time)', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Capacity', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Meeting Link', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-exam-success' ); ?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>

<div class="modal fade" id="wpesSessionModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="wpesSessionForm">
				<div class="modal-header">
					<h5 class="modal-title" id="wpesSessionModalTitle"><?php esc_html_e( 'Add Session', 'wp-exam-success' ); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'Class', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
						<select class="form-select" name="class_id" id="wpesSessionClassId" required>
							<?php foreach ( $classes as $class ) : ?>
								<option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label" for="wpesSessionTitle"><?php esc_html_e( 'Title (optional)', 'wp-exam-success' ); ?></label>
						<input type="text" class="form-control" name="title" id="wpesSessionTitle" />
					</div>
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'Description', 'wp-exam-success' ); ?></label>
						<?php
						wp_editor(
							'',
							'wpesSessionDescription',
							array(
								'textarea_name' => 'description',
								'media_buttons' => false,
								'textarea_rows' => 5,
								'teeny'         => true,
							)
						);
						?>
					</div>
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'Level', 'wp-exam-success' ); ?></label>
						<select name="level" id="wpesSessionLevel" class="form-select">
							<option value=""><?php esc_html_e( 'Select Level', 'wp-exam-success' ); ?></option>
							<?php foreach ( WPES_Sessions::get_levels() as $level_code => $level_label ) : ?>
								<option value="<?php echo esc_attr( $level_code ); ?>"><?php echo esc_html( $level_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="row wpes-session-datetime">
						<div class="col-6 mb-3">
							<label class="form-label"><?php esc_html_e( 'Date', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
							<input type="date" class="form-control" name="date" id="wpesSessionDate" required />
						</div>
						<div class="col-6 mb-3">
							<label class="form-label"><?php esc_html_e( 'Timezone', 'wp-exam-success' ); ?></label>
							<input type="text" class="form-control" name="timezone" id="wpesSessionTimezone" value="<?php echo esc_attr( wp_timezone_string() ); ?>" />
						</div>
						<div class="col-6 mb-3">
							<label class="form-label"><?php esc_html_e( 'Start Time', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
							<input type="time" class="form-control" name="start_time" id="wpesSessionStart" required />
						</div>
						<div class="col-6 mb-3">
							<label class="form-label"><?php esc_html_e( 'End Time', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
							<input type="time" class="form-control" name="end_time" id="wpesSessionEnd" required />
						</div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="wpesSessionMax"><?php esc_html_e( 'Max Attendees', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
						<input type="number" class="form-control" name="max_attendees" id="wpesSessionMax" min="1" value="10" required />
					</div>
					<div class="mb-3">
						<label class="form-label" for="wpesSessionLink"><?php esc_html_e( 'Meeting Link', 'wp-exam-success' ); ?></label>
						<input type="url" class="form-control" name="meeting_link" id="wpesSessionLink" placeholder="https://" />
					</div>
					<div class="row wpes-session-recurrence">
						<div class="col-6 mb-3">
							<label class="form-label"><?php esc_html_e( 'Repeats', 'wp-exam-success' ); ?></label>
							<select class="form-select" name="repeat" id="wpesRepeatSelect">
								<option value="none"><?php esc_html_e( 'Does not repeat', 'wp-exam-success' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'wp-exam-success' ); ?></option>
							</select>
						</div>
						<div class="col-6 mb-3" id="wpesRepeatCountWrap" style="display:none;">
							<label class="form-label"><?php esc_html_e( 'Occurrences', 'wp-exam-success' ); ?></label>
							<input type="number" class="form-control" name="repeat_count" min="1" value="8" />
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'wp-exam-success' ); ?></button>
					<button type="submit" class="btn btn-primary" id="wpesSessionSubmitBtn"><?php esc_html_e( 'Create Session(s)', 'wp-exam-success' ); ?></button>
				</div>
				<input type="hidden" name="id" id="wpesSessionId" value="0" />
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="wpesEnrollModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="wpesEnrollForm">
				<div class="modal-header">
					<h5 class="modal-title"><?php esc_html_e( 'Manual Enrollment', 'wp-exam-success' ); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="session_id" id="wpesEnrollSessionId" value="0" />
					<div class="mb-3">
						<label class="form-label" for="wpesEnrollEmail"><?php esc_html_e( 'Customer Email', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
						<input type="email" class="form-control" id="wpesEnrollEmail" name="email" required placeholder="customer@example.com" />
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'wp-exam-success' ); ?></button>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Enroll', 'wp-exam-success' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="wpesAttendeesModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?php esc_html_e( 'Attendees', 'wp-exam-success' ); ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body" id="wpesAttendeesModalBody">
				<div class="text-center py-4"><?php esc_html_e( 'Loading…', 'wp-exam-success' ); ?></div>
			</div>
		</div>
	</div>
</div>
