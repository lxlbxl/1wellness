Generate a personalized 90-day Vitale (Men's Vitality) treatment plan for the following client:

## Client Information
- **Name:** {{NAME}}
- **Age:** {{AGE}}
- **Men's Profile:** {{MENS_PROFILE}}
- **Primary Symptoms:** {{SYMPTOMS}}
- **Goals:** {{GOALS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Activity Level:** {{ACTIVITY_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}
- **Stress Level:** {{STRESS_LEVEL}}
- **Training Experience:** {{TRAINING_EXPERIENCE}}

## Region Profile (for localization)
```json
{{REGION_PROFILE}}
```

## Instructions

Based on the client's profile ({{MENS_PROFILE}}), create a comprehensive 90-day plan that includes:

1. **Personalized Vitality Assessment** — Direct assessment of where they are and why. No fluff — explain the mechanisms (blood sugar, cortisol, sleep, training) affecting their energy and performance.

2. **Phase Progression Arc** — 3 phases across 90 days:
   - Phase 1 (Weeks 1-4): Restore — fix sleep, reduce stress, build foundation
   - Phase 2 (Weeks 5-8): Build — progressive training, optimize nutrition
   - Phase 3 (Weeks 9-12): Optimize — peak performance, fine-tune protocols

3. **7-Day Vitality-Supporting Meal Plan** — Using ONLY foods from their Region Profile:
   - Protein-focused (1.6-2.2g/kg for body composition goals)
   - Blood-sugar stabilizing meals
   - Localized, culturally familiar foods
   - Include testosterone-supporting nutrients (zinc, vitamin D, healthy fats)

4. **Strength/Movement Protocol (CORE)** — Progressive and phase-based:
   - Low Energy: Build foundation, progressive overload
   - Low-T Markers: Compound movements, heavy lifting
   - Stress/Burnout: Gentle initially, build gradually
   - Body Composition: Periodized training
   - Include: exercise, sets, reps, progression notes

5. **Sleep & Recovery Protocol (CORE)** — Non-negotiable for men's health:
   - Target hours (7-9)
   - Sleep hygiene checklist
   - Wind-down routine
   - Environment optimization

6. **Herbal Protocol** — Vitality herbs from their Region Profile:
   - Local name + English name
   - Dosage and timing
   - Safety check (especially for hormone-related herbs)

7. **Supplement Protocol** — Evidence-based:
   - Zinc, vitamin D, magnesium (foundational)
   - Adaptogens for stress (ashwagandha, rhodiola)
   - Performance supplements if appropriate (creatine)

8. **Stress/Adaptogen Protocol** — If stress/burnout profile:
   - Cortisol management techniques
   - Adaptogen stacking
   - Lifestyle modifications

9. **Tracking Guidance** — Structured:
    - Energy (1-10)
    - Libido
    - Focus (1-10)
    - Sleep hours
    - Sleep quality
    - Strength session done (boolean)
    - Mood (1-10)

10. **Shopping List** — Foods organized by category

## Safety Guardrails
- REFER for labs before any hormone-related claims
- NO anabolic/steroid/TRT advice
- Cardiac risk flag for older/sedentary users before intense protocols
- NO libido drug advice
- Ashwagandha: max 600mg/day
- Always include medical disclaimer

## CRITICAL RULES
- Use ONLY foods and herbs from the Region Profile
- Use {{MEASUREMENT_SYSTEM}} units throughout
- Every recommendation must be specific to {{MENS_PROFILE}}
- Strength protocol IS required
- Sleep protocol IS required
- Frame everything around vitality, energy, performance
- Return ONLY valid JSON matching the schema