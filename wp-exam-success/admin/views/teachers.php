<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $classes */
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="wpes-page-title mb-0"><?php esc_html_e( 'Teachers', 'wp-exam-success' ); ?></h1>
			<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wpesTeacherModal" id="wpesAddTeacherBtn">
				<?php esc_html_e( '+ Add Teacher', 'wp-exam-success' ); ?>
			</button>
		</div>

		<p class="text-muted">
			<?php esc_html_e( 'Teachers are invited to a session by email once it reaches the minimum participant count — no WordPress login required. Link each teacher to the classes (subjects) they are suitable to teach below.', 'wp-exam-success' ); ?>
		</p>

		<div class="row g-2 mb-3">
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesTeacherStatusFilter">
					<option value=""><?php esc_html_e( 'All statuses', 'wp-exam-success' ); ?></option>
					<option value="active"><?php esc_html_e( 'Active', 'wp-exam-success' ); ?></option>
					<option value="archived"><?php esc_html_e( 'Archived', 'wp-exam-success' ); ?></option>
				</select>
			</div>
		</div>

		<table id="wpesTeachersTable" class="table table-striped table-bordered w-100">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Classes', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-exam-success' ); ?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>

<div class="modal fade" id="wpesTeacherModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="wpesTeacherForm">
				<div class="modal-header">
					<h5 class="modal-title"><?php esc_html_e( 'Add Teacher', 'wp-exam-success' ); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="wpesTeacherName"><?php esc_html_e( 'Name', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
						<input type="text" class="form-control" id="wpesTeacherName" name="name" required />
					</div>
					<div class="mb-3">
						<label class="form-label" for="wpesTeacherEmail"><?php esc_html_e( 'Email', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
						<input type="email" class="form-control" id="wpesTeacherEmail" name="email" required placeholder="teacher@example.com" />
						<div class="form-text"><?php esc_html_e( 'Session invitations and assignment confirmations are sent to this address.', 'wp-exam-success' ); ?></div>
					</div>
					<div class="mb-3">
						<label class="form-label"><?php esc_html_e( 'Suitable Classes', 'wp-exam-success' ); ?></label>
						<div class="wpes-teacher-classes border rounded p-2" style="max-height: 220px; overflow-y: auto;">
							<?php if ( empty( $classes ) ) : ?>
								<div class="text-muted small"><?php esc_html_e( 'No active classes yet.', 'wp-exam-success' ); ?></div>
							<?php endif; ?>
							<?php foreach ( $classes as $class ) : ?>
								<div class="form-check">
									<input class="form-check-input wpes-teacher-class-check" type="checkbox" name="class_ids[]" value="<?php echo esc_attr( $class->id ); ?>" id="wpesTeacherClass<?php echo esc_attr( $class->id ); ?>" />
									<label class="form-check-label" for="wpesTeacherClass<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->name ); ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="wpesTeacherStatus"><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></label>
						<select class="form-select" id="wpesTeacherStatus" name="status">
							<option value="active"><?php esc_html_e( 'Active', 'wp-exam-success' ); ?></option>
							<option value="archived"><?php esc_html_e( 'Archived', 'wp-exam-success' ); ?></option>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'wp-exam-success' ); ?></button>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save Teacher', 'wp-exam-success' ); ?></button>
				</div>
				<input type="hidden" name="id" id="wpesTeacherId" value="0" />
			</form>
		</div>
	</div>
</div>
