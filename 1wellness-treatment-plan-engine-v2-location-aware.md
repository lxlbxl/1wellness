# 1wellness — Treatment Plan Engine: Per-Funnel, Location-Aware Prompts & Logic

**Repo:** lxlbxl/1wellness (master)
**Audience:** Coding agent (Claude Code / OpenClaw)
**Scope:** The plan-generation brain — system/user prompts, generator routing, daily plan logic, per-condition module shape, **and a new geo-adaptive layer** so every plan is localized to the user's region (foods, herbs, sourcing, units, climate). The goal: **a truly unique, value-packed plan per funnel AND per location that over-delivers on the sales-page promise.**
**Companion docs:** `1wellness-hardening-and-member-experience.md`, `1wellness-conversion-audit.md`, `1wellness-ab-engine-implementation.md`

---

## 0. What's Broken Today (Findings)

1. **One generator serves all four funnels.** `backend/api/generate-plan.php` is commented "all funnels" but hardcodes `new PcosGenerator()` (line 68). An **acne, weight, or men's buyer receives a PCOS plan** — PCOS types, berberine/inositol, cycle phases, and all.
2. **Exercise is hardcoded for everyone.** `MEAL_PLANNER_PROMPT.txt` instruction #2: "Generate a 15-minute workout/movement activity" — emitted in every daily plan regardless of condition. **Acne does not need a daily workout.**
3. **Only PCOS has a real knowledge base.** Strong PCOS prompt (type-specific root causes, herbs, safety guardrails); **no equivalent for acne, weight, or men's.**
4. **Everything is hardcoded to Nigeria.** Prompts force "real Nigerian dishes" (Ofada rice, Amala, Efo Riro) and Yoruba herb names (Ewedu, Ewuro, Ata-ile). **1wellness targets multiple regions internationally.** A user in Serbia, Germany, the Philippines, or Brazil receives Nigerian meals and West-African herbs they cannot source — instant refund.
5. **The daily plan shape is fixed** (breakfast/lunch/dinner/snack + workout + shopping list) — cannot express skin routines, photo check-ins, strength progression, or sleep protocols.
6. **Tracking guidance is generic** and not wired to condition-specific member-area logging.

**Design principle:** one engine; personalization on **two independent axes** — (A) **condition** (pcos/acne/weight/mens) determines the clinical protocol and module set; (B) **location** determines the *expression* — which foods, herbs, local names, units, sourcing. **Clinical logic is universal; ingredients and herbs are local.** A Serbian PCOS user and a Kenyan PCOS user get the *same insulin-resistance strategy* expressed through *completely different meals and herbs*.

---

## 1. The Geo-Adaptive Layer (New — Core of This Rewrite)

### 1.1 Capture location at onboarding
- Add a location step: **country** (required), **city/region** (optional, improves accuracy), **cuisine/dietary preference** free-field (vegetarian, halal, Mediterranean, "I cook mostly local").
- IP geolocation as a default guess (IP is already captured for tracking), **confirmed/edited by the user** — never rely on IP alone (travelers, VPNs, diaspora).
- Persist to the user record.

Migration `011_location.sql`:
```sql
ALTER TABLE users
  ADD COLUMN country_code CHAR(2) NULL,          -- ISO 3166-1 alpha-2: RS, KE, DE, PH...
  ADD COLUMN country_name VARCHAR(80) NULL,
  ADD COLUMN region_city VARCHAR(120) NULL,
  ADD COLUMN locale VARCHAR(10) NULL,            -- en-KE, sr-RS...
  ADD COLUMN measurement_system ENUM('metric','imperial') DEFAULT 'metric',
  ADD COLUMN cuisine_pref VARCHAR(160) NULL,
  ADD COLUMN climate_zone VARCHAR(40) NULL;      -- temperate, tropical, arid, continental...
```

### 1.2 Region Profile resolver
Create `backend/classes/RegionProfile.php`. Given country (+ optional city), return a structured profile the prompt builder injects. Source order: curated **region pack** → AI-generated profile (cached) for uncovered regions.

Region Profile shape:
```json
{
  "country": "Serbia", "country_code": "RS",
  "climate_zone": "continental", "measurement_system": "metric",
  "staple_foods": ["whole-grain bread", "beans (pasulj)", "cabbage", "peppers", "yogurt/kajmak", "plums", "walnuts", "freshwater fish"],
  "common_proteins": ["chicken", "pork", "freshwater fish", "eggs", "legumes"],
  "locally_available_herbs": [
     {"name": "Nettle", "local_name": "kopriva", "use": "anti-inflammatory, mineral-rich"},
     {"name": "Chamomile", "local_name": "kamilica", "use": "calming, sleep"},
     {"name": "St John's Wort", "local_name": "kantarion", "use": "mood — FLAG interactions"},
     {"name": "Yarrow", "local_name": "hajdučka trava", "use": "cycle support"}
  ],
  "where_to_source": "green markets (pijaca), apoteka/herbal pharmacies, health shops",
  "typical_cost_band": "local currency estimate",
  "dietary_norms": "pork common; large dairy presence; Orthodox fasting periods",
  "language_for_local_names": "Serbian"
}
```
The same resolver for **Nairobi** returns sukuma wiki, ugali, managu, terere, moringa, local markets, KES costs, Swahili names; for **Manila, Berlin, São Paulo, Cairo**, etc. accordingly.

### 1.3 Region packs (curated > generated)
- Ship **curated region packs** for top markets first (define from sales geo). Curated packs are clinician/nutritionist-reviewable and higher quality.
- For any uncovered country, the resolver calls AI once, **caches** the profile (`region_profiles` table), flags it `unreviewed` for later curation. Global coverage from day one without 195 hand-built packs.
- Region packs are **data, not code** — JSON in DB/files so non-engineers expand them.

### 1.4 Herb safety is global; sourcing is local
**Herb *safety* rules are universal and must NOT vary by region**, but *which* herbs are suggested is local. Maintain a master **herb safety table** (contraindications, max doses, pregnancy flags, drug interactions). The resolver may only surface herbs that (a) are locally available AND (b) pass the safety table for that user's profile/medications. Never recommend a local herb just because it's local if it fails safety.

---

## 2. Architecture: Generalize `PcosGenerator` → `ProtocolGenerator`

### 2.1 Routing
```php
$condition = $assessment['condition'] ?? $sale['funnel_name'];   // pcos|acne|weight|mens
$region    = (new RegionProfile())->resolve($user);              // location-aware context
$generator = ProtocolGeneratorFactory::for($condition);
$pdfBinary = $generator->generate($assessment, $name, $email, $region);
```

### 2.2 Class shape
- `AbstractProtocolGenerator` — shared AI call, JSON extraction/repair, validation, PDF render, email, async hand-off, **Region-Profile injection into every prompt.**
- `Pcos|Acne|Weight|MensProtocolGenerator` — each supplies system prompt, user-prompt template, plan schema (module set), validation rules, compliance guardrails.
- Prompts as files: `backend/prompts/{condition}/system-prompt.md`, `user-prompt.md`; editable via admin `system_prompts` table, versioned.

### 2.3 Module manifest per condition (the "no workout for acne" mechanism)
Modules not listed are never generated, rendered, or tracked:

| Module | PCOS | Acne | Weight | Men's |
|---|:---:|:---:|:---:|:---:|
| Meal plan (**localized**) | ✅ phase-synced | ✅ anti-inflammatory | ✅ macro-targeted | ✅ vitality-supporting |
| Movement/exercise | ✅ | ❌ **omit** | ✅ **core, progressive** | ✅ strength/recovery |
| Topical skincare routine (AM/PM) | ❌ | ✅ **core** | ❌ | ❌ |
| Herbal protocol (**localized + safety-gated**) | ✅ | ✅ | ✅ | ✅ |
| Supplements | ✅ | ✅ | ✅ | ✅ |
| Cycle/phase sync | ✅ **core** | ⚠️ hormonal-acne + female only | ❌ | ❌ |
| Sleep & stress protocol | ✅ | ✅ | ✅ | ✅ **core** |
| Daily logging set | cycle, mood, energy, cravings, symptoms | **skin photo**, flares, triggers, mood | weight (trend), measurements, energy, habits | energy, libido, focus, sleep, strength, mood |
| Progress visualization | cycle regularity | **clarity + photo timeline** | **weight trend** | vitality score + streaks |

> `MealPlanner.php` reads this manifest and only emits declared blocks. **Strip the unconditional workout from `MEAL_PLANNER_PROMPT.txt`**; movement becomes manifest-gated. The meal generator also takes the Region Profile so daily meals are local.

---

## 3. Per-Condition Knowledge Bases (System Prompts) — Region-Neutral Clinical Core

Each system prompt carries the **universal clinical taxonomy** and is explicitly **region-neutral**. Localization is injected at runtime via the Region Profile. (All taxonomies require qualified clinical review before production.)

**Shared localization block (inject into all four system prompts):**
```
LOCALIZATION RULES:
- You will receive a REGION PROFILE (country, climate, local staples, locally available herbs
  with local-language names, sourcing, units).
- Every meal MUST use foods locally available and culturally familiar in the user's region.
  Never recommend ingredients the user cannot reasonably buy where they live.
- Refer to herbs by common English name AND the local name from the Region Profile.
- Only suggest herbs in the Region Profile's available list AND permitted by the safety table for
  this user. If a clinically ideal herb is unavailable locally, suggest the closest local alternative
  and say so.
- Use the user's measurement system (metric/imperial) and local units throughout.
- Respect stated dietary norms/preferences (halal, vegetarian, fasting periods).
- Do NOT default to any single country's cuisine. The same protocol must read naturally for a user
  in Serbia, Kenya, the Philippines, Germany, or Brazil.
```

### 3.1 PCOS (CycleSync)
Retain the existing PCOS clinical taxonomy (Insulin-Resistant, Inflammatory, Adrenal, Post-Pill — root causes, markers, supplement classes, exercise guidance per type) — the model of quality. **Strip the hardcoded Nigerian meals and Yoruba herb names** → region-injected. Keep universal supplement science (inositol, berberine ceiling). Add the localization block.

### 3.2 Acne (GlowClear)
Types: **hormonal, inflammatory, comedonal, fungal (folliculitis), stress/cortisol** — each root cause/markers/strategy. Modules: AM/PM topical routine (type-specific actives), anti-inflammatory **localized** meals, skin herbs **from region pack** (nettle/chamomile in Europe; neem/moringa in tropics; calendula widely), supplements (zinc, omega-3, DIM, probiotics), trigger-elimination, sleep/stress. **No workout.** Signature: baseline + progress **photo protocol** + flare playbook. Guardrails: distinguish fungal vs bacterial (wrong protocol worsens fungal); dermatologist referral for cystic/scarring; no prescription-drug advice; patch-test warning; pregnancy-unsafe actives flagged.

### 3.3 Weight (LeanFlow)
Types: insulin-resistant/metabolic, stress/cortisol, hormonal (thyroid/perimenopause), habit/lifestyle. Modules: **macro-targeted localized** meals (targets from profile + measurement system), **progressive movement (core)**, metabolic herbs/supplements (region-gated), habit protocol, plateau-response, sleep. Signature: **trend-smoothed** weight graph + measurements + NSV log. Guardrails: no crash deficits/rapid-loss promises; calorie floors; disordered-eating red-flag referral and NO precise targets if those signals present; thyroid referral.

### 3.4 Men's (Vitale)
Profiles: low-energy/fatigue, low-T markers, stress/burnout, body-composition. Modules: vitality-supporting **localized** nutrition, **strength/movement (core)**, **sleep & recovery (core)**, vitality supplements + region herbs (safety-gated), stress/adaptogen. Signature: vitality score + streaks + recovery tracking. Guardrails: labs-referral before hormone claims; no anabolic/steroid/TRT advice; cardiac-risk flag for older/sedentary; no libido-drug advice.

---

## 4. User-Prompt Templates (Per Condition) — with Region Injection

Mirror the existing PCOS `user-prompt.md` per condition. Each injects: condition **type** variable, assessment answers (name/age/symptoms/goals/restrictions/lifestyle/medications), **and the full Region Profile JSON.** Each must: route by type; feel written-for-them by symptoms AND location; enforce localization (local foods, herb names, units, sourcing); specify length/depth; demand JSON-only.

**Critical instruction in every user prompt:** "Only include modules defined for this condition. Do NOT add a workout block unless the manifest includes movement. Do NOT add cycle-phase content for non-cyclical conditions. Use ONLY foods and herbs available in the user's region per the Region Profile."

---

## 5. Daily Logging: Condition-Specific, Wired to Member Area

`tracking_guidance` outputs **structured, machine-readable** definitions (not prose):
```json
{ "key": "skin_clarity", "label": "Skin Clarity (1-10)", "type": "scale|number|photo|boolean|select",
  "frequency": "daily|weekly", "unit": "kg|lb|...", "why": "string", "chart": "trend|streak|none" }
```
Default sets — PCOS: cycle day, mood, energy, cravings, flare, weight(weekly). Acne: **skin photo**, clarity 1–10, flare count, trigger checklist, mood. Weight: weight (daily, smoothed, **user units**), measurements (weekly), energy, habits, NSV. Men's: energy, libido, focus, sleep, strength-done, mood. Units follow `measurement_system`.

---

## 6. Over-Delivery: Worth Far More Than Its Price

- Personalized **root-cause explainer** per condition, plain language.
- **Phase progression** per condition (acne: barrier-repair → treatment → maintenance; weight: foundation → momentum → sustainability; men's: restore → build → optimize; PCOS: existing 3-phase arc).
- **"Why this works"** on every recommendation.
- **Local sourcing guide** — where to buy herbs/foods in *their* city and approximate local-currency cost. The geo-layer's highest-value payoff: advice actionable locally, today.
- **Localized swaps** — re-resolving the Region Profile re-localizes the plan if a user moves/travels.
- **Dual output** — printable PDF + structured live-module data from one plan object.
- **First-action quick win** for onboarding.
- **Shopping list auto-derived** from the localized meal plan, grouped by where to buy locally.

---

## 7. Validation, Safety & Quality Gates

1. **Schema validation per condition** — reject/repair output omitting required modules or including forbidden ones (workout in acne → fail → regenerate).
2. **Localization validation** — flag/regenerate if the plan contains ingredients/herbs not in the user's Region Profile, or wrong units.
3. **Global herb-safety gate** — every suggested herb must pass the master safety table for the user's profile + medications regardless of local availability (block St John's Wort with interacting meds; berberine ≤1500mg; ashwagandha ≤600mg).
4. **Compliance classifier pass** (shared with A/B challenger + member chat): no cure claims, guaranteed outcomes, unsafe dosages, pregnancy-contraindicated items without warning, disordered-eating-triggering targets → block + regenerate.
5. **Medical disclaimer** in every plan; per-condition red-flag referral triggers.
6. **Human review** of all four clinical knowledge bases AND each curated region pack before production. AI-generated region profiles ship `unreviewed`, queued for curation.
7. **Golden-output tests in CI** — fixtures crossing condition × region: *acne + Serbia* → skincare routine + Serbian foods + nettle/chamomile + NO workout; *PCOS + Kenya* → cycle sync + ugali/sukuma-wiki + local herbs + metric; *weight + USA* → macro targets in imperial + trend chart; *men's + Philippines* → recovery protocol + local foods. Assert correct modules, localization, units.

---

## 8. Build Sequence

| Phase | Scope | Gate |
|---|---|---|
| 1 | Generator refactor (`AbstractProtocolGenerator` + factory by condition); strip hardcoded workout AND Nigerian content; manifest system | PCOS generates via new path, region-neutral, no forced workout |
| 2 | Geo layer: location capture, `RegionProfile` resolver, `region_profiles` cache, 3–5 curated packs for top markets, AI-fallback, master herb-safety table | A PCOS plan localizes correctly for 2 different countries from one prompt |
| 3 | Author the three new condition prompts (acne, weight, men's) with region injection + schemas | Each funnel generates condition- and region-correct plans; golden tests pass |
| 4 | Structured per-condition logging wired to member tracker (unit-aware) | Correct tracker + units per funnel and region |
| 5 | Over-delivery layer (phase arcs, local sourcing guide, dual output, quick win) | Each plan maps 1:1 to sales-page promise, locally actionable |
| 6 | Localization + compliance + safety gates + golden CI matrix + human review | Red-team passes; clinical + regional sign-off recorded |

---

## Definition of Done

One engine; personalized on condition AND location:
- **Acne buyer in Serbia** → GlowClear: acne-type root cause, AM/PM skincare routine, anti-inflammatory meals from Serbian staples, skin herbs available in Serbia by local name (kopriva, kamilica), trigger + photo logging, metric units, **no workout block.**
- **PCOS buyer in Nairobi** → CycleSync: same insulin-resistance strategy via ugali/sukuma wiki/local proteins, locally-available herbs with Swahili names, cycle sync, metric units.
- **Weight buyer in the USA** → LeanFlow: macro targets in imperial, progressive movement, US-available foods, trend-smoothed tracking.
- **Men's buyer in the Philippines** → Vitale: T-supporting local nutrition, strength + recovery, local herbs, vitality logging.

Every plan is condition-correct, region-correct, omits irrelevant modules, passes safety + compliance gates, and over-delivers — with **no hardcoded country anywhere in the system.**

*Instruction set v2.0 (location-aware) — June 2026. For agentic execution against lxlbxl/1wellness (master). All medical taxonomies and curated region packs require qualified clinical/nutritional review before production. Herb-safety rules are global and must never be relaxed for local availability.*
