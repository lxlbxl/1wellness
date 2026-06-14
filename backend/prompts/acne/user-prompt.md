Generate a personalized 90-day GlowClear (Acne) treatment plan for the following client:

## Client Information
- **Name:** {{NAME}}
- **Age:** {{AGE}}
- **Gender:** {{GENDER}}
- **Acne Type:** {{ACNE_TYPE}}
- **Primary Symptoms:** {{SYMPTOMS}}
- **Affected Areas:** {{AFFECTED_AREAS}}
- **Goals:** {{GOALS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Current Skincare:** {{CURRENT_SKINCARE}}
- **Stress Level:** {{STRESS_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}

## Region Profile (for localization)
```json
{{REGION_PROFILE}}
```

## Instructions

Based on the client's acne type ({{ACNE_TYPE}}), create a comprehensive 90-day plan that includes:

1. **Personalized Root Cause Analysis** — Explain WHY their specific acne type developed. Connect their symptoms to the underlying mechanism. Be empathetic — acne carries emotional weight.

2. **Phase Progression Arc** — 3 phases across 90 days:
   - Phase 1 (Weeks 1-4): Barrier Repair — calm inflammation, repair skin barrier
   - Phase 2 (Weeks 5-8): Active Treatment — target the root cause
   - Phase 3 (Weeks 9-12): Maintenance — sustain results, prevent relapse

3. **AM/PM Skincare Routine** — Type-specific:
   - Hormonal: Focus on gentle cleansing, niacinamide, spot treatment
   - Inflammatory: Barrier repair, anti-inflammatory ingredients
   - Comedonal: BHA/salicylic exfoliation, non-comedogenic products
   - Fungal: Anti-fungal cleanser, avoid oils that feed malassezia
   - Stress: Gentle barrier support, cortisol management
   - Include: step, product type, key ingredient, why it helps

4. **7-Day Anti-Inflammatory Meal Plan** — Using ONLY foods from their Region Profile:
   - Focus on anti-inflammatory foods
   - Include omega-3 sources, zinc-rich foods
   - Note any common triggers to watch

5. **Herbal Protocol** — Skin-clearing herbs from their Region Profile:
   - Include local name + English name
   - Preparation method
   - Dosage and timing
   - Safety check against their medications

6. **Supplement Protocol** — Evidence-based for acne:
   - Zinc, omega-3, DIM (for hormonal), probiotics
   - Dosages and timing

7. **Trigger Elimination Protocol** — Common triggers and elimination plan

8. **Photo Protocol** — Baseline + progress photos:
   - How to take consistent photos
   - Lighting, angle, frequency
   - Tracking clarity over time

9. **Flare Response Playbook** — What to do when breaking out:
   - Immediate actions
   - What NOT to do
   - When to see a dermatologist

10. **Tracking Guidance** — Structured:
    - Skin photo (daily/weekly)
    - Clarity score (1-10)
    - Flare count
    - Trigger checklist (dairy, sugar, sleep, stress, new products)

11. **Shopping List** — Foods, skincare products, herbs, supplements

## CRITICAL: NO WORKOUT MODULE
Do NOT include a daily workout/exercise block. Optional gentle stress-movement only (walking, restorative yoga).

## CRITICAL RULES
- Use ONLY foods and herbs from the Region Profile
- Use {{MEASUREMENT_SYSTEM}} units throughout
- Every recommendation must be specific to {{ACNE_TYPE}}
- Distinguish fungal vs bacterial if relevant
- Include patch-test warnings for topicals
- Flag pregnancy-unsafe actives (retinoids, high-dose salicylic)
- NO prescription drug advice
- Return ONLY valid JSON matching the schema