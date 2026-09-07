<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<h1 class="wpes-page-title"><?php esc_html_e( 'Teacher Invites', 'wp-exam-success' ); ?></h1>
		<p class="text-muted"><?php esc_html_e( 'Every Accept-Link invitation sent to a teacher, across all sessions, in one place — who was asked, when, and how they responded.', 'wp-exam-success' ); ?></p>

		<div class="row g-2 mb-3">
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesInviteStatusFilter">
					<option value=""><?php esc_html_e( 'All statuses', 'wp-exam-success' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'wp-exam-success' ); ?></option>
					<option value="accepted"><?php esc_html_e( 'Accepted', 'wp-exam-success' ); ?></option>
					<option value="expired"><?php esc_html_e( 'Expired', 'wp-exam-success' ); ?></option>
					<option value="superseded"><?php esc_html_e( 'Superseded', 'wp-exam-success' ); ?></option>
				</select>
			</div>
		</div>

		<table id="wpesInvitesTable" class="table table-striped table-bordered w-100">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Session', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Teacher', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Sent', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Responded', 'wp-exam-success' ); ?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>
