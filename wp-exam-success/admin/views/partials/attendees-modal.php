<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $attendees */
/** @var array $log */
?>
<h6><?php esc_html_e( 'Attendees', 'wp-exam-success' ); ?></h6>
<?php if ( empty( $attendees ) ) : ?>
	<p class="text-muted"><?php esc_html_e( 'No attendees yet.', 'wp-exam-success' ); ?></p>
<?php else : ?>
	<table class="table table-sm">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'wp-exam-success' ); ?></th>
				<th><?php esc_html_e( 'Email', 'wp-exam-success' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-exam-success' ); ?></th>
				<th><?php esc_html_e( 'Order', 'wp-exam-success' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $attendees as $attendee ) : ?>
				<tr>
					<td><?php echo esc_html( $attendee->customer_name ?: '—' ); ?></td>
					<td><?php echo esc_html( $attendee->customer_email ?: '—' ); ?></td>
					<td><?php echo esc_html( ucfirst( $attendee->status ) ); ?></td>
					<td><?php echo $attendee->order_id ? '#' . (int) $attendee->order_id : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<h6 class="mt-4"><?php esc_html_e( 'Meeting Link Send History', 'wp-exam-success' ); ?></h6>
<?php if ( empty( $log ) ) : ?>
	<p class="text-muted"><?php esc_html_e( 'No sends recorded yet.', 'wp-exam-success' ); ?></p>
<?php else : ?>
	<table class="table table-sm">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Sent At (site time)', 'wp-exam-success' ); ?></th>
				<th><?php esc_html_e( 'Type', 'wp-exam-success' ); ?></th>
				<th><?php esc_html_e( 'Recipients', 'wp-exam-success' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $log as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( get_date_from_gmt( $entry->sent_at, 'M j, Y g:i A' ) ); ?></td>
					<td><?php echo esc_html( ucfirst( $entry->send_type ) ); ?></td>
					<td><?php echo esc_html( $entry->recipient_count ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
