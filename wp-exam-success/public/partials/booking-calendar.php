<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $classes Active classes, used to populate the Skill filter. */
/** @var array<string,bool> $filters Which filters are enabled in settings. */
$filters = isset( $filters ) && is_array( $filters ) ? $filters : array(
	'class_id'     => true,
	'level'        => true,
	'day'          => true,
	'time'         => true,
	'availability' => true,
);
$any_filter = in_array( true, $filters, true );
?>
<div class="wpes-booking" id="wpesBooking" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">

	<div class="wpes-booking__head">
		<div class="wpes-booking__heading">
			<h2><?php esc_html_e( 'Schedule & Booking', 'wp-exam-success' ); ?></h2>
			<p><?php esc_html_e( 'Choose a session and book when it works for you.', 'wp-exam-success' ); ?></p>
		</div>

		<button type="button" class="wpes-tz-pill" id="wpesTzPill">
			<span class="wpes-tz-pill__icon" aria-hidden="true"></span>
			<span class="wpes-tz-pill__text">
				<span class="wpes-tz-pill__label"><?php esc_html_e( 'Times shown in your local time', 'wp-exam-success' ); ?></span>
				<span class="wpes-tz-pill__value" id="wpesTzValue">&nbsp;</span>
			</span>
			<span class="wpes-tz-pill__change"><?php esc_html_e( 'Change', 'wp-exam-success' ); ?></span>
		</button>
	</div>

	<?php
	$wpes_chevron_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
	$wpes_filter_icons = array(
		'skill' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"></path><rect x="2" y="14" width="5" height="7" rx="1.5"></rect><rect x="17" y="14" width="5" height="7" rx="1.5"></rect></svg>',
		'level' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="20" x2="5" y2="14"></line><line x1="12" y1="20" x2="12" y2="8"></line><line x1="19" y1="20" x2="19" y2="4"></line></svg>',
		'day'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="8" y1="3" x2="8" y2="7"></line><line x1="16" y1="3" x2="16" y2="7"></line></svg>',
		'time'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 16 14"></polyline></svg>',
		'more'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"></line><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"></circle><line x1="4" y1="12" x2="20" y2="12"></line><circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"></circle><line x1="4" y1="18" x2="20" y2="18"></line><circle cx="10" cy="18" r="2" fill="currentColor" stroke="none"></circle></svg>',
	);
	?>
	<?php if ( $any_filter ) : ?>
	<div class="wpes-filters">
		<?php if ( ! empty( $filters['class_id'] ) ) : ?>
		<div class="wpes-filter" data-filter="class_id">
			<button type="button" class="wpes-filter__trigger">
				<span class="wpes-filter__icon"><?php echo $wpes_filter_icons['skill']; // phpcs:ignore ?></span>
				<span class="wpes-filter__text">
					<span class="wpes-filter__label"><?php esc_html_e( 'Skill', 'wp-exam-success' ); ?></span>
					<span class="wpes-filter__value"><?php esc_html_e( 'All Skills', 'wp-exam-success' ); ?></span>
				</span>
				<span class="wpes-filter__chevron"><?php echo $wpes_chevron_svg; // phpcs:ignore ?></span>
			</button>
			<div class="wpes-filter__dropdown" hidden></div>
			<select class="wpes-filter__select" name="class_id" hidden>
				<option value=""><?php esc_html_e( 'All Skills', 'wp-exam-success' ); ?></option>
				<?php foreach ( $classes as $class ) : ?>
					<option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $filters['level'] ) ) : ?>
		<div class="wpes-filter" data-filter="level">
			<button type="button" class="wpes-filter__trigger">
				<span class="wpes-filter__icon"><?php echo $wpes_filter_icons['level']; // phpcs:ignore ?></span>
				<span class="wpes-filter__text">
					<span class="wpes-filter__label"><?php esc_html_e( 'Level', 'wp-exam-success' ); ?></span>
					<span class="wpes-filter__value"><?php esc_html_e( 'All Levels', 'wp-exam-success' ); ?></span>
				</span>
				<span class="wpes-filter__chevron"><?php echo $wpes_chevron_svg; // phpcs:ignore ?></span>
			</button>
			<div class="wpes-filter__dropdown" hidden></div>
			<select class="wpes-filter__select" name="level" hidden>
				<option value=""><?php esc_html_e( 'All Levels', 'wp-exam-success' ); ?></option>
				<?php foreach ( WPES_Sessions::get_levels() as $level_code => $level_label ) : ?>
					<option value="<?php echo esc_attr( $level_code ); ?>"><?php echo esc_html( $level_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $filters['day'] ) ) : ?>
		<div class="wpes-filter" data-filter="day">
			<button type="button" class="wpes-filter__trigger">
				<span class="wpes-filter__icon"><?php echo $wpes_filter_icons['day']; // phpcs:ignore ?></span>
				<span class="wpes-filter__text">
					<span class="wpes-filter__label"><?php esc_html_e( 'Day', 'wp-exam-success' ); ?></span>
					<span class="wpes-filter__value"><?php esc_html_e( 'All Days', 'wp-exam-success' ); ?></span>
				</span>
				<span class="wpes-filter__chevron"><?php echo $wpes_chevron_svg; // phpcs:ignore ?></span>
			</button>
			<div class="wpes-filter__dropdown" hidden></div>
			<select class="wpes-filter__select" name="day" hidden>
				<option value=""><?php esc_html_e( 'All Days', 'wp-exam-success' ); ?></option>
				<option value="1"><?php esc_html_e( 'Monday', 'wp-exam-success' ); ?></option>
				<option value="2"><?php esc_html_e( 'Tuesday', 'wp-exam-success' ); ?></option>
				<option value="3"><?php esc_html_e( 'Wednesday', 'wp-exam-success' ); ?></option>
				<option value="4"><?php esc_html_e( 'Thursday', 'wp-exam-success' ); ?></option>
				<option value="5"><?php esc_html_e( 'Friday', 'wp-exam-success' ); ?></option>
				<option value="6"><?php esc_html_e( 'Saturday', 'wp-exam-success' ); ?></option>
				<option value="0"><?php esc_html_e( 'Sunday', 'wp-exam-success' ); ?></option>
			</select>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $filters['time'] ) ) : ?>
		<div class="wpes-filter" data-filter="time">
			<button type="button" class="wpes-filter__trigger">
				<span class="wpes-filter__icon"><?php echo $wpes_filter_icons['time']; // phpcs:ignore ?></span>
				<span class="wpes-filter__text">
					<span class="wpes-filter__label"><?php esc_html_e( 'Time', 'wp-exam-success' ); ?></span>
					<span class="wpes-filter__value"><?php esc_html_e( 'All Times', 'wp-exam-success' ); ?></span>
				</span>
				<span class="wpes-filter__chevron"><?php echo $wpes_chevron_svg; // phpcs:ignore ?></span>
			</button>
			<div class="wpes-filter__dropdown" hidden></div>
			<select class="wpes-filter__select" name="time" hidden>
				<option value=""><?php esc_html_e( 'All Times', 'wp-exam-success' ); ?></option>
				<option value="morning"><?php esc_html_e( 'Morning (before 12pm)', 'wp-exam-success' ); ?></option>
				<option value="afternoon"><?php esc_html_e( 'Afternoon (12–5pm)', 'wp-exam-success' ); ?></option>
				<option value="evening"><?php esc_html_e( 'Evening (after 5pm)', 'wp-exam-success' ); ?></option>
			</select>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $filters['availability'] ) ) : ?>
		<div class="wpes-filter wpes-filter--more">
			<button type="button" class="wpes-filter__trigger">
				<span class="wpes-filter__icon"><?php echo $wpes_filter_icons['more']; // phpcs:ignore ?></span>
				<span class="wpes-filter__text">
					<span class="wpes-filter__label"><span><?php esc_html_e( 'More', 'wp-exam-success' ) ?> </span><?php esc_html_e( 'Filters', 'wp-exam-success' ); ?></span>
					<span class="wpes-filter__value"><?php esc_html_e( 'Any availability', 'wp-exam-success' ); ?></span>
				</span>
				<span class="wpes-filter__chevron"><?php echo $wpes_chevron_svg; // phpcs:ignore ?></span>
			</button>
			<div class="wpes-filter__dropdown" hidden></div>
			<select class="wpes-filter__select" name="availability" hidden>
				<option value=""><?php esc_html_e( 'Any availability', 'wp-exam-success' ); ?></option>
				<option value="available"><?php esc_html_e( 'Only show open spots', 'wp-exam-success' ); ?></option>
			</select>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="wpes-weeknav" id="wpesWeekNav">
		<button type="button" class="wpes-weeknav__arrow" id="wpesWeekPrev" aria-label="<?php esc_attr_e( 'Previous week', 'wp-exam-success' ); ?>">&lsaquo;</button>
		<div class="wpes-weeknav__range" id="wpesWeekRangeWrap">
			<span class="wpes-weeknav__icon" aria-hidden="true"></span>
			<span id="wpesWeekRange"></span>
		</div>
		<button type="button" class="wpes-weeknav__arrow" id="wpesWeekNext" aria-label="<?php esc_attr_e( 'Next week', 'wp-exam-success' ); ?>">&rsaquo;</button>

		<div class="wpes-daterange-active" id="wpesDateRangeActive" hidden>
			<span class="wpes-daterange-active__text" id="wpesDateRangeText"></span>
			<button type="button" class="wpes-daterange-active__clear" id="wpesDateRangeClear"><?php esc_html_e( 'Clear', 'wp-exam-success' ); ?></button>
		</div>

		<button type="button" class="wpes-daterange-btn" id="wpesDateRangeBtn" aria-label="<?php esc_attr_e( 'Pick a custom date range', 'wp-exam-success' ); ?>">
		<span class="wpes-daterange-btn__icon">
			<svg viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
				<rect width="18" height="18" rx="2" x="3" y="4" />
				<path d="M16 2v4" />
				<path d="M3 10h18" />
				<path d="M8 2v4" />
				<path d="M17 14h-6" />
				<path d="M13 18H7" />
				<path d="M7 14h0.01" />
				<path d="M17 18h0.01" />
			</svg>
		</span>
		</button>

		<div class="wpes-weeknav__view" id="wpesViewToggleWrap">
			<button type="button" class="wpes-view-toggle" data-view="week"><?php esc_html_e( 'Week View', 'wp-exam-success' ); ?></button>
			<button type="button" class="wpes-view-toggle is-active" data-view="list"><?php esc_html_e( 'List View', 'wp-exam-success' ); ?></button>
		</div>
	</div>

	<button type="button" class="wpes-weeknav__hint" id="wpesWeeknavHint">
		<span class="wpes-weeknav__hint-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none" xmlns="http://www.w3.org/2000/svg">
				<rect width="18" height="18" rx="2" x="3" y="4" />
				<path d="M16 2v4" />
				<path d="M3 10h18" />
				<path d="M8 2v4" />
				<path d="M17 14h-6" />
				<path d="M13 18H7" />
				<path d="M7 14h0.01" />
				<path d="M17 18h0.01" />
			</svg>
		</span>
		<span class="wpes-weeknav__hint-text">
			<strong><?php esc_html_e( 'Need more sessions?', 'wp-exam-success' ); ?></strong>
			<span><?php esc_html_e( 'Use the calendar to view additional weeks', 'wp-exam-success' ); ?></span>
		</span>
		<span class="wpes-weeknav__hint-arrow" aria-hidden="true">
			<svg viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M9 6l6 6-6 6" />
			</svg>
		</span>
	</button>

	<div class="wpes-calendar" id="wpesCalendar" aria-live="polite">
		<div class="wpes-calendar__loading"><?php esc_html_e( 'Loading sessions…', 'wp-exam-success' ); ?></div>
	</div>

	<div class="wpes-waitlist">
		<div class="wpes-waitlist__icon" aria-hidden="true"></div>
		<div class="wpes-waitlist__text">
			<strong><?php esc_html_e( "Can't find the right time?", 'wp-exam-success' ); ?></strong>
			<span><?php esc_html_e( 'New sessions are added regularly. Join the waitlist and we’ll notify you when new slots open.', 'wp-exam-success' ); ?></span>
		</div>
		<button type="button" class="wpes-btn wpes-btn--ghost" id="wpesJoinWaitlist"><?php esc_html_e( 'Join Waitlist', 'wp-exam-success' ); ?> &rsaquo;</button>
	</div>

	<!-- Row-level template for a single session (cloned by JS) -->
	<template id="wpesSessionRowTpl">
		<div class="wpes-session" data-session-id="">
			<div class="wpes-session__icon" aria-hidden="true"></div>
			<div class="wpes-session__info">
				<div class="wpes-session__title-line">
					<span class="wpes-session__title"></span>
					<span class="wpes-session__level"></span>
				</div>
				<div class="wpes-session__sub"></div>
			</div>
			<div class="wpes-session__time">
				<span class="wpes-session__time-icon" aria-hidden="true"></span>
				<span class="wpes-session__time-text"></span>
			</div>
			<div class="wpes-session__avail">
				<span class="wpes-session__avail-icon" aria-hidden="true"></span>
				<span class="wpes-session__avail-text"></span>
			</div>
			<div class="wpes-session__actions">
				<button type="button" class="wpes-btn wpes-btn--outline wpes-session__details"><?php esc_html_e( 'View Details', 'wp-exam-success' ); ?></button>
				<button type="button" class="wpes-btn wpes-btn--primary wpes-session__choose">
					<span class="wpes-session__choose-label"></span>
					<span class="wpes-session__choose-icon" aria-hidden="true"></span>
				</button>
			</div>
		</div>
	</template>

	<!-- Sticky selection bar -->
	<div class="wpes-stickybar" id="wpesStickyBar" hidden>
		<button type="button" class="wpes-stickybar__toggle" id="wpesStickyToggle" aria-label="<?php esc_attr_e( 'Toggle selection bar', 'wp-exam-success' ); ?>">&#9660;</button>
		<div class="wpes-stickybar__inner">
			<div class="wpes-stickybar__head">
				<div>
					<strong id="wpesStickyPackageName"></strong>
					<button type="button" class="wpes-stickybar__change" id="wpesChangePackage"><?php esc_html_e( 'Change package', 'wp-exam-success' ); ?></button>
				</div>
				<span id="wpesStickyCount"></span>
				<button type="button" class="wpes-stickybar__list-toggle" id="wpesStickyListToggle" aria-expanded="false">
					<span id="wpesStickyListToggleLabel"></span>
					<span class="wpes-stickybar__list-toggle-icon" aria-hidden="true">&#9662;</span>
				</button>
			</div>
			<div class="wpes-stickybar__progress">
				<div class="wpes-stickybar__progress-fill" id="wpesStickyProgressFill"></div>
			</div>
			<ul class="wpes-stickybar__list is-collapsed" id="wpesStickyList"></ul>
			<button type="button" class="wpes-btn wpes-btn--primary wpes-stickybar__checkout" id="wpesCheckoutBtn" disabled>
				<?php esc_html_e( 'Checkout', 'wp-exam-success' ); ?>
			</button>
		</div>
	</div>

	<!-- Timezone modal -->
	<div class="wpes-modal" id="wpesTzModal" hidden>
		<div class="wpes-modal__backdrop" data-close></div>
		<div class="wpes-modal__dialog wpes-modal__dialog--tz">
			<div class="wpes-modal__header">
				<h3><?php esc_html_e( 'Choose your timezone', 'wp-exam-success' ); ?></h3>
				<button type="button" class="wpes-modal__close" data-close aria-label="<?php esc_attr_e( 'Close', 'wp-exam-success' ); ?>">&times;</button>
			</div>
			<div class="wpes-modal__body">
				<input type="search" class="wpes-tz-search" id="wpesTzSearch" placeholder="<?php esc_attr_e( 'Search for a city or timezone…', 'wp-exam-success' ); ?>" />
				<ul class="wpes-tz-list" id="wpesTzList"></ul>

				<div class="wpes-tz-preview">
					<div class="wpes-weeknav wpes-weeknav--compact">
						<button type="button" class="wpes-weeknav__arrow" id="wpesTzWeekPrev" aria-label="<?php esc_attr_e( 'Previous week', 'wp-exam-success' ); ?>">&lsaquo;</button>
						<div class="wpes-weeknav__range"><span id="wpesTzWeekRange"></span></div>
						<button type="button" class="wpes-weeknav__arrow" id="wpesTzWeekNext" aria-label="<?php esc_attr_e( 'Next week', 'wp-exam-success' ); ?>">&rsaquo;</button>
					</div>
					<p class="wpes-tz-preview__hint"><?php esc_html_e( 'Session times on the schedule will update to this timezone.', 'wp-exam-success' ); ?></p>
				</div>
			</div>
			<div class="wpes-modal__footer">
				<button type="button" class="wpes-btn wpes-btn--ghost" data-close><?php esc_html_e( 'Cancel', 'wp-exam-success' ); ?></button>
				<button type="button" class="wpes-btn wpes-btn--primary" id="wpesTzApply"><?php esc_html_e( 'Apply', 'wp-exam-success' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Session details modal -->
	<div class="wpes-modal" id="wpesDetailsModal" hidden>
		<div class="wpes-modal__backdrop" data-close></div>
		<div class="wpes-modal__dialog wpes-modal__dialog--details">
			<div class="wpes-modal__header">
				<h3 id="wpesDetailsTitle"></h3>
				<button type="button" class="wpes-modal__close" data-close aria-label="<?php esc_attr_e( 'Close', 'wp-exam-success' ); ?>">&times;</button>
			</div>
			<div class="wpes-modal__body wpes-details__body">
				<p class="wpes-details__meta" id="wpesDetailsMeta"></p>
				<div id="wpesDetailsBody"></div>
			</div>
			<div class="wpes-modal__footer">
				<button type="button" class="wpes-btn wpes-btn--primary" data-close><?php esc_html_e( 'Close', 'wp-exam-success' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Package chooser modal -->
	<div class="wpes-modal" id="wpesPackageModal" hidden>
		<div class="wpes-modal__backdrop" data-close></div>
		<div class="wpes-modal__dialog">
			<div class="wpes-modal__header">
				<h3><?php esc_html_e( 'Choose a package', 'wp-exam-success' ); ?></h3>
				<button type="button" class="wpes-modal__close" data-close aria-label="<?php esc_attr_e( 'Close', 'wp-exam-success' ); ?>">&times;</button>
			</div>
			<div class="wpes-modal__body">
				<ul class="wpes-package-list" id="wpesPackageList">
					<li class="wpes-package-list__loading"><?php esc_html_e( 'Loading packages…', 'wp-exam-success' ); ?></li>
				</ul>
			</div>
		</div>
	</div>

</div>
