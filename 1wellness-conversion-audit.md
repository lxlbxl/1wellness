# 1wellness Funnel Conversion Audit & Improvement Plan

**Repo:** lxlbxl/1wellness (master) · **Funnels audited:** /pcos/ (primary, deep-dive), /acne/, /weight/, /mens/ (pattern-checked — they share templates, so findings replicate)
**Funnel flow:** index → assessment → generating-plan → results → select-plan → checkout (Flutterwave) → thank-you
**Companion doc:** `1wellness-ab-engine-implementation.md` — items marked **[TEST]** become the first experiment backlog for the A/B engine; items marked **[FIX]** are defects to ship directly, no test needed.

---

## 0. Critical Fixes — Ship Before Any Optimization (Week 0)

These are bugs and trust-killers. Testing on top of them wastes traffic.

### 0.1 [FIX] Leaked NGN prices in `<title>` tags — ALL funnels
Found in every funnel directory:

| File | Current Title |
|---|---|
| `*/30-day-plan.html` | "30-Day … Starter Plan - **$18,000**" |
| `*/digital-plan.html` | "… Digital Protocol - **$5,000**" |
| `*/90-day-plan.html` | "90-Day … - **$45**" |

These are Naira prices left in titles on the USD deployment. Impact: they appear in browser tabs, Google SERP snippets, link previews on WhatsApp/social shares, and ad landing page review. A visitor seeing "$18,000" in the tab is gone instantly; Meta/Google ad review may flag price mismatch. **Remove all prices from title tags entirely** — titles should carry the value proposition, not pricing.

### 0.2 [FIX] Price inconsistency across the stack
- `js/data-manager.js`: PCOS plan $197 → $97; acne listed at $137 original in one place, README says $147.
- `pcos/results.html`: value stack totals $197, plan pages reference $45.
- README pricing table differs from data-manager on two products.

One source of truth: drive ALL price rendering from `DataManager.pricing` (which the A/B engine's `config` overrides already hook into). Audit every hardcoded `$` in funnel HTML and replace with DataManager bindings. Inconsistent pricing is the #1 silent checkout killer — visitors who notice assume scam.

### 0.3 [FIX] Production hygiene
- Remove `sampleUI.html` from public funnel dirs (it's live and indexed-crawlable).
- Add `<meta name="robots" content="noindex">` to results, generating-plan, select-plan, thank-you pages (prevents users landing mid-funnel from search with no session).
- Default admin credentials and dev CSRF bypass (flagged in the engine doc checklist) — confirm closed.

---

## 1. Landing Page (index.html) — Goal: maximize `assessment_start`

**Current state:** Strong foundation — "Discover Your PCOS Type / Get the Plan That Actually Works" is a solid type-quiz hook, 8 social proof references, decent design system (serif/forest/terracotta).

**Gaps & improvements:**

1.1 **[FIX] Zero guarantee mention on the landing page** (grep count: 0). The money-back guarantee exists in the product features but is invisible where trust is first built. Add a guarantee badge near the primary CTA.

1.2 **[TEST] Headline framing.** Control vs: (a) symptom-led "Irregular cycles, stubborn weight, exhausting fatigue — there's a reason, and it has a name", (b) outcome-led "Your hormones aren't broken. Your plan was." Quiz-type headlines win often, but symptom mirroring frequently beats them for cold Meta traffic. Judge on `assessment_start`.

1.3 **[TEST] CTA copy.** Generic "start" CTAs underperform possessive, low-commitment framings. Test "Take the 2-Minute Quiz" vs "Find My PCOS Type — Free" vs control. Always state the time cost — "2-minute" framing reliably lifts quiz starts.

1.4 **[TEST] Above-the-fold quiz embed.** Highest-leverage structural test: embed question 1 directly in the hero (answering it routes into assessment.html with answer prefilled). Removes one full click + page load. This is the single most consistent winner pattern in quiz funnels.

1.5 **[FIX] Add specific, named social proof.** Current proof is volume-based. Add 2–3 short testimonials with first name + age + specific outcome + timeframe ("cycle returned after 11 weeks") — specificity converts; volume claims alone read as decoration. Source from real OJG customer base; never fabricate.

## 2. Assessment (assessment.html) — Goal: maximize `assessment_complete` and email capture quality

**Current state:** ~12 questions, email captured mid-flow (~line 267 of 756 — roughly mid-assessment). Good: email is not left to the end.

2.1 **[FIX] Move email capture to the sunk-cost sweet spot — after the final question, before results, framed as delivery** ("Where should we send your PCOS Type results?"). Mid-flow capture leaks completers; end-gated capture converts 15–30% better because the user has invested all 12 answers. Critical for abandonment recovery (see 2.4).

2.2 **[TEST] Question count: 12 vs 8.** Each question is friction but also perceived personalization. Test a trimmed 8-question variant. Judge on `assessment_complete` AND downstream purchase rate — fewer questions can lift completion but cut perceived plan value. This is exactly the multi-metric case the bandit's RPV reward handles.

2.3 **[FIX] Progress mechanics.** Ensure: visible progress bar that starts at ~15% (not 0% — endowed progress effect), one question per screen, auto-advance on selection (no "Next" click for single-choice), back button available. Verify all four are true; auto-advance alone typically lifts completion 5–10%.

2.4 **[FIX] Abandonment recovery loop.** You already capture email mid-assessment and have `backend/cron/daily_nudge.php`. Wire: if `assessment_start` exists but no `assessment_complete` within 1 hour → trigger n8n email/WhatsApp "Your results are 4 questions away" with deep-link resuming at their last question (persist answers to localStorage + server on each answer, not on submit). This is recovered revenue at zero traffic cost.

2.5 **[TEST] Micro-commitment opener.** Variant where Q1 is trivially easy and self-selecting ("How long have you been dealing with these symptoms?") vs current opener. Easy openers lift full completion via commitment momentum.

## 3. Generating-Plan → Results — Goal: maximize `plan_select`

**Current state:** generating-plan.html provides an AI-personalization theater moment (good — keep it; perceived effort raises perceived value). Results page has the value stack ($45 + $35 + $27 + $90 = $197). Weak spots: only 2 social proof references, 1 guarantee mention.

3.1 **[FIX] Personalize the results headline with their data.** The single biggest results-page lever: echo their answers back. "Based on your answers, you're showing strong markers of **Insulin-Resistant PCOS** — found in 38% of women we assess." Use name if captured. Generic results pages waste the entire assessment's personalization equity.

3.2 **[FIX] Add type-matched testimonial.** One testimonial from someone with the *same PCOS type* directly under the diagnosis. "Same type as you" proof outperforms generic proof dramatically. Requires tagging testimonials by type in DataManager.

3.3 **[TEST] Results depth gating.** Control (full results shown) vs variant showing type + 2 insights free, with deeper protocol details revealed on plan page. Judge on RPV — gating can lift purchases or backfire on trust; only the bandit settles this.

3.4 **[FIX] During generating-plan animation, show step labels** ("Analyzing cycle patterns… Matching herbal protocols… Building your meal framework…") rather than a generic spinner. Labeled steps measurably increase trust in the output.

## 4. Select-Plan / Pricing — Goal: maximize `checkout_init` and RPV

**Current state:** Value-stack anchoring present ($197 total value). Found: **0** urgency/countdown elements, **0** order bumps, guarantee thin, 4 social proof refs.

4.1 **[FIX] Add the guarantee as a visual centerpiece** — badge + box adjacent to the CTA: "90-Day Money-Back Guarantee — if your symptoms haven't improved, full refund. Keep the materials." Risk reversal is the highest-ROI fix on this page, and it's currently nearly absent.

4.2 **[TEST] Price points.** The engine's `config` override makes this trivial: $97 control vs $87 vs $77 on PCOS 90-day. Judge strictly on **RPV, not conversion rate** — $77 may convert more but earn less. This is the flagship revenue-reward experiment.

4.3 **[TEST] Tier presentation.** If digital/30-day/90-day tiers are offered: test 3-tier good-better-best with the 90-day visually pre-selected ("Most Popular") vs single-offer page. Decoy-tier structures usually lift AOV; single-offer sometimes lifts total conversions. RPV decides.

4.4 **[FIX] Add an order bump at checkout** (none exists). Pre-checked-off checkbox above the pay button: WhatsApp Expert Access or a recipe pack at $17–27. Order bumps convert 20–40% of buyers with zero traffic cost — the fastest AOV lift available in this codebase. (You already deliver WhatsApp access post-purchase on thank-you.html — monetize it as a bump for non-buyers of the top tier instead of giving it uniformly.)

4.5 **[TEST] Honest urgency.** No countdown timers exist (good instinct — fake scarcity erodes a health brand). Test honest variants only: cohort framing ("This week's onboarding group closes Sunday — next group starts in 9 days") if operationally true. If it isn't true, skip urgency entirely; don't fabricate.

4.6 **[FIX] Payment trust strip** near the Flutterwave button: secure-payment icons, accepted cards, "Powered by Flutterwave", support email. International USD buyers facing an unfamiliar African payment processor need explicit reassurance — this is a known friction point for your exact gateway/audience combination.

## 5. Checkout & Post-Purchase

5.1 **[FIX] Checkout abandonment recovery.** Log `checkout_init` without `purchase` within 1 hour → n8n email: guarantee restatement + support contact + payment link. Recovers 5–15% of abandoners.

5.2 **[FIX] Thank-you page upsell.** WhatsApp access is presented (good). Add a one-click post-purchase offer (no re-entry of card if Flutterwave tokenization allows; otherwise a discounted "add the 30-day herbal refill now" link). Post-purchase is the highest-converting placement that exists; currently unmonetized.

5.3 **[FIX] Set purchase-event integrity.** Per the engine doc: purchases logged server-side from `webhook_pcos.php` etc. only, with session_id passed through Flutterwave metadata. Without this, every test above is judged on corrupt data.

## 6. Cross-Funnel & Measurement

6.1 **[FIX] Replicate every fix in §0–§5 across acne/weight/mens** — they share the template, and the title-tag bug confirms they share the defects.

6.2 **[FIX] Tag all elements named above with `data-exp` attributes** during the fix pass (headline, CTA, guarantee box, price block, testimonial slots). You're touching these files anyway; tagging now means the A/B engine tests them later with zero additional HTML changes.

6.3 **[FIX] Speed pass.** Funnel pages carry inline SVG noise filters, backdrop-blur, and (likely) CDN Tailwind + Alpine. Run Lighthouse on 3G throttle; for paid traffic, every second of LCP costs ~7% conversion. Quick wins: preload hero image, defer non-critical JS, self-host fonts.

---

## 7. Prioritized Roadmap (ICE-ordered)

| # | Item | Type | Stage | Impact | Effort | Priority |
|---|---|---|---|---|---|---|
| 1 | Remove leaked $18,000/$5,000 NGN titles | FIX | All | Critical | Trivial | **P0 — today** |
| 2 | Single price source of truth (DataManager) | FIX | All | High | Low | P0 |
| 3 | Guarantee badge on landing + pricing | FIX | 1, 4 | High | Low | P0 |
| 4 | Order bump at checkout | FIX | 4 | High (AOV) | Med | P1 |
| 5 | Personalized results headline + type-matched testimonial | FIX | 3 | High | Med | P1 |
| 6 | Email capture moved to pre-results + abandonment recovery (n8n) | FIX | 2 | High | Med | P1 |
| 7 | Payment trust strip at Flutterwave step | FIX | 4 | Med-High | Low | P1 |
| 8 | Checkout abandonment email | FIX | 5 | Med | Low | P1 |
| 9 | Price test $97/$87/$77 (RPV-judged) | TEST | 4 | High | Low* | P2 — engine wk 2 |
| 10 | Hero quiz embed | TEST | 1 | High | Med | P2 |
| 11 | Headline + CTA copy tests | TEST | 1 | Med | Low* | P2 |
| 12 | 12Q vs 8Q assessment | TEST | 2 | Med | Med | P3 |
| 13 | Tier presentation test | TEST | 4 | Med | Med | P3 |
| 14 | Speed pass | FIX | All | Med | Med | P3 |
| 15 | Post-purchase one-click upsell | FIX | 5 | Med (AOV) | High | P3 |

*Low effort once the A/B engine's element-override system is live.

**Sequencing logic:** Ship P0–P1 fixes first — they raise the baseline so the bandit converges on a healthy control, not a broken one. Launch the engine in parallel (engine doc Phases 1–2), then run P2 tests as the inaugural experiments: price test on /pcos/ pricing stage + headline test on /pcos/ landing stage simultaneously (different stages, different metrics — compliant with the one-experiment-per-stage concurrency rule).

**Expected compound effect:** P0–P1 fixes alone typically move quiz-funnel conversion 20–40% (guarantee visibility, price coherence, recovery loops, and order bump are each individually proven levers). The test backlog then compounds on top via the engine.

---

*Audit version 1.0 — June 2026. All [TEST] items map to the experiment schema in `1wellness-ab-engine-implementation.md` §4–§7.*
