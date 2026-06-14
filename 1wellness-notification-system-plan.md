# 1wellness Notification & Reminder System — Implementation Plan

**Channels:** Email · WhatsApp · SMS (all admin-configured, per-channel toggles)
**Goals:** user experience (timely, useful reminders) · conversion (abandonment recovery) · follow-up (onboarding, delivery) · retention (daily protocol adherence, renewal)
**Companion docs:** `1wellness-conversion-audit.md` (events this system consumes) · `1wellness-ab-engine-implementation.md` (experimentation hooks)

---

## 1. Guiding Principles

1. **One pipeline, many channels.** Every message — lead nurture, checkout recovery, tea reminder — flows through a single queue + dispatcher with per-channel adapters. No journey talks to Twilio/SMTP directly.
2. **Admin is the control plane.** Providers, credentials, channel on/off, quiet hours, frequency caps, templates, and per-journey toggles all live in the existing `settings` store and a new Notifications admin section. Nothing hardcoded.
3. **Channel ladder, not channel spam.** Each journey declares a channel priority (e.g. WhatsApp → Email → SMS). Send on the first available/consented channel; fall through only on hard failure, never duplicate the same message across channels.
4. **Consent-first.** Email/phone are captured with GDPR consent (`components/gdpr-form-consent.html` already exists). Every channel has independent opt-out; suppression is enforced centrally in the dispatcher, not per-journey.
5. **Honest and useful.** Same rule as the conversion audit: no fabricated urgency. Every message must carry real value (resume link, guarantee restatement, today's meal) — this is a health brand.
6. **Stop on conversion.** Any purchase/visit that fulfils a journey's goal cancels its remaining pending messages (the `1w_purchased` localStorage check in `js/webhook-manager.js:681` becomes a server-side rule).

---

## 2. Current State (what we build on)

| Asset | Location | Status |
|---|---|---|
| Lead nurture queue (`nurture_queue`: step, `next_contact_at`, status, funnel, pcos_type) | `backend/database/schema.sql:313` | Table exists, populated by `WebhookManager.scheduleNurture()`; **no sender** |
| Member notifications (`user_notifications`: type, schedule, `channel` incl. `whatsapp`/`email`, status lifecycle) | `backend/database/enhanced_schema.sql:150` | Table exists; **nothing writes/sends** |
| Per-user reminder time windows (meals, teas, movement) | `backend/database/enhanced_schema.sql:274` (`user_time_windows`) | Table exists with defaults |
| Queue worker pattern (batch, retries, `next_attempt`, max-retry) | `backend/cron/process_webhooks.php` | Proven pattern to copy |
| Daily nudge cron (generates plan, **mock** `sendNotification()`) | `backend/cron/daily_nudge.php` | Skeleton; mail() commented out |
| Admin settings store + SMTP config + test-email button + webhook secret | `backend/admin/settings.php`, `Settings` class | Working |
| Funnel events: `assessment_start`, `assessment_complete`, `checkout_init`, `purchase`, `post_purchase_upsell_click`, sales-page visit | `js/webhook-manager.js`, `backend/api/track-event.php`, `funnel_tracking` table | Live (checkout_init/assessment_start added June 2026) |
| Resume deep-link for abandoned assessments (`assessment.html?resume=1`, answers persisted per-answer) | all 4 funnels' `assessment.html` | Live |
| Purchase webhooks per funnel (Flutterwave) | `backend/api/webhook_pcos.php` etc. | Live — source of truth for `purchase` |
| Streaks, weekly progress, reassessments, motivational messages | `enhanced_schema.sql` | Tables exist — retention content sources |
| n8n export/debug endpoints | `backend/api/n8n-export.php` etc. | Optional external orchestration |

**Gaps:** no channel adapters (WhatsApp/SMS providers entirely absent; SMTP configured but unused by journeys), no template system, no journey engine, no suppression/consent ledger, no notification analytics.

**Decision — native PHP, not n8n-first.** The queue-worker pattern, settings store, and admin already exist in PHP; building journeys natively keeps one deploy unit and makes Admin the single control plane. n8n remains an optional mirror (events already export) for marketing-team experimentation, but **the system of record for sends is the PHP pipeline.**

---

## 3. Architecture

```
                     ┌────────────────────────────────────────────────┐
 Funnel pages ───▶   │ track-event.php / form-handler.php / webhooks  │
 (events)            └───────────────┬────────────────────────────────┘
                                     ▼
                          ┌─────────────────────┐
                          │   Journey Engine    │  cron: journeys.php (every 5 min)
                          │ (rules → schedule)  │  - evaluates triggers & delays
                          └─────────┬───────────┘  - cancels on goal completion
                                    ▼
                          ┌─────────────────────┐
                          │  notification_queue │  (new table — one row per send)
                          └─────────┬───────────┘
                                    ▼
                          ┌─────────────────────┐
                          │     Dispatcher      │  cron: send_notifications.php (every 1 min)
                          │ consent ▸ quiet hrs │
                          │ caps ▸ channel pick │
                          └──┬───────┬───────┬──┘
                             ▼       ▼       ▼
                          Email   WhatsApp  SMS      (ChannelAdapter interface)
                          (SMTP/  (Meta     (Twilio/
                          Mailgun) Cloud API Termii)
                                   or Twilio)
                             │       │       │
                             └───────┴───────┘
                                     ▼
                          delivery webhooks → notification_log (sent/delivered/read/clicked/replied)
```

### 3.1 New backend components

```
backend/
  classes/
    Notifications/
      NotificationService.php      # enqueue(), cancelJourney(), public API for all code
      JourneyEngine.php            # trigger evaluation, step scheduling, goal-cancel
      TemplateRenderer.php         # {{name}}, {{type}}, {{resume_link}}, funnel-aware
      Channels/
        ChannelAdapterInterface.php  # send(to, renderedMessage): DeliveryResult
        EmailChannel.php             # uses existing SMTP settings (PHPMailer)
        WhatsAppChannel.php          # Meta WhatsApp Cloud API (default) / Twilio driver
        SmsChannel.php               # Twilio (intl default) / Termii driver (NG option)
      ConsentManager.php           # opt-in/out ledger, suppression checks
  cron/
    journeys.php                   # every 5 min: evaluate triggers → fill queue
    send_notifications.php         # every 1 min: drain queue via adapters (replaces the
                                   #   mock send in daily_nudge.php)
  api/
    notify-webhook.php             # provider delivery callbacks (Twilio/Meta/Mailgun)
    unsubscribe.php                # one-click email unsubscribe + preference page
    notification-prefs.php         # member portal: channels + time windows (writes
                                   #   user_time_windows)
  admin/
    notifications.php              # dashboard: journeys on/off, queue, failures
    notification-templates.php     # template CRUD + test-send per channel
```

### 3.2 Data model (migration `004_notifications.sql`)

```sql
-- One row per scheduled send. Mirrors webhook_queue's retry semantics.
CREATE TABLE IF NOT EXISTS notification_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journey_key VARCHAR(60) NOT NULL,        -- 'assessment_abandon', 'daily_nudge', ...
    step INTEGER DEFAULT 1,
    recipient_type VARCHAR(10) NOT NULL,     -- 'lead' (nurture_queue/contacts) | 'user'
    recipient_id INTEGER,                    -- users.id when recipient_type='user'
    email VARCHAR(255), phone VARCHAR(30),   -- denormalized at enqueue time
    funnel VARCHAR(20),                      -- pcos|acne|weight|mens
    template_key VARCHAR(80) NOT NULL,
    payload TEXT,                            -- JSON merge vars (name, type, resume url…)
    channel_ladder VARCHAR(60) NOT NULL,     -- 'whatsapp,email' / 'email' / 'sms,email'
    dedupe_key VARCHAR(120) UNIQUE,          -- journey+step+recipient → idempotent enqueue
    send_after DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',    -- pending|sent|failed|cancelled|suppressed
    attempts INTEGER DEFAULT 0,
    next_attempt DATETIME,
    cancelled_reason VARCHAR(60),            -- 'purchased', 'completed', 'opted_out'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_nq_due ON notification_queue(status, send_after);
CREATE INDEX idx_nq_recipient ON notification_queue(email, journey_key);

-- Immutable send/delivery ledger (powers analytics + frequency caps).
CREATE TABLE IF NOT EXISTS notification_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    queue_id INTEGER,
    journey_key VARCHAR(60), step INTEGER,
    channel VARCHAR(20) NOT NULL,            -- 'email'|'whatsapp'|'sms'
    provider VARCHAR(30), provider_msg_id VARCHAR(120),
    email VARCHAR(255), phone VARCHAR(30),
    status VARCHAR(20) NOT NULL,             -- sent|delivered|read|clicked|replied|bounced|failed
    error TEXT,
    cost_usd DECIMAL(8,5),                   -- SMS/WA unit cost for ROI reporting
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_nl_msgid ON notification_log(provider_msg_id);

-- Channel-level consent & suppression (leads AND members).
CREATE TABLE IF NOT EXISTS notification_consent (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255), phone VARCHAR(30),
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,              -- 'opted_in'|'opted_out'|'bounced'|'complained'
    source VARCHAR(60),                       -- 'assessment_form', 'sms_stop', 'unsub_link'
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(email, phone, channel)
);

-- Admin-editable message templates, versioned per channel.
CREATE TABLE IF NOT EXISTS notification_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_key VARCHAR(80) NOT NULL,        -- 'assessment_abandon_1'
    channel VARCHAR(20) NOT NULL,
    funnel VARCHAR(20) DEFAULT 'all',
    subject VARCHAR(255),                     -- email only
    body TEXT NOT NULL,                       -- supports {{merge_vars}}
    wa_template_name VARCHAR(120),            -- approved Meta template for >24h sends
    active INTEGER DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(template_key, channel, funnel)
);
```

Existing tables are reused, not duplicated: `nurture_queue` stays the **lead state machine** (journey engine reads/advances `nurture_step`); `user_notifications` becomes the **member in-app inbox** (the dispatcher writes a row alongside email/WA sends so the portal shows history); `user_time_windows` drives reminder times.

### 3.3 Admin configuration (Settings keys + UI)

New **Notifications** tab in `backend/admin/settings.php` (same pattern as the SMTP block, which stays as the email transport):

```
notify_email_enabled        = 1
notify_whatsapp_enabled     = 0          # off until provider approved
notify_sms_enabled          = 0
whatsapp_provider           = meta|twilio
whatsapp_phone_number_id    = …          # Meta Cloud API
whatsapp_access_token       = …          # (or twilio_sid/token reused)
sms_provider                = twilio|termii
sms_from / twilio_sid / twilio_token / termii_api_key
notify_quiet_start          = 21:00      # recipient-local; default Europe/London
notify_quiet_end            = 08:00
notify_daily_cap_marketing  = 1          # max marketing msgs/recipient/day
notify_weekly_cap_marketing = 4
notify_dry_run              = 1          # log-only mode for staging/rollout
journey_<key>_enabled       = 1          # per-journey kill switches
```

UI requirements: per-channel **"Send test to…"** button (mirrors the existing SMTP test at `settings.php:249`), provider connection check on save, dry-run banner when active, and a journeys table (enable/disable, last 24h sends, failure count, per-step delay editing).

---

## 4. Journey Catalog (the product)

Channel ladder legend: first listed = preferred. *Transactional* messages ignore marketing caps and quiet hours are relaxed (sent next-morning if in quiet window). *Marketing* messages obey caps, quiet hours, and unsubscribe.

### 4.1 Conversion journeys (lead → buyer)

| # | Journey (`journey_key`) | Trigger | Steps & timing | Channels | Content / merge vars | Cancel when |
|---|---|---|---|---|---|---|
| C1 | `assessment_abandon` | `assessment_start` event, no `assessment_complete` after **1 h** | 1 h → 24 h | Email → SMS | "Your results are {{questions_left}} questions away" + `{{resume_link}}` (`assessment.html?resume=1` — answers already persist client-side; step C1 requires capturing email *early*; until the optional early-capture test ships, this journey only fires for leads who reached the contact form and abandoned there) | `assessment_complete`, opt-out |
| C2 | `results_no_plan_view` | `assessment_complete`, no sales-page visit after **2 h** | 2 h → day 1 → day 3 | Email → WhatsApp | Type-specific education ("What {{type}} means"), type-matched testimonial, link to results/plan. Reuses `nurture_queue.pcos_type` | sales-page visit, purchase |
| C3 | `checkout_abandon` | `checkout_init` event, no `purchase` after **1 h** | 1 h → 20 h | WhatsApp → Email → SMS | Guarantee restatement (30-day), payment link, support contact, "keep the materials" copy. **No fake discounts.** | `purchase` (server-side via Flutterwave webhook + session_id metadata) |
| C4 | `nurture_long` | C2 finished without purchase | day 5 → day 9 → day 14 | Email | Value content series (recipes, success stories per funnel), soft CTA. Existing `nurture_queue.nurture_step` advances here | purchase, opt-out, step > 3 |

### 4.2 Follow-up journeys (buyer onboarding) — *transactional*

| # | Journey | Trigger | Steps | Channels | Content |
|---|---|---|---|---|---|
| F1 | `purchase_confirm` | Flutterwave webhook `purchase` | immediate | Email + WhatsApp | Receipt, portal credentials link (`get-credentials.php` flow), what-happens-next. WhatsApp doubles as the support-thread opener |
| F2 | `onboarding` | `purchase` +1 d / +3 d / +7 d | day 1, 3, 7 | WhatsApp → Email | D1: "Did you log in? Here's your first meal plan" · D3: tea ritual how-to + set your reminder times (`notification-prefs.php`) · D7: first-week check-in + streak status |
| F3 | `pdf_delivery` | PDF generation complete (`generate-plan.php`) | immediate | Email | Attach/link protocol PDF (already emailed today — route through pipeline for logging) |
| F4 | `order_bump_fulfil` | purchase with `order_bump=whatsapp-expert-17` | immediate | WhatsApp | Activate the Expert Access thread the buyer paid for — this is now a paid SLA, must be reliable |

### 4.3 Retention journeys (member adherence & revenue)

| # | Journey | Trigger | Steps | Channels | Content |
|---|---|---|---|---|---|
| R1 | `daily_nudge` | cron 07:00 user-local (replaces mock in `daily_nudge.php`) | daily | WhatsApp → Email (+ in-app row) | Today's focus tip + meal headline from `MealPlanner`, deep link to portal. Member can switch channel/time in prefs |
| R2 | `ritual_reminders` | `user_time_windows` (tea/meal/movement windows) | per window, opt-in only | WhatsApp or SMS | Short nudge ("Evening tea window opens 20:30 🍵"). Strictly opt-in per ritual in member prefs — default OFF except daily_nudge |
| R3 | `streak_save` | `user_streaks` about to break (no log by 19:00 user-local) | same evening | WhatsApp → in-app | "Log today to keep your {{streak_days}}-day streak" |
| R4 | `weekly_summary` | cron Sunday 18:00 | weekly | Email | `weekly_progress` recap: logs, weight/cycle trend, next week preview |
| R5 | `reassessment` | `purchase` +30 d (30-day plan) / +75 d (90-day) | once | Email → WhatsApp | Re-take assessment (`pcos_reassessments`), show progress vs baseline — sets up renewal conversation |
| R6 | `renewal_refill` | plan end −14 d, −3 d | two steps | WhatsApp → Email | Herbal refill / next-phase offer (matches thank-you page refill upsell); honest framing, customer rate |
| R7 | `winback` | no portal login 14 d / 30 d | 14 d → 30 d | Email | "Your plan is waiting" + one-tap resume; 30 d step asks for feedback (`user_feedback`) instead of selling |
| R8 | `review_nps` | `purchase` +21 d AND streak ≥ 7 | once | Email | Testimonial/review request — feeds the type-matched testimonial slots the funnels now have |

### 4.4 Channel rules

- **WhatsApp:** within 24 h of a user message → free-form; otherwise must use **approved template messages** (`wa_template_name`). Templates to pre-approve: purchase confirm, daily nudge, checkout follow-up, refill offer, streak save. Meta Cloud API is the default driver (lower per-msg cost, native templates); Twilio driver as fallback.
- **SMS:** reserved for short, high-value moments (C1/C3 final step, R2 if user chooses). Always append `STOP` opt-out; honor instantly via `notify-webhook.php`. Cost-cap per day in settings.
- **Email:** all bodies get list-unsubscribe headers + footer link to `unsubscribe.php` (per-journey-category granularity: transactional / reminders / offers).

---

## 5. Compliance & Safety (blockers, not afterthoughts)

1. **Consent capture:** assessment contact form and checkout add an explicit consent line (reuse `components/gdpr-form-consent.html`); store in `notification_consent` with source. Transactional (F-series) is legitimate-interest; C/R marketing series require the opt-in.
2. **Suppression precedence:** `opted_out`/`complained`/`bounced` beats everything, checked in the dispatcher (single choke point). Purchase suppresses all C-series instantly.
3. **Quiet hours & timezone:** store recipient timezone (capture via JS `Intl.DateTimeFormat().resolvedOptions().timeZone` at assessment; default Europe/London). Marketing sends shift into the next allowed window.
4. **Frequency caps:** marketing ≤ 1/day, ≤ 4/week per recipient across channels (settings-tunable). Dispatcher consults `notification_log`.
5. **Health-brand tone:** no countdown pressure, no fabricated discounts — consistent with the June 2026 conversion-audit cleanup. Medical disclaimer footer on educational emails.
6. **Data retention:** purge `notification_log` PII per `gdpr/data-retention-policy.html`; `unsubscribe.php` doubles as the GDPR preference center.

---

## 6. Prerequisite Fixes — fully specified (ship inside Phase 1–2)

The plan above depends on two things that are **currently broken/missing**. Both fixes are specified here end-to-end so they ship as part of this project, with admin visibility, rather than remaining "prerequisites."

### Fix A — Purchase-event integrity (audit §5.3) ✅ IMPLEMENTED (June 2026)

> **Status:** A1–A4 are live in the codebase and smoke-tested (fail-closed 503 when hash unset, 401 on bad hash, 200/ignored on non-successful charges, 500 + retry on unverifiable transactions, every event logged). Remaining ops steps: set *Webhook Secret Hash* in Admin → Settings → Payment, point the Flutterwave dashboard webhook at `backend/api/flutterwave-webhook.php`, and schedule `backend/cron/reconcile_payments.php` daily. Implementation note: this work surfaced and fixed a pre-existing `Settings` class bug — it queried `key`/`value` columns while `schema.sql` creates `setting_key`/`setting_value`, so on SQL deployments **every admin settings save silently failed and every read returned defaults** (including Flutterwave keys and `payment_plans` pricing). `Settings` now auto-detects the column convention.

**Verified defect:** `js/flutterwave-integration.js` builds `flutterwaveConfig` (line ~458) **without a `meta` field**, so `paymentData.meta` (session_id, order_bump, delivery_preference, type) never reaches Flutterwave. Consequently the server can never attribute a purchase to a funnel session; purchase confirmation today rests on the client-side `callback` → `confirmPurchase()`, which silently fails on tab close, ad blockers, or network errors. Every cancel-on-purchase rule in §4 — and all A/B revenue attribution — is unreliable until this lands.

Shipped files: `js/flutterwave-integration.js` (meta block), `backend/api/flutterwave-webhook.php`, `backend/classes/PaymentIntegrity.php`, `backend/cron/reconcile_payments.php`, `backend/admin/payment-integrity.php` (+ nav link), `backend/admin/settings.php` (Webhook Secret Hash field), `backend/classes/Settings.php` (column-convention fix).

**A1. Client (`js/flutterwave-integration.js`):** add to `flutterwaveConfig`:

```js
meta: {
    ...(paymentData.meta || {}),
    session_id: (window.AB && window.AB.sessionId) ||
                (window.WebhookManager && window.WebhookManager.sessionId) || '',
    funnel: paymentData.category,           // pcos|acne|weight|mens
    plan: paymentData.plan,
    order_bump: (paymentData.meta && paymentData.meta.order_bump) || 'none'
}
```

`tx_ref` already encodes the plan (`generateTransactionRef`); keep it as the idempotency key.

**A2. Server webhook (`backend/api/flutterwave-webhook.php`, new — the four `webhook_<funnel>.php` files currently expect client posts, not Flutterwave's server webhook):**
- Verify the `verif-hash` header against a new `flutterwave_webhook_hash` setting (entered in Admin → Settings → Payment, set the same value in the Flutterwave dashboard).
- On `charge.completed` + status `successful`: re-verify the transaction by ID via Flutterwave's verify API (never trust the webhook payload alone), then **idempotently upsert** (`UNIQUE(tx_ref)`) into `sales` and write a `purchase` row to `funnel_tracking` carrying `meta.session_id`, `funnel`, `plan`, `order_bump`.
- Fire `NotificationService::cancelJourney(email/session_id, ['assessment_abandon','results_no_plan_view','checkout_abandon'])` and enqueue **F1/F4** — purchase becomes the single server-side source of truth.
- Client `confirmPurchase()` is demoted to advisory (UX redirect only); if it races the webhook, the `tx_ref` upsert makes the duplicate harmless.

**A3. Reconciliation cron (`backend/cron/reconcile_payments.php`, daily):** pull the last 48 h of transactions from the Flutterwave API, diff against `sales` by `tx_ref`, insert any missed purchases flagged `source='reconciliation'`, and emit an admin alert when misses > 0 (webhooks are silently failing).

**A4. Admin visibility (Admin → Notifications → *Payment Integrity* panel):**
| Widget | Source |
|---|---|
| Webhook health: last received, verified/rejected counts (24 h) | `notification_log` ∪ webhook receipt log |
| Unattributed purchases (no `session_id`) — count + list | `sales LEFT JOIN funnel_tracking` |
| Reconciliation diffs (missed webhooks) per day | `sales.source='reconciliation'` |
| "Send test webhook" button (signed sample payload → endpoint) | new endpoint in test mode |

**Acceptance:** sandbox purchase with browser killed mid-callback still produces exactly one `sales` row with `session_id`, cancels pending C-journeys, and triggers F1 — with zero client-side involvement.

### Fix B — Abandonment identity capture (C1 limitation) 🟠 ships with Phase 2

**Problem:** email is captured *after* question 12 (deliberate, audit §2.1), so pre-contact-form abandons have no reachable identity; C1 covers only contact-form abandons.

**B1. Server-side partial progress (no UX change, default ON):** the assessment pages already persist every answer to localStorage; additionally POST each answer to `backend/api/track-event.php` (`event=assessment_progress`, `session_id`, `question_n`, funnel — *answers' values stay client-side; only progress depth is sent*). When contact info is later submitted anywhere (assessment form, checkout form, or a recovered session), `form-handler.php` back-links that `session_id` → email in `nurture_queue`, making the abandon history actionable retroactively.

**B2. Optional mid-quiz save prompt (admin-toggleable, default OFF):** after question 6, a dismissible one-field card — *"Want to save your progress? We'll email you a resume link."* Writes email + consent to `nurture_queue` immediately; skipping it never blocks the quiz. Controlled by `journey_assessment_abandon_capture = 0|1` in Admin and tagged `data-exp="midquiz-capture"` so the A/B engine can judge it on completion-rate vs. recovered-revenue (this is the honest resolution of the audit's capture-placement tension — measured, not assumed).

**B3. Journey C1 becomes two-tier:**
| Tier | Identity available | Action |
|---|---|---|
| Contact-form abandon | email + phone | full C1 ladder (1 h email, 24 h SMS) |
| Mid-quiz abandon, B2 accepted | email | resume-link email at 1 h |
| Mid-quiz abandon, no email | session only | no send; counted in Admin as "unreachable abandons" to size B2's value |

**Admin visibility:** Notifications dashboard shows abandons by tier, reachable %, and recovery rate per tier — making the B2 toggle a data-driven decision the admin can flip without a deploy.

---

## 7. Implementation Phases

### Phase 1 — Foundation (week 1) ✅ prerequisite for everything
- **Fix A (A1+A2):** Flutterwave `meta` passthrough + verified server webhook with idempotent `sales` upsert — everything downstream keys off this.
- Migration `004_notifications.sql` (tables above).
- `ChannelAdapterInterface` + **EmailChannel** (PHPMailer over existing SMTP settings) with `notify_dry_run` mode.
- `NotificationService::enqueue()` with dedupe; `send_notifications.php` worker cloned from `process_webhooks.php` (batching, retries, `next_attempt` backoff).
- Admin: Notifications settings block + per-channel test send + **Payment Integrity panel (A4)**; seed `notification_templates` for F1/C3.
- Wire **F1 purchase_confirm** end-to-end (server webhook → enqueue → email) as the proving journey.
- Start Meta WhatsApp business verification (lead time for Phase 3).
- Acceptance: Fix A acceptance test passes; dry-run log shows correct render/suppression; live test purchase produces one email, one `notification_log` row, admin dashboard count.

### Phase 2 — Conversion recovery (week 2) 💰 fastest ROI
- `JourneyEngine` + `journeys.php` cron reading `funnel_tracking`/`nurture_queue`.
- **Fix A (A3):** reconciliation cron + admin alerting.
- **Fix B (B1+B3):** server-side progress depth events, session→email back-linking, two-tier C1. (B2 mid-quiz prompt ships behind its admin toggle, default OFF, for the A/B engine to evaluate.)
- Journeys **C1–C3** on email only; cancel-on-purchase driven by the Fix A server webhook.
- `unsubscribe.php` + consent checkboxes on the four funnels' contact forms.
- Acceptance: synthetic abandon → email at +1 h with working resume link; purchase within window cancels step 2; "unreachable abandons" tier visible in Admin.

### Phase 3 — WhatsApp + SMS channels (week 3)
- Meta WhatsApp Cloud API onboarding (business verification + template approval — **start the approval in week 1**, it has lead time) and Twilio SMS.
- `WhatsAppChannel`/`SmsChannel` + `notify-webhook.php` delivery/STOP callbacks.
- Upgrade C3/F1/F2/F4 ladders to include WhatsApp; F4 (paid Expert Access) is the launch gate — it must be reliable before the bump keeps selling.

### Phase 4 — Member retention (week 4)
- Replace `daily_nudge.php` mock with pipeline enqueue (R1); member prefs page (`notification-prefs.php`) writing `user_time_windows` + channel choice; in-app inbox rows via `user_notifications`.
- R2–R4 (opt-in rituals, streak save, weekly summary).
- Onboarding F2 sequence.

### Phase 5 — Revenue & lifecycle (week 5+)
- R5–R8 (reassessment, renewal/refill, winback, review ask) and C4 long nurture.
- Analytics view in `backend/admin/notifications.php`: per-journey funnel (queued → sent → delivered → clicked → converted), channel cost vs revenue (`cost_usd` × `sales`).
- A/B engine integration: journey copy/timing become experiment surfaces (templates carry `data-exp`-style variant keys; reward = journey conversion), per `1wellness-ab-engine-implementation.md`.

---

## 8. KPIs & Instrumentation

| Area | Metric | Target (90 days) |
|---|---|---|
| C1 | abandoned assessments recovered | ≥ 15% of abandons complete after nudge |
| C3 | checkout abandons recovered | 5–15% (audit benchmark) |
| F-series | purchase-confirm delivery rate | ≥ 99% within 2 min |
| R1/R2 | daily nudge → same-day portal login | ≥ 35% |
| R6 | refill/renewal take rate | ≥ 10% of expiring plans |
| Hygiene | spam complaints / SMS STOP rate | < 0.1% / < 2% |
| Cost | channel cost per recovered purchase | < 10% of AOV |

Every outbound link carries `utm_source=notify&utm_campaign={journey_key}_{step}&utm_medium={channel}` so `funnel_tracking` and the A/B engine attribute revenue to messages.

---

## 9. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| WhatsApp template approval delays | Start Meta business verification in Phase 1; ship Phases 1–2 email-only |
| Flutterwave webhook misconfigured/silently failing | Fix A: signed-hash verification, daily reconciliation cron, admin Payment Integrity panel with alerting |
| Pre-contact abandons unreachable | Fix B: two-tier C1 + admin-toggleable mid-quiz save prompt, sized by the "unreachable abandons" metric |
| SMS cost runaway | Daily cost cap setting + SMS only as last rung on two journeys |
| Duplicate sends on cron overlap | `dedupe_key` UNIQUE + row-level status locking (same approach as `process_webhooks.php`) |
| Mixed lead/user identity (email vs user_id) | `recipient_type` discriminator; merge on purchase (lead rows linked to created user) |
| Deliverability (new domain volume) | Warm up: dry-run → admin-only → 10% cohort → full; SPF/DKIM/DMARC checklist before Phase 2 go-live |

---

*Plan v1.1 — June 2026. Builds on the deployed conversion-audit fixes (resume links, checkout_init/assessment_start events). The two former external prerequisites are now in-scope, fully specified fixes: §6 Fix A (purchase-event integrity — verified server webhook + reconciliation + admin Payment Integrity panel) and §6 Fix B (abandonment identity capture — two-tier recovery with admin toggle). Nothing in this plan depends on work outside it.*
