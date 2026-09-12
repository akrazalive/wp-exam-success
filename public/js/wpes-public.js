/**
 * WPES Public Booking Calendar
 */
(function ($) {
    'use strict';

    var STORAGE_KEY = 'wpes_booking_state';
    var TZ_STORAGE_KEY = 'wpes_timezone';
    var TZ_MANUAL_IP_KEY = 'wpes_tz_manual_ip';
    var IP_COOKIE_NAME = 'wpes_visitor_ip';
    var packagesCache = [];

    function wpesAjaxUrl() {
        return wpesBooking.ajaxUrl + (wpesBooking.ajaxUrl.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now() + Math.random().toString(36).slice(2);
    }

    var state = {
        currentWeekStart: null,
        viewMode: 'list',
        savedViewMode: 'list',
        timezone: '',
        filters: { class_id: '', level: '', day: '', time: '', availability: '' },
        package: null,
        selectedSessions: [],
        // Final Acceptance review (2026-09-11, hardened 2026-09-12): the
        // session a visitor clicked to select BEFORE any package was chosen
        // yet. toggleSession() records it here instead of just discarding
        // it, so selectPackage() can carry it over as the first selected
        // session once a package is actually picked — previously the click
        // was silently lost and the visitor had to click the same session
        // again after choosing a package.
        //
        // Client re-reported this as "intermittently" still lost
        // (2026-09-12) after the first fix, which stored only the session
        // ID and re-looked it up in state.sessionsData inside
        // selectPackage(). That lookup can fail if sessionsData is
        // replaced in between (e.g. the automatic timezone-detection reload
        // in initTimezone() firing while the package modal is still open,
        // or a week/date-range change) — same session ID, but the array
        // holding it has since been swapped out, or the newly-loaded range
        // no longer includes that specific session. Storing the full
        // session snapshot at the moment of the click — when we already
        // know for certain it exists, since the click came from a card
        // built from the current sessionsData — removes that dependency
        // completely; selectPackage() never needs to look anything up
        // again afterward.
        pendingSession: null,
        sessionsData: [],
        isLoading: false,
        visitorIp: '',
        customRange: null
    };

    var litepickerInstance = null;

    var $booking, $calendar, $weekRange, $weekPrev, $weekNext,
        $tzPill, $tzValue, $tzModal, $tzSearch, $tzList, $tzApply,
        $packageModal, $packageList,
        $stickyBar, $stickyPackageName, $stickyCount, $stickyProgressFill,
        $stickyList, $checkoutBtn, $changePackage,
        $viewToggles, $filters, $detailsModal,
        $weekNav, $weekRangeWrap, $dateRangeBtn, $dateRangeActive, $dateRangeText, $dateRangeClear,
        $viewToggleWrap, $stickyListToggle, $stickyListToggleLabel, $weeknavHint;

    $(document).ready(function () {
        if (!$('#wpesBooking').length) return;
        cacheDOM();
        initFiltersFromUrl();
        buildFilterDropdowns();
        bindEvents();
        initTimezone();
        initStateFromStorage();
        setInitialWeek();
        initDateRangePicker();
        detectIpTimezone();
        loadCalendar();
    });

    function cacheDOM() {
        $booking            = $('#wpesBooking');
        $calendar           = $('#wpesCalendar');
        $weekRange          = $('#wpesWeekRange');
        $weekPrev           = $('#wpesWeekPrev');
        $weekNext           = $('#wpesWeekNext');
        $tzPill             = $('#wpesTzPill');
        $tzValue            = $('#wpesTzValue');
        $tzModal             = $('#wpesTzModal');
        $tzSearch            = $('#wpesTzSearch');
        $tzList              = $('#wpesTzList');
        $tzApply             = $('#wpesTzApply');
        $packageModal        = $('#wpesPackageModal');
        $packageList         = $('#wpesPackageList');
        $stickyBar           = $('#wpesStickyBar');
        $stickyPackageName   = $('#wpesStickyPackageName');
        $stickyCount         = $('#wpesStickyCount');
        $stickyProgressFill  = $('#wpesStickyProgressFill');
        $stickyList          = $('#wpesStickyList');
        $checkoutBtn         = $('#wpesCheckoutBtn');
        $changePackage       = $('#wpesChangePackage');
        $viewToggles          = $('.wpes-view-toggle');
        $filters              = $('.wpes-filter__select');
        $detailsModal         = $('#wpesDetailsModal');
        $weekNav              = $('#wpesWeekNav');
        $weekRangeWrap        = $('#wpesWeekRangeWrap');
        $dateRangeBtn         = $('#wpesDateRangeBtn');
        $dateRangeActive      = $('#wpesDateRangeActive');
        $dateRangeText        = $('#wpesDateRangeText');
        $dateRangeClear       = $('#wpesDateRangeClear');
        $viewToggleWrap       = $('#wpesViewToggleWrap');
        $stickyListToggle     = $('#wpesStickyListToggle');
        $stickyListToggleLabel = $('#wpesStickyListToggleLabel');
        $weeknavHint          = $('#wpesWeeknavHint');
    }

    /* ---- Query string filters ---- */
    function initFiltersFromUrl() {
        var params = new URLSearchParams(window.location.search);
        ['class_id', 'level', 'day', 'time', 'availability'].forEach(function (key) {
            if (params.has('wpes_' + key)) {
                state.filters[key] = params.get('wpes_' + key);
            }
        });
        if (params.has('wpes_view')) {
            state.viewMode = params.get('wpes_view');
            state.savedViewMode = state.viewMode;
            $viewToggles.removeClass('is-active');
            $viewToggles.filter('[data-view="' + state.viewMode + '"]').addClass('is-active');
        }
        $filters.each(function () {
            var name = $(this).attr('name');
            if (state.filters[name] !== undefined) {
                $(this).val(state.filters[name]);
            }
        });
    }

    function syncFiltersToUrl() {
        var params = new URLSearchParams(window.location.search);
        ['class_id', 'level', 'day', 'time', 'availability'].forEach(function (key) {
            params.delete('wpes_' + key);
            if (state.filters[key]) {
                params.set('wpes_' + key, state.filters[key]);
            }
        });
        params.set('wpes_view', state.viewMode);
        var qs = params.toString();
        var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
        window.history.replaceState(null, '', url);
    }

    /* ---- Custom filter dropdowns (native <select> stays hidden, drives value + change) ---- */
    function buildFilterDropdowns() {
        $filters.each(function () {
            var $select = $(this);
            var $filter = $select.closest('.wpes-filter');
            var $dropdown = $filter.find('.wpes-filter__dropdown');

            $dropdown.empty();
            $select.find('option').each(function () {
                var $opt = $(this);
                var $item = $('<div class="wpes-filter__option"></div>')
                    .attr('data-value', $opt.attr('value'))
                    .text($opt.text());
                if ($opt.is(':selected')) $item.addClass('is-selected');
                $dropdown.append($item);
            });

            syncFilterValueText($filter);
        });
    }

    function syncFilterValueText($filter) {
        var $select = $filter.find('.wpes-filter__select');
        var selectedText = $select.find('option:selected').text();
        $filter.find('.wpes-filter__value').text(selectedText);
    }

    function closeAllFilterDropdowns(except) {
        $('.wpes-filter').each(function () {
            if (except && this === except.get(0)) return;
            $(this).removeClass('is-open').find('.wpes-filter__dropdown').prop('hidden', true);
        });
    }

    function toggleFilterDropdown($filter) {
        var isOpen = $filter.hasClass('is-open');
        closeAllFilterDropdowns();
        if (!isOpen) {
            $filter.addClass('is-open').find('.wpes-filter__dropdown').prop('hidden', false);
        }
    }

    function selectFilterOption($filter, value) {
        var $select = $filter.find('.wpes-filter__select');
        $select.val(value);
        $filter.find('.wpes-filter__option').removeClass('is-selected');
        $filter.find('.wpes-filter__option[data-value="' + value + '"]').addClass('is-selected');
        syncFilterValueText($filter);
        closeAllFilterDropdowns();
        $select.trigger('change');
    }

    /* ---- Cookies ---- */
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function setCookie(name, value, opts) {
        opts = opts || {};
        var parts = [name + '=' + encodeURIComponent(value)];
        var maxAge = opts.maxAge || 365 * 24 * 60 * 60;
        parts.push('max-age=' + maxAge);
        parts.push('path=' + (opts.path || '/'));
        if (opts.domain) {
            parts.push('domain=' + opts.domain);
        }
        parts.push('SameSite=Lax');
        if (opts.secure || window.location.protocol === 'https:') {
            parts.push('Secure');
        }
        document.cookie = parts.join('; ');
    }

    /* ---- Timezone ---- */
    function initTimezone() {
        var stored = localStorage.getItem(TZ_STORAGE_KEY);
        if (stored && moment.tz.zone(stored)) {
            state.timezone = stored;
        } else {
            state.timezone = moment.tz.guess() || 'UTC';
        }
        updateTzDisplay();
    }

    function isManualTimezoneForIp(ip) {
        var manualIp = localStorage.getItem(TZ_MANUAL_IP_KEY);
        if (!manualIp) return false;
        if (manualIp === '*') return true;
        return ip && manualIp === ip;
    }

    function detectIpTimezone() {
        var storedIp = getCookie(IP_COOKIE_NAME);

        $.post(wpesAjaxUrl(), {
            action: 'wpes_detect_timezone',
            nonce: wpesBooking.nonce,
            stored_ip: storedIp
        }).done(function (res) {
            if (!res.success || !res.data) return;

            var currentIp = res.data.ip || '';
            state.visitorIp = currentIp;

            if (res.data.cookie && res.data.cookie.value) {
                setCookie(res.data.cookie.name || IP_COOKIE_NAME, res.data.cookie.value, {
                    maxAge: res.data.cookie.maxAge,
                    path: res.data.cookie.path,
                    domain: res.data.cookie.domain,
                    secure: res.data.cookie.secure
                });
            } else if (currentIp) {
                setCookie(IP_COOKIE_NAME, currentIp);
            }

            // Manual override for this IP (or pending before IP was known) — do not re-guess.
            if (isManualTimezoneForIp(currentIp)) {
                if (localStorage.getItem(TZ_MANUAL_IP_KEY) === '*' && currentIp) {
                    localStorage.setItem(TZ_MANUAL_IP_KEY, currentIp);
                }
                return;
            }

            var guessedTz = res.data.timezone;
            if (!guessedTz || !moment.tz.zone(guessedTz)) return;

            var manualIp = localStorage.getItem(TZ_MANUAL_IP_KEY);
            var ipChanged = !!(storedIp && currentIp && storedIp !== currentIp);
            var staleManual = !!(manualIp && manualIp !== '*' && currentIp && manualIp !== currentIp);
            var isFirstVisit = !storedIp;
            var shouldApplyGuess = isFirstVisit || ipChanged || staleManual;

            if (!shouldApplyGuess) return;

            var tzChanged = state.timezone !== guessedTz;
            state.timezone = guessedTz;
            localStorage.setItem(TZ_STORAGE_KEY, guessedTz);
            localStorage.removeItem(TZ_MANUAL_IP_KEY);
            updateTzDisplay();

            if (tzChanged) {
                setInitialWeek();
                loadCalendar();
            }

            if ((ipChanged || staleManual) && typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: wpesBooking.i18n.tzUpdatedTitle,
                    text: wpesBooking.i18n.tzUpdated.replace('%s', guessedTz),
                    timer: 5000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    }

    function updateTzDisplay() {
        var abbr = moment().tz(state.timezone).format('z');
        $tzValue.text(state.timezone + (abbr ? ' (' + abbr + ')' : ''));
    }

    function getZoneMoment(utcString) {
        return moment.utc(utcString).tz(state.timezone);
    }

    function setInitialWeek() {
        state.currentWeekStart = moment().tz(state.timezone).startOf('isoWeek');
        updateWeekRangeDisplay();
    }

    function updateWeekRangeDisplay() {
        var start = state.currentWeekStart.clone();
        var end   = start.clone().add(6, 'days');
        var fmt   = start.year() !== end.year() ? 'D MMM YYYY' : 'D MMM';
        $weekRange.text(start.format(fmt) + ' – ' + end.format('D MMM YYYY'));
    }

    function shiftWeek(dir) {
        state.currentWeekStart.add(dir * 7, 'days');
        updateWeekRangeDisplay();
        loadCalendar();
    }

    /* ---- Custom date range ---- */
    var MOBILE_BREAKPOINT = 640;

    function isMobileViewport() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    function initDateRangePicker() {
        if (typeof Litepicker === 'undefined' || !$dateRangeBtn.length) return;

        createLitepicker(isMobileViewport());

        var resizeTimer = null;
        var currentlyMobile = isMobileViewport();
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                var nowMobile = isMobileViewport();
                if (nowMobile !== currentlyMobile) {
                    currentlyMobile = nowMobile;
                    createLitepicker(nowMobile);
                }
            }, 200);
        });
    }

    function createLitepicker(mobile) {
        if (litepickerInstance) {
            litepickerInstance.destroy();
            litepickerInstance = null;
        }

        litepickerInstance = new Litepicker({
            element: $dateRangeBtn.get(0),
            parentEl: document.body,
            singleMode: false,
            numberOfMonths: mobile ? 1 : 2,
            numberOfColumns: mobile ? 1 : 2,
            minDate: moment().tz(state.timezone).format('YYYY-MM-DD'),
            format: 'DD MMM YYYY',
            tooltipText: { one: 'day', other: 'days' },
            setup: function (picker) {
                picker.on('selected', function (start, end) {
                    applyCustomRange(moment(start.dateInstance), moment(end.dateInstance));
                });
            }
        });
    }

    function applyCustomRange(start, end) {
        if (!state.customRange) {
            state.savedViewMode = state.viewMode;
        }
        state.customRange = {
            start: start.clone().startOf('day'),
            end: end.clone().endOf('day')
        };
        state.viewMode = 'list';

        $weekRangeWrap.prop('hidden', true);
        $weekPrev.prop('hidden', true);
        $weekNext.prop('hidden', true);

        $viewToggles.removeClass('is-active');
        $viewToggles.filter('[data-view="' + state.viewMode + '"]').addClass('is-active');

        var fmt = state.customRange.start.year() !== state.customRange.end.year() ? 'D MMM YYYY' : 'D MMM';
        $dateRangeText.text(state.customRange.start.format(fmt) + ' – ' + state.customRange.end.format('D MMM YYYY'));
        $dateRangeActive.prop('hidden', false);

        loadCalendar();
    }

    function clearCustomRange() {
        state.customRange = null;
        state.viewMode = state.savedViewMode;
        $viewToggles.removeClass('is-active');
        $viewToggles.filter('[data-view="' + state.viewMode + '"]').addClass('is-active');

        $dateRangeActive.prop('hidden', true);
        $weekRangeWrap.prop('hidden', false);
        $weekPrev.prop('hidden', false);
        $weekNext.prop('hidden', false);
        $viewToggleWrap.prop('hidden', false);

        if (litepickerInstance) litepickerInstance.clearSelection();

        loadCalendar();
    }

    /* ---- Calendar AJAX ---- */
    function loadCalendar() {
        if (state.isLoading) return;
        state.isLoading = true;
        $booking.addClass('is-loading');
        $calendar.html('<div class="wpes-calendar__loading"><span class="wpes-spinner"></span> ' + wpesBooking.i18n.loading + '</div>');

        var startGMT, endGMT;
        if (state.customRange) {
            startGMT = state.customRange.start.clone().utc().format('YYYY-MM-DD HH:mm:ss');
            endGMT   = state.customRange.end.clone().utc().format('YYYY-MM-DD HH:mm:ss');
        } else {
            startGMT = state.currentWeekStart.clone().utc().format('YYYY-MM-DD HH:mm:ss');
            endGMT   = state.currentWeekStart.clone().add(7, 'days').utc().format('YYYY-MM-DD HH:mm:ss');
        }

        $.ajax({
            url: wpesAjaxUrl(),
            type: 'POST',
            data: {
                action: 'wpes_get_calendar',
                nonce: wpesBooking.nonce,
                start_gmt: startGMT,
                end_gmt: endGMT,
                class_id: state.filters.class_id,
                level: state.filters.level
            },
            success: function (res) {
                state.isLoading = false;
                $booking.removeClass('is-loading');
                if (res.success && res.data && res.data.sessions) {
                    state.sessionsData = res.data.sessions;
                    renderCalendar();
                } else {
                    $calendar.html('<div class="wpes-calendar__empty">' + wpesBooking.i18n.noSessions + '</div>');
                }
            },
            error: function () {
                state.isLoading = false;
                $booking.removeClass('is-loading');
                $calendar.html('<div class="wpes-calendar__empty">' + wpesBooking.i18n.loadError + '</div>');
            }
        });
    }

    function renderCalendar() {
        var sessions = applyClientFilters(state.sessionsData);
        if (sessions.length === 0) {
            $calendar.html('<div class="wpes-calendar__empty">' + wpesBooking.i18n.noSessions + '</div>');
            return;
        }
        if (state.viewMode === 'week') {
            renderWeekView(sessions);
        } else {
            renderListView(sessions);
        }
    }

    function applyClientFilters(sessions) {
        return sessions.filter(function (s) {
            var m = getZoneMoment(s.starts_at_gmt);
            if (state.filters.day !== '' && m.day() !== parseInt(state.filters.day, 10)) return false;
            if (state.filters.time !== '') {
                var hour = m.hour();
                if (state.filters.time === 'morning' && hour >= 12) return false;
                if (state.filters.time === 'afternoon' && (hour < 12 || hour >= 17)) return false;
                if (state.filters.time === 'evening' && hour < 17) return false;
            }
            if (state.filters.availability === 'available' && s.remaining <= 0) return false;
            return true;
        });
    }

    function renderWeekView(sessions) {
        var dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        var dayCount = 7;
        var rangeStart = state.currentWeekStart;
        if (state.customRange) {
            rangeStart = state.customRange.start.clone().startOf('day');
            dayCount = state.customRange.end.clone().startOf('day').diff(rangeStart, 'days') + 1;
        }

        var days = {};
        $.each(sessions, function (i, s) {
            var key = getZoneMoment(s.starts_at_gmt).format('YYYY-MM-DD');
            if (!days[key]) days[key] = [];
            days[key].push(s);
        });

        var $wrap = $('<div class="wpes-calendar__week"></div>');
        for (var d = 0; d < dayCount; d++) {
            var dayMoment = rangeStart.clone().add(d, 'days');
            var dayKey = dayMoment.format('YYYY-MM-DD');
            var daySessions = days[dayKey] || [];
            var $col = $('<div class="wpes-day' + (dayMoment.isSame(moment().tz(state.timezone), 'day') ? ' is-today' : '') + '"></div>');
            $col.append('<div class="wpes-day__header"><span class="wpes-day__name">' + dayNames[dayMoment.isoWeekday() - 1] + '</span><span class="wpes-day__num">' + dayMoment.date() + '</span></div>');
            var $list = $('<div class="wpes-day__list"></div>');
            if (!daySessions.length) {
                $list.append('<div class="wpes-day__empty">—</div>');
            } else {
                $.each(daySessions, function (j, s) { $list.append(buildSessionCard(s)); });
            }
            $col.append($list);
            $wrap.append($col);
        }
        $calendar.empty().append($wrap);
    }

    function renderListView(sessions) {
        sessions.sort(function (a, b) {
            return moment.utc(a.starts_at_gmt).diff(moment.utc(b.starts_at_gmt));
        });
        var $wrap = $('<div class="wpes-calendar__list"></div>');
        var currentDay = null;
        $.each(sessions, function (i, s) {
            var dayKey = getZoneMoment(s.starts_at_gmt).format('YYYY-MM-DD');
            if (dayKey !== currentDay) {
                currentDay = dayKey;
                var label = getZoneMoment(s.starts_at_gmt).format('dddd, D MMMM').toUpperCase();
                $wrap.append('<div class="wpes-day-group__label">' + label + '</div>');
            }
            $wrap.append(buildSessionCard(s));
        });
        $calendar.empty().append($wrap);
    }

    function buildSessionCard(session) {
        var tpl = document.getElementById('wpesSessionRowTpl');
        var $card = $(tpl.content.cloneNode(true)).children('.wpes-session');
        var start = getZoneMoment(session.starts_at_gmt);
        var end   = getZoneMoment(session.ends_at_gmt);
        var rem   = parseInt(session.remaining, 10) || 0;
        var max   = parseInt(session.max_attendees, 10) || 0;
        var booked = Math.max(0, max - rem);
        var availText, availClass;

        if (rem <= 0) {
            availText = wpesBooking.i18n.fullyBooked;
            availClass = 'is-full';
        } else if (booked > 9 && rem === 1) {
            availText = wpesBooking.i18n.remainingOf.replace('%1$d', rem).replace('%2$d', max);
            availClass = 'is-almost-full';
        } else if (booked > 9 && rem > 1) {
            availText = wpesBooking.i18n.remainingOf.replace('%1$d', rem).replace('%2$d', max);
            availClass = 'is-limited';
        } else {
            // 0–9 bookings with spots remaining
            availText = wpesBooking.i18n.available;
            availClass = 'is-open';
        }

        $card.attr('data-session-id', session.id);
        $card.addClass('wpes-session--icon-' + session.icon_index);
        $card.find('.wpes-session__title').text(session.class_name);
        $card.find('.wpes-session__level').text(session.level || '');
        $card.find('.wpes-session__sub').text(session.title);
        var durationMins = end.diff(start, 'minutes');
        $card.find('.wpes-session__time-text').html(
            start.format('HH:mm') + '-' + end.format('HH:mm') +
            '<br><small>(' + durationMins + ' Min.)</small>'
        );
        $card.find('.wpes-session__avail-text').text(availText);
        $card.find('.wpes-session__avail').addClass(availClass);

        var $chooseBtn = $card.find('.wpes-session__choose');
        if (rem <= 0) {
            $chooseBtn.prop('disabled', true).addClass('is-disabled');
            $chooseBtn.find('.wpes-session__choose-label').text(wpesBooking.i18n.full);
        } else if (isSessionSelected(session.id)) {
            $chooseBtn.addClass('is-selected');
            $chooseBtn.find('.wpes-session__choose-label').text(wpesBooking.i18n.selected);
        } else {
            $chooseBtn.find('.wpes-session__choose-label').text('+');
        }
        return $card;
    }

    /* ---- Session selection ---- */
    function isSessionSelected(id) {
        // Also treat the not-yet-committed pending click (no package chosen
        // yet) as "selected" so the "+" button reflects the click
        // immediately, instead of looking unclicked while the package
        // modal is open — see state.pendingSession above.
        if (state.pendingSession && state.pendingSession.id === id) return true;
        return state.selectedSessions.some(function (s) { return s.id === id; });
    }

    $( '.view-packages-btn' ).on( 'click', function() {
        openPackageModal();
    } );

    function toggleSession(sessionId) {
        if (!state.package) {
            var clicked = findSessionById(sessionId);
            state.pendingSession = clicked ? {
                id: clicked.id, title: clicked.title, class_name: clicked.class_name,
                level: clicked.level, starts_at_gmt: clicked.starts_at_gmt, ends_at_gmt: clicked.ends_at_gmt
            } : null;
            renderCalendar();
            openPackageModal();
            return;
        }
        var idx = state.selectedSessions.findIndex(function (s) { return s.id === sessionId; });
        if (idx >= 0) {
            state.selectedSessions.splice(idx, 1);
        } else {
            var session = findSessionById(sessionId);
            if (!session) return;
            var max = state.package.unlimited ? 999 : state.package.required;
            if (!state.package.unlimited && state.selectedSessions.length >= max) return;
            state.selectedSessions.push({
                id: session.id, title: session.title, class_name: session.class_name,
                level: session.level, starts_at_gmt: session.starts_at_gmt, ends_at_gmt: session.ends_at_gmt
            });
        }
        saveState();
        renderCalendar();
        updateStickyBar();
    }

    function findSessionById(id) {
        return state.sessionsData.find(function (s) { return s.id === id; }) || null;
    }

    /* ---- Sticky bar ---- */
    function updateStickyBar() {
        if (!state.package) { $stickyBar.prop('hidden', true); $( 'body' ).removeClass( 'package-bar-open' ); return; }
        
        $stickyBar.prop('hidden', false);
        $( 'body' ).addClass( 'package-bar-open' );

        $stickyPackageName.text(state.package.name);
        var required = state.package.unlimited ? state.package.min : state.package.required;
        var count = state.selectedSessions.length;
        var total = state.package.unlimited ? Math.max(required, count) : required;
        var pct = Math.min((count / total) * 100, 100);

        $stickyCount.text(state.package.unlimited
            ? wpesBooking.i18n.sessionsUnlimited.replace('%1$d', count).replace('%2$d', required)
            : wpesBooking.i18n.sessionsOf.replace('%1$d', count).replace('%2$d', required));
        $stickyProgressFill.css('width', pct + '%');
        $stickyListToggleLabel.text(count + (count === 1 ? ' session' : ' sessions'));

        $stickyList.empty();
        $.each(state.selectedSessions, function (i, s) {
            var m = getZoneMoment(s.starts_at_gmt);
            var $li = $('<li class="wpes-stickybar__item"></li>');
            $li.append('<span class="wpes-stickybar__item-title">' + escapeHtml(s.title) + (s.level ? ' <small>(' + escapeHtml(s.level) + ')</small>' : '') + '</span>');
            $li.append('<span class="wpes-stickybar__item-time">' + m.format('ddd, D MMM • h:mm A') + '</span>');
            $li.append('<button type="button" class="wpes-stickybar__remove" data-session-id="' + s.id + '">&times;</button>');
            $stickyList.append($li);
        });

        var canCheckout = state.package.unlimited ? count >= state.package.min : count >= state.package.required;
        $checkoutBtn.prop('disabled', !canCheckout);
    }

    /* ---- Package modal ---- */
    function openPackageModal() {
        $packageModal.prop('hidden', false);
        $('body').addClass('wpes-modal-open');
        loadPackages();
    }

    function closePackageModal() {
        $packageModal.prop('hidden', true);
        $('body').removeClass('wpes-modal-open');
        // Dismissed without choosing a package (X / backdrop) — don't let a
        // stale pending click surface later against an unrelated package.
        // selectPackage() already consumes and clears this before calling
        // closePackageModal() on the success path, so this is a no-op there.
        if (state.pendingSession) {
            state.pendingSession = null;
            renderCalendar();
        }
    }

    function loadPackages() {
        $packageList.html('<li class="wpes-package-list__loading">' + wpesBooking.i18n.loading + '</li>');
        $.post(wpesAjaxUrl(), { action: 'wpes_get_packages', nonce: wpesBooking.nonce })
            .done(function (res) {
                if (res.success && res.data && res.data.packages) {
                    packagesCache = res.data.packages;
                    renderPackages(packagesCache);
                } else {
                    $packageList.html('<li class="wpes-package-list__empty">' + wpesBooking.i18n.loadError + '</li>');
                }
            })
            .fail(function () {
                $packageList.html('<li class="wpes-package-list__empty">' + wpesBooking.i18n.loadError + '</li>');
            });
    }

    function renderPackages(packages) {
        $packageList.empty();
        $.each(packages, function (i, pkg) {
            var meta = pkg.unlimited ? 'Unlimited sessions (min ' + pkg.min + ')' : pkg.required + ' session' + (pkg.required > 1 ? 's' : '');
            var $li = $('<li class="wpes-package" data-package-id="' + pkg.id + '"></li>');
            $li.append('<div class="wpes-package__name">' + escapeHtml(pkg.name) + '</div>');
            $li.append('<div class="wpes-package__price">' + pkg.price_html + '</div>');
            if (pkg.description) $li.append('<div class="wpes-package__desc">' + escapeHtml(pkg.description) + '</div>');
            $li.append('<div class="wpes-package__meta">' + meta + '</div>');
            $li.append('<button type="button" class="wpes-btn wpes-btn--primary wpes-package__choose">' + wpesBooking.i18n.choosePackage + '</button>');
            $packageList.append($li);
        });
    }

    function selectPackage(pkg) {
        state.package = pkg;
        state.selectedSessions = [];

        // Carry over the session the visitor clicked before this package
        // modal was opened (see state.pendingSession above), so it doesn't
        // have to be re-clicked. Uses the snapshot captured at click time
        // directly — no re-lookup against state.sessionsData here, which is
        // exactly what could silently fail if that array had been replaced
        // in the meantime (see state.pendingSession's comment).
        if (state.pendingSession) {
            state.selectedSessions.push(state.pendingSession);
        }
        state.pendingSession = null;

        saveState();
        closePackageModal();
        updateStickyBar();
        renderCalendar();
    }

    /* ---- Details modal ---- */
    function openDetailsModal(sessionId) {
        var session = findSessionById(sessionId);
        if (!session) return;
        var start = getZoneMoment(session.starts_at_gmt);
        var end   = getZoneMoment(session.ends_at_gmt);

        $('#wpesDetailsTitle').text(session.title);
        $('#wpesDetailsMeta').html(
            '<strong>' + escapeHtml(session.class_name) + '</strong>' +
            (session.level ? ' · ' + escapeHtml(session.level) : '') +
            '<br>' + start.format('dddd, D MMMM YYYY') +
            '<br>' + start.format('h:mm A') + ' – ' + end.format('h:mm A') + ' (' + escapeHtml(state.timezone) + ')'
        );

        var classDesc = session.class_description ? '<h4>' + wpesBooking.i18n.classDescription + '</h4><div class="wpes-details__content">' + session.class_description + '</div>' : '';
        var sessDesc  = session.description ? '<h4>' + wpesBooking.i18n.sessionDescription + '</h4><div class="wpes-details__content">' + session.description + '</div>' : '';
        $('#wpesDetailsBody').html(classDesc + sessDesc || '<p class="wpes-details__empty">No additional details for this session.</p>');

        $detailsModal.prop('hidden', false);
        $('body').addClass('wpes-modal-open');
    }

    function closeDetailsModal() {
        $detailsModal.prop('hidden', true);
        $('body').removeClass('wpes-modal-open');
    }

    /* ---- Timezone modal ---- */
    var tzData = [];

    function openTzModal() {
        $tzModal.prop('hidden', false);
        $('body').addClass('wpes-modal-open');
        if (!tzData.length) {
            tzData = moment.tz.names().map(function (z) {
                return { name: z, offset: moment().tz(z).format('Z') };
            }).sort(function (a, b) {
                return a.offset !== b.offset ? a.offset.localeCompare(b.offset) : a.name.localeCompare(b.name);
            });
        }
        renderTzList(tzData);
    }

    function closeTzModal() {
        $tzModal.prop('hidden', true);
        $('body').removeClass('wpes-modal-open');
    }

    function renderTzList(list) {
        $tzList.empty();
        $.each(list, function (i, tz) {
            var $li = $('<li class="wpes-tz-item' + (tz.name === state.timezone ? ' is-active' : '') + '" data-tz="' + tz.name + '"></li>');
            $li.append('<span class="wpes-tz-item__name">' + escapeHtml(tz.name) + '</span>');
            $li.append('<span class="wpes-tz-item__offset">' + tz.offset + '</span>');
            $tzList.append($li);
        });
    }

    function applyTimezone(newTz) {
        state.timezone = newTz;
        localStorage.setItem(TZ_STORAGE_KEY, newTz);
        var ip = state.visitorIp || getCookie(IP_COOKIE_NAME);
        localStorage.setItem(TZ_MANUAL_IP_KEY, ip || '*');
        updateTzDisplay();
        closeTzModal();
        setInitialWeek();
        loadCalendar();
    }

    /* ---- Storage ---- */
    function saveState() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ package: state.package, selectedSessions: state.selectedSessions }));
    }

    function initStateFromStorage() {
        try {
            var data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            if (data.package) state.package = data.package;
            if (Array.isArray(data.selectedSessions)) state.selectedSessions = data.selectedSessions;
            updateStickyBar();
        } catch (e) { /* ignore */ }
    }

    /* ---- Checkout ---- */
    function doCheckout() {
        if (!state.package || !state.selectedSessions.length) return;
        var required = state.package.unlimited ? state.package.min : state.package.required;
        if (state.selectedSessions.length < required) return;

        var sessionData = state.selectedSessions.map(function (s) {
            var m = getZoneMoment(s.starts_at_gmt);
            return { id: s.id, title: s.title, class_name: s.class_name, level: s.level,
                starts_at_gmt: s.starts_at_gmt, local_time: m.format('YYYY-MM-DD HH:mm:ss'), timezone: state.timezone };
        });

        var $form = $('<form method="post" action="' + wpesBooking.cartUrl + '" style="display:none;"></form>');
        $form.append('<input type="hidden" name="add-to-cart" value="' + state.package.id + '" />');
        $form.append('<input type="hidden" name="quantity" value="1" />');
        $form.append('<input type="hidden" name="wpes_sessions" value="' + encodeURIComponent(JSON.stringify(sessionData)) + '" />');
        $('body').append($form);
        localStorage.removeItem(STORAGE_KEY);
        $form.submit();
    }

    /* ---- Events ---- */
    function bindEvents() {
        $weekPrev.on('click', function () { shiftWeek(-1); });
        $weekNext.on('click', function () { shiftWeek(1); });

        $weeknavHint.on('click', function () {
            if ($dateRangeBtn.length) $dateRangeBtn.trigger('click');
        });

        $viewToggles.on('click', function () {
            var mode = $(this).data('view');
            if (mode === state.viewMode) return;
            state.viewMode = mode;
            $viewToggles.removeClass('is-active');
            $(this).addClass('is-active');
            syncFiltersToUrl();
            renderCalendar();
        });

        $('.wpes-filters').on('click', '.wpes-filter__trigger', function (e) {
            e.stopPropagation();
            toggleFilterDropdown($(this).closest('.wpes-filter'));
        });

        $('.wpes-filters').on('click', '.wpes-filter__option', function () {
            var $filter = $(this).closest('.wpes-filter');
            selectFilterOption($filter, $(this).attr('data-value'));
        });

        $(document).on('click', function () {
            closeAllFilterDropdowns();
        });

        $filters.on('change', function () {
            var name = $(this).attr('name');
            state.filters[name] = $(this).val();
            syncFiltersToUrl();
            var serverFilters = ['class_id', 'level'];
            if (serverFilters.indexOf(name) !== -1) {
                loadCalendar();
            } else {
                $booking.addClass('is-filtering');
                setTimeout(function () {
                    renderCalendar();
                    $booking.removeClass('is-filtering');
                }, 150);
            }
        });

        $tzPill.on('click', openTzModal);
        $tzModal.on('click', '[data-close]', closeTzModal);
        $tzModal.on('click', '.wpes-tz-item', function () {
            $tzList.find('.wpes-tz-item').removeClass('is-active');
            $(this).addClass('is-active');
        });
        $tzApply.on('click', function () {
            var selected = $tzList.find('.wpes-tz-item.is-active').data('tz');
            if (selected) applyTimezone(selected);
        });
        $tzSearch.on('input', function () {
            var q = $(this).val().toLowerCase();
            renderTzList(tzData.filter(function (tz) { return tz.name.toLowerCase().indexOf(q) !== -1; }));
        });

        $packageModal.on('click', '[data-close]', closePackageModal);
        $packageList.on('click', '.wpes-package__choose', function () {
            var pkgId = $(this).closest('.wpes-package').data('package-id');
            var pkg = packagesCache.find(function (p) { return p.id == pkgId; });
            if (pkg) selectPackage(pkg);
        });

        $calendar.on('click', '.wpes-session__choose:not(.is-disabled):not(:disabled)', function () {
            toggleSession(parseInt($(this).closest('.wpes-session').data('session-id'), 10));
        });
        $calendar.on('click', '.wpes-session__details', function () {
            openDetailsModal(parseInt($(this).closest('.wpes-session').data('session-id'), 10));
        });

        $detailsModal.on('click', '[data-close]', closeDetailsModal);
        $changePackage.on('click', openPackageModal);
        $checkoutBtn.on('click', doCheckout);
        $stickyList.on('click', '.wpes-stickybar__remove', function () {
            toggleSession(parseInt($(this).data('session-id'), 10));
        });

        $('#wpesStickyToggle').on('click', function () {
            $stickyBar.toggleClass('is-collapsed');
        });

        $stickyListToggle.on('click', function () {
            var expanded = $stickyList.toggleClass('is-collapsed').hasClass('is-collapsed') === false;
            $(this).attr('aria-expanded', expanded ? 'true' : 'false');
        });

        $dateRangeClear.on('click', clearCustomRange);

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                closeTzModal();
                closePackageModal();
                closeDetailsModal();
                closeAllFilterDropdowns();
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})(jQuery);
