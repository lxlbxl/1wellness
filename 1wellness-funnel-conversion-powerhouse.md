# 1wellness — Turn All Funnels Into a Conversion Powerhouse

**Repo:** lxlbxl/1wellness (master)
**Audience:** Coding agent (Claude Code / OpenClaw)
**Applies to:** All four funnels — pcos (CycleSync), acne (GlowClear), weight (LeanFlow), mens (Vitale). They share templates, so every change is built once with condition-specific content injected from config.
**Pages in scope:** `{funnel}/index.html`, `assessment.html`, `results.html`, `select-plan.html`, plus `thank-you.html` for post-purchase. Primary focus: **results.html and select-plan.html** (the pitch + the ask).
**Companion docs:** `1wellness-conversion-audit.md`, `1wellness-ab-engine-implementation.md`, `1wellness-treatment-plan-engine-v2-location-aware.md`, `1wellness-hardening-and-member-experience.md`

> **Core principle:** convert the SKEPTIC, not the already-sold. Every change must move one of three levers: **(1) prove it's real**, **(2) prove it fits ME**, **(3) remove the risk** — so the answer becomes an obvious, guilt-free "yes." No fake scarcity, no guilt pressure. This is a health brand for anxious users; manipulation generates refunds and chargebacks. The target emotion is *"this is a no-brainer,"* never *"I'll feel bad if I don't."*

---

## PHASE 0 — Critical Bug Fixes (Ship Before Anything; Outside Any A/B Test)

These are live defects costing conversions right now on EVERY funnel. They are not tests — just fix them.

### 0.1 Unify social proof to ONE true number across the whole funnel
**Defect (confirmed all funnels):** contradictory counts on the same journey — pcos: "12 women" (index) → "database of **10+** women" (results) → "**500+** Women" (results/select). acne: "500+ skin profiles". The "10+" reads as *we've barely helped anyone* directly beside "500+", which reads as a lie.
**Fix:**
1. Define one honest, defensible proof number per funnel in `js/config.js` (`socialProof.count`, `socialProof.label`) — e.g. `{ count: "500+", label: "women" }` (pcos/weight), `{ count: "500+", label: "people" }` or `"skin profiles"` (acne), `{ count: "500+", label: "men" }` (mens).
2. Replace ALL hardcoded proof strings on index/results/select-plan with this single binding.
3. **Never render "10+".** Kill the "database of X" phrasing entirely (it implies a tiny sample at the diagnosis moment). Replace with confident framing: "Your answers matched against {count} {label} we've helped."
4. If you cannot honestly defend 500+, use the real number — but make it consistent. One true number everywhere beats an impressive inconsistent one.

### 0.2 Fix blank template variables on results.html (all funnels)
**Defect:** live pages render "To reverse **[blank]**, you need a holistic..." and "Without a **[blank]**" — the condition/type variable isn't interpolating. Blank merge fields on the "personalized for you" page are the most trust-destroying thing in the funnel.
**Fix:**
1. Locate every templated variable on results.html ({{TYPE}}, {{CONDITION}}, symptom echoes) and ensure interpolation runs before render.
2. Add non-empty fallbacks so a missing variable NEVER renders blank (e.g. fallback to the condition's generic name).
3. Add a guard: if the critical personalization variable (their type) is missing, route back to assessment rather than show a broken results page.

### 0.3 Align the guarantee to the program length (all funnels)
**Defect:** guarantee shows "30-Day Money Back" while the program is a **90-Day** transformation. A 30-day guarantee on a 90-day plan is a logic hole skeptics catch.
**Fix:** make it a **90-day** (minimum) guarantee everywhere — results, select-plan, checkout. One guarantee string in config, bound everywhere. (See 3.1 for the upgraded wording.)

### 0.4 Remove leaked currency in titles + price inconsistency
Per the conversion-audit doc: strip "$18,000 / $5,000" NGN values from `<title>` tags on all `*/30-day-plan.html` and `*/digital-plan.html`; drive every price from `DataManager.pricing` (single source). Do this in the same pass.

**Phase 0 acceptance:** grep every funnel for "10+", blank `[ ]` merge artifacts, "30-Day", and NGN title prices — all return clean. One proof number, one guarantee length, one price source per funnel.

---

## PHASE 1 — Results Page: From "Here's Your Type" to "This Was Built For Me, And It's Real"

The results page is the pitch. Its job: convert the assessment's personalization equity into belief. Build these blocks (config-driven, condition-aware) into `results.html` for all funnels.

### 1.1 Personalized diagnosis header (fix + amplify)
After 0.2, make the type reveal the hero moment: "Based on your answers, you're showing strong markers of **{type}**" + a one-line prevalence stat ("the most common driver — affects ~70% of women with PCOS") to normalize and validate. Echo back 2–3 of THEIR specific symptoms from the assessment so it reads hand-written.

### 1.2 Empathy beat BEFORE the pitch (new — disarms skeptics)
Insert a short, condition-specific empathy block before any selling: acknowledge the frustration and remove self-blame. PCOS example: "If you've been dismissed by doctors, told to 'just lose weight,' or wasted money on supplements that did nothing — that wasn't your fault. Here's what's actually been happening in your body." Each funnel gets its own (acne: dismissed/tried-every-product; weight: every-diet-failed/blamed-for-willpower; mens: told-it's-just-age). Pull from the brand bible voice. This single block lowers defensiveness more than any badge.

### 1.3 The mechanism — "why this works when other things didn't" (new)
A skeptic's #1 objection is "I've tried things before." Answer it explicitly: a 3-step "why generic advice failed you / why type-specific works" block. "Generic PCOS advice treats all PCOS the same. But insulin-resistant PCOS needs the opposite approach to adrenal PCOS. Yours is {type}-specific — that's the difference." Make the personalization the logical reason for confidence.

### 1.4 Testimonials — the single biggest missing converter (new)
**Currently ZERO testimonials on results AND select-plan across all funnels.** Add a testimonials system:
1. Config-driven testimonial store, **tagged by funnel AND by type** (so an insulin-resistant viewer sees an insulin-resistant success story — "same as you" proof outconverts generic proof dramatically).
2. Each testimonial: first name, age, specific outcome, **timeframe** ("cycle returned in week 9"), and ideally a photo or before/after where consentfully available.
3. Show 2–3 type-matched on results, a different 3–4 on select-plan.
4. **Only real, consented testimonials.** Never fabricate. If you don't have enough yet, use honestly-framed outcome data or founder/practitioner credibility instead, and prioritize collecting real ones immediately (post-purchase + member milestones are natural capture points).

### 1.5 Cost-of-inaction framing (ethical urgency, new)
Honest loss-aversion specific to each condition — what continuing as-is costs: another year of {symptom}, more money on things that don't work, symptoms compounding. PCOS/weight: insulin resistance worsens untreated, fertility windows. acne: scarring becomes permanent. mens: energy/vitality decline accelerates. State it factually, not as a threat. This is true and ethical — unlike a fake countdown.

### 1.6 Value stack (move up from buried)
Surface the value-stack (meals/herbal/movement/tracking with honest per-item value, totalling $197) prominently with a clear "total value" line — sets up the price reveal on the next page.

### 1.7 Strong, single CTA to select-plan
One clear primary action ("See My Plan Options" / "Get My {type} Protocol"), repeated after the testimonials. Carry the type/personalization through the click (don't revert to generic on the next page — see 2.x).

---

## PHASE 2 — Select-Plan Page: From "Pick a Format" to "Obvious Yes"

**Reframe the entire page.** Today it's a post-decision menu ("Choose Your Preferred Format"). It must first re-earn the yes, then present format as abundance.

### 2.1 Keep the personalization thread alive
The page currently reverts to generic ("the exact same hormone-balancing meal plans"). Instead: "Your **{type}** protocol is ready, {name}." Bind the type and name from the assessment/session so the decision page feels continuous with the diagnosis, not a generic catalog.

### 2.2 Make the guarantee a visible centerpiece (highest-ROI change)
Upgrade from the buried "Safe, encrypted, and guaranteed" line to a bold, boxed, badge-backed guarantee adjacent to EVERY CTA and in the order summary:
> **"Try the full 90-day protocol. If your {condition} symptoms haven't improved, email us for a full refund — and keep everything. The only risk is staying where you are."**
Risk reversal is what converts "maybe." Make it impossible to miss.

### 2.3 Value stack vs price — build the no-brainer gap
Show everything included with honest itemized value, total it visibly ("Total value: $197"), then reveal the price as a fraction ("Today: $97"). The visible gap between value and price IS the no-brainer feeling. Bind price from DataManager (single source, A/B-engine `config`-override compatible).

### 2.4 Reframe format choice as abundance, not a fork
Currently "PDF or Portal" feels like *less* (choose one). Reframe: Portal is the hero (interactive, updates, tracking, community, AI specialist); the PDF is a *bonus you also get*. Surface the buried "✓ Both options include the full 90-day protocol" as a headline benefit. Abundance ("you get more than you expected") converts better than either/or.

### 2.5 FAQ / objection-handling block (new — currently absent)
Add a 6–8 item FAQ answering the REAL hesitations per condition:
- "I've tried everything — how is this different?" → type-specific mechanism (1.3).
- "Is this medical advice?" → honest wellness-guide framing + "works alongside your doctor".
- "I'm on medication — is this safe?" → consult-your-doctor + the plan flags interactions.
- "What exactly do I get / how fast will I see results?" → honest timeline, no false promises.
- "What if it doesn't work for me?" → restate the 90-day guarantee.
- "Is my payment secure?" → Flutterwave trust + encryption.
Each answer closes a doubt loop a skeptic is silently holding.

### 2.6 Payment trust strip at the checkout step (new)
Near the Flutterwave button: secure-payment icons, accepted cards, "Powered by Flutterwave", encryption note, support email. International USD buyers facing an unfamiliar processor need explicit reassurance — a known friction point for this gateway/audience.

### 2.7 Order bump (new — fastest AOV lift in the codebase)
A pre-checkout, opt-in checkbox above the pay button: a condition-relevant add-on at $17–27 (e.g. WhatsApp Expert Access, recipe/herbal pack). Order bumps convert 20–40% of buyers at zero extra traffic. (You already give WhatsApp access post-purchase on thank-you — monetize it here as a bump instead.)

### 2.8 Honest urgency only (replace vague "Offer Active")
"Offer Active" / "Assessment Offer Active" is meaningless and ignored. Either make it concrete AND true ("Your assessment unlocks launch pricing for 24h" — only if real, enforced server-side) or remove it. Never fake a countdown on a health brand.

---

## PHASE 3 — Cross-Funnel, Post-Purchase & Measurement

### 3.1 Replicate everything across all four funnels via config
All four share templates. Build each block ONCE, inject condition-specific content (audience noun women/men/people, type taxonomy, empathy copy, testimonials, cost-of-inaction, FAQ) from `js/config.js` + the testimonial store. Acceptance: changing one funnel's content is a config edit, not an HTML fork.

### 3.2 Thank-you page (post-purchase conversion continues)
Add a one-click post-purchase upsell (next-tier or refill) and make the WhatsApp access feel like a premium welcome, not the first time it's mentioned. Reinforce the guarantee so buyer's remorse doesn't trigger refunds.

### 3.3 Tag everything for the A/B engine
While editing, add `data-exp` attributes to every block above (headline, empathy, mechanism, testimonials, guarantee box, value stack, price, CTA, FAQ, order bump). Per the A/B engine doc, this lets you test variations later with zero additional HTML changes.

### 3.4 What to fix vs what to test
- **Fix outside any test (Phase 0–2 structural blocks):** bugs, guarantee, testimonials existence, FAQ, trust strip, order bump, personalization thread. These are known wins — don't waste traffic "testing" whether a guarantee helps.
- **Test via the engine (after fixes ship):** headline framing, CTA wording, price points ($97/$87/$77, RPV-judged), empathy-copy variants, testimonial selection, order-bump offer/price. A broken control must never enter an experiment — that's why Phase 0 ships first.

---

## PHASE 4 — Speed & Polish (conversion tax)
- Lighthouse pass on 3G throttle; every second of LCP ≈ 7% conversion lost on paid traffic.
- Preload hero image, self-host fonts (currently Google Fonts CDN), defer non-critical JS, purge Tailwind (currently CDN, unpurged).
- Anti-flicker on any A/B override injection (per engine doc).
- Mobile-first QA: most paid traffic is mobile; verify the value stack, guarantee box, testimonials, and order bump all render cleanly on small screens.

---

## Build Sequence & Acceptance

| Phase | Scope | Acceptance gate |
|---|---|---|
| **0** | Bug fixes: unify proof number, fix blank vars, align guarantee, strip NGN titles/price | Grep clean across all 4 funnels; no "10+", no blank merge fields, one guarantee length, one price source |
| **1** | results.html: personalized diagnosis, empathy beat, mechanism, testimonials, cost-of-inaction, value stack, single CTA | Skeptic-converter blocks live on all 4; testimonials type-matched; no generic-revert |
| **2** | select-plan.html: keep personalization, centerpiece guarantee, value-vs-price, abundance reframe, FAQ, trust strip, order bump, honest urgency | All 4 funnels; order bump functional; guarantee adjacent to every CTA |
| **3** | Config-driven replication, thank-you upsell, `data-exp` tagging, fix/test split | One config edit themes a funnel; tags present for engine |
| **4** | Speed + mobile polish | Lighthouse mobile pass; clean mobile render |

---

## Definition of Done

A skeptic landing on any funnel's results page: gets a diagnosis that visibly reflects THEIR answers (no blank fields), feels understood (empathy beat), understands WHY this works when past attempts didn't (mechanism), sees proof from someone with their exact type (testimonials), and grasps the cost of doing nothing. On select-plan: the personalization carries through, a bold 90-day guarantee makes the risk near-zero, an itemized value stack dwarfs the price, the format choice feels like a bonus not a fork, an FAQ closes their last doubts, a trust strip reassures payment, and an order bump lifts AOV. **One honest proof number, one aligned guarantee, zero fabricated urgency, zero guilt pressure** — the purchase reads as an obvious, safe, value-packed "yes" across all four funnels, driven entirely from config.

*Instruction set v1.0 — June 2026. For agentic execution against lxlbxl/1wellness (master). Use only real, consented testimonials and truthful urgency; this is a health brand and manipulation is both unethical and a refund driver.*
