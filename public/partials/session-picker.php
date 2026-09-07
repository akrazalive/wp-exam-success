<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $sessions */
/** @var int $required_count */

// Group by class for a readable picker instead of one long flat list.
$grouped = array();
foreach ( $sessions as $session ) {
	$grouped[ $session->class_name ][] = $session;
}
?>
<div class="wpes-session-picker" data-required="<?php echo esc_attr( $required_count ); ?>">
	<p class="wpes-picker-instructions">
		<?php
		printf(
			/* translators: %d: number of sessions the customer must choose */
			esc_html__( 'Choose %d session(s) below. Times are shown in your local time zone.', 'wp-exam-success' ),
			(int) $required_count
		);
		?>
	</p>

	<p class="wpes-picker-count" aria-live="polite">
		<span class="wpes-selected-count">0</span> / <?php echo esc_html( $required_count ); ?> <?php esc_html_e( 'selected', 'wp-exam-success' ); ?>
	</p>

	<?php if ( empty( $sessions ) ) : ?>
		<p class="wpes-notice"><?php esc_html_e( 'No sessions currently have open seats. Please check back soon.', 'wp-exam-success' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $grouped as $class_name => $class_sessions ) : ?>
		<fieldset class="wpes-class-group">
			<legend><?php echo esc_html( $class_name ); ?></legend>
			<ul class="wpes-session-list">
				<?php foreach ( $class_sessions as $session ) : ?>
					<?php
					$utc_iso = gmdate( 'c', strtotime( $session->starts_at_gmt ) );
					$end_iso = gmdate( 'c', strtotime( $session->ends_at_gmt ) );
					?>
					<li class="wpes-session-item">
						<label>
							<input
								type="checkbox"
								name="wpes_session_ids[]"
								value="<?php echo esc_attr( $session->id ); ?>"
								class="wpes-session-checkbox"
							/>
							<span class="wpes-session-title"><?php echo esc_html( $session->title ?: $class_name ); ?></span>
							<span class="wpes-local-time" data-utc="<?php echo esc_attr( $utc_iso ); ?>" data-utc-end="<?php echo esc_attr( $end_iso ); ?>">
								<?php echo esc_html( $session->starts_at_gmt ); ?> UTC
							</span>
							<span class="wpes-seats-remaining" data-remaining="<?php echo esc_attr( $session->remaining ); ?>">
								<?php
								printf(
									/* translators: %d: seats remaining */
									esc_html__( '%d seat(s) left', 'wp-exam-success' ),
									(int) $session->remaining
								);
								?>
							</span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php endforeach; ?>
</div>
