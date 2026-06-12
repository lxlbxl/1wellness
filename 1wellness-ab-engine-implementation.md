# 1wellness Smart A/B Testing Engine — Implementation Instructions

**Project:** AI-Powered Funnel Experimentation Engine for 1wellness (lxlbxl/1wellness)
**Stack:** PHP 7.4+, MySQL (required for tracking), Vanilla JS, n8n, Claude API via existing `AIOrchestrator.php`
**Algorithm:** Thompson Sampling (Bayesian multi-armed bandit) with revenue-per-visitor reward
**Status:** Implementation specification — hand to dev or coding agent as-is

---

## 1. Objective

Build a self-optimizing experimentation layer on top of the existing four funnels (`/pcos/`, `/acne/`, `/weight/`, `/mens/`) that:

1. Routes incoming traffic across funnel variations via sticky, server-side assignment.
2. Allocates traffic dynamically using Thompson Sampling — winners automatically receive more traffic; losers are starved.
3. Judges variants on measurable metrics tied to the existing funnel event flow, with **revenue per visitor (RPV)** as the primary bottom-funnel reward.
4. Uses the existing `AIOrchestrator` to diagnose losing variants, explain funnel leaks, and generate challenger variants — gated behind human approval in the admin panel.

## 2. Existing Assets to Reuse (Do Not Rebuild)

| Asset | Location | Role in Engine |
|---|---|---|
| `funnel_tracking` table | `backend/database/schema_mysql.sql` | Extend with `experiment_id`, `variant_id` columns — becomes the event store |
| `FunnelDiscovery.php` | `backend/classes/` | Extend to detect variant directories (`pcos__b/`) |
| `AIOrchestrator.php` | `backend/classes/` | All AI calls: diagnostics + challenger generation |
| `tracking.js` | `js/` | Add experiment event emitter + override applier |
| Cron infrastructure | `backend/cron/` | Posterior recompute + AI diagnostic jobs |
| `system_prompts` table | DB | Store AI agent prompts (versioned, editable from admin) |
| Brand bible | `1wellness-brand-bible.html` | Grounding context for AI variant generation |
| GDPR consent | `js/gdpr-cookie-consent.js` | Gate conversion event logging |

## 3. Architecture Overview

```
Visitor → .htaccess rewrite → router.php
              │
              ├── Read/set sticky cookie (1w_exp, domain=.1wellness.club)
              ├── Thompson Sampling assignment per active experiment
              ├── Serve structural variant dir OR inject variant_overrides JSON
              │
Page (tracking.js) → emits funnel events → /backend/api/track-event.php
              │                                   │
              │                          funnel_tracking (+experiment_id, +variant_id)
              │                                   │
Cron (hourly) → recompute_posteriors.php → variants.alpha/beta updated
Cron (weekly) → ai_diagnostics.php → AIOrchestrator → insights + challengers
              │
Admin Panel → Experiments tab → approve/edit/launch challengers
```

## 4. Database Schema (Migration 001)

Create `backend/database/migrations/001_ab_engine.sql`:

```sql
CREATE TABLE IF NOT EXISTS experiments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funnel_name VARCHAR(50) NOT NULL,            -- pcos | acne | weight | mens
    name VARCHAR(120) NOT NULL,
    hypothesis TEXT,
    stage VARCHAR(50) NOT NULL,                  -- landing | assessment | results | pricing | checkout
    primary_metric VARCHAR(60) NOT NULL,         -- e.g. assessment_start | purchase_rpv
    reward_type ENUM('binary','revenue') DEFAULT 'binary',
    status ENUM('draft','burn_in','active','concluded','archived') DEFAULT 'draft',
    burn_in_hours INT DEFAULT 48,
    min_exposure_floor DECIMAL(4,3) DEFAULT 0.100,  -- 10% traffic floor per variant
    min_samples_per_variant INT DEFAULT 1000,
    decision_p_best DECIMAL(4,3) DEFAULT 0.950,     -- promote at P(best) > 95%
    decision_expected_loss DECIMAL(5,4) DEFAULT 0.0050,
    winner_variant_id INT NULL,
    started_at DATETIME NULL,
    concluded_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,                  -- "Control", "B: urgency headline"
    type ENUM('control','structural','element') NOT NULL,
    directory VARCHAR(120) NULL,                 -- for structural: e.g. pcos__b
    overrides JSON NULL,                         -- for element: see §6.2
    -- Thompson Sampling state (binary reward)
    alpha DECIMAL(12,4) DEFAULT 1.0,             -- prior successes + observed
    beta  DECIMAL(12,4) DEFAULT 1.0,             -- prior failures + observed
    -- Revenue reward state (Gaussian/empirical)
    exposures INT DEFAULT 0,
    conversions INT DEFAULT 0,
    revenue_total DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending_approval','active','killed','winner') DEFAULT 'active',
    source ENUM('human','ai_challenger') DEFAULT 'human',
    ai_rationale TEXT NULL,                      -- why the AI proposed this
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS assignments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    experiment_id INT NOT NULL,
    variant_id INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_experiment (session_id, experiment_id),
    KEY idx_assign_variant (variant_id)
);

CREATE TABLE IF NOT EXISTS variant_metrics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT NOT NULL,
    metric_date DATE NOT NULL,
    exposures INT DEFAULT 0,
    assessment_starts INT DEFAULT 0,
    assessment_completes INT DEFAULT 0,
    results_views INT DEFAULT 0,
    plan_selects INT DEFAULT 0,
    checkout_inits INT DEFAULT 0,
    purchases INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0,
    UNIQUE KEY uq_variant_date (variant_id, metric_date)
);

CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NULL,
    funnel_name VARCHAR(50),
    insight_type ENUM('diagnostic','suggestion','challenger_rationale'),
    content JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Extend existing event store
ALTER TABLE funnel_tracking
    ADD COLUMN experiment_id INT NULL,
    ADD COLUMN variant_id INT NULL,
    ADD COLUMN revenue DECIMAL(10,2) NULL,
    ADD INDEX idx_ft_variant (variant_id, event_type, created_at);
```

> **Hard requirement:** these tables must live on MySQL, not SQLite. Per-pageview assignment writes + event inserts will hit SQLite write-lock contention under paid traffic. If the main app stays on SQLite, point only the tracking/experiment tables at MySQL via a second PDO connection in `Database.php`.

## 5. Event Taxonomy (Fixed — Do Not Improvise)

Every funnel emits exactly these events to `/backend/api/track-event.php`:

| Event | Fires On | Used As Reward For |
|---|---|---|
| `view` | Any funnel page load (deduped per session per page) | Exposure denominator |
| `assessment_start` | First question answered | Landing-stage experiments |
| `assessment_complete` | Assessment submitted | Assessment-stage experiments |
| `results_view` | Results page load | — |
| `plan_select` | Plan card clicked on select-plan | Results-stage experiments |
| `checkout_init` | Flutterwave modal opened | Pricing-stage experiments |
| `purchase` | Payment webhook confirmed (server-side, from `webhook_*.php`) | Bottom-funnel; carries `revenue` |

**Rules:**
- `purchase` is logged **server-side only** from the Flutterwave webhook handlers (`webhook_pcos.php` etc.) — never from the browser. Match to session via the `session_id` passed through checkout metadata.
- All client events gate behind GDPR consent state from `gdpr-cookie-consent.js`. The assignment cookie itself is functional and exempt; event logging is not.
- Bot filter at the API: drop events where user-agent matches known bot patterns or where `assessment_start` arrives < 2 seconds after `view`.

## 6. Variant Serving

### 6.1 Router

Add to `.htaccess` (per funnel dir or root):

```apache
RewriteEngine On
RewriteRule ^(pcos|acne|weight|mens)/?$ backend/router.php?funnel=$1 [L,QSA]
```

`backend/router.php` logic:

1. Read `1w_exp` cookie (JSON map of `experiment_id → variant_id`). Set with `Domain=.1wellness.club; Max-Age=7776000; SameSite=Lax` so assignment survives the subdomain split (pcos.1wellness.club, etc.).
2. For each `active` experiment on this funnel missing from the cookie: run Thompson Sampling assignment (§7), write to `assignments` table, update cookie.
3. If assigned variant is `structural`: serve `{funnel}__{dir}/index.html`. If `element`: serve the control HTML and inject `<script>window.__VARIANT_OVERRIDES = {...}</script>` before `</head>`.
4. Log an `view` exposure event with experiment/variant IDs.

### 6.2 Element Overrides (where 80% of tests live)

Override JSON schema stored in `variants.overrides`:

```json
{
  "text":  { "[data-exp='hero-headline']": "Your hormones aren't broken. Your plan was.",
             "[data-exp='cta-primary']": "Get My 90-Day Plan" },
  "html":  { "[data-exp='social-proof']": "<strong>2,317 women</strong> started this week" },
  "attr":  { "[data-exp='hero-img']": { "src": "/images/pcos/hero-b.webp" } },
  "style": { "[data-exp='price-anchor']": { "display": "none" } },
  "config":{ "salePrice": 87 }
}
```

Implementation steps:
1. Tag testable elements in funnel HTML with `data-exp` attributes (one-time pass per funnel: headline, subhead, CTA, hero image, social proof block, price anchor, guarantee copy).
2. Add an applier to `tracking.js` that reads `window.__VARIANT_OVERRIDES` and applies text/html/attr/style maps on `DOMContentLoaded`. Add a 1-frame anti-flicker style (`html{opacity:0}` released by the applier, 300ms hard timeout fallback).
3. `config` overrides merge into `window.DataManager.pricing` before render — this is how price tests work without touching `data-manager.js` logic.

### 6.3 Structural Variants

Whole alternate page sets live in sibling dirs named `{funnel}__{slug}/` (e.g. `pcos__longform/`). Extend `FunnelDiscovery::scan()` to recognize the `__` pattern and register them as variant candidates instead of standalone funnels (add `pcos__*` style matching to the exclusion-from-funnels logic, insertion into `variants` as `type='structural'`).

## 7. Thompson Sampling Allocation

`backend/classes/Bandit.php`:

```php
class Bandit
{
    /** Pick a variant for an experiment. */
    public function assign(array $experiment, array $variants): array
    {
        // 1. Burn-in: equal random split for first N hours
        if ($experiment['status'] === 'burn_in') {
            return $variants[array_rand($variants)];
        }

        // 2. Exposure floor: if any variant is below floor share, force-serve it
        $totalExposures = max(1, array_sum(array_column($variants, 'exposures')));
        foreach ($variants as $v) {
            if ($v['exposures'] / $totalExposures < $experiment['min_exposure_floor']) {
                return $v;
            }
        }

        // 3. Thompson Sampling: sample each posterior, serve the max
        $best = null; $bestDraw = -1;
        foreach ($variants as $v) {
            $draw = ($experiment['reward_type'] === 'revenue')
                ? $this->sampleRevenuePosterior($v)
                : $this->sampleBeta((float)$v['alpha'], (float)$v['beta']);
            if ($draw > $bestDraw) { $bestDraw = $draw; $best = $v; }
        }
        return $best;
    }

    private function sampleBeta(float $a, float $b): float
    {
        $x = $this->sampleGamma($a);
        $y = $this->sampleGamma($b);
        return $x / ($x + $y);
    }

    private function sampleGamma(float $shape): float
    {
        // Marsaglia & Tsang method
        if ($shape < 1) {
            return $this->sampleGamma($shape + 1) * pow($this->u(), 1 / $shape);
        }
        $d = $shape - 1/3; $c = 1 / sqrt(9 * $d);
        while (true) {
            do { $x = $this->normal(); $v = pow(1 + $c * $x, 3); } while ($v <= 0);
            $u = $this->u();
            if ($u < 1 - 0.0331 * pow($x, 4)) return $d * $v;
            if (log($u) < 0.5 * $x * $x + $d * (1 - $v + log($v))) return $d * $v;
        }
    }

    private function sampleRevenuePosterior(array $v): float
    {
        // RPV = P(convert) × E(order value). Sample conversion from Beta,
        // multiply by empirical mean order value with shrinkage prior.
        $pConv = $this->sampleBeta($v['alpha'], $v['beta']);
        $aov = $v['conversions'] > 0
            ? $v['revenue_total'] / $v['conversions']
            : 100.0; // prior AOV across catalog (~$67–$197 mid)
        return $pConv * $aov;
    }

    private function normal(): float
    {
        return sqrt(-2 * log($this->u())) * cos(2 * M_PI * $this->u());
    }

    private function u(): float
    {
        return mt_rand(1, mt_getrandmax() - 1) / mt_getrandmax();
    }
}
```

**Posterior updates** happen in `backend/cron/recompute_posteriors.php` (hourly):
- For each active variant: `alpha = 1 + conversions_on_primary_metric`, `beta = 1 + exposures − conversions`, plus `exposures`, `conversions`, `revenue_total` refreshed from `funnel_tracking`.
- Roll daily counts into `variant_metrics_daily`.
- **Status transitions:** `burn_in → active` after `burn_in_hours`; experiment → `concluded` when a variant hits P(best) > `decision_p_best` AND expected loss < `decision_expected_loss` AND every variant ≥ `min_samples_per_variant` exposures. Compute P(best) by Monte Carlo: draw 10,000 samples per variant posterior, count win share.
- On conclusion: set `winner_variant_id`, route 100% traffic to winner, fire n8n notification webhook.

## 8. AI Layer (via existing AIOrchestrator)

Add two system prompts to the `system_prompts` table.

### 8.1 Weekly Diagnostic Agent — `backend/cron/ai_diagnostics.php`

**Input payload (JSON):** per active experiment — variant performance table (exposures, step-by-step counts, RPV, P(best)), step drop-off rates vs funnel baseline, segment splits (device from user_agent, country from IP geo, UTM source from metadata), list of concurrently running experiments on the same funnel.

**System prompt key:** `ab_diagnostic_agent`. Core instructions:

```
You are a CRO analyst for a wellness brand. You receive structured A/B
experiment data. Respond ONLY with JSON:
{
  "funnel_leaks": [{"stage": "", "drop_rate": 0, "vs_baseline": "", "severity": "high|med|low"}],
  "variant_analysis": [{"variant": "", "verdict": "", "likely_cause": ""}],
  "segment_effects": [{"segment": "", "observation": ""}],
  "overlap_warnings": [],   // experiments on same funnel judged on same metric
  "suggestions": [{"priority": 1, "stage": "", "test_idea": "", "expected_impact": "", "rationale": ""}]
}
Never invent data. Flag low-sample conclusions explicitly.
```

Output is stored in `ai_insights` and surfaced in the admin dashboard. Push a summary to n8n for WhatsApp/Slack delivery.

### 8.2 Challenger Generator

Triggered when a variant is `killed`. **Context fed to the model:** the brand bible content (strip the HTML from `1wellness-brand-bible.html` once, cache as text), the killed variant's overrides + the diagnostic verdict on why it lost, the current control's copy, and the sub-brand identity from `js/config.js` (CycleSync/GlowClear/LeanFlow/Vitale voice).

**System prompt key:** `ab_challenger_agent`. Output contract: a valid `variant_overrides` JSON (§6.2 schema) + `ai_rationale` string. Insert into `variants` with `status='pending_approval'`, `source='ai_challenger'`.

**Compliance guardrail (non-negotiable for a health brand):** the prompt must prohibit medical cure claims, guaranteed outcomes, and specific timeframe promises ("cures PCOS", "lose 10kg in 30 days"). Add a second cheap AI pass that classifies the generated copy as compliant/non-compliant before it even reaches the approval queue.

**Human-in-the-loop:** nothing AI-generated ever serves traffic without admin approval. Period.

## 9. Admin Panel — Experiments Tab (`backend/admin/experiments.php`)

Minimum viable screens:

1. **Experiment list:** per funnel — status, days running, traffic share per variant (live bandit weights), P(best) bars, RPV per variant.
2. **Experiment detail:** funnel-step waterfall per variant, daily trend chart from `variant_metrics_daily`, latest AI diagnostic rendered from `ai_insights`.
3. **Approval queue:** AI challengers with side-by-side diff vs control, rationale, compliance flag, Approve / Edit / Reject buttons.
4. **Create experiment:** funnel, stage, primary metric, variants (paste overrides JSON or pick structural dir), guardrail settings (prefilled defaults).

Reuse the existing admin auth (`Admin.php`) and layout.

## 10. Capacity Rules (Operating Doctrine)

- **Per experiment:** technically unlimited variants; statistically traffic-bound.
  - Purchase/RPV-judged: 1,000–3,000 exposures needed per variant. At 500 visitors/day/funnel → run 3–4 variants, expect 2–4 week convergence. At 2,000/day → 6–8 variants.
  - Micro-conversion-judged (assessment_start ~20–60% base rate): converges 5–10× faster → 8–12 variants viable.
- **Concurrency rule:** max ONE active experiment per funnel stage per funnel, each judged on its own downstream-adjacent metric. PCOS can run landing + assessment + pricing tests simultaneously (~10–12 live variants) without contamination. Never two experiments judged on the same final metric in one funnel.
- **Overload behavior:** bandits degrade gracefully — too many variants on thin traffic costs time, not validity. Obvious losers get starved early.
- Across all 4 funnels: 40+ concurrent live variations is comfortably within design.

## 11. Build Phases

| Phase | Scope | Deliverables |
|---|---|---|
| **1 (Wk 1)** | Foundation | Migration 001, `router.php`, sticky cookie, `Bandit.php` with burn-in only, `data-exp` tagging pass on PCOS funnel |
| **2 (Wk 2)** | Metrics + brain | `track-event.php`, event emitters in tracking.js, server-side purchase logging in webhooks, `recompute_posteriors.php`, full Thompson Sampling live |
| **3 (Wk 3)** | Visibility | Admin Experiments tab (all 4 screens), n8n winner notifications |
| **4 (Wk 4)** | AI loop | Diagnostic agent cron, challenger generator, compliance pass, approval queue wiring. Roll out tagging to acne/weight/mens |

## 12. Pre-Launch Checklist

- [ ] Tracking/experiment tables on MySQL (not SQLite)
- [ ] `APP_ENV` set to production (CSRF bypass in `form-handler.php` is dev-only — verify)
- [ ] Default admin credentials (`admin/admin123`) changed
- [ ] GDPR: event logging gated on consent; assignment cookie documented in cookie policy
- [ ] Bot filtering active on track-event endpoint
- [ ] Anti-flicker snippet verified on slow 3G throttle
- [ ] Purchase events confirmed server-side via Flutterwave webhook test
- [ ] One end-to-end dry run: create experiment → burn-in → forced conversions → posterior update → conclusion → winner promotion

---

*Spec version 1.0 — June 2026. Designed for the lxlbxl/1wellness repo (master). Compatible with OpenClaw / Claude Code agent execution: phases are independently shippable and each ends in a verifiable state.*
