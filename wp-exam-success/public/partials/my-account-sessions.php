<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $active_list */
/** @var array $upcoming */
/** @var array $past */
/** @var array $on_hold */
/** @var string $tab */
/** @var string $base_url */
/** @var array $replacement_credits */
/** @var array $available_sessions */
$wpes_notice = isset( $_GET['wpes_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpes_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wpes-myaccount">
	<div class="wpes-myaccount__header">
		<h2 class="title is-2"><?php esc_html_e( 'My Sessions', 'wp-exam-success' ); ?></h2>
		<p class="subtitle is-4 has-text-grey"><?php esc_html_e( 'View your booked class sessions.', 'wp-exam-success' ); ?></p>
	</div>

	<?php if ( 'redeem_success' === $wpes_notice ) : ?>
		<div class="notification is-success is-light"><?php esc_html_e( 'Your replacement session is booked and confirmed.', 'wp-exam-success' ); ?></div>
	<?php elseif ( 'redeem_failed' === $wpes_notice ) : ?>
		<div class="notification is-danger is-light"><?php esc_html_e( 'That session could no longer be booked with this credit — it may have just filled up. Please try a different one.', 'wp-exam-success' ); ?></div>
	<?php elseif ( 'redeem_missing' === $wpes_notice ) : ?>
		<div class="notification is-danger is-light"><?php esc_html_e( 'Please choose a session before submitting.', 'wp-exam-success' ); ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $replacement_credits ) ) : ?>
		<div class="notification is-warning is-light wpes-myaccount__credits">
			<p class="has-text-weight-semibold"><?php esc_html_e( 'Replacement session(s) available', 'wp-exam-success' ); ?></p>
			<?php foreach ( $replacement_credits as $credit ) : ?>
				<div class="wpes-credit-row">
					<p>
						<?php
						printf(
							/* translators: 1: original class/session name */
							esc_html__( 'Your session for %s did not reach the minimum number of participants and was cancelled. Choose a replacement below — no additional charge.', 'wp-exam-success' ),
							esc_html( $credit->source_title ?: ( $credit->source_class_name ?: __( 'a class', 'wp-exam-success' ) ) )
						);
						?>
					</p>
					<?php if ( empty( $available_sessions ) ) : ?>
						<p class="has-text-grey"><?php esc_html_e( 'No sessions currently have open seats. Please check back soon.', 'wp-exam-success' ); ?></p>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpes-redeem-form">
							<input type="hidden" name="action" value="wpes_redeem_credit" />
							<input type="hidden" name="credit_id" value="<?php echo esc_attr( $credit->id ); ?>" />
							<?php wp_nonce_field( 'wpes_redeem_credit', 'wpes_redeem_nonce' ); ?>
							<div class="select">
								<select name="session_id" required>
									<option value=""><?php esc_html_e( 'Select a session…', 'wp-exam-success' ); ?></option>
									<?php foreach ( $available_sessions as $option ) : ?>
										<option value="<?php echo esc_attr( $option->id ); ?>">
											<?php
											echo esc_html(
												sprintf(
													'%s — %s (%s)',
													$option->class_name,
													$option->title ?: $option->class_name,
													get_date_from_gmt( $option->starts_at_gmt, 'M j, Y g:i A' )
												)
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<button type="submit" class="button is-warning"><?php esc_html_e( 'Confirm Replacement Session', 'wp-exam-success' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="tabs is-boxed wpes-myaccount__tabs">
		<ul>
			<li class="<?php echo 'upcoming' === $tab ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( add_query_arg( 'wpes_tab', 'upcoming', $base_url ) ); ?>">
					<?php
					printf(
						/* translators: %d: session count */
						esc_html__( 'Upcoming (%d)', 'wp-exam-success' ),
						count( $upcoming )
					);
					?>
				</a>
			</li>
			<li class="<?php echo 'past' === $tab ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( add_query_arg( 'wpes_tab', 'past', $base_url ) ); ?>">
					<?php
					printf(
						/* translators: %d: session count */
						esc_html__( 'Past (%d)', 'wp-exam-success' ),
						count( $past )
					);
					?>
				</a>
			</li>
			<li class="<?php echo 'on-hold' === $tab ? 'is-active' : ''; ?>">
				<a href="<?php echo esc_url( add_query_arg( 'wpes_tab', 'on-hold', $base_url ) ); ?>">
					<?php
					printf(
						/* translators: %d: session count */
						esc_html__( 'On-Hold (%d)', 'wp-exam-success' ),
						count( $on_hold )
					);
					?>
				</a>
			</li>
		</ul>
	</div>

	<?php if ( 'on-hold' === $tab && ! empty( $active_list ) ) : ?>
		<div class="notification is-warning is-light wpes-myaccount__onhold-notice">
			<p class="has-text-weight-semibold"><?php esc_html_e( 'Booking pending payment confirmation', 'wp-exam-success' ); ?></p>
			<p><?php esc_html_e( 'A booking has been created for the session(s) below, but your order is currently on-hold pending payment confirmation. Your seat will be confirmed automatically once payment clears.', 'wp-exam-success' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $active_list ) ) : ?>
		<div class="notification is-light wpes-myaccount__empty">
			<?php if ( 'past' === $tab ) : ?>
				<p class="has-text-weight-semibold"><?php esc_html_e( 'No past sessions', 'wp-exam-success' ); ?></p>
				<p><?php esc_html_e( 'Sessions you have attended will appear here.', 'wp-exam-success' ); ?></p>
			<?php elseif ( 'on-hold' === $tab ) : ?>
				<p class="has-text-weight-semibold"><?php esc_html_e( 'No on-hold bookings', 'wp-exam-success' ); ?></p>
				<p><?php esc_html_e( 'Bookings awaiting payment confirmation will appear here.', 'wp-exam-success' ); ?></p>
			<?php else : ?>
				<p class="has-text-weight-semibold"><?php esc_html_e( 'No upcoming sessions', 'wp-exam-success' ); ?></p>
				<p><?php esc_html_e( 'Book a session from our schedule to get started.', 'wp-exam-success' ); ?></p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="columns is-multiline">
			<?php foreach ( $active_list as $session ) : ?>
				<?php
				$title     = $session->title ?: $session->class_name;
				$date      = get_date_from_gmt( $session->starts_at_gmt, 'l, F j, Y' );
				$time      = get_date_from_gmt( $session->starts_at_gmt, 'g:i A' ) . ' – ' . get_date_from_gmt( $session->ends_at_gmt, 'g:i A' );
				$tz_label  = wp_timezone_string();
				?>
				<div class="column is-half-tablet is-one-third-desktop">
					<div class="card wpes-session-card">
						<div class="card-content">
							<p class="heading has-text-weight-semibold has-text-primary"><?php echo esc_html( $session->class_name ); ?></p>
							<p class="title is-5 mb-2"><?php echo esc_html( $title ); ?></p>
							<div class="content is-medium">
								<p>
									<span class="icon-text">
										<span class="icon"><i class="wpes-icon-calendar" aria-hidden="true">📅</i></span>
										<span><?php echo esc_html( $date ); ?></span>
									</span>
								</p>
								<p>
									<span class="icon-text">
										<span class="icon"><i class="wpes-icon-clock" aria-hidden="true">🕐</i></span>
										<span><?php echo esc_html( $time . ' (' . $tz_label . ')' ); ?></span>
									</span>
								</p>
								<?php if ( ! empty( $session->meeting_link ) ) : ?>
									<p class="mt-3">
										<a href="<?php echo esc_url( $session->meeting_link ); ?>" class="button is-link is-medium is-fullwidth" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Join Meeting', 'wp-exam-success' ); ?>
										</a>
									</p>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
