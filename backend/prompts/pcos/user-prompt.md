Generate a personalized 90-day CycleSync (PCOS) treatment plan for the following client:

## Client Information
- **Name:** {{NAME}}
- **Age:** {{AGE}}
- **PCOS Type:** {{PCOS_TYPE}}
- **Primary Symptoms:** {{SYMPTOMS}}
- **Goals:** {{GOALS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Activity Level:** {{ACTIVITY_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}
- **Stress Level:** {{STRESS_LEVEL}}

## Region Profile (for localization)
```json
{{REGION_PROFILE}}
```

## Instructions

Based on the client's PCOS type ({{PCOS_TYPE}}), create a comprehensive 90-day plan that includes:

1. **Personalized Root Cause Analysis** — Explain in plain language WHY their specific PCOS type developed, connecting their symptoms to the underlying mechanism.

2. **Phase Progression Arc** — 3 phases across 90 days with clear focus for each:
   - Phase 1 (Weeks 1-4): Foundation & Reset
   - Phase 2 (Weeks 5-8): Active Protocol
   - Phase 3 (Weeks 9-12): Optimization & Maintenance

3. **7-Day Meal Plan** — Using ONLY foods from their Region Profile. Each meal should include:
   - Meal name and description
   - Key ingredients (localized)
   - Specific benefit for their PCOS type

4. **Movement Protocol** — Type-appropriate exercise based on their PCOS type:
   - Insulin-Resistant: Strength training + walking
   - Inflammatory: Yoga, swimming, gentle movement
   - Adrenal: Gentle ONLY — yoga, walking, pilates. NO HIIT
   - Post-Pill: Moderate combination

5. **Herbal Protocol** — Herbs from their Region Profile that are:
   - Locally available (from region profile)
   - Safe for their profile (check safety table)
   - Include local name + English name + preparation + dosage

6. **Supplement Protocol** — Evidence-based supplements for their type with dosages

7. **Daily Routines** — Morning and evening routines with specific actions and timing

8. **Tracking Guidance** — Structured tracking items:
   - Cycle day, mood, energy, cravings, symptoms
   - Each with key, label, type, frequency

9. **Shopping List** — Foods organized by category (proteins, vegetables, carbs, fats, herbs)

## CRITICAL RULES
- Use ONLY foods and herbs from the Region Profile
- Use {{MEASUREMENT_SYSTEM}} units throughout
- Every recommendation must be specific to {{PCOS_TYPE}}
- Include both English and local herb names
- NO generic advice — everything must be personalized
- Return ONLY valid JSON matching the schema