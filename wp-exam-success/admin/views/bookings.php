<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $classes */
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<h1 class="wpes-page-title"><?php esc_html_e( 'Bookings & Reporting', 'wp-exam-success' ); ?></h1>

		<ul class="nav nav-tabs mb-3" id="wpesBookingsTabs">
			<li class="nav-item">
				<button class="nav-link active" data-tab="active" type="button"><?php esc_html_e( 'Active Bookings', 'wp-exam-success' ); ?></button>
			</li>
			<li class="nav-item">
				<button class="nav-link" data-tab="pending" type="button"><?php esc_html_e( 'Pending Bookings', 'wp-exam-success' ); ?></button>
			</li>
			<li class="nav-item">
				<button class="nav-link" data-tab="on-hold" type="button"><?php esc_html_e( 'On-Hold', 'wp-exam-success' ); ?></button>
			</li>
			<li class="nav-item">
				<button class="nav-link" data-tab="cancelled" type="button"><?php esc_html_e( 'Cancelled', 'wp-exam-success' ); ?></button>
			</li>
		</ul>

		<div class="row g-2 mb-3">
			<div class="col-auto">
				<select class="form-select form-select-sm" id="wpesBookingClassFilter">
					<option value=""><?php esc_html_e( 'All classes', 'wp-exam-success' ); ?></option>
					<?php foreach ( $classes as $class ) : ?>
						<option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<table id="wpesBookingsTable" class="table table-striped table-bordered w-100">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Class', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Session', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Session Time', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Order Reference', 'wp-exam-success' ); ?></th>
					<th><?php esc_html_e( 'Booked On', 'wp-exam-success' ); ?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>

<input type="hidden" id="wpesBookingTabFilter" value="active" />
