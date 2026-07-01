<?php
/**
 * WeightProtocolGenerator — Weight Management (LeanFlow) treatment plan generator.
 *
 * Brand: LeanFlow
 * Modules: meal_plan (macro-targeted), movement (core, progressive), herbal_protocol,
 *          supplements, sleep_stress, daily_logging, progress_visualization,
 *          habit_protocol, plateau_protocol
 *
 * Weight types: Insulin-Resistant/Metabolic, Stress/Cortisol-Driven,
 *               Hormonal (Thyroid/Perimenopause), Habit/Lifestyle
 *
 * Signature: Trend-smoothed weight graph + measurements + non-scale-victory log
 */
class WeightProtocolGenerator extends AbstractProtocolGenerator
{
    protected $condition = 'weight';
    protected $brandName = 'LeanFlow';

    /**
     * Weight-specific prompt variables.
     */
    protected function getPromptVariables(array $assessment, string $name): array
    {
        $vars = parent::getPromptVariables($assessment, $name);

        // Weight-specific variables
        $vars['WEIGHT_TYPE'] = $this->resolveWeightType($assessment);
        $vars['CURRENT_WEIGHT'] = $assessment['current_weight'] ?? 'not specified';
        $vars['TARGET_WEIGHT'] = $assessment['target_weight'] ?? $assessment['goal_weight'] ?? 'not specified';
        $vars['HEIGHT'] = $assessment['height'] ?? 'not specified';
        $vars['ACTIVITY_LEVEL'] = $assessment['activity_level'] ?? 'sedentary';
        $vars['GOAL_TIMELINE'] = $assessment['goal_timeline'] ?? 'not specified';
        $vars['DIET_HISTORY'] = $assessment['diet_history'] ?? $assessment['previous_diets'] ?? 'not specified';
        $vars['EXERCISE_EXPERIENCE'] = $assessment['exercise_experience'] ?? 'beginner';
        $vars['EATING_PATTERNS'] = $assessment['eating_patterns'] ?? 'not specified';
        $vars['STRESS_EATING'] = $assessment['stress_eating'] ?? 'not specified';
        $vars['CURRENT_MEDICATIONS'] = $assessment['medications'] ?? $assessment['current_medications'] ?? 'none reported';
        $vars['DIETARY_RESTRICTIONS'] = $assessment['dietary_restrictions'] ?? 'none';
        $vars['STRESS_LEVEL'] = $assessment['stress_level'] ?? 'moderate';
        $vars['SLEEP_QUALITY'] = $assessment['sleep_quality'] ?? 'not specified';

        // Calculate macro targets if we have the data
        $vars['MACRO_TARGETS'] = $this->calculateMacroTargets($assessment);
        $vars['CALORIE_TARGET'] = $this->calculateCalorieTarget($assessment);

        // Phase progression
        $vars['PHASE_ARC'] = $this->getPhaseArcDescription();

        return $vars;
    }

    /**
     * Resolve weight management type from assessment.
     */
    private function resolveWeightType(array $assessment): string
    {
        $type = $assessment['weight_type'] ?? $assessment['weightType'] ?? '';

        // Frontend sends {primary, scores, confidence}, not a plain string.
        if (is_array($type)) {
            $type = $type['primary'] ?? '';
            $driverMap = [
                'insulin' => 'insulin-resistant',
                'adrenal' => 'stress-driven',
                'inflammatory' => 'hormonal',
                'transition' => 'habit-lifestyle',
            ];
            $type = $driverMap[strtolower((string)$type)] ?? $type;
        }

        if (empty($type) || !is_string($type)) {
            $desc = strtolower(implode(' ', $assessment));

            if (strpos($desc, 'insulin') !== false || strpos($desc, 'craving') !== false || strpos($desc, 'belly') !== false || strpos($desc, 'metabolic') !== false) {
                return 'insulin-resistant';
            }
            if (strpos($desc, 'stress') !== false || strpos($desc, 'cortisol') !== false || strpos($desc, 'emotional') !== false) {
                return 'stress-driven';
            }
            if (strpos($desc, 'thyroid') !== false || strpos($desc, 'perimenopause') !== false || strpos($desc, 'hormonal') !== false) {
                return 'hormonal';
            }

            return 'habit-lifestyle'; // Default
        }

        return strtolower($type);
    }

    /**
     * Calculate macro targets based on profile.
     */
    private function calculateMacroTargets(array $assessment): string
    {
        $weight = (float) ($assessment['current_weight'] ?? 0);
        $height = (float) ($assessment['height'] ?? 0);
        $age = (int) ($assessment['age'] ?? 30);
        $activity = $assessment['activity_level'] ?? 'sedentary';
        $gender = strtolower($assessment['gender'] ?? 'female');

        if (!$weight || !$height) {
            return "Personalized macro targets will be calculated based on your profile.";
        }

        // Mifflin-St Jeor for BMR
        if ($gender === 'male') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        } else {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        }

        // Activity multiplier
        $multipliers = [
            'sedentary' => 1.2,
            'light' => 1.375,
            'moderate' => 1.55,
            'active' => 1.725,
            'very_active' => 1.9,
        ];
        $tdee = $bmr * ($multipliers[$activity] ?? 1.2);

        // Moderate deficit for weight loss (20%)
        $targetCalories = round($tdee * 0.8);

        // Minimum calorie floors
        $minCalories = $gender === 'male' ? 1500 : 1200;
        $targetCalories = max($targetCalories, $minCalories);

        // Macros: 30% protein, 35% fat, 35% carbs (balanced for satiety)
        $protein = round(($targetCalories * 0.30) / 4); // 4 cal/g
        $fat = round(($targetCalories * 0.35) / 9);     // 9 cal/g
        $carbs = round(($targetCalories * 0.35) / 4);   // 4 cal/g

        return "Estimated targets: {$targetCalories} kcal/day | Protein: {$protein}g | Fat: {$fat}g | Carbs: {$carbs}g (Adjust based on progress and satiety)";
    }

    /**
     * Calculate calorie target.
     */
    private function calculateCalorieTarget(array $assessment): string
    {
        $weight = (float) ($assessment['current_weight'] ?? 0);
        if (!$weight)
            return "Calculate based on profile";

        // Simple: bodyweight in lbs x 10-12 for weight loss
        $lbs = $weight * 2.205;
        $low = round($lbs * 10);
        $high = round($lbs * 12);

        return "{$low}-{$high} kcal/day range (adjust based on progress)";
    }

    /**
     * Get phase arc description for weight management.
     */
    private function getPhaseArcDescription(): string
    {
        return "Phase 1 (Weeks 1-4): Foundation — Build sustainable habits, establish nutrition baseline, start progressive movement, no crash dieting.
Phase 2 (Weeks 5-8): Momentum — Optimize macros, increase movement intensity, address plateaus, celebrate non-scale victories.
Phase 3 (Weeks 9-12): Sustainability — Long-term habit architecture, maintenance preparation, lifestyle integration.";
    }

    /**
     * Default system prompt for Weight Management.
     */
    protected function getDefaultSystemPrompt(): string
    {
        return <<<'PROMPT'
# LeanFlow — Weight Management Treatment Plan Generator

You are a sustainable metabolic coach creating personalized 90-day weight management plans. Your approach is explicitly anti-crash-diet, anti-shame, and focuses on metabolic health, sustainable habits, and progressive movement.

## Core Philosophy
- NO crash diets or aggressive calorie restriction
- NO rapid-loss promises
- Minimum calorie floors enforced (1200 for women, 1500 for men)
- Disordered eating red flags → referral, NOT precise targets
- Trend-smoothed tracking (daily scale noise causes churn)
- Non-scale victories matter (energy, measurements, how clothes fit)

## Weight Type Knowledge Base

### Insulin-Resistant / Metabolic
**Markers:** Central weight gain, sugar cravings, fatigue after meals, dark skin patches, elevated fasting insulin
**Root Cause:** Insulin resistance → fat storage mode → cravings → more insulin
**Protocol Focus:**
- Protein-first meals (30g+ per meal)
- Low glycemic load carbohydrates
- Strength training (builds insulin-sensitive muscle)
- Walking after meals (blunts glucose spike)
- Berberine, chromium, inositol
- Fiber-rich foods

### Stress/Cortisol-Driven
**Markers:** Stress eating, poor sleep, belly fat retention, "wired but tired"
**Root Cause:** Chronic cortisol → fat storage (especially visceral) → cravings → weight retention
**Protocol Focus:**
- Cortisol management FIRST (not aggressive deficit)
- Sleep optimization
- Gentle movement initially (walking, yoga)
- Adaptogens: ashwagandha, rhodiola
- Magnesium for stress response
- No aggressive calorie restriction (worsens cortisol)

### Hormonal (Thyroid/Perimenopause)
**Markers:** Slow metabolism, cold intolerance, fatigue, unexplained weight gain
**Root Cause:** Thyroid dysfunction or hormonal shifts → metabolic slowdown
**Protocol Focus:**
- Thyroid-supportive nutrition (selenium, zinc, iodine from food)
- REFER for labs — do not diagnose
- Moderate movement (not excessive)
- Anti-inflammatory foods
- Medical referral for proper thyroid workup

### Habit/Lifestyle
**Markers:** Portion creep, sedentary patterns, emotional eating, inconsistent meals
**Root Cause:** Behavioral patterns → calorie surplus over time
**Protocol Focus:**
- Habit architecture (implementation intentions)
- NEAT (non-exercise activity thermogenesis)
- Sustainable moderate deficit
- Protein for satiety
- Movement they enjoy
- Sleep optimization

## Plan Structure

Generate a comprehensive 90-day plan with these sections:

1. **Personalized Root Cause Analysis** — Explain WHY weight is hard for them (not willpower!)
2. **Phase Progression Arc** — Foundation → Momentum → Sustainability
3. **7-Day Macro-Targeted Meal Plan** — Localized foods with portions and macro estimates
4. **Progressive Movement Plan (CORE)** — Starting appropriate, building over 90 days
5. **Herbal Protocol** — Metabolic herbs from user's region (safety-gated)
6. **Supplement Protocol** — Evidence-based for their type
7. **Habit Formation Protocol** — Specific habits to build, implementation intentions
8. **Plateau Response Protocol** — What to do when weight stalls (NOT "eat less")
9. **Sleep & Recovery Protocol** — Sleep's role in metabolic health
10. **Tracking Guidance** — Structured: weight (daily, smoothed), measurements, energy, habits, NSV
11. **Shopping List** — Foods organized by category
12. **Non-Scale Victory Log** — What to track besides weight

## Output Format

Return ONLY valid JSON with this structure:
```json
{
  "summary": "Personalized root cause explanation",
  "weight_type": "insulin-resistant|stress-driven|hormonal|habit-lifestyle",
  "goals": ["goal1", "goal2", "goal3"],
  "phase_arc": {
    "phase1": {"name": "Foundation", "weeks": "1-4", "focus": "..."},
    "phase2": {"name": "Momentum", "weeks": "5-8", "focus": "..."},
    "phase3": {"name": "Sustainability", "weeks": "9-12", "focus": "..."}
  },
  "macro_targets": {"calories": "...", "protein_g": "...", "fat_g": "...", "carbs_g": "...", "notes": "..."},
  "meal_plan": [
    {"day": "Monday", "meals": {"breakfast": {...}, "lunch": {...}, "dinner": {...}, "snack": {...}}, "daily_macros_estimate": {...}}
  ],
  "movement_protocol": {
    "type": "progressive",
    "phase1": {"focus": "...", "frequency": "...", "exercises": [...]},
    "phase2": {"focus": "...", "frequency": "...", "exercises": [...]},
    "phase3": {"focus": "...", "frequency": "...", "exercises": [...]}
  },
  "herbal_protocols": [{"herb": "...", "local_name": "...", "dosage": "...", "why": "...", "caution": "..."}],
  "supplements": [{"name": "...", "dosage": "...", "timing": "...", "why": "..."}],
  "habit_protocol": {"habits": [{"habit": "...", "cue": "...", "action": "...", "reward": "..."}], "implementation_plan": "..."},
  "plateau_protocol": {"when_stalled": [...], "do_not": [...], "adjustments": [...]},
  "sleep_protocol": {"sleep_hygiene": "...", "target_hours": "...", "tips": "..."},
  "tracking_guidance": [{"key": "...", "label": "...", "type": "...", "frequency": "...", "unit": "..."}],
  "shopping_list": {"proteins": [], "vegetables": [], "carbs": [], "fats": [], "herbs": []},
  "non_scale_victories": ["energy improvement", "clothing fit", "measurements", "strength gains", "sleep quality"],
  "progress_visualization": {"primary_metric": "weight_trend", "chart_type": "smoothed_trend", "secondary_metrics": ["measurements", "energy", "habits"]}
}
```

## Safety Rules

- NO crash deficits (<1200 kcal women, <1500 kcal men)
- NO rapid-loss promises (>2 lbs/week is unsustainable)
- Disordered eating red flags → REFERRAL, not targets:
  - History of eating disorders
  - Extreme restriction patterns
  - Compensatory behaviors
  - Body dysmorphia indicators
- Medical referral for: suspected thyroid issues, unexplained weight gain
- Berberine: max 1500mg/day, NOT in pregnancy
- Always include medical disclaimer
- Frame weight as ONE metric among many (energy, mood, strength, sleep)
PROMPT;
    }

    /**
     * Default user prompt template for Weight Management.
     */
    protected function getDefaultUserPromptTemplate(): string
    {
        return <<<'PROMPT'
Create a personalized 90-day weight management plan for {{NAME}}.

## Assessment Data
- Age: {{AGE}}
- Gender: {{GENDER}}
- Weight Type: {{WEIGHT_TYPE}}
- Current Weight: {{CURRENT_WEIGHT}}
- Target Weight: {{TARGET_WEIGHT}}
- Height: {{HEIGHT}}
- Activity Level: {{ACTIVITY_LEVEL}}
- Exercise Experience: {{EXERCISE_EXPERIENCE}}
- Goal Timeline: {{GOAL_TIMELINE}}
- Diet History: {{DIET_HISTORY}}
- Eating Patterns: {{EATING_PATTERNS}}
- Stress Eating: {{STRESS_EATING}}
- Current Medications: {{CURRENT_MEDICATIONS}}
- Dietary Restrictions: {{DIETARY_RESTRICTIONS}}
- Stress Level: {{STRESS_LEVEL}}
- Sleep Quality: {{SLEEP_QUALITY}}

## Calculated Targets
- Macro Targets: {{MACRO_TARGETS}}
- Calorie Target: {{CALORIE_TARGET}}

## Phase Progression
{{PHASE_ARC}}

## Region Profile (for localization)
{{REGION_PROFILE}}

## Instructions

1. **Root Cause Analysis**: Explain in plain, compassionate language WHY weight management is hard for {{NAME}}. This is NOT about willpower — explain the metabolic/hormonal/behavioral factors. Remove shame.

2. **Meal Plan**: Create 7 days of meals using ONLY foods from the Region Profile. Each meal should include:
   - Specific foods with portions (use {{MEASUREMENT_SYSTEM}} units)
   - Approximate macro breakdown
   - Why this helps their weight type

3. **Movement Protocol**: Create a PROGRESSIVE movement plan across 3 phases:
   - Phase 1: Start where they are ({{ACTIVITY_LEVEL}}), build habit
   - Phase 2: Increase intensity/duration
   - Phase 3: Sustainable long-term movement pattern
   Include specific exercises, frequency, duration.

4. **Herbal Protocol**: Select metabolic herbs from the Region Profile. Include local names.

5. **Habit Protocol**: Define 3-5 specific habits to build with implementation intentions (when/where/how).

6. **Tracking**: Output structured tracking guidance with keys: weight (daily, in {{MEASUREMENT_SYSTEM}}), measurements_weekly, energy (1-10), habits_completed, nsv_note.

7. **Shopping List**: Organize by category (proteins, vegetables, carbs, fats, herbs).

Return ONLY valid JSON matching the schema. Use {{MEASUREMENT_SYSTEM}} units throughout.
PROMPT;
    }

    /**
     * Stricter validation for Weight plans.
     */
    protected function validatePlan(array $plan, array $assessment): void
    {
        parent::validatePlan($plan, $assessment);

        // Weight-specific validations
        if (!isset($plan['weight_type'])) {
            error_log("[WeightGenerator] Missing weight_type in plan");
        }

        if (!isset($plan['movement_protocol'])) {
            error_log("[WeightGenerator] Missing movement_protocol (core module for weight)");
        }

        if (!isset($plan['macro_targets'])) {
            error_log("[WeightGenerator] Missing macro_targets");
        }

        if (!isset($plan['habit_protocol'])) {
            error_log("[WeightGenerator] Missing habit_protocol");
        }

        if (!isset($plan['plateau_protocol'])) {
            error_log("[WeightGenerator] Missing plateau_protocol");
        }

        // Validate movement IS included (weight has movement module)
        if (!isset($plan['movement_protocol'])) {
            error_log("[WeightGenerator] Movement protocol required for weight management");
        }
    }
}