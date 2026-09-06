<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array<string,bool> $filters */
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
	</div>
</div>
