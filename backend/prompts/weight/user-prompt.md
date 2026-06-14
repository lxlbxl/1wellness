Generate a personalized 90-day LeanFlow (Weight Management) treatment plan for the following client:

## Client Information
- **Name:** {{NAME}}
- **Age:** {{AGE}}
- **Gender:** {{GENDER}}
- **Weight Type:** {{WEIGHT_TYPE}}
- **Current Weight:** {{CURRENT_WEIGHT}} {{WEIGHT_UNIT}}
- **Goal Weight:** {{GOAL_WEIGHT}} {{WEIGHT_UNIT}}
- **Height:** {{HEIGHT}} {{HEIGHT_UNIT}}
- **Primary Symptoms:** {{SYMPTOMS}}
- **Goals:** {{GOALS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Activity Level:** {{ACTIVITY_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}
- **Stress Level:** {{STRESS_LEVEL}}
- **Eating History:** {{EATING_HISTORY}}

## Region Profile (for localization)
```json
{{REGION_PROFILE}}
```

## Instructions

Based on the client's weight type ({{WEIGHT_TYPE}}), create a comprehensive 90-day plan that includes:

1. **Personalized Root Cause Analysis** — Explain WHY weight management is hard for them. This is NOT about willpower — explain the metabolic, hormonal, or behavioral mechanisms at play. Be empathetic and anti-shame.

2. **Phase Progression Arc** — 3 phases across 90 days:
   - Phase 1 (Weeks 1-4): Foundation — build habits, reset metabolism
   - Phase 2 (Weeks 5-8): Momentum — accelerate progress
   - Phase 3 (Weeks 9-12): Sustainability — make it stick for life

3. **Macro Targets** — Calculate based on their profile:
   - Daily calorie target (safe, sustainable — NO crash diet)
   - Protein, fat, carb targets in grams
   - Notes on timing and distribution

4. **7-Day Macro-Targeted Meal Plan** — Using ONLY foods from their Region Profile:
   - Each meal with macros noted
   - Localized, culturally familiar foods
   - Portion guidance using their measurement system

5. **Progressive Movement Plan (CORE)** — Type-appropriate:
   - Insulin-Resistant: Strength training + walking after meals
   - Stress-Driven: Gentle movement first, build gradually
   - Hormonal: Moderate, refer for labs
   - Habit/Lifestyle: Movement they enjoy, build NEAT
   - Progressive across 3 phases

6. **Herbal Protocol** — Metabolic herbs from their Region Profile:
   - Local name + English name
   - Dosage and timing
   - Safety check

7. **Supplement Protocol** — Evidence-based:
   - Berberine, chromium, inositol (for insulin-resistant)
   - Adaptogens (for stress-driven)
   - Thyroid support nutrients (for hormonal)

8. **Habit Formation Protocol** — Specific habits with:
   - Cue → Action → Reward loops
   - Implementation intentions
   - Progressive habit stacking

9. **Plateau Response Protocol** — What to do when weight stalls:
   - NOT "eat less" — smarter adjustments
   - Check stress, sleep, hormones
   - When to adjust macros vs when to hold steady

10. **Sleep & Recovery Protocol** — Sleep's critical role in metabolic health

11. **Tracking Guidance** — Structured:
    - Weight (daily, smoothed trend) — use their units (kg/lb)
    - Measurements (weekly)
    - Energy (1-10)
    - Habits completed
    - Non-scale victories

12. **Shopping List** — Foods organized by category

## Safety Rules
- NO crash deficits (<1200 kcal women, <1500 kcal men)
- NO rapid-loss promises
- If disordered eating signals present → REFERRAL, not targets
- Medical referral for suspected thyroid issues
- Always include medical disclaimer

## CRITICAL RULES
- Use ONLY foods and herbs from the Region Profile
- Use {{MEASUREMENT_SYSTEM}} units throughout
- Every recommendation must be specific to {{WEIGHT_TYPE}}
- Movement protocol IS required
- Include non-scale victories tracking
- Return ONLY valid JSON matching the schema