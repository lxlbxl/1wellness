# 1wellness — Treatment Plan Engine: Per-Funnel Prompts & Logic

**Repo:** lxlbxl/1wellness (master)
**Audience:** Coding agent (Claude Code / OpenClaw)
**Scope:** The plan-generation brain — system/user prompts, generator routing, daily plan logic, and per-condition module shape. The goal: **a truly unique, value-packed plan per funnel that over-delivers on the sales-page promise** — never a one-size-fits-all template.
**Companion docs:** `1wellness-hardening-and-member-experience.md` (member area), `1wellness-conversion-audit.md`, `1wellness-ab-engine-implementation.md`

---

## 0. What's Broken Today (Findings)

1. **One generator serves all four funnels.** `backend/api/generate-plan.php` is commented "all funnels" but hardcodes `new PcosGenerator()` (line 68). An **acne, weight, or men's buyer receives a PCOS plan** — PCOS types, berberine/inositol, cycle phases, and all.
2. **Exercise is hardcoded for everyone.** `MEAL_PLANNER_PROMPT.txt` instruction #2: "Generate a 15-minute workout/movement activity" — emitted in every daily plan's JSON regardless of condition. **Acne does not need a daily workout.** This is the exact problem to eliminate.
3. **Only PCOS has a real knowledge base.** The PCOS system prompt is excellent — type-specific root causes, herbs with Yoruba names, safety guardrails, Nigerian meals. **No equivalent exists for acne, weight, or men's.** Those funnels have no clinical grounding at all.
4. **The daily plan shape is fixed** (breakfast/lunch/dinner/snack + workout + shopping list) — it cannot express skin routines, photo check-ins, strength progression, or sleep protocols that other conditions require.
5. **Tracking guidance is generic.** The plan tells users "what to track" as prose, but nothing wires it to the member-area logging modules, and the *right things to log differ per condition* (cycle vs skin photos vs weight trend vs energy).

**Design principle for the fix:** one orchestration engine, four deeply specialized knowledge bases and plan schemas. Shared scaffolding, condition-specific everything-that-matters. Each plan must include modules the condition needs and **omit modules it doesn't** (no workout for acne).

---

## 1. Architecture: Generalize `PcosGenerator` → `ProtocolGenerator`

### 1.1 Routing
Refactor `generate-plan.php` to select a generator strategy by `condition` (pcos | acne | weight | mens), resolved from the assessment/sale, not hardcoded:

```php
$condition = $assessment['condition'] ?? $sale['funnel_name'];   // pcos|acne|weight|mens
$generator = ProtocolGeneratorFactory::for($condition);
$pdfBinary  = $generator->generate($assessment, $name, $email);
```

### 1.2 Class shape
- `AbstractProtocolGenerator` — shared: AI call, JSON extraction/repair, validation, PDF render, email, async-job hand-off. (Lift the reusable parts out of the current `PcosGenerator`.)
- `PcosProtocolGenerator`, `AcneProtocolGenerator`, `WeightProtocolGenerator`, `MensProtocolGenerator` — each supplies: its system prompt, its user-prompt template, its **plan schema (module set)**, its validation rules, and its compliance guardrails.
- Prompts live as files per condition: `backend/prompts/{condition}/system-prompt.md` and `user-prompt.md`. Keep them editable from the admin `system_prompts` table (versioned), loaded by key.

### 1.3 Module manifest per condition (the "no workout for acne" mechanism)
Each generator declares which modules it emits. The schema is composed from this manifest — modules not listed are never generated, never rendered, never tracked:

| Module | PCOS (CycleSync) | Acne (GlowClear) | Weight (LeanFlow) | Men's (Vitale) |
|---|:---:|:---:|:---:|:---:|
| Meal plan | ✅ phase-synced | ✅ anti-inflammatory | ✅ calorie/macro-targeted | ✅ T-supporting |
| Movement/exercise | ✅ type-appropriate | ❌ **omit** (optional stress-only) | ✅ **core, progressive** | ✅ strength/recovery |
| Topical skincare routine (AM/PM) | ❌ | ✅ **core** | ❌ | ❌ |
| Herbal protocol | ✅ | ✅ (skin-clearing) | ✅ (metabolic) | ✅ (vitality) |
| Supplements | ✅ | ✅ | ✅ | ✅ |
| Cycle/phase sync | ✅ **core** | ⚠️ only if hormonal-acne + female | ❌ | ❌ |
| Sleep & stress protocol | ✅ | ✅ (cortisol→skin) | ✅ | ✅ **core (recovery)** |
| Daily logging set | cycle, mood, energy, cravings, symptoms | **skin photo**, flares, triggers (dairy/sugar/sleep), mood | weight (trend-smoothed), measurements, energy, habits | energy, libido, focus, sleep, strength, mood |
| Progress visualization | cycle regularity | **clarity-score + photo timeline** | **weight trend + measurements** | vitality score + streaks |

> The daily-plan generator (`MealPlanner.php`) must read this manifest and only emit blocks the condition declares. Strip the unconditional workout out of `MEAL_PLANNER_PROMPT.txt`; movement becomes a manifest-gated block.

---

## 2. Per-Condition Knowledge Bases (System Prompts)

Replicate the *quality and structure* of the existing PCOS prompt — root-cause taxonomy, type-specific protocols, local (Nigerian/West African) grounding, safety guardrails, JSON-only output — for each condition. Below is the required substance for each. (Medical content must be reviewed by a qualified professional before production; treat the taxonomies below as scaffolding to be clinically validated, not final medical copy.)

### 2.1 Acne — GlowClear System Prompt
**Persona:** holistic dermatology-informed skin specialist; gentle, anti-shame, evidence-aware.
**Acne-type knowledge base (root cause → markers → protocol):**
- **Hormonal acne** — jawline/chin cysts, premenstrual flares, adult-onset. Focus: blood-sugar balance, DIM/zinc, spearmint tea, dairy reduction. (If female + cyclical, may enable optional cycle-awareness module.)
- **Inflammatory acne** — red papules/pustules, sensitivity, diet-reactive. Focus: anti-inflammatory diet, omega-3, gut support, barrier repair.
- **Comedonal acne** — blackheads/whiteheads, congestion, oily T-zone. Focus: exfoliation routine (salicylic), niacinamide, non-comedogenic regimen.
- **Fungal acne (folliculitis)** — uniform itchy bumps, worse with sweat. Focus: anti-fungal routine, avoid oils that feed malassezia, sweat hygiene. (Critical to distinguish — wrong protocol worsens it.)
- **Stress/cortisol acne** — flares tracking stress/poor sleep. Focus: cortisol management, sleep protocol, adaptogens.

**Required modules:** AM/PM topical routine (cleanser → active → moisturizer → SPF, with specific ingredient guidance per type), anti-inflammatory meal plan, skin-clearing herbs (Nigerian-available: bitter leaf, neem/dongoyaro, turmeric, moringa), targeted supplements (zinc, omega-3, DIM for hormonal, probiotics), trigger-elimination protocol, sleep/stress block. **No daily workout module.** Optional gentle stress-movement only.
**Signature deliverable:** baseline + progress **photo protocol** and a flare-response playbook.
**Guardrails:** distinguish fungal vs bacterial explicitly; refer to dermatologist for cystic/scarring/severe; no prescription-drug advice (isotretinoin, antibiotics); patch-test warning on all topicals; pregnancy-unsafe actives (retinoids, high-dose salicylic) flagged.

### 2.2 Weight — LeanFlow System Prompt
**Persona:** sustainable metabolic coach; explicitly anti-crash-diet, anti-shame.
**Type knowledge base:**
- **Insulin-resistant / metabolic** — central weight, cravings, fatigue after meals. Focus: protein-first, low-GL, strength + walking, berberine/chromium.
- **Stress/cortisol-driven** — stress eating, poor sleep, belly retention. Focus: cortisol management, sleep, gentle movement, no aggressive deficits.
- **Hormonal (thyroid/perimenopause)** — slow metabolism, cold, fatigue. Focus: thyroid-supportive nutrition, refer for labs, moderate movement.
- **Habit/lifestyle** — portion/activity patterns. Focus: habit architecture, NEAT, sustainable deficit.

**Required modules:** calorie/macro-targeted meal plan (computed from profile — age/weight/height/activity/goal), **progressive movement plan (core)**, metabolic herbs/supplements, habit-formation protocol, plateau-response protocol, sleep block.
**Signature deliverable:** **trend-smoothed** weight graph + measurement tracking + non-scale-victory log (daily scale noise causes churn — smooth and reframe).
**Guardrails:** no crash deficits or rapid-loss promises; minimum calorie floors; refer for eating-disorder red flags (per global wellbeing standard — never provide precise targets if disordered-eating signals present); medical referral for thyroid/PCOS-driven cases.

### 2.3 Men's — Vitale System Prompt
**Persona:** direct, no-fluff, performance-and-vitality framed.
**Type/goal knowledge base:**
- **Low energy / fatigue** — afternoon crashes, poor recovery. Focus: blood-sugar stability, sleep, adaptogens (ashwagandha), B-vitamins.
- **Low testosterone markers** — low libido, mood, muscle loss, belly fat. Focus: strength training, zinc/vitamin D/magnesium, sleep, refer for labs.
- **Stress/burnout** — wired-tired, poor sleep, irritability. Focus: cortisol management, recovery protocol, adaptogens.
- **Body composition / performance** — recomposition goals. Focus: protein, progressive strength, recovery.

**Required modules:** T-supporting nutrition plan, **strength/movement protocol (core, progressive)**, **sleep & recovery protocol (core)**, vitality supplements + Nigerian herbs (fadogia-class/tribulus-class only if safe & validated, bitter kola, moringa), stress/adaptogen block.
**Signature deliverable:** vitality/energy score + habit streaks + sleep-recovery tracking.
**Guardrails:** refer for labs before any hormone-related claims; no anabolic/steroid/TRT advice; flag cardiac risk for older/sedentary users before intense protocols; no libido drug advice.

### 2.4 PCOS — keep & refine the existing prompt
The current PCOS system prompt is the gold standard — retain it. Minor upgrades: feed it the manifest so cycle-sync is explicit, and align its `tracking_guidance` output to the member-area logging keys (§4).

---

## 3. User-Prompt Templates (Per Condition)

Mirror the existing PCOS `user-prompt.md` per condition, injecting the assessment variables that condition actually collects. Each must:
1. Bind the condition's **type** ({{ACNE_TYPE}}, {{WEIGHT_TYPE}}, {{ENERGY_PROFILE}}, etc.) and route to the matching knowledge-base entry.
2. Inject the real assessment answers so the plan reads as written-for-them (name, age, symptoms, goals, restrictions, lifestyle).
3. Enforce Nigerian/West-African food + herb localization (carry over from PCOS).
4. Specify length/depth per field so output stays premium.
5. Demand JSON-only matching that condition's schema.

**Critical instruction to embed in every user prompt:** "Only include the modules defined for this condition. Do NOT add an exercise/workout block unless this condition's manifest includes movement. Do NOT add cycle-phase content for non-cyclical conditions."

---

## 4. Daily Logging: Condition-Specific, Wired to the Member Area

The plan's `tracking_guidance` must output **structured, machine-readable logging definitions** (not prose), so the member-area logging modules render automatically. Schema per tracked item:
```json
{ "key": "skin_clarity", "label": "Skin Clarity (1-10)", "type": "scale|number|photo|boolean|select",
  "frequency": "daily|weekly", "options": [...], "why": "string", "chart": "trend|streak|none" }
```
Default logging sets per condition (the AI personalizes within these):
- **PCOS:** cycle day, mood, energy, cravings, symptom flare (boolean+note), weight (weekly).
- **Acne:** **skin photo (daily/weekly)**, clarity score (1–10), flare count, trigger checklist (dairy/sugar/sleep/stress), mood.
- **Weight:** weight (daily, charted as smoothed trend), measurements (weekly), energy, habit checklist, NSV note.
- **Men's:** energy (1–10), libido, focus, sleep hours/quality, strength session done, mood.

This is the bridge to the member-experience doc: those logging keys are exactly what the per-funnel tracker modules consume.

---

## 5. Over-Delivery: Make Each Plan Worth Far More Than Its Price

Beyond fixing correctness, pack value the sales page implies but the current plan doesn't fully deliver:
- **Personalized root-cause explainer** in their words ("here's *why* your skin breaks out on your jaw before your period") — already strong in PCOS, replicate everywhere.
- **Phase progression** (the PCOS 3-phase 90-day arc) adapted per condition — acne: barrier-repair → active-treatment → maintenance; weight: foundation → momentum → sustainability; men's: restore → build → optimize.
- **A "why this works" rationale** on every recommendation (the PCOS prompt's `benefit`/`why` fields — enforce across all).
- **Local sourcing guide** — where to buy the herbs/foods in Nigeria, approximate cost — turns advice into action.
- **A printable + in-app version** (PDF for the promise, structured data for the live member modules — generate both from one plan object).
- **First-action quick win** surfaced for onboarding (ties to member-experience C.4).
- **Shopping list auto-derived** from the meal plan (MealPlanner already does this — keep, make condition-aware).

---

## 6. Validation, Safety & Quality Gates

1. **Schema validation per condition** — reject/repair AI output that omits required modules or includes forbidden ones (e.g. a workout block in an acne plan fails validation → regenerate).
2. **Compliance classifier pass** (shared with A/B challenger + member chat): scan generated plan for cure claims, guaranteed outcomes, unsafe dosages, pregnancy-contraindicated items without warning, disordered-eating-triggering targets → block + regenerate.
3. **Dosage bounds** enforced programmatically per condition (carry the PCOS guardrails: berberine ≤1500mg, ashwagandha ≤600mg, etc.; build equivalent tables per condition).
4. **Medical disclaimer** injected into every plan; medical-referral triggers per condition's red-flag list.
5. **Human medical review** of all four knowledge bases before production — the taxonomies here are scaffolding, not clinician-approved final copy. Non-negotiable for a health brand.
6. **Golden-output tests** — a fixture assessment per condition with assertions: acne plan contains skincare routine + photo logging + NO workout; weight plan contains macro targets + trend chart; men's contains recovery protocol; PCOS contains cycle sync. Run in CI.

---

## 7. Build Sequence

| Phase | Scope | Gate |
|---|---|---|
| 1 | Refactor to `AbstractProtocolGenerator` + factory routing by condition; strip hardcoded workout from daily prompt; manifest system | PCOS still generates correctly via new path; daily plan no longer forces workout |
| 2 | Author + clinically-stub the three new system/user prompts (acne, weight, men's) with knowledge bases & schemas | Each funnel generates a condition-correct plan; golden tests pass |
| 3 | Structured per-condition logging output wired to member tracker modules | Logging keys render correct tracker per funnel |
| 4 | Over-delivery layer (phase arcs, sourcing guide, dual PDF+live output, quick win) | Each plan maps 1:1 to its sales-page promise |
| 5 | Compliance classifier + dosage bounds + golden CI tests + human medical review | Compliance red-team passes; sign-off recorded |

---

## Definition of Done

Four buyers, four funnels, one engine:
- **Acne buyer** gets a GlowClear plan: acne-type root cause, AM/PM skincare routine, anti-inflammatory Nigerian meals, skin-clearing herbs, trigger protocol, **daily skin-photo + clarity logging — and no workout block anywhere.**
- **Weight buyer** gets a LeanFlow plan: macro-targeted meals, a progressive movement plan, trend-smoothed weight tracking, plateau protocol.
- **Men's buyer** gets a Vitale plan: T-supporting nutrition, strength + recovery protocol, vitality/sleep logging.
- **PCOS buyer** keeps today's excellent plan, now with cycle-sync explicit and logging wired to the tracker.
Every plan is condition-correct, omits irrelevant modules, passes the compliance + dosage gates, and delivers more than the sales page promised.

*Instruction set v1.0 — June 2026. For agentic execution against lxlbxl/1wellness (master). All medical taxonomies require qualified clinical review before production deployment.*
