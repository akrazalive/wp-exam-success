<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var string[] $form_names */
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<h1 class="wpes-page-title"><?php esc_html_e( 'Waitlist', 'wp-exam-success' ); ?></h1>
		<p class="text-muted"><?php esc_html_e( 'Submissions collected via the Elementor Forms “Waitlist” action.', 'wp-exam-success' ); ?></p>

		<div class="row g-2 mb-3 align-items-end">
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesWaitlistFormFilter">
					<option value=""><?php esc_html_e( 'All forms', 'wp-exam-success' ); ?></option>
					<?php foreach ( $form_names as $form_name ) : ?>
						<option value="<?php echo esc_attr( $form_name ); ?>"><?php echo esc_html( $form_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-auto">
				<button type="button" class="btn btn-sm btn-primary" id="wpesWaitlistBulkMessage"><?php esc_html_e( 'Message selected', 'wp-exam-success' ); ?></button>
			</div>
			<div class="col-auto">
				<button type="button" class="btn btn-sm btn-outline-primary" id="wpesWaitlistBulkFiltered"><?php esc_html_e( 'Message all filtered', 'wp-exam-success' ); ?></button>
			</div>
		</div>

		<table id="wpesWaitlistTable" class="table table-striped table-bordered w-100">
			<thead>
				<tr>
					<th><input type="checkbox" class="form-check-input" id="wpesWaitlistCheckAll" /></th>
					<th><?php esc_html_e( 'Name', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Form', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Fields', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Notified', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-exam-success' ); ?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>

<div class="modal fade" id="wpesWaitlistMessageModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?php esc_html_e( 'Send message', 'wp-exam-success' ); ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'wp-exam-success' ); ?>"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="wpesWaitlistMessageIds" value="" />
				<input type="hidden" id="wpesWaitlistMessageAllFiltered" value="0" />
				<p class="small text-muted mb-3" id="wpesWaitlistMessageRecipients"></p>
				<div class="mb-3">
					<label class="form-label" for="wpesWaitlistMessageSubject"><?php esc_html_e( 'Subject', 'wp-exam-success' ); ?></label>
					<input type="text" class="form-control" id="wpesWaitlistMessageSubject" />
				</div>
				<div class="mb-3">
					<label class="form-label" for="wpesWaitlistMessageBody"><?php esc_html_e( 'Message', 'wp-exam-success' ); ?></label>
					<textarea class="form-control" id="wpesWaitlistMessageBody" rows="8"></textarea>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancel', 'wp-exam-success' ); ?></button>
				<button type="button" class="btn btn-primary" id="wpesWaitlistSendBtn"><?php esc_html_e( 'Send', 'wp-exam-success' ); ?></button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="wpesWaitlistDetailsModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><?php esc_html_e( 'Submission details', 'wp-exam-success' ); ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'wp-exam-success' ); ?>"></button>
			</div>
			<div class="modal-body" id="wpesWaitlistDetailsBody"></div>
		</div>
	</div>
</div>
