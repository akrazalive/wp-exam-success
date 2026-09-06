# AGENTS.md

Guidance for AI agents and human contributors working on **WP Exam Success**
(`wp-exam-success`). This file is mandatory reading before making any change.

## You must be a proficient WordPress developer

This is a production WordPress/WooCommerce plugin, not a generic PHP app.
Every edit operates inside the WordPress plugin API. To work here competently
you must already know, and you will be expected to apply without being told:

- The plugin lifecycle: `activation/deactivation hooks`, `plugins_loaded`,
  `init`, and when each hook actually fires.
- Hooks and their parameter signatures — `add_action`/`add_filter` with exact
  `$accepted_args` counts (see `WPES_WooCommerce::init()` for correct usage).
- The `$wpdb` API: `prepare()`, `insert()`, `update()`, `get_row`/`get_results`/
  `get_var`, format array types, and `$wpdb->prefix` (never hardcode a prefix).
- `dbDelta()` table definition format from `includes/class-wpes-activator.php`.
- WordPress security non-negotiables:
  - Input handling: `sanitize_*()` / `wp_unslash()` on every `$_GET`/`$_POST`/
    `$_COOKIE` read.
  - Output: `esc_html()`/`esc_attr()`/`esc_url()` for variables in markup, or
    WPCS-compliant escaping in views.
  - `check_ajax_referer()` on every `wp_ajax_*` endpoint (see
    `WPES_Timezone::ajax_detect()`).
  - Capability checks with `current_user_can()` on admin/AJAX actions
    (`WPES_Admin::CAP = 'manage_woocommerce'`).
  - All CRUD through `$wpdb->prepare()` with placeholders — never string
    interpolation of untrusted values into SQL.
- WooCommerce specifics: cart item data filters, order status transitions
  (`processing`/`completed`/`cancelled`/`failed`/`refunded`), line-item meta,
  `WC_Geolocation`, and the Store API (`woocommerce_store_api_checkout_order_processed`).
- i18n: every user-facing string wrapped in `__( '…', 'wp-exam-success' )`.

If you are not confident with the above, stop and ask for review rather than
guessing — this plugin is deployed to real customers and holds real money and
seats.

## Project overview

Sells course "packages" (WooCommerce products). Each package grants a fixed
number of sessions the customer picks from recurring, capacity-limited class
sessions. Handles reservation locking, timezone-correct display, meeting-link
dispatch, and waitlists.

Core rule — the product is the sellable unit in WooCommerce; session data lives
in custom `wp_wpes_*` tables (not CPTs/postmeta). See `README.md` for the full
rationale, schema, and booking lifecycle. Read it before touching bookings.

## Architecture

Everything is static classes, bootstrapped from `wp-exam-success.php::wpes_bootstrap()`.

| File | Responsibility |
| --- | --- |
| `wp-exam-success.php` | Entry point, constants, boot order, WC-required bail |
| `includes/class-wpes-activator.php` | `dbDelta()` schema + version upgrades |
| `includes/class-wpes-db.php` | Table-name helpers + `now_gmt()` |
| `includes/class-wpes-classes.php` | Subjects (e.g. "Reading") |
| `includes/class-wpes-sessions.php` | Session occurrences, recurrence, capacity |
| `includes/class-wpes-bookings.php` | Seat lifecycle — the concurrency-critical code |
| `includes/class-wpes-woocommerce.php` | Package products, cart, order lifecycle |
| `includes/class-wpes-my-account.php` | Customer's purchased sessions |
| `includes/class-wpes-magic-login.php` | Token-based auto login |
| `includes/class-wpes-timezone.php` | IP → timezone detection |
| `includes/class-wpes-emailer.php` | Meeting-link and notification emails |
| `includes/class-wpes-cron.php` | WP-Cron schedules (5 min / 12 hr sweeps) |
| `includes/class-wpes-waitlist.php` | Waitlist capture + matching |
| `includes/elementor/…` | Elementor Pro Forms "Waitlist" action |
| `admin/class-wpes-admin.php` | Admin menu, assets, AJAX endpoints, DataTables |
| `admin/views/…` | Admin screen templates |
| `public/class-wpes-public.php` | Frontend rendering + booking calendar |

### Conventions to follow

- One main class per file, named `WPES_*`, matching the filename
  `class-wpes-*.php`. Static methods only; `init()` hooks everything.
- Indent with tabs (WordPress coding standards). Spaces are wrong here.
- PHP 7.4 minimum — no typed properties, no `match`, no arrow fn in changed
  code unless added deliberately. Null-coalescing `??` is preferred (already used).
- Prefix everything global by hand: functions `wpes_*`, hooks/actions/events
  `wpes_*`, nonces/options/cookies `wpes_*`, AJAX actions `wp_ajax_wpes_*`.
- Every file starts with the ABSPATH guard.
- Do not add comments unless they explain a non-obvious decision (the existing
  code documents *why*, e.g. the `FOR UPDATE` rationale — protect that).

### Database rules (strict)

- All timestamps in DB are UTC (`*_gmt`, `created_at`, …). Always write
  `WPES_DB::now_gmt()`, never `date()`/`current_time('mysql')`.
- Access tables only via `WPES_DB::*_table()` helpers — never hand-write
  `$wpdb->prefix . 'wpes_…'` outside `class-wpes-db.php` and the activator.
- Schema changes go through `dbDelta()` in `WPES_Activator::create_tables()`.
  Bump `DB_VERSION` and `WPES_VERSION`, and add an idempotent migration in
  `maybe_upgrade()` if data must be transformed (see waitlist backfill pattern).

### Concurrency — do not break this

`WPES_Bookings::reserve()` is the only place where correctness depends on
serializing concurrent writes. It uses `START TRANSACTION` + `SELECT … FOR UPDATE`
on the session row guard. Never replace it with a "check then insert" pattern,
never remove the lock, never add an operation that bypasses it (a manual
enroll path, an admin bulk action, a refund release) without going through the
same locking discipline. Add new capacity-releasing paths through
`WPES_Bookings` methods.

### Timezones — the other hard invariant

- Everything rendered in admin: convert with `get_date_from_gmt()` and always
  show the site timezone name.
- Frontend: render UTC with a `data-utc` attribute and let JS
  (`Intl.DateTimeFormat`) convert client-side. This must not regress to
  server-side rendering of "the" local time.

### Assets

- Admin uses Bootstrap 5 + DataTables, both via CDN, enqueued in
  `WPES_Admin::enqueue_assets()`. Reuse them; don't hand-roll admin tables.
- Version local assets with `filemtime(...)`, CDN assets with fixed versions.
- Frontend JS/CSS live in `public/js` and `public/css`.

## Workflow and verification

- No Composer, no npm, no PHPCS config in this repo. Verification is manual:
  1. Lint every touched PHP file: `php -l path/to/file.php`.
  2. Grep your diff for the non-negotiables before finishing: any `$_SERVER`/
     `$_POST`/`$_GET`/`$_COOKIE` read must be sanitized + unserialized via
     `wp_unslash`; any echoed variable must be escaped; any new AJAX handler
     must nonce-check and capability-check.
  3. Never commit unless explicitly asked.
- Keep changes scoped: add a new feature = new `WPES_*` class or `init()`
  hook in this one, wire it in `wp-exam-success.php`, register tables in the
  activator if needed. Don't restructure existing classes as a side effect.
- When you are about to change `reserve()`/timezone/order-status logic, call it
  out in your change summary — it gets a human review.