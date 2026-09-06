<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="wpes-page-title mb-0"><?php esc_html_e( 'Classes', 'wp-exam-success' ); ?></h1>
			<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wpesClassModal" id="wpesAddClassBtn">
				<?php esc_html_e( '+ Add Class', 'wp-exam-success' ); ?>
			</button>
		</div>

		<div class="row g-2 mb-3">
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesClassStatusFilter">
					<option value=""><?php esc_html_e( 'All statuses', 'wp-exam-success' ); ?></option>
					<option value="active"><?php esc_html_e( 'Active', 'wp-exam-success' ); ?></option>
					<option value="archived"><?php esc_html_e( 'Archived', 'wp-exam-success' ); ?></option>
				</select>
			</div>
		</div>

		<table id="wpesClassesTable" class="table table-striped table-bordered w-100">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Description', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-exam-success' ); ?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>

<div class="modal fade" id="wpesClassModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="wpesClassForm">
				<div class="modal-header">
					<h5 class="modal-title"><?php esc_html_e( 'Add Class', 'wp-exam-success' ); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="wpesClassName"><?php esc_html_e( 'Name', 'wp-exam-success' ); ?> <span class="text-danger">*</span></label>
						<input type="text" class="form-control" id="wpesClassName" name="name" required />
					</div>
					<div class="mb-3">
						<label class="form-label" for="wpesClassDescription"><?php esc_html_e( 'Description', 'wp-exam-success' ); ?></label>
						<?php
						wp_editor(
							'',
							'wpesClassDescription',
							array(
								'textarea_name' => 'description',
								'media_buttons' => false,
								'textarea_rows' => 6,
								'teeny'         => true,
							)
						);
						?>
					</div>
					<div class="mb-3">
						<label class="form-label" for="wpesClassStatus"><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></label>
						<select class="form-select" id="wpesClassStatus" name="status">
							<option value="active"><?php esc_html_e( 'Active', 'wp-exam-success' ); ?></option>
							<option value="archived"><?php esc_html_e( 'Archived', 'wp-exam-success' ); ?></option>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'wp-exam-success' ); ?></button>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save Class', 'wp-exam-success' ); ?></button>
				</div>
				<input type="hidden" name="id" id="wpesClassId" value="0" />
			</form>
		</div>
	</div>
</div>
