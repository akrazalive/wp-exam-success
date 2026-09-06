<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$classes_table  = WPES_DB::classes_table();
$sessions_table = WPES_DB::sessions_table();
$bookings_table = WPES_DB::bookings_table();

$total_classes      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$classes_table} WHERE status = 'active'" );
$upcoming_sessions  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE status = 'scheduled' AND starts_at_gmt >= %s", WPES_DB::now_gmt() ) );
$confirmed_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'confirmed'" );
$sessions_no_link   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE status = 'scheduled' AND starts_at_gmt >= %s AND (meeting_link IS NULL OR meeting_link = '')", WPES_DB::now_gmt() ) );
?>
<div class="wrap wpes-wrap">
	<div class="container-fluid px-0">
		<h1 class="wpes-page-title"><?php esc_html_e( 'Exam Success — Dashboard', 'wp-exam-success' ); ?></h1>

		<div class="row g-3 mt-2">
			<div class="col-md-3">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpes-classes' ) ); ?>" class="text-decoration-none">
					<div class="card shadow-sm wpes-stat-card h-100">
						<div class="card-body">
							<div class="text-muted small"><?php esc_html_e( 'Active Classes', 'wp-exam-success' ); ?></div>
							<div class="fs-2 fw-bold text-dark"><?php echo esc_html( $total_classes ); ?></div>
						</div>
					</div>
				</a>
			</div>
			<div class="col-md-3">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpes-sessions' ) ); ?>" class="text-decoration-none">
					<div class="card shadow-sm wpes-stat-card h-100">
						<div class="card-body">
							<div class="text-muted small"><?php esc_html_e( 'Upcoming Sessions', 'wp-exam-success' ); ?></div>
							<div class="fs-2 fw-bold text-dark"><?php echo esc_html( $upcoming_sessions ); ?></div>
						</div>
					</div>
				</a>
			</div>
			<div class="col-md-3">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpes-bookings' ) ); ?>" class="text-decoration-none">
					<div class="card shadow-sm wpes-stat-card h-100">
						<div class="card-body">
							<div class="text-muted small"><?php esc_html_e( 'Confirmed Bookings', 'wp-exam-success' ); ?></div>
							<div class="fs-2 fw-bold text-dark"><?php echo esc_html( $confirmed_bookings ); ?></div>
						</div>
					</div>
				</a>
			</div>
			<div class="col-md-3">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpes-sessions&wpes_filter=no_link' ) ); ?>" class="text-decoration-none">
					<div class="card shadow-sm wpes-stat-card h-100 <?php echo $sessions_no_link > 0 ? 'border-warning' : ''; ?>">
						<div class="card-body">
							<div class="text-muted small"><?php esc_html_e( 'Sessions Missing Meeting Link', 'wp-exam-success' ); ?></div>
							<div class="fs-2 fw-bold <?php echo $sessions_no_link > 0 ? 'text-warning' : 'text-dark'; ?>"><?php echo esc_html( $sessions_no_link ); ?></div>
						</div>
					</div>
				</a>
			</div>
		</div>
	</div>
</div>
