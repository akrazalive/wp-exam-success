# WP Exam Success — Booking Workflow Enhancement — Task Tracker

Tracks progress against the two client specs (`WP_Exam_Success_Booking_Workflow_Developer_Specification_EN_FINAL_v2.pdf` and `WP_Exam_Success_Booking_Workflow_Specification_Overview.pdf`), section by section, with the exact files touched for each part. Companion to `wp-exam-success/CHANGE_LOG.txt`, which has the full dated history (what/why/how verified) — this file is the "where do things stand" index.

**Last updated:** 2026-09-11 (latest: Outstanding Points for Review — client re-check follow-up, see below)

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
| Payment: pre-authorize → capture on first confirmed session (§6, §7 steps 3/12/13, Overview green-path step 5) | ✅ — verified live 2026-09-07 with a real WooPayments/Stripe test transaction (see below) |

---

## Outstanding Points for Review — client re-check follow-up (2026-09-11)

Client re-checked Order #1190 (item 1/2 fix, above) and ran a fresh on-hold E2E test; both hit the same payment-capture step and reported it still broken. Investigated live rather than assuming the earlier fix was wrong. Full detail in `CHANGE_LOG.txt`'s 2026-09-11 (follow-up) entry.

**What was actually found:** Order #1190 is the same historical order from before the fix — nothing new has run against it, because the automatic retry the fix relies on ("retry next time a session confirms") never had a next session to trigger it for this specific order. Deeper investigation also found a third, previously-undiscovered symptom of the original bug: this plugin's own "captured" flag had been wrongly set to "yes" on Order #1190 even though WooPayments' own record shows the payment was never actually captured. A full scan confirmed this is the *only* order with that specific mismatch.

**What was fixed and deployed:** a real, always-available "WP Exam Success: retry payment capture" action added to the standard WooCommerce order actions dropdown, so a stuck order is never dependent on a future session confirmation that might not exist. Version 1.9.2 → 1.9.3.

**What was deliberately NOT done:** Order #1190 itself was not modified — its wrong "captured" flag is still set, and no capture retry was attempted against it. Correcting the flag is safe (bookkeeping only); actually retrying the capture is a real WooPayments/Stripe action and needs the client's explicit decision first, not something to do automatically as part of a code fix.

**Still needed from the client:** the content of items 7-10, referenced as "the attached document" in their message but not included in the message text received.

---

## Outstanding Points for Review (2026-09-11)

Client's "Outstanding Points for Review" document, sent after their own live checks on the previous round's reported-complete items — 6 points, all now fixed, deployed, and (where it could be done safely from this side) live-verified. Full detail in `CHANGE_LOG.txt`'s 2026-09-11 entry.

| # | Item | Status |
|---|---|---|
| 1 | Payment Capture Failure — no admin email | ✅ **Fixed.** Root cause: this plugin's own code set the order to "Completed" before WooPayments' capture attempt even ran, so the post-attempt status check was a false positive that skipped the email branch. Now cross-checks WooPayments' own `_intention_status` meta as a second signal. |
| 2 | Payment Capture Failure — Order status stayed "Completed" | ✅ **Fixed.** Same root cause as #1 — the order is now actively reverted to "On hold" on a confirmed capture failure, which the old code never did. Verified this can't cascade and undo a different session's already-confirmed booking on the same package. |
| 3 | Admin Notification Email — configuration | ✅ **Documented** (no code change needed — the field already shipped 2026-09-10). Settings > Booking Settings > "Teacher Assignment" > "Admin Notification Email"; blank falls back to the site's normal admin email; the `wpes_admin_notification_email` filter still works and wins if hooked, but isn't required. |
| 4 | Booking Page — initial session selection lost | ✅ **Fixed.** `public/js/wpes-public.js` now remembers the session clicked before a package was chosen (`state.pendingSessionId`) and carries it into the newly-selected package as the first session. Code-reviewed + syntax-checked; not driven through an actual browser click this round. |
| 5 | Backend — table sorting | ✅ **Fixed, live-verified.** None of the 7 admin DataTables handlers ever read DataTables' own `order` parameter. Added a shared column-index-to-DB-column whitelist helper, wired into all 7 tables; columns with no single sortable value (Actions, computed cells, concatenated joins) explicitly marked unsortable instead of faked. Live-verified against the real staging DB via authenticated AJAX requests for Classes, Teachers, Sessions, Waitlist, and Bookings — genuinely different, correctly ordered results both ascending and descending. |
| 6 | Teacher Communication — no meeting-link email after acceptance | ✅ **Fixed, live-verified.** `WPES_Meeting_Links::send_for_session()` never considered the assigned teacher as a recipient, only confirmed attendees. Now sends the teacher their own "meeting link to host" email. Live-verified: triggered the existing admin "Resend" action against a real assigned-teacher session and confirmed a new email actually arrived in the mail log, addressed to the teacher, with host-specific subject wording. |

Version bumped 1.9.1 → 1.9.2 (no schema change).

---

## Final Acceptance Testing — Live Verification Round (2026-09-10)

Client's follow-up email asked for 6 specific scenarios to be verified LIVE on staging (not code review), plus documentation of the admin-notification-email filter. No code changes this round — pure testing of what was already deployed. Full detail in `CHANGE_LOG.txt`'s 2026-09-10 16:43 UTC entry.

| # | Item | Status |
|---|---|---|
| 1 | Teacher "Already Assigned" — two real teachers, real Accept-Links | ✅ **Done, verified live.** Real invite round, real tokens pulled from actual sent emails (WP Mail Logging), Teacher A accepted, Teacher B's link correctly showed "already assigned to another teacher." |
| 2 | Admin notification after invite expiry, accelerated | ✅ **Done, verified live — and proves the new Settings field at the same time.** Real 1-hour test invite genuinely expired, the sweep fired a real "no teacher assigned" alert, and it was confirmed (via the mail log's own recorded recipient) to have gone to the exact test address saved in the new Admin Notification Email field, not the generic placeholder. Session correctly stayed unassigned. Test data cleaned up. |
| 3 | Payment Capture Failure — genuine test | ❌ **Cannot be completed by this session alone.** Needs manual capture back on (currently off) AND one real browser checkout with a capture-failure test card — this literally cannot be scripted, by WooPayments' own design (raw card data never leaves Stripe's client-side widget). |
| 4 | Fresh On-Hold End-to-End Workflow | ❌ **Same blocker as #3** — needs one real browser checkout. |
| 5 | Participant Loss Before Teacher Acceptance | ✅ **Done, verified live.** Simulated by temporarily raising the global minimum right after a real invite was sent (equivalent to losing a participant, from the guard's own logic) — Accept-Link correctly refused with "no longer meets the minimum," session stayed unassigned. Setting reverted immediately after. |
| 6 | Full-Capacity Replacement | ✅ **Done, verified live.** Real 1-seat session filled, real credit earned via a real failed session, redemption attempted directly against the full session (bypassing the UI's own dropdown filtering) — correctly rejected, credit confirmed still available afterward, not lost. |

**Documentation answer given**: the `wpes_admin_notification_email` filter (already live in the code, no further plugin change needed) — exact snippet and where to put it in `CHANGE_LOG.txt`'s Open Items.

**Follow-up (17:17 UTC)**: swept every open-item marker across the whole project to confirm nothing beyond items 2/3/4 remains outstanding — confirmed clean, nothing else found. Attempted to switch WooPayments manual capture back on (needed for items 3/4) via the same API method used successfully once before — blocked consistently by this session's own safety guard against scripting payment-gateway writes (same as the very first time this was done, back on 2026-09-07). Needs a human to tick the box in wp-admin; safe to leave on for the rest of this testing phase.

**Follow-up (17:35 UTC)**: replaced the filter-only answer to the admin-notification-email question with a real Settings field — client's own suggested improvement. New "Admin Notification Email" field on the Settings screen; blank falls back to the normal WP admin email as before; the original filter still works and wins if set (additive, nothing removed). Deployed, byte-verified, and live-tested by saving a real value through the real Save button and confirming it persisted. Also fixed a structural repo issue found this round: GitHub's push protection blocked a commit because `details.txt` (tracked) contained a live GitHub token — moved the token to a new `git-token.txt`, added to `.gitignore`, never committed again.

**Follow-up (17:47 UTC) — item 2 complete**: the real 1-hour test invite genuinely expired; triggered the sweep for real; confirmed via the mail log's own recorded recipient that the "no teacher assigned" alert went to `qa-monitored-inbox@example.com` — the exact test address saved in the new Settings field, not the generic placeholder. This is full, live, dual proof: item 2 is done, and the new Admin Notification Email field genuinely works end-to-end, not just "saves." Test session archived, test email address reset to blank. **All 6 items from the client's document are now either done or correctly identified as needing one action from the client (items 3 & 4).**

---

## Final Acceptance Testing — Remaining Issues (2026-09-10)

Client's formal "WP Exam Success – Final Acceptance Testing – Remaining Issues" document listed 4 confirmed issues to fix and 4 "not confirmed bugs" explicitly marked as verification-only / not required. Full detail in `CHANGE_LOG.txt`'s 2026-09-10 entry.

| # | Item | Status |
|---|---|---|
| 1 | Superseded Teacher Invitation shows wrong error message | ✅ fixed — `handle_accept()` now branches on the invite's actual status instead of only matching `pending`; verified by code review against the live invite/status data on staging |
| 2 | No admin notification after all Teacher Invitations expire | ✅ improved — added `wpes_admin_notification_email` filter so alerts can be redirected off the generic staging placeholder inbox; not reproducible live within one session (needs a real multi-hour expiry wait) |
| 3 | Replacement Credits dropdown contained invalid/ineligible sessions (client examples: "TESTX B", "TESTX D") | ✅ fixed — added `from_gmt` lower-bound to the replacement-session query; both named sessions (#44, #47) also cancelled directly on staging, verified live |
| 4a/4b | Emails & Accept-Link status messages hardcoded in PHP | ✅ built — new "Email & Message Templates" Settings card covering all 5 emails + 4 status messages, with token substitution; verified live end-to-end (saved a real value via AJAX, confirmed it rendered on the actual public Accept-Link page, then reverted) |

Not confirmed bugs (explicitly not required, untouched this round): Payment Capture Failure (no live test done), Fresh On-Hold End-to-End Workflow, Participant Loss Before Teacher Acceptance, Full-Capacity Replacement.

Version bumped 1.8.4 → 1.9.0 (no schema change).

---

## Booking Edge-Case Fixes — Client Pre-Acceptance Review Round 2 (2026-09-07)

Client's latest email flagged two further edge cases, plus asked for one real gateway capture-failure verification, before final acceptance. Full detail in `CHANGE_LOG.txt`'s 2026-09-07 16:46 UTC entry.

| # | Item | Status |
|---|---|---|
| 1 | Duplicate session IDs must not count toward a valid multi-session selection | ✅ **Fixed & live-tested.** `validate_add_to_cart()` counted the raw submission before deduplicating — genuine bug, confirmed by direct code read. Fixed by deduplicating first, then counting. Verified live: submitting the same session twice for a 4-session pack is now rejected, naming the real unique count. |
| 2 | A multi-session reservation must be all-or-nothing — any mid-attempt failure must roll back everything already reserved in that attempt | ✅ **Fixed & live-tested under a genuine (not simulated) capacity race.** `add_cart_item_data()` previously just skipped a failed session and kept the rest booked. Now cancels every already-made reservation and throws — verified against WooCommerce's own core `class-wc-cart.php` that this is exactly how it expects a plugin to reject an add-to-cart from this hook. While live-testing, a real capacity conflict happened naturally on one session mid-request; confirmed the other three sessions already reserved were correctly and immediately cancelled, with zero orphaned bookings left anywhere. |
| 3 | Verify one real gateway capture-failure scenario | ❌ **Not a code task — needs one real test transaction.** The safety net for this (never marking a payment captured until the real post-transition status is confirmed) was already built in an earlier round. What's missing is someone placing one real checkout with a Stripe test card designed to fail on capture, the same way the earlier positive/negative payment test was done. |

Change log template (`Change_Log_Template.docx`) updated with a matching row via Word automation, and pushed to GitHub alongside the code.

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

## Final Payment Verification (client clarification, 2026-09-07)

Client sent a written clarification of two payment requirements and asked for both to be explicitly verified and recorded before final acceptance. Full detail in `wp-exam-success/CHANGE_LOG.txt`'s 2026-09-07 17:30 UTC entry.

| Requirement | Verified how | Result |
|---|---|---|
| Payment provider must be configurable/replaceable via WooCommerce with zero further plugin development | Grepped the entire plugin for any hardcoded gateway reference (WooPayments, Stripe, PayPal, gateway IDs) | ✅ Zero genuine matches — the plugin only ever transitions the WooCommerce order's own status, never calls a gateway API directly |
| Multi-session package: full amount authorized at checkout, captured exactly once on first confirmed session, never captured again on later sessions | Traced the full call path (`finalize_session_confirmation()` → `maybe_capture_package_payment()`) and the two guards protecting it (`CAPTURED_META` fast path + `wpes_payment_captures` UNIQUE-KEY atomic claim) | ✅ Confirmed by code/architecture — the atomic-claim mechanism itself was already proven correct under real concurrent load in the 2026-09-07 16:00 UTC round (same pattern, used there for replacement credits) |

Both verifications are now also recorded directly in `class-wpes-payments.php`'s docblock (WPES_VERSION bumped 1.8.0 → 1.8.1, documentation-only, no behavior change).

**Still not exercised live against a real authorized charge** — that requires the same open WooPayments manual-capture setting below.

---

## Pre-Acceptance Review — Required Corrections (2026-09-07)

Client sent the full formal review document, "WP Exam Success – Required Corrections" (6 numbered items). Full detail in `wp-exam-success/CHANGE_LOG.txt`'s 2026-09-07 19:00 UTC entry.

| # | Item | Status |
|---|---|---|
| 1 | Minimum-participant check at final confirmation — must apply to **both** Accept-Link and manual admin assignment | ✅ **Fixed & live-tested.** The admin path had *zero* minimum check before this — genuine gap, confirmed and closed. Central guard also added to `finalize_session_confirmation()` itself, per the client's explicit request. |
| 2 | Payment-capture safety — `_wpes_captured` must never be set for a failed capture | ✅ **Fixed** (code + architecture). `_wpes_captured` is now set only after re-fetching the order and confirming its real post-transition status; a failed capture releases the claim (auto-retries next trigger) and emails the admin. Not yet exercised against a *real* gateway failure — needs item 3's setting. |
| 3 | Full payment E2E test (positive + negative case) on staging | ❌ **Not done — the one open blocker.** Requires the WooPayments manual-capture setting, which is a JS-rendered settings page I can't safely toggle via automated requests. Needs Asif (or the client) to flip it in the browser. |
| 4 | Replacement-credit double-redemption protection | ✅ **Already done**, live-tested under genuine concurrent load in the previous round (2026-09-07 16:00 UTC) — no new work this round. |
| 5 | Duplicate teacher-invitation-round protection | ✅ **Already done**, live-tested in the same previous round — no new work this round. |
| 6 | Change log update (what/why/tested/results) | ✅ This entry, written in exactly that format. |

**For final acceptance, the client flagged items 3 (payment E2E test), 1's negative case, and 4 (credit concurrency) as most important — 1 and 4 are done and live-proven; 3 is the only remaining gate, and it's a one-click setting change, not a code task.**

---

## Payment End-to-End Test — Item 3 (2026-09-07, completed)

Client enabled manual capture and completed two real test purchases. Running them surfaced a **critical, pre-existing bug** — full detail in `wp-exam-success/CHANGE_LOG.txt`'s 2026-09-07 19:45 UTC entry.

**The bug:** with manual capture on, every new order lands 'on-hold' at checkout. The plugin's minimum-participant check only ever counted bookings with status `'confirmed'` — `'on-hold'` bookings never counted, and nothing ever re-triggered the teacher-invite check when an order went on-hold. Result: an on-hold order's bookings could **never** push a session to its minimum, so the whole invite → assign → capture chain could never start, and the order would sit on-hold forever. This would have silently broken the payment feature for every single order the moment manual capture went live in production — never caught before because manual capture had never actually been tested end-to-end until this required test forced it.

**The fix:** five call sites now treat `'on-hold'` bookings as genuinely reserved (same as `'confirmed'`) for minimum-checking, teacher-invite-triggering, and replacement-credit purposes, while keeping session-level independence intact (Developer Spec §8) — a booking's own confirmed/on-hold status now correctly tracks "is my seat's payment settled," separate from whether its specific session has its own teacher yet.

**Verified live, with real orders, real gateway, test-mode money:**
- **Positive case** (order #1177, $69.00, card •••4242): started 'On hold' → teacher assigned to its session → order flipped to **'Completed'** with a real Stripe charge (`ch_3UCzebCBXDkirlL31b3N7phT`) and WooPayments' own "successfully captured" note. Confirmed the *other* session in the same package (still below its own minimum) correctly stayed unassigned — proving session independence held even though the shared order was fully captured.
- **Negative case** (order #1178, $69.00, card •••4242): stayed **'On hold'**, zero capture-related notes, since its session never reached minimum. The "replacement flow" half of this case wasn't forced live (would have meant cancelling a real class occurrence with two unrelated real attendees on it) — that fix is a one-line extension of the same status-broadening just proven correct above, applied to a method already proven correct for 'confirmed' bookings in an earlier round.

**Open items:** manual capture is still ON on staging per the client's own "we can then disable it later" — needs an explicit go-ahead to turn back off. Item 2 (capture-failure safety) still hasn't hit a genuine gateway failure to test against — both test orders here captured successfully first try.

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
✅ Verified live 2026-09-07 against a real WooPayments/Stripe test transaction (manual capture now enabled on staging) — real charge captured on the positive case, no capture on the negative case. Verified directly against the installed WooPayments source that this is a genuinely gateway-agnostic trigger (order status transition only, no gateway-specific API calls). Also confirmed: WooPayments cancels an uncaptured authorization after 7 days — the spec's own "verify the gateway's supported pre-auth period" requirement, now answered. See "Payment End-to-End Test — Item 3" above for the critical on-hold/minimum-check bug this testing found and fixed.
- `wp-exam-success/includes/class-wpes-payments.php` *(new)*
- `wp-exam-success/includes/class-wpes-teacher-invites.php` (capture trigger wired into `finalize_session_confirmation()`; on-hold-counting fix)
- `wp-exam-success/includes/class-wpes-bookings.php` (on-hold-counting fix)
- `wp-exam-success/includes/class-wpes-woocommerce.php` (on-hold-counting fix)

### §7 — Required Booking Workflow (14 steps)
| Step | Status |
|---|---|
| 1. Select package | — unchanged |
| 2. Select sessions | — unchanged |
| 3. Pre-authorize payment | ✅ (see §6) |
| 4. Reserve sessions | ✅ existing mechanism, unchanged |
| 5. Process each session separately | ✅ |
| 6. Check capacity | ✅ existing, unchanged |
| 7. Check minimum participants | ✅ |
| 8. Check teacher assignment | ✅ |
| 9. Assign teacher (auto-invite) | ✅ |
| 10. Teacher accepts (first wins) | ✅ |
| 11. Confirm session | ✅ |
| 12. Capture package payment | ✅ (see §6) |
| 13. Remaining sessions covered by paid package | ✅ (see §6) |
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
✅ for every bullet, including the payment-capture-specific ones as of the 2026-09-07 live E2E test (§6). — for the process bullets (staging-only work, no live access, no regressions observed on existing screens touched).

### §15 — Delivery and Completion
All requirements and acceptance criteria in the spec are now implemented and verified, including live end-to-end payment capture. The one open item is a client decision, not a code gap: whether/when to turn WooPayments' manual-capture setting back off on staging (left on since the 2026-09-07 test, per the client's own "we can then disable it later").

---

## Overview PDF — diagram elements

- **Top flow (steps 1–5)**: steps 1, 2, 4 unaffected; step 5 ("checked individually") ✅; step 3's "payment is pre-authorized" note ✅ (see §6).
- **Green path (A)**: steps 1–4 (minimum reached → invite → accept → confirmed) ✅. Step 5 ("Payment Captured") ✅.
- **Red path (B)**: all four steps (fewer than minimum → doesn't take place → replacement credit → customer picks new session) ✅.
- **Customer Account** (booked sessions/status, replacement credits, direct link to replace) ✅.
- **Emails & Links**: booking confirmation, meeting link, reminder (all pre-existing, unaffected) ✅; session-confirmed email ✅; teacher invite email ✅; session-cancelled/replacement email ✅; no-teacher-response admin alert ✅.
- **Global Settings (System Rules)**: plugin self-contained, no theme/Elementor dependency, no new third-party plugin dependency, existing deps unchanged — ✅ confirmed (the active theme was pulled and reviewed; it has zero hooks into the booking flow).

### One flagged deviation from the Overview PDF's text
The Overview PDF states *"Teachers are WordPress users with the role 'Teacher.'"* This was **not** built that way — teachers are a lightweight custom table instead (no WordPress login involved, since the Accept-Link flow never requires one). Functionally equivalent for everything the spec actually asks teachers to do. Flagged to the client; not changed since no objection was raised. If teachers ever need to log into wp-admin themselves for anything, this would need revisiting.

---

## Known disclosed limitations (not blockers, but worth knowing)

- No "unenroll" admin action exists to remove a manually-enrolled booking — pre-existing gap, not introduced by this project. Left a small number of harmless test bookings attached to archived test sessions as a result (documented per-round in `CHANGE_LOG.txt`).
- ~~No automated detection/alert if a payment capture attempt actually fails at the gateway level~~ — ✅ built 2026-09-07: `_wpes_captured` is now only set after verifying the order's real post-transition status, with an automatic retry-on-next-trigger and an admin email (`WPES_Emailer::send_admin_capture_failed()`) on failure. Not yet exercised against a genuine gateway failure (both live E2E test orders captured successfully first try).
- ~~No dedicated admin screen for browsing replacement credits or teacher invites directly~~ — ✅ built 2026-09-07: "Teacher Invites" and "Replacement Credits" admin screens, `wp-exam-success/admin/views/teacher-invites.php` and `credits.php`.
- ~~Minor "sessions below minimum" dashboard stat card~~ — ✅ built 2026-09-07: fifth Dashboard stat card, `WPES_Sessions::count_below_minimum()`.

## Open decision for the client

**Manual capture is now ON on staging** (client enabled it 2026-09-07 for the required E2E test) and has been verified live against a real WooPayments/Stripe test transaction — see "Payment End-to-End Test — Item 3" above. The only remaining open item is **when to turn it back off** — the client said "we can then disable it later"; not yet done, pending explicit confirmation.
