# 1wellness Smart A/B Testing Engine

Self-optimizing experimentation layer over the four funnels (`/pcos/`, `/acne/`, `/weight/`, `/mens/`).
Traffic is allocated by **Thompson Sampling** (Bayesian multi-armed bandit): winners automatically
earn more traffic, losers are starved, and experiments auto-conclude when the posterior evidence
clears the decision rule. An AI layer (via the existing `AIOrchestrator`) diagnoses funnel leaks
and proposes challenger variants — always gated behind human approval.

Related docs: [API-REFERENCE.md](API-REFERENCE.md) · [WEBHOOKS.md](WEBHOOKS.md)

---

## 1. Architecture

```
Visitor → .htaccess rewrite → backend/router.php
              │
              ├── Sticky cookies: 1w_sid (session), 1w_exp (experiment→variant map, 90d)
              ├── Thompson Sampling assignment per running experiment (Bandit.php)
              ├── Serves structural variant dir OR injects window.__VARIANT_OVERRIDES
              ├── Anti-flicker guard (html{opacity:0}, released by applier, 300ms hard timeout)
              └── Logs server-side `view` exposure (deduped per session/day, bot-filtered)

Funnel pages (js/tracking.js → AB module)
              ├── Applies overrides (text/html/attr/style/config) on DOMContentLoaded
              ├── Emits funnel events → POST /backend/api/track-event.php
              │     (GDPR analytics-consent gated; purchase NOT accepted from browser)
              └── window.AB.track(event, meta) public API

Flutterwave payment webhooks (webhook_pcos.php etc.)
              └── AutomationOrchestrator logs server-side `purchase` event with revenue,
                  attributed to the session's variant assignments + fires outbound webhooks

Cron
  ├── hourly  backend/cron/recompute_posteriors.php   posteriors, transitions, auto-conclusion
  ├── weekly  backend/cron/ai_diagnostics.php          AI funnel diagnostics → ai_insights
  └── minute  backend/cron/process_webhooks.php        outbound webhook delivery + retries

Admin panel
  ├── backend/admin/experiments.php        list, create, start/pause/archive/delete
  ├── backend/admin/experiment-detail.php  waterfall, trends, AI insight, approval queue
  └── backend/admin/webhooks.php           webhook subscriptions with event selection
```

## 2. Database (Migration 001)

Tables (created by `php backend/database/migrations/migrate.php`, or lazily by `ABSchema::ensure()`):

| Table | Purpose |
|---|---|
| `experiments` | One row per experiment: funnel, stage, metric, guardrails, status, winner |
| `variants` | Variants with Thompson state (`alpha`/`beta`), revenue counters, cached `p_best`/`expected_loss` |
| `assignments` | Sticky session→variant map (unique per session+experiment) |
| `variant_metrics_daily` | Per-day step counts per variant (charts) |
| `ai_insights` | AI diagnostic verdicts, suggestions, challenger rationales |
| `webhooks` | Outbound webhook subscriptions (see WEBHOOKS.md) |
| `webhook_queue` | Delivery queue (ensured here; also in base schema) |
| `funnel_tracking` | Extended with `experiment_id`, `variant_id`, `revenue` |

> **Production requirement:** these tables must run on **MySQL**. SQLite is supported for local
> development only — per-pageview assignment writes hit SQLite write-lock contention under paid traffic.
> The raw MySQL DDL is at `backend/database/migrations/001_ab_engine.sql`.

## 3. Event taxonomy (fixed — do not improvise)

| Event | Fires on | Source |
|---|---|---|
| `view` | Funnel page load (deduped per session/page/day) | router (server) + tracking.js |
| `assessment_start` | First question interaction | tracking.js (auto-wired) |
| `assessment_complete` | Assessment form submit | tracking.js (auto-wired) |
| `results_view` | Results page load | tracking.js (auto-wired) |
| `plan_select` | Plan card clicked | tracking.js (auto-wired) |
| `checkout_init` | Flutterwave modal opened | flutterwave-integration.js |
| `purchase` | Payment confirmed — **server-side only**, carries `revenue` | AutomationOrchestrator |

Rules enforced by `backend/api/track-event.php`:
- `purchase` from the browser is **rejected** (HTTP 400).
- Bot filter: UA patterns + `assessment_start` arriving < 2 s after `view` is dropped.
- Client events are deduped (view per page/day; step events first-touch per session).
- All client events are gated on GDPR **analytics** consent in tracking.js. The assignment
  cookie (`1w_exp`) is functional and exempt; document it in the cookie policy.
- Attribution is server-side: events are stamped with the session's live assignments from the
  `assignments` table, so inner funnel pages need no injected context.

## 4. Variant types

### 4.1 Element variants (where ~80% of tests live)
Override JSON stored in `variants.overrides`, applied by tracking.js against `data-exp` selectors:

```json
{
  "text":  { "[data-exp='headline']": "Your hormones aren't broken. Your plan was." },
  "html":  { "[data-exp='social-proof']": "<strong>2,317 women</strong> started this week" },
  "attr":  { "[data-exp='hero-img']": { "src": "/images/pcos/hero-b.webp" } },
  "style": { "[data-exp='price-anchor']": { "display": "none" } },
  "config": { "salePrice": 87 }
}
```

`config` merges into the funnel's `CONFIG.pricing` entry before render — that's how price
tests work without touching `data-manager.js`.

Tagged elements already in the funnels: `headline`, `subhead`, `cta-primary`, `cta-final`,
`social-proof`, `guarantee-badge`, `results-headline`, `price-block`, `guarantee-box`,
`cta-plan`, `cta-pay`, `order-bump`, `payment-trust`, `testimonial-grid`, `value-stack`, …
(grep `data-exp` to list all).

### 4.2 Structural variants
Whole alternate page sets in sibling dirs named `{funnel}__{slug}/` (e.g. `pcos__longform/`)
with their own `index.html`. `FunnelDiscovery` excludes `__` dirs from funnel registration and
exposes them as variant candidates in the create-experiment form.

## 5. Thompson Sampling & decision rule (`backend/classes/Bandit.php`)

- **Binary reward**: Beta(α, β) posterior on conversion; α = 1 + conversions, β = 1 + exposures − conversions.
- **Revenue reward (`purchase_rpv`)**: RPV draw = Beta conversion sample × AOV with shrinkage prior
  (catalog prior $100, k = 5 pseudo-observations).
- **Burn-in**: equal random split for `burn_in_hours` (default 48 h).
- **Exposure floor**: any variant under `min_exposure_floor` share (default 10%) is force-served,
  chosen at random among the starved set. Live exposure counters keep this accurate between recomputes.
- **Auto-conclusion** (hourly cron): experiment concludes when ALL of:
  - every serveable variant ≥ `min_samples_per_variant` exposures (default 1000),
  - top variant's Monte Carlo P(best) > `decision_p_best` (default 0.95, 10,000 draws),
  - its expected loss < `decision_expected_loss` (default 0.005 — note: this is in conversion-rate
    units for binary experiments; for `purchase_rpv` it's in $/visitor, so set it higher, e.g. 0.50).
- On conclusion the winner gets status `winner`, losers are killed, traffic stops being split
  (concluded experiments don't assign), and the `experiment.concluded` webhook fires.

## 6. AI layer

System prompts (seeded into `system_prompts`, editable from admin → System Prompts):

| Key | Role |
|---|---|
| `ab_diagnostic_agent` | Weekly CRO diagnostic: funnel leaks, variant verdicts, segments, suggestions (strict JSON) |
| `ab_challenger_agent` | Generates challenger overrides for killed variants (brand-bible grounded) |
| `ab_compliance_agent` | Classifies generated copy compliant/non-compliant (fails closed) |

- **Diagnostics** (`backend/cron/ai_diagnostics.php`, weekly): builds per-funnel performance payload
  (variant tables, step drop-offs vs baseline, device segments, concurrent experiments), stores the
  verdict in `ai_insights`, fires `experiment.insight_ready`. Add `--challengers` to auto-generate
  challengers for killed variants that have none.
- **Challenger generation** (`backend/classes/ChallengerGenerator.php`): triggered from the admin
  "Kill" action, the API (`kill_variant` with `generate_challenger`), or the diagnostics cron.
  Context: brand bible (stripped & cached), sub-brand voice, control copy (live `data-exp` extraction),
  the killed variant + diagnostic verdict. Output is schema-validated, then compliance-classified.
- **Human-in-the-loop (non-negotiable)**: AI variants are inserted with `status='pending_approval'`
  and **never serve traffic** until an admin approves them in the approval queue. Compliance
  violations are flagged in red. The compliance check fails closed if the classifier is unavailable.

## 7. Admin panel

- **Experiments** (`/backend/admin/experiments.php`): per-funnel cards with status, days running,
  live traffic-share bars, P(best), CR and RPV per variant. Create modal with variant builder
  (element overrides JSON or structural dir picker) and guardrail settings. Start/Pause/Delete.
- **Detail** (`experiment-detail.php?id=N`): funnel-step waterfall per variant, 30-day daily trend
  chart (CR or RPV), latest AI diagnostic, approval queue with side-by-side copy diff vs the live
  control, Approve / Edit / Reject, Kill (+AI challenger), Promote-as-winner.
- **Webhooks** (`webhooks.php`): see WEBHOOKS.md.

## 8. Cron setup

```cron
* * * * *  php /path/to/backend/cron/process_webhooks.php
0 * * * *  php /path/to/backend/cron/recompute_posteriors.php
0 6 * * 1  php /path/to/backend/cron/ai_diagnostics.php --challengers
```

## 9. Capacity rules (operating doctrine)

- **Concurrency rule (enforced at creation):** max ONE non-concluded experiment per funnel stage.
  PCOS can run landing + assessment + pricing tests simultaneously; never two experiments judged
  on the same final metric in one funnel.
- Purchase/RPV-judged: 1,000–3,000 exposures per variant; at 500 visitors/day/funnel run 3–4
  variants, expect 2–4 week convergence. Micro-conversion metrics converge 5–10× faster (8–12
  variants viable).
- Overload degrades gracefully: too many variants on thin traffic costs time, not validity.

## 10. Pre-launch checklist

- [ ] Tracking/experiment tables on **MySQL** (`DB_TYPE=mysql` in `.env`) — not SQLite
- [ ] `php backend/database/migrations/migrate.php` run (or first request auto-installs)
- [ ] Root `.htaccess` deployed (funnel rewrites to router)
- [ ] `APP_ENV=production` set; default admin credentials changed
- [ ] GDPR: cookie policy documents `1w_sid` / `1w_exp` as functional cookies
- [ ] Bot filtering verified on track-event endpoint
- [ ] Anti-flicker verified on slow 3G throttle (300 ms reveal fallback)
- [ ] Flutterwave webhook test purchase → `purchase` row in `funnel_tracking` with `revenue`
- [ ] End-to-end dry run green: `php backend/tests/ab_engine_dryrun.php` (38 checks)
- [ ] Cron jobs installed (§8)
- [ ] n8n webhook subscribed to `experiment.concluded` + `experiment.insight_ready`

## 11. Quick start (first experiment)

1. Admin → Experiments → **New Experiment**: funnel `pcos`, stage `landing`,
   metric `assessment_start`.
2. Keep Control + add an element variant, e.g.
   `{"text": {"[data-exp='headline']": "New headline"}}`.
3. **Create**, then **Start** — burn-in serves 50/50 for 48 h, then the bandit takes over.
4. Watch P(best) bars; the hourly cron auto-concludes at 95% confidence.
5. Kill a clear loser → the AI proposes a challenger → review the diff → Approve.
