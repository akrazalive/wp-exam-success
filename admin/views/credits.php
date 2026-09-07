<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<h1 class="wpes-page-title"><?php esc_html_e( 'Replacement Credits', 'wp-exam-success' ); ?></h1>
		<p class="text-muted"><?php esc_html_e( 'One row per replacement credit issued when a session did not reach its minimum participant count — previously only visible one customer at a time via My Account.', 'wp-exam-success' ); ?></p>

		<div class="row g-2 mb-3">
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesCreditStatusFilter">
					<option value=""><?php esc_html_e( 'All statuses', 'wp-exam-success' ); ?></option>
					<option value="available"><?php esc_html_e( 'Available', 'wp-exam-success' ); ?></option>
					<option value="used"><?php esc_html_e( 'Used', 'wp-exam-success' ); ?></option>
				</select>
			</div>
		</div>

		<table id="wpesCreditsTable" class="table table-striped table-bordered w-100">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Source Session (cancelled)', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Issued', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Redeemed For', 'wp-exam-success' ); ?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>
