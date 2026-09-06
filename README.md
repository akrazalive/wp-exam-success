# WP Exam Success

A WordPress/WooCommerce plugin that sells course "packages" (WooCommerce
products), where each package entitles the buyer to a fixed number of
class sessions that they choose themselves at purchase time, from a
pool of recurring, capacity-limited sessions.

## Why custom DB tables instead of CPTs/post meta

WooCommerce products stay as the *sellable unit* (price, checkout, tax,
refunds). Everything about sessions — recurrence, per-occurrence
capacity, attendee lists, meeting-link send logs — lives in five
purpose-built tables (`wp_wpes_*`). Reasons:

- **Capacity correctness under concurrency.** Two customers can hit
  "add to cart" on the last seat at the same instant. `WPES_Bookings::reserve()`
  uses `SELECT ... FOR UPDATE` inside a transaction to serialize this —
  something you cannot do reliably against `postmeta`.
- **Reporting speed.** The Bookings & Reporting screen is a handful of
  indexed JOINs, not a `WP_Query` meta-query scan.
- **Recurrence.** Each occurrence of a repeating session is its own row
  (sharing a `series_id`), so each Monday's "Reading" session has its
  own independent capacity, attendee list, and meeting link — while
  still supporting "cancel all future occurrences" as one query.

## Schema

- `wpes_classes` — subjects (e.g. "Reading").
- `wpes_sessions` — individual session occurrences. `starts_at_gmt` /
  `ends_at_gmt` are always UTC. `series_id` groups recurring
  occurrences together.
- `wpes_bookings` — one row per seat. `status`: `pending` (reserved at
  add-to-cart, holds for 20 minutes) → `confirmed` (order paid) →
  `cancelled`/`expired` (seat released).
- `wpes_meeting_link_log` — audit trail of every meeting-link email
  blast (who sent it, when, to how many).
- `wpes_package_classes` — which classes a given WooCommerce product
  allows sessions to be chosen from.

## Booking lifecycle (concurrency-safe)

1. Customer checks N sessions on the product page → AJAX add-to-cart.
2. `WPES_WooCommerce::add_cart_item_data()` calls `WPES_Bookings::reserve()`
   per selected session — each reservation is a locked transaction, so
   overselling is structurally prevented, not just checked-then-hoped.
3. Reservations are `pending` with a 20-minute hold. A cron
   (`wpes_cleanup_expired_reservations`, every 5 min) releases stale
   holds from abandoned carts.
4. On `woocommerce_order_status_processing` / `completed`, bookings
   flip to `confirmed`. On `cancelled` / `failed` / `refunded`, they're
   released back to the pool.

## Timezones

- Everything in the DB is UTC (`*_gmt` columns).
- Admin screens render times converted to the site's configured WP
  timezone (`get_date_from_gmt()`), with the timezone name shown so
  nobody mistakes it for their own local time.
- The frontend renders a `data-utc="2026-08-03T14:00:00Z"` attribute
  and JS (`Intl.DateTimeFormat`) converts to the visitor's actual
  browser timezone — this is the one strict requirement, and it's
  handled entirely client-side so it's always correct regardless of
  server config.

## Admin UI

Bootstrap 5 + DataTables, both loaded from CDN (`admin/class-wpes-admin.php::enqueue_assets()`),
rather than hand-rolled admin CSS — gives sortable/searchable/paginated
tables on Classes, Sessions, and Bookings out of the box.

## What still needs product/dev decisions before going live

- **Payment failure mid-series:** if a package buys 5 sessions and
  payment fails, all 5 reservations release together (order-level),
  which seems right but confirm with the client.
- **Refund/reschedule UX:** there's no "swap this session for another"
  flow yet — cancelling a booking and re-adding to cart is the only
  path today.
- **.ics calendar attachments** on the meeting-link email — not built,
  flagged as a good v1.1 addition.
- **Waitlists** for full sessions — not built; `wpes_session_full`
  currently just blocks the add-to-cart.
- Load-test `WPES_Bookings::reserve()` against your actual expected
  concurrent-checkout volume before a big launch — the row lock is
  correct but worth confirming under real traffic.
