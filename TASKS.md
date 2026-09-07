# WP Exam Success — Booking Workflow Enhancement — Task Tracker

Tracks progress against the two client specs (`WP_Exam_Success_Booking_Workflow_Developer_Specification_EN_FINAL_v2.pdf` and `WP_Exam_Success_Booking_Workflow_Specification_Overview.pdf`), section by section, with the exact files touched for each part. Companion to `wp-exam-success/CHANGE_LOG.txt`, which has the full dated history (what/why/how verified) — this file is the "where do things stand" index.

**Last updated:** 2026-09-07

## Legend

| Symbol | Meaning |
|---|---|
| ✅ | Done, deployed to staging, and verified live (not just written/linted) |
| ⏸ | Code done and deployed, but inert pending one client decision |
| ❌ | Not started |
| — | Not applicable / process requirement, not a code deliverable |

---

## Summary

| Spec area | Status |
|---|---|
| Capacity + teacher-assignment checks, race-condition safety (§5) | ✅ |
| Minimum participant requirement (§9) | ✅ |
| Teacher assignment via email + Accept-Link (§10) | ✅ |
| Replacement session / credit flow (§11, Overview red path) | ✅ |
| Global Settings additions (§12) | ✅ |
| Payment: pre-authorize → capture on first confirmed session (§6, §7 steps 3/12/13, Overview green-path step 5) | ⏸ — code done, waiting on one gateway setting |

---

## Client Review Round — Concurrency & Safety Hardening (2026-09-07)

Client reviewed the staging build and change log, and flagged four areas to correct before final acceptance (no specific bugs named — each was audited against the running code, and all four turned out to be genuine gaps). Full detail in `wp-exam-success/CHANGE_LOG.txt`'s 2026-09-07 16:00 UTC entry.

| Area flagged | Root cause found | Fix | Verified |
|---|---|---|---|
| Minimum-participant check at final confirmation | `handle_accept()` re-checked "no teacher assigned yet" but never re-checked the minimum was still met at accept time | Folded a live confirmed-attendee COUNT into the same atomic UPDATE that claims the assignment | Code review + regression only — the exact edge case needs the real Accept-Link token (email-only), not exercised live this round |
| Payment-capture safety | `maybe_capture_package_payment()` was a plain read-then-act with no atomicity | New table `wpes_payment_captures`, UNIQUE KEY on `order_id`, claimed via `INSERT IGNORE` | Code review + dbDelta trigger only — needs the WooPayments manual-capture setting on to exercise live (see Open decision below) |
| Replacement-credit concurrency | `redeem_credit()` checked credit status, then wrote 'used' back much later with no WHERE condition — a textbook double-spend race | Claim the credit first via `UPDATE ... WHERE status = 'available'`; hand it back if the reservation itself then fails | ✅ **Live concurrency test**: fired two simultaneous redemption requests at the same real credit — one succeeded, one correctly rejected, exactly one booking created |
| Duplicate teacher invitation rounds | `maybe_invite_teachers()` checked `has_pending_invites()` then inserted rows — another check-then-act race | Wrapped check-and-insert in `START TRANSACTION` + `SELECT ... FOR UPDATE` on the session row | ✅ **Live test**: enrolled a session to minimum (1 invite round sent), enrolled a 6th attendee, confirmed still exactly 1 invite row (no duplicate round) |

All four fixes reuse the same compare-and-set/row-lock discipline already established in `WPES_Bookings::reserve()`, per `AGENTS.md`.

---

## Developer Spec — section by section

### §1–3 — Objective, extend-not-rebuild, staging-only access
— Process requirements, not code. Respected throughout: all work done on `staging.exam-success.de` only, no live-site access at any point, existing plugin architecture extended rather than replaced.

### §4 — Package and Booking Model
✅ Already existed before this project (4/8/16-session WooCommerce products). No changes needed or made.

### §4 (duplicate numbering in source doc) — Rotating/Recurring Course System
✅ Every booking and teacher-assignment attempt re-checks current state rather than relying on cached/prior state.
- `wp-exam-success/includes/class-wpes-teacher-invites.php`
- `wp-exam-success/includes/class-wpes-woocommerce.php`

### §5 — Capacity and Teacher Assignment
✅ Done and verified live (concurrent-safe atomic claim, mirrors the plugin's existing `WPES_Bookings::reserve()` discipline).
- `wp-exam-success/includes/class-wpes-teachers.php` *(new)*
- `wp-exam-success/includes/class-wpes-teacher-invites.php` *(new)*
- `wp-exam-success/includes/class-wpes-db.php`
- `wp-exam-success/includes/class-wpes-activator.php` (new tables/columns)

### §6 — Payment Logic
⏸ Plugin code complete and deployed; inert until WooPayments' "manual capture" gateway setting is turned on (a site config change, not code — paused pending explicit client go-ahead, per the client's own "confirm before payment changes" rule). Verified directly against the installed WooPayments source that this is a genuinely gateway-agnostic trigger (order status transition only, no gateway-specific API calls). Also confirmed: WooPayments cancels an uncaptured authorization after 7 days — the spec's own "verify the gateway's supported pre-auth period" requirement, now answered.
- `wp-exam-success/includes/class-wpes-payments.php` *(new)*
- `wp-exam-success/includes/class-wpes-teacher-invites.php` (capture trigger wired into `finalize_session_confirmation()`)

### §7 — Required Booking Workflow (14 steps)
| Step | Status |
|---|---|
| 1. Select package | — unchanged |
| 2. Select sessions | — unchanged |
| 3. Pre-authorize payment | ⏸ (see §6) |
| 4. Reserve sessions | ✅ existing mechanism, unchanged |
| 5. Process each session separately | ✅ |
| 6. Check capacity | ✅ existing, unchanged |
| 7. Check minimum participants | ✅ |
| 8. Check teacher assignment | ✅ |
| 9. Assign teacher (auto-invite) | ✅ |
| 10. Teacher accepts (first wins) | ✅ |
| 11. Confirm session | ✅ |
| 12. Capture package payment | ⏸ (see §6) |
| 13. Remaining sessions covered by paid package | ⏸ (see §6) |
| 14. Session cannot proceed → replacement | ✅ |

### §8 — Independent Session Processing (outcomes A/B/C/D)
✅ All four outcomes handled: A/C/D (teacher assignment paths) and B (minimum not reached → replacement session, not just "does not confirm").
- `wp-exam-success/includes/class-wpes-teacher-invites.php`
- `wp-exam-success/includes/class-wpes-replacements.php` *(new)*

### §9 — Minimum Participant Requirement
✅ Per-session check, global default 5, added to existing Global Settings (no new settings page).
- `wp-exam-success/admin/views/settings.php`
- `wp-exam-success/admin/class-wpes-admin.php`

### §10 — Teacher Assignment via Email and Accept Link
✅ Every bullet in this section verified live: unique secure link, 4h validity (configurable), re-check before assignment, first-valid-acceptance wins, race-condition safe, "already assigned" message on a late click, admin email if nobody accepts in time.
- `wp-exam-success/includes/class-wpes-teacher-invites.php` *(new)*
- `wp-exam-success/includes/class-wpes-emailer.php`
- `wp-exam-success/public/class-wpes-public.php` (public Accept-Link endpoint)
- `wp-exam-success/includes/class-wpes-cron.php` (expiry sweep + final check, reusing existing schedules)

### §11 — Replacement Session
✅ Every bullet verified live end-to-end (forced a real test session below minimum through the real final-check cron, then completed a real credit redemption as a logged-in customer): customer informed, offered a replacement, given a mechanism to pick one, no additional payment, existing session-selection/booking infrastructure reused.
- `wp-exam-success/includes/class-wpes-replacements.php` *(new)*
- `wp-exam-success/includes/class-wpes-my-account.php`
- `wp-exam-success/public/partials/my-account-sessions.php`
- `wp-exam-success/includes/class-wpes-emailer.php`

### §12 — Global Settings
✅ Minimum Participants (default 5), Automatic Teacher Assignment (on/off), Teacher invitation validity (default 4h, changeable) — all added to the *existing* Settings screen, no new page, as required. (Also added a Final-Check-Before-Session window, an extra beyond the spec's minimum ask.)
- `wp-exam-success/admin/views/settings.php`
- `wp-exam-success/admin/class-wpes-admin.php`

### §13 — Existing Infrastructure
— Reused throughout rather than rebuilt: booking/session DB, reservation mechanism, capacity checking, WooCommerce order/payment hooks, `WPES_Emailer`, cron infrastructure, timezone handling, meeting-link functionality. Customer account (My Sessions) extended, not replaced, for the replacement-credit UI.

### §14 — Acceptance Criteria
✅ for every teacher/capacity/replacement-related bullet (verified live). ⏸ for the payment-capture-specific bullets, pending the §6 gateway decision. — for the process bullets (staging-only work, no live access, no regressions observed on existing screens touched).

### §15 — Delivery and Completion
Not yet formally "complete" per the spec's own definition, solely because of the one pending gateway-setting decision in §6 — everything else in the spec is implemented and verified.

---

## Overview PDF — diagram elements

- **Top flow (steps 1–5)**: steps 1, 2, 4 unaffected; step 5 ("checked individually") ✅; step 3's "payment is pre-authorized" note ⏸ (see §6).
- **Green path (A)**: steps 1–4 (minimum reached → invite → accept → confirmed) ✅. Step 5 ("Payment Captured") ⏸.
- **Red path (B)**: all four steps (fewer than minimum → doesn't take place → replacement credit → customer picks new session) ✅.
- **Customer Account** (booked sessions/status, replacement credits, direct link to replace) ✅.
- **Emails & Links**: booking confirmation, meeting link, reminder (all pre-existing, unaffected) ✅; session-confirmed email ✅; teacher invite email ✅; session-cancelled/replacement email ✅; no-teacher-response admin alert ✅.
- **Global Settings (System Rules)**: plugin self-contained, no theme/Elementor dependency, no new third-party plugin dependency, existing deps unchanged — ✅ confirmed (the active theme was pulled and reviewed; it has zero hooks into the booking flow).

### One flagged deviation from the Overview PDF's text
The Overview PDF states *"Teachers are WordPress users with the role 'Teacher.'"* This was **not** built that way — teachers are a lightweight custom table instead (no WordPress login involved, since the Accept-Link flow never requires one). Functionally equivalent for everything the spec actually asks teachers to do. Flagged to the client; not changed since no objection was raised. If teachers ever need to log into wp-admin themselves for anything, this would need revisiting.

---

## Known disclosed limitations (not blockers, but worth knowing)

- No "unenroll" admin action exists to remove a manually-enrolled booking — pre-existing gap, not introduced by this project. Left a small number of harmless test bookings attached to archived test sessions as a result (documented per-round in `CHANGE_LOG.txt`).
- No automated detection/alert if a payment capture attempt actually fails at the gateway level (e.g. the 7-day WooPayments window lapsed) — WooPayments logs its own order note in that case, but nothing in this plugin surfaces it separately yet.
- ~~No dedicated admin screen for browsing replacement credits or teacher invites directly~~ — ✅ built 2026-09-07: "Teacher Invites" and "Replacement Credits" admin screens, `wp-exam-success/admin/views/teacher-invites.php` and `credits.php`.
- ~~Minor "sessions below minimum" dashboard stat card~~ — ✅ built 2026-09-07: fifth Dashboard stat card, `WPES_Sessions::count_below_minimum()`.

## Open decision for the client

**Turn on WooPayments' manual-capture setting** (WooCommerce → Settings → Payments → WooPayments → "Issue an authorization on checkout, and capture later.") to activate the §6 payment logic. Confirmed safe to flip on staging — this store sells only the 3 exam-package products, nothing else a global gateway setting could unintentionally affect. As of 2026-09-07, the capture trigger also has a concurrency-safe atomic claim (`wpes_payment_captures` table) — flipping this setting is now also what's needed to exercise that guard live, not just the base payment flow.
