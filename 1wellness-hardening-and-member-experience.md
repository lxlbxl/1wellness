# 1wellness — Hardening & Member Experience Implementation Instructions

**Repo:** lxlbxl/1wellness (master)
**Audience:** Coding agent (Claude Code / OpenClaw) — phases are independently shippable, each ends in a verifiable state.
**Stack:** PHP 7.4+, MySQL/SQLite via PDO, Vanilla JS + Alpine, n8n, Claude API via `AIOrchestrator.php`
**Companion docs:** `1wellness-ab-engine-implementation.md`, `1wellness-conversion-audit.md`

> **Priority order is deliberate.** Part A (security) is exploitable in production *now* and ships before everything, including conversion work. Part B is backend foundation. **Part C (Member Experience & Promise Delivery) is the core of this document** and where most effort belongs — it is what converts one-time buyers into retained, refund-resistant, referral-generating members, and it is currently the weakest part of the app.

---

# PART A — Critical Security Fixes (Ship First, Same Day)

## A.1 Seal the credential-leak endpoint — `backend/api/get-credentials.php`
**Current defect:** Unauthenticated GET with an email returns `user_id`, `username`, **`password_hash`**, name, and plan dates. Anyone can harvest bcrypt hashes for offline cracking.

**Required changes:**
1. Never include `password_hash` in any API response. Remove it from the SELECT.
2. Require proof of ownership before returning anything: this endpoint must only run inside the post-purchase redirect, authenticated by a short-lived, single-use signed token issued server-side at webhook confirmation (HMAC of `tx_ref + user_id + expiry`, 15-min TTL, marked consumed after first use). No token → 403.
3. The post-purchase "set your password" flow should issue a one-time password-set link by email, not return account internals to the browser.

## A.2 Verify Flutterwave webhook signatures — `backend/api/webhook_{pcos,acne,weight,mens}.php`
**Current defect:** No `verif-hash` check. Anyone can POST a forged "completed" payment → free product access + corrupted sales data (which also corrupts every A/B experiment).

**Required changes:**
1. At the top of each webhook, read the `verif-hash` header and compare (hash_equals, constant-time) against the secret stored in settings/env. Mismatch → 401, log attempt, exit.
2. Add **idempotency:** unique constraint on `sales.tx_ref`; on duplicate webhook delivery, no-op instead of creating a second sale/user. Flutterwave retries are normal.
3. Re-verify the transaction server-side via Flutterwave's verify API using `transaction_id` before granting access — never trust the POST body's amount/status alone.
4. Confirm currency + amount match the expected plan price (guards tampered-amount attacks).

## A.3 Authentication brute-force protection
**Current defect:** Zero rate limiting/lockout on `MemberAuth.php`, `Admin.php`, or any API endpoint.

**Required changes:**
1. Add a `login_attempts` table (identifier, ip, attempted_at). Lock after 5 failures in 15 min (per identifier AND per IP). Exponential backoff thereafter.
2. Add a generic API rate limiter (token bucket, keyed by IP) as middleware in `index.php`/`form-handler.php`. 60 req/min default.
3. Change default admin credentials; confirm `APP_ENV=production` disables the dev CSRF bypass in `form-handler.php` and sets `display_errors = 0`.

## A.4 Remove public debug/PowerShell artifacts
Delete from the public web root: `backend/api/n8n-debug-auth.php`, `n8n-debug-db.php`, `n8n-debug-*`, and the committed `cleanup.ps1`, `cleanup2.ps1`, `rebrand.ps1`, `final-verify.ps1` (or move to a non-served `/scripts` dir excluded by server config). These leak paths/config and shouldn't be web-accessible.

**Part A acceptance:** automated check that (a) get-credentials returns 403 without a valid token and never contains `password_hash`; (b) a forged webhook POST is rejected 401 and a replayed valid one creates no duplicate; (c) 6th bad login in 15 min is blocked; (d) debug endpoints 404.

---

# PART B — Backend Foundation (Week 1, parallel to conversion fixes)

## B.1 Dependency management & tests
- Add `composer.json`; pull in PHPMailer (replace hand-rolled SMTP), a JWT lib for member tokens, and PHPUnit.
- Stand up `tests/` with unit coverage on the highest-risk classes first: `Database`, `MemberAuth`, webhook handlers, `MealPlanner`, `PcosGenerator`.
- Add a minimal GitHub Actions CI: install, run PHPUnit, run `php -l` lint across `backend/`.

## B.2 Migration runner
- Add `backend/database/migrate.php` that applies numbered SQL files from `migrations/` and records applied versions in a `schema_migrations` table. The A/B engine and Part C both ship as migrations — this is a hard dependency.

## B.3 Async job queue
**Current defect:** plan generation, AI calls, emails all block the request. A slow Claude call = spinner/timeout.
- Add a `jobs` table (type, payload JSON, status, attempts, run_after) + `backend/cron/worker.php` processing it every minute (you already have the cron pattern).
- Move to async: AI plan generation, all outbound email, n8n pushes, PDF generation. The browser gets an instant "your protocol is being built" state and polls/receives a push when ready (ties into C.4).

## B.4 Transactional email + idempotency + monitoring
- Route `Mailer.php` through a real provider (Resend/Postmark/SES) with retry + delivery logging. Required for the nurture/recovery sequences in Part C.
- Add Sentry (or equivalent) error tracking; add an uptime check on `/backend/api/health.php`.
- Document a DB backup job in DEPLOY.md (especially critical on SQLite). Add nightly dump to off-box storage.

## B.5 Per-answer assessment persistence
- Persist each assessment answer to localStorage AND server (`assessment_progress` table keyed by session) on selection, not on final submit. Unlocks: resume-after-refresh, and the abandonment-recovery loop in the conversion audit.

---

# PART C — Member Experience & Promise Delivery (THE CORE WORK)

## C.0 The Central Problem

The member area today is **hardcoded to PCOS** — `member/index.html` contains 38 PCOS references and literally reads "Tailored for your PCOS type" for every member regardless of which funnel they bought. Yet:
- Four distinct sub-brands already exist in `js/config.js`: **CycleSync** (PCOS), **GlowClear** (acne), **LeanFlow** (weight), **Vitale** (men's).
- `MealPlanner.php` is **already condition-aware** (`getFunnelPhase($condition, $profile)`, 17 `condition` references) — the backend can already personalize; the frontend throws it away.
- The results/sales pages **promise** specific deliverables ("Body Data & Progress Tracking" valued at $90, "Type-specific herbal rituals", "Phase-synced movement", "Cycle-aware plans") that the member area only partially delivers — and the tracker shows "Coming Soon" / "Apple Health — Coming Soon".

**Selling a personalized, condition-specific transformation and delivering a generic PCOS dashboard is the #1 driver of refunds, chargebacks, and dead subscriptions.** Promise delivery IS retention. This part fixes that.

## C.1 Make the Member Experience Funnel-Aware (Architecture)

The member experience must resolve everything from the user's **`condition`** (pcos | acne | weight | mens), set at purchase and stored on the user record.

### C.1.1 Schema
Migration `010_member_personalization.sql`:
```sql
ALTER TABLE users
  ADD COLUMN condition VARCHAR(20) NULL,        -- pcos|acne|weight|mens (the funnel bought)
  ADD COLUMN sub_brand VARCHAR(30) NULL,        -- CycleSync|GlowClear|LeanFlow|Vitale
  ADD COLUMN assessment_type VARCHAR(60) NULL,  -- e.g. "Insulin-Resistant PCOS", "Hormonal Acne"
  ADD COLUMN assessment_json JSON NULL,         -- full answers, for ongoing personalization
  ADD COLUMN onboarded_at DATETIME NULL,
  ADD COLUMN streak_count INT DEFAULT 0,
  ADD COLUMN last_active_date DATE NULL;

CREATE TABLE member_milestones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  milestone_key VARCHAR(60),       -- first_week_complete, first_cycle_logged, 30_day_mark...
  achieved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_milestone (user_id, milestone_key)
);
```
Backfill `condition`/`sub_brand` for existing users from their original `sales.funnel_name`.

### C.1.2 Config-driven theming
Create `member/config/conditions.php` — a single registry that every member component reads from. Per condition it defines: sub-brand name, accent color/theme tokens, terminology map, the module set, the tracker metrics, the milestone schedule, and the AI persona key. **No component may hardcode condition-specific strings** — they pull from this registry keyed on `user.condition`. This is the mechanism that makes the experience "unique per funnel" without four copies of the member area.

### C.1.3 Refactor existing components
Sweep `member/components/*.php` (dashboard, nourish, weekly, tracker, profile, sidebar, modals) and `member/index.html`: replace every literal "PCOS"/"your PCOS type"/"cycle" string with a lookup from the conditions registry. The dashboard greeting, module labels, and "Tailored for your X" line all resolve dynamically.

## C.2 Per-Funnel Member Experience Definitions

Each funnel delivers a distinct, promise-matched experience. Same engine, different registry config + AI persona + module set.

### C.2.1 CycleSync (PCOS) — *cycle & hormone regulation*
- **Hero metric:** cycle regularity / next predicted period. **Persona:** "Aisha"-style recovered-cyster wise-friend (reuse OJG PCOS voice).
- **Modules:** Cycle tracker (phase-aware), Phase-synced meals (MealPlanner already supports cycle phase), Type-specific herbal ritual protocol w/ reminders, Phase-synced movement, Symptom tracker (acne flares, mood, energy, cravings).
- **Milestones:** first cycle logged → first full cycle tracked → first regular cycle → 90-day transformation.
- **Promise-delivery checklist:** every line item from `pcos/results.html` value stack ($45 meals / $35 herbal / $27 movement / $90 tracking) must have a corresponding *working* module.

### C.2.2 GlowClear (Acne) — *skin clarity over time*
- **Hero metric:** skin-clarity score trend + flare frequency. **Persona:** dermatology-informed, gentle, anti-shame.
- **Modules:** Daily skin log with optional progress-photo timeline (private, encrypted), trigger tracker (dairy/sugar/stress/sleep correlation surfaced by AI), skin-type-specific topical + ingestible routine (AM/PM), flare-response protocol, anti-inflammatory meal plan.
- **Differentiator:** the **photo progress timeline** is GlowClear's signature promise-delivery artifact — clarity is visual; show it.
- **Milestones:** baseline photo → 2-week routine adherence → first flare-free week → cleared-skin milestone.

### C.2.3 LeanFlow (Weight) — *metabolic & body composition*
- **Hero metric:** weight trend + non-scale victories (energy, measurements). **Persona:** metabolic coach, sustainable-not-crash framing (compliance guardrail: no crash-diet/rapid-loss promises).
- **Modules:** Weight & measurement tracker w/ trend smoothing (not daily noise), metabolic meal plan w/ calorie/macro targets from profile, progressive movement plan, habit/NSV tracker, plateau-response protocol.
- **Differentiator:** trend-smoothed graph + measurement tracking (scale weight alone causes churn via daily fluctuation anxiety — smooth it).
- **Milestones:** first week logged → first 2% bodyweight → first plateau broken → goal progress bands.

### C.2.4 Vitale (Men's) — *vitality, energy, hormonal health*
- **Hero metric:** energy/vitality score + key habit streaks. **Persona:** direct, no-fluff, performance-framed.
- **Modules:** Energy & vitality tracker, T-supporting nutrition plan, strength/movement protocol, sleep & recovery tracker, supplement protocol w/ reminders, libido/mood/focus log.
- **Differentiator:** habit-streak / performance framing and recovery metrics.
- **Milestones:** baseline → first streak week → energy-score improvement → 90-day vitality milestone.

> Implementation note: all four share the SAME dashboard shell, tracker engine, meal/plan generator, and reminder system. Only the conditions-registry entry, the module list, the tracker metric definitions, the AI persona prompt, and the theme tokens differ. Build once, configure four times.

## C.3 Close the Promise-Delivery Gaps (Make "Coming Soon" Real)

Audit found the tracker shows "Coming Soon" and device integration is stubbed, while the sales pages charge for "Body Data & Progress Tracking" ($90 claimed value). Ship the promised features or stop selling them.

- **C.3.1 Working tracker per condition** (not "Coming Soon"): manual entry + trend charts for each condition's hero metric. Defer Apple Health/device sync explicitly with an honest "in development" label OR remove the claim from sales pages — do not show a dead "Coming Soon" to a paying member.
- **C.3.2 Progress visualization:** every condition gets a primary trend chart on the dashboard (cycle regularity / clarity score / weight trend / vitality score). This is the visible proof the program works — the single biggest retention driver.
- **C.3.3 The actual personalized protocol:** ensure `MealPlanner` + `PcosGenerator` (generalize to a `ProtocolGenerator` covering all four conditions) produce condition- and assessment-type-specific plans, not PCOS defaults. The user's `assessment_json` feeds personalization.
- **C.3.4 Herbal/supplement reorder:** the "get all essentials in one order" block must link to a working, condition-correct product bundle (ties to OJG fulfillment).

## C.4 Onboarding — The First 10 Minutes (Retention Is Won Here)

A buyer who doesn't activate in session one churns. Build a guided first-run:
1. **Instant gratification:** on first login, the personalized protocol is already generated (async job fired at purchase, per B.3) — never make a paying member wait at a spinner.
2. **Welcome sequence:** condition-specific welcome from the AI persona, 3-step orientation (here's your plan / here's how to track / here's your first action today), set their first reminder.
3. **First quick win:** prompt one trivially completable action today (log baseline weight / take baseline photo / log first ritual) → fire `first_action` milestone → dopamine + data baseline.
4. **Expectation setting:** honestly frame the timeline ("most members see X by week N") — matches the compliance-safe claims from the conversion audit.

## C.5 Ongoing Engagement & Retention Loop

- **C.5.1 Streaks & milestones:** `member_milestones` + `streak_count`. Celebrate milestones in-app and via the persona over email/WhatsApp. Streaks drive daily return.
- **C.5.2 Proactive AI check-ins** (you already have `proactive_gen` in `data.php` + `AutomationOrchestrator.php`): condition-aware nudges — "You're in your luteal phase, here's what to expect" (CycleSync), "Your skin log shows dairy correlation" (GlowClear), "Plateau detected — switching your protocol" (LeanFlow), "Energy dipped this week — let's adjust recovery" (Vitale).
- **C.5.3 Weekly review ritual:** an auto-generated weekly summary (progress chart + wins + next week's focus) delivered in-app and by email. This is the recurring "the program is working" moment.
- **C.5.4 Win-back:** inactivity (no login 7/14/30 days) triggers persona-led re-engagement referencing their specific progress.

## C.6 Member-Area AI Specialist ("Chat with Specialist")

The nav already lists "Chat with Specialist." Make it real via `AIOrchestrator`:
- Condition-specific system persona (the four personas above), grounded in the member's `assessment_json`, current protocol, and recent logs (RAG over their own data).
- **Compliance guardrail (non-negotiable, health brand):** no diagnosis, no medical cure claims, no medication advice; hard-coded escalation to "consult your doctor" on red-flag inputs (pregnancy, severe symptoms, medication interactions, self-harm). Same compliance classifier pass used in the A/B engine's challenger generator.
- Log conversations for the proactive engine to reference.

## C.7 Recurring Revenue & Lifecycle

- **C.7.1 Subscription billing:** app is currently one-time purchase only. Add recurring billing (Flutterwave recurring / Paystack subscriptions) for ongoing member access — the member experience above is what justifies a subscription vs one-time PDF.
- **C.7.2 Self-service:** password reset flow (verify it exists end-to-end), plan renewal (`verify_renewal` exists — complete it), cancellation flow with a save-offer step, and refund handling that checks the guarantee window.
- **C.7.3 Referral:** condition-matched referral ("invite a friend dealing with PCOS") — same-condition referrals convert best, mirrors the same-type-testimonial insight from the conversion audit.

---

# PART D — Build Sequencing

| Phase | Scope | Gate |
|---|---|---|
| **0 (Day 1)** | Part A — all security fixes | A-acceptance checks pass |
| **1 (Wk 1)** | Part B — composer/tests/CI, migration runner, job queue, email provider, monitoring, per-answer persistence | Worker processes a job; email delivers; CI green |
| **2 (Wk 1–2)** | C.1 — funnel-aware architecture: schema, conditions registry, de-hardcode components, backfill existing users | A PCOS and an acne member see correctly themed, correctly-labelled dashboards from one codebase |
| **3 (Wk 2–3)** | C.3 + C.2 — close promise-delivery gaps, working trackers + trend charts, generalize ProtocolGenerator to 4 conditions, per-funnel module sets | Every sales-page value-stack line item maps to a working module; no "Coming Soon" on a paid feature |
| **4 (Wk 3–4)** | C.4 + C.5 — onboarding first-run, streaks/milestones, proactive check-ins, weekly review, win-back | New buyer reaches first-win milestone in session one; weekly review fires |
| **5 (Wk 4+)** | C.6 + C.7 — AI specialist with compliance guard, subscriptions, self-service lifecycle, referral | Specialist passes compliance red-team; a subscription renews; a referral attributes |

**Dependency callouts:** C.4's instant-protocol depends on B.3 (job queue). All recovery/nurture/proactive loops depend on B.4 (real email). Everything in Part C depends on C.1 (funnel-aware architecture) landing first — do not start C.2/C.3 before the conditions registry exists, or you'll hardcode condition logic four times.

---

## Definition of Done (Member Experience)

A member who bought the **acne** funnel logs in and sees: a **GlowClear**-branded dashboard (not PCOS), a skin-clarity trend chart, a photo progress timeline, an AM/PM routine matched to their assessed skin type, a dairy-correlation insight from their own logs, a same-condition AI specialist, a first-week milestone they hit on day one, and a weekly review showing their progress — with **zero** PCOS strings and **zero** "Coming Soon" on anything they paid for. The same is true, appropriately themed, for pcos/weight/mens buyers, all from one codebase.

*Instruction set v1.0 — June 2026. Built for agentic execution against lxlbxl/1wellness (master).*
