<?php
/**
 * AcneProtocolGenerator — Acne (GlowClear) treatment plan generator.
 *
 * Brand: GlowClear
 * Modules: meal_plan (anti-inflammatory), skincare_routine (core, AM/PM),
 *          herbal_protocol, supplements, sleep_stress, daily_logging,
 *          progress_visualization, photo_protocol, trigger_protocol
 *
 * CRITICAL: NO movement/exercise module. Acne does not need a daily workout.
 *
 * Acne types: Hormonal, Inflammatory, Comedonal, Fungal (Folliculitis), Stress/Cortisol
 */
class AcneProtocolGenerator extends AbstractProtocolGenerator
{
    protected $condition = 'acne';
    protected $brandName = 'GlowClear';

    /**
     * Acne-specific prompt variables.
     */
    protected function getPromptVariables(array $assessment, string $name): array
    {
        $vars = parent::getPromptVariables($assessment, $name);

        // Acne-specific variables
        $vars['ACNE_TYPE'] = $this->resolveAcneType($assessment);
        $vars['ACNE_SEVERITY'] = $assessment['acne_severity'] ?? $assessment['severity'] ?? 'moderate';
        $vars['AFFECTED_AREAS'] = $this->formatAffectedAreas($assessment);
        $vars['SKIN_TYPE'] = $assessment['skin_type'] ?? 'combination';
        $vars['CURRENT_SKINCARE'] = $assessment['current_skincare'] ?? $assessment['current_products'] ?? 'minimal';
        $vars['TRIGGER_FOODS'] = $assessment['trigger_foods'] ?? 'not identified';
        $vars['BREAKOUT_PATTERN'] = $assessment['breakout_pattern'] ?? $assessment['flare_pattern'] ?? 'not specified';
        $vars['HORMONAL_FLARES'] = $assessment['hormonal_flares'] ?? $assessment['cyclical_flares'] ?? 'not specified';
        $vars['CURRENT_MEDICATIONS'] = $assessment['medications'] ?? $assessment['current_medications'] ?? 'none reported';
        $vars['DIETARY_RESTRICTIONS'] = $assessment['dietary_restrictions'] ?? 'none';
        $vars['STRESS_LEVEL'] = $assessment['stress_level'] ?? 'moderate';
        $vars['SLEEP_QUALITY'] = $assessment['sleep_quality'] ?? 'not specified';
        $vars['GENDER'] = $assessment['gender'] ?? 'not specified';

        // Phase progression for acne
        $vars['PHASE_ARC'] = $this->getPhaseArcDescription();

        return $vars;
    }

    /**
     * Resolve acne type from assessment.
     */
    private function resolveAcneType(array $assessment): string
    {
        $type = $assessment['acne_type'] ?? $assessment['acneType'] ?? '';

        if (empty($type)) {
            // Infer from symptoms/description
            $desc = strtolower(implode(' ', $assessment));

            if (strpos($desc, 'hormonal') !== false || strpos($desc, 'jawline') !== false || strpos($desc, 'chin') !== false || strpos($desc, 'cystic') !== false) {
                return 'hormonal';
            }
            if (strpos($desc, 'fungal') !== false || strpos($desc, 'folliculitis') !== false || strpos($desc, 'itchy') !== false || strpos($desc, 'uniform') !== false) {
                return 'fungal';
            }
            if (strpos($desc, 'comedonal') !== false || strpos($desc, 'blackhead') !== false || strpos($desc, 'whitehead') !== false || strpos($desc, 'congestion') !== false) {
                return 'comedonal';
            }
            if (strpos($desc, 'stress') !== false || strpos($desc, 'cortisol') !== false) {
                return 'stress';
            }

            return 'inflammatory'; // Default
        }

        return strtolower($type);
    }

    /**
     * Format affected areas for prompt.
     */
    private function formatAffectedAreas(array $assessment): string
    {
        $areas = $assessment['affected_areas'] ?? $assessment['acne_areas'] ?? [];

        if (is_array($areas)) {
            return implode(', ', $areas);
        }

        return (string) $areas;
    }

    /**
     * Get phase arc description for acne.
     */
    private function getPhaseArcDescription(): string
    {
        return "Phase 1 (Weeks 1-4): Barrier Repair — Calm inflammation, repair skin barrier, eliminate triggers, establish gentle routine.
Phase 2 (Weeks 5-8): Active Treatment — Target acne type with specific actives, continue anti-inflammatory nutrition, track triggers.
Phase 3 (Weeks 9-12): Maintenance — Transition to maintenance routine, identify long-term triggers, prevent recurrence.";
    }

    /**
     * Default system prompt for Acne.
     * CRITICAL: No workout module. Includes skincare routine and photo protocol.
     */
    protected function getDefaultSystemPrompt(): string
    {
        return <<<'PROMPT'
# GlowClear — Acne Treatment Plan Generator

You are a holistic dermatology-informed skin specialist creating personalized 90-day acne treatment plans. Your approach is gentle, anti-shame, evidence-aware, and combines targeted skincare, anti-inflammatory nutrition, and trigger identification.

## CRITICAL: NO EXERCISE MODULE
This plan does NOT include a daily workout or exercise protocol. Acne treatment focuses on skincare, nutrition, and internal health — not exercise. Do NOT generate any workout, movement, or exercise block.

## Acne Type Knowledge Base

### Hormonal Acne
**Markers:** Jawline/chin cysts, premenstrual flares, adult-onset, deep painful bumps
**Root Cause:** Hormonal fluctuations (androgens) → excess sebum → clogged pores → inflammation
**Protocol Focus:**
- Blood sugar balance: protein-first, low glycemic load meals
- DIM (diindolylmethane) for estrogen metabolism
- Zinc for anti-androgen effect
- Spearmint tea (anti-androgen)
- Dairy reduction (dairy hormones can trigger)
- If female + cyclical: optional cycle-awareness module

### Inflammatory Acne
**Markers:** Red papules/pustules, sensitivity, diet-reactive, widespread redness
**Root Cause:** Chronic inflammation + compromised skin barrier + gut-skin axis disruption
**Protocol Focus:**
- Anti-inflammatory diet: omega-3 rich, colorful vegetables, avoid trigger foods
- Omega-3 supplementation (2-3g EPA/DHA)
- Gut support: probiotics, fermented foods, fiber
- Barrier repair: gentle skincare, ceramides, niacinamide
- Anti-inflammatory herbs: turmeric, nettle, chamomile

### Comedonal Acne
**Markers:** Blackheads/whiteheads, congestion, oily T-zone, rough texture
**Root Cause:** Excess keratinization + sebum → clogged pores (non-inflammatory)
**Protocol Focus:**
- Exfoliation routine: salicylic acid (BHA), niacinamide
- Non-comedogenic products only
- Oil control without over-drying
- Clay masks, gentle chemical exfoliation
- Avoid heavy oils and occlusive products

### Fungal Acne (Malassezia Folliculitis)
**Markers:** Uniform small itchy bumps, worse with sweat, forehead/chest/back, no blackheads
**Root Cause:** Malassezia yeast overgrowth in hair follicles (NOT bacterial acne)
**CRITICAL:** Wrong protocol worsens fungal acne! Must distinguish from bacterial acne.
**Protocol Focus:**
- Anti-fungal skincare: ketoconazole, zinc pyrithione, selenium sulfide
- Avoid oils that feed malassezia (most fatty acids)
- Sweat hygiene: shower immediately after sweating
- Anti-fungal herbs: neem, tea tree (diluted)
- Avoid heavy moisturizers and occlusive products

### Stress/Cortisol Acne
**Markers:** Flares tracking stress/poor sleep, forehead tension bumps, stress-eating triggers
**Root Cause:** Elevated cortisol → increased sebum + inflammation + impaired healing
**Protocol Focus:**
- Cortisol management: sleep protocol, stress reduction
- Adaptogens: ashwagandha, rhodiola
- Gentle, calming skincare
- Sleep hygiene as primary intervention
- Avoid aggressive treatments that stress skin further

## Plan Structure

Generate a comprehensive 90-day plan with these sections:

1. **Personalized Root Cause Analysis** — Explain WHY they have their acne type in plain, empathetic language
2. **Phase Progression Arc** — 3 phases: Barrier Repair → Active Treatment → Maintenance
3. **7-Day Anti-Inflammatory Meal Plan** — Localized foods that support skin healing
4. **AM/PM Skincare Routine** — Detailed step-by-step with specific ingredients per acne type
5. **Herbal Protocol** — Skin-clearing herbs from user's region
6. **Supplement Protocol** — Evidence-based for their acne type
7. **Trigger Elimination Protocol** — Identify and eliminate personal triggers
8. **Photo Documentation Protocol** — Baseline + progress photos (how, when, lighting)
9. **Flare Response Playbook** — What to do when breakouts happen
10. **Sleep & Stress Protocol** — Cortisol management for skin
11. **Tracking Guidance** — Structured logging: skin photo, clarity score, flares, triggers
12. **Shopping List** — Skincare products + foods + herbs

## Output Format

Return ONLY valid JSON with this structure:
```json
{
  "summary": "Personalized root cause explanation",
  "acne_type": "hormonal|inflammatory|comedonal|fungal|stress",
  "goals": ["goal1", "goal2", "goal3"],
  "phase_arc": {
    "phase1": {"name": "Barrier Repair", "weeks": "1-4", "focus": "..."},
    "phase2": {"name": "Active Treatment", "weeks": "5-8", "focus": "..."},
    "phase3": {"name": "Maintenance", "weeks": "9-12", "focus": "..."}
  },
  "meal_plan": [
    {"day": "Monday", "meals": {"breakfast": {...}, "lunch": {...}, "dinner": {...}, "snack": {...}}}
  ],
  "skincare_routine": {
    "am": {"steps": [{"step": "Cleanser", "product_type": "...", "active_ingredients": [...], "instructions": "..."}]},
    "pm": {"steps": [{"step": "Cleanser", "product_type": "...", "active_ingredients": [...], "instructions": "..."}]},
    "weekly_treatments": [{"treatment": "...", "frequency": "...", "instructions": "..."}]
  },
  "herbal_protocols": [{"herb": "...", "local_name": "...", "preparation": "...", "why": "...", "caution": "..."}],
  "supplements": [{"name": "...", "dosage": "...", "timing": "...", "why": "..."}],
  "trigger_protocol": {"common_triggers": [...], "elimination_protocol": "...", "tracking": "..."},
  "photo_protocol": {"baseline": "...", "frequency": "...", "lighting": "...", "angles": [...], "tips": "..."},
  "flare_playbook": {"immediate_actions": [...], "what_to_avoid": [...], "when_to_see_dermatologist": "..."},
  "sleep_stress_protocol": {"sleep_hygiene": "...", "stress_management": "...", "adaptogens": "..."},
  "tracking_guidance": [{"key": "...", "label": "...", "type": "...", "frequency": "..."}],
  "shopping_list": {"skincare": [], "foods": [], "herbs": [], "supplements": []},
  "progress_visualization": {"primary_metric": "clarity_score", "photo_timeline": true}
}
```

## Safety Rules

- Distinguish fungal vs bacterial acne EXPLICITLY — wrong protocol worsens fungal
- Refer to dermatologist for cystic/scarring/severe acne
- NO prescription drug advice (isotretinoin, antibiotics, spironolactone)
- Patch-test warning on ALL topical products
- Pregnancy-unsafe actives flagged: retinoids, high-dose salicylic acid (>2%)
- Niacinamide: generally safe, start with lower concentrations
- Always include medical disclaimer
- Zinc: max 50mg/day long-term (copper depletion risk)
- DIM: NOT in pregnancy
PROMPT;
    }

    /**
     * Default user prompt template for Acne.
     */
    protected function getDefaultUserPromptTemplate(): string
    {
        return <<<'PROMPT'
Create a personalized 90-day acne treatment plan for {{NAME}}.

## Assessment Data
- Age: {{AGE}}
- Gender: {{GENDER}}
- Acne Type: {{ACNE_TYPE}}
- Acne Severity: {{ACNE_SEVERITY}}
- Affected Areas: {{AFFECTED_AREAS}}
- Skin Type: {{SKIN_TYPE}}
- Current Skincare: {{CURRENT_SKINCARE}}
- Known Trigger Foods: {{TRIGGER_FOODS}}
- Breakout Pattern: {{BREAKOUT_PATTERN}}
- Hormonal Flares: {{HORMONAL_FLARES}}
- Current Medications: {{CURRENT_MEDICATIONS}}
- Dietary Restrictions: {{DIETARY_RESTRICTIONS}}
- Stress Level: {{STRESS_LEVEL}}
- Sleep Quality: {{SLEEP_QUALITY}}

## Phase Progression
{{PHASE_ARC}}

## Region Profile (for localization)
{{REGION_PROFILE}}

## CRITICAL INSTRUCTIONS

1. **DO NOT include any workout, exercise, or movement block.** This is an acne plan — exercise is not part of the treatment protocol.

2. **Root Cause Analysis**: Explain in plain, empathetic language WHY {{NAME}} has {{ACNE_TYPE}} acne. Address any shame — acne is not about being "unclean." Make it personal and actionable.

3. **Meal Plan**: Create 7 days of anti-inflammatory meals using ONLY foods from the Region Profile. Focus on:
   - Omega-3 rich foods
   - Colorful vegetables (antioxidants)
   - Low glycemic load
   - Gut-supporting foods
   - Avoid common acne triggers (dairy, high sugar, processed foods)

4. **Skincare Routine**: Create detailed AM and PM routines with:
   - Step-by-step instructions
   - Specific active ingredients for {{ACNE_TYPE}} acne
   - Product type recommendations (not specific brands)
   - How to introduce new products gradually

5. **Herbal Protocol**: Select skin-clearing herbs from the Region Profile. Include local names and preparation methods (tea, tincture, topical).

6. **Photo Protocol**: Provide clear guidance on taking baseline and progress photos (consistent lighting, angles, timing).

7. **Tracking**: Output structured tracking guidance with keys: skin_photo, skin_clarity (1-10), flare_count, trigger_dairy, trigger_sugar, trigger_sleep, trigger_stress, mood.

8. **Shopping List**: Include skincare products, foods, herbs, and supplements.

Return ONLY valid JSON matching the schema. Use {{MEASUREMENT_SYSTEM}} units throughout.
PROMPT;
    }

    /**
     * Stricter validation for Acne plans.
     */
    protected function validatePlan(array $plan, array $assessment): void
    {
        parent::validatePlan($plan, $assessment);

        // Acne-specific validations
        if (!isset($plan['acne_type'])) {
            error_log("[AcneGenerator] Missing acne_type in plan");
        }

        if (!isset($plan['skincare_routine'])) {
            error_log("[AcneGenerator] Missing skincare_routine (core module for acne)");
        }

        if (!isset($plan['photo_protocol'])) {
            error_log("[AcneGenerator] Missing photo_protocol (signature deliverable)");
        }

        // CRITICAL: Validate NO movement/workout module
        if (isset($plan['movement_protocol']) || isset($plan['workout']) || isset($plan['exercise'])) {
            error_log("[AcneGenerator] FORBIDDEN: Plan contains movement/workout module — acne plans must NOT include exercise");
            unset($plan['movement_protocol'], $plan['workout'], $plan['exercise']);
        }

        // Validate fungal distinction if type is fungal
        $acneType = $plan['acne_type'] ?? '';
        if ($acneType === 'fungal') {
            if (!isset($plan['summary']) || stripos($plan['summary'], 'fungal') === false) {
                error_log("[AcneGenerator] Fungal acne plan should explicitly distinguish from bacterial acne");
            }
        }
    }
}