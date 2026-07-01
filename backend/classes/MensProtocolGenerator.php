<?php
/**
 * MensProtocolGenerator — Men's Health (Vitale) treatment plan generator.
 *
 * Brand: Vitale
 * Modules: meal_plan (vitality-supporting), movement (core, strength/recovery),
 *          herbal_protocol, supplements, sleep_recovery (core), daily_logging,
 *          progress_visualization, stress_adaptogen
 *
 * Profiles: Low Energy/Fatigue, Low Testosterone Markers, Stress/Burnout,
 *           Body Composition/Performance
 *
 * Signature: Vitality/energy score + habit streaks + sleep-recovery tracking
 */
class MensProtocolGenerator extends AbstractProtocolGenerator
{
    protected $condition = 'mens';
    protected $brandName = 'Vitale';

    /**
     * Men's health-specific prompt variables.
     */
    protected function getPromptVariables(array $assessment, string $name): array
    {
        $vars = parent::getPromptVariables($assessment, $name);

        // Men's health-specific variables
        $vars['VITALITY_PROFILE'] = $this->resolveVitalityProfile($assessment);
        $vars['PRIMARY_GOAL'] = $assessment['primary_goal'] ?? $assessment['goal'] ?? 'energy';
        $vars['ENERGY_LEVEL'] = $assessment['energy_level'] ?? $assessment['current_energy'] ?? 'not specified';
        $vars['LIBIDO'] = $assessment['libido'] ?? 'not specified';
        $vars['FOCUS_QUALITY'] = $assessment['focus_quality'] ?? 'not specified';
        $vars['STRESS_LEVEL'] = $assessment['stress_level'] ?? 'moderate';
        $vars['SLEEP_QUALITY'] = $assessment['sleep_quality'] ?? 'not specified';
        $vars['SLEEP_HOURS'] = $assessment['sleep_hours'] ?? 'not specified';
        $vars['EXERCISE_EXPERIENCE'] = $assessment['exercise_experience'] ?? 'beginner';
        $vars['TRAINING_PREFERENCE'] = $assessment['training_preference'] ?? $assessment['exercise_preference'] ?? 'strength';
        $vars['WORK_TYPE'] = $assessment['work_type'] ?? 'not specified';
        $vars['ALCOHOL'] = $assessment['alcohol'] ?? $assessment['alcohol_consumption'] ?? 'not specified';
        $vars['SMOKING'] = $assessment['smoking'] ?? 'not specified';
        $vars['CURRENT_MEDICATIONS'] = $assessment['medications'] ?? $assessment['current_medications'] ?? 'none reported';
        $vars['DIETARY_RESTRICTIONS'] = $assessment['dietary_restrictions'] ?? 'none';

        // Phase progression
        $vars['PHASE_ARC'] = $this->getPhaseArcDescription();

        return $vars;
    }

    /**
     * Resolve vitality profile from assessment.
     */
    private function resolveVitalityProfile(array $assessment): string
    {
        $profile = $assessment['vitality_profile'] ?? $assessment['mensType'] ?? $assessment['mens_type'] ?? '';

        // Frontend sends {primary, scores, confidence} keyed on cortisol/androgen/
        // mitochondrial/inflammatory, not a plain string in this vocabulary.
        if (is_array($profile)) {
            $profile = $profile['primary'] ?? '';
            $driverMap = [
                'cortisol' => 'stress-burnout',
                'androgen' => 'low-testosterone-markers',
                'mitochondrial' => 'low-energy',
                'inflammatory' => 'body-composition',
            ];
            $profile = $driverMap[strtolower((string)$profile)] ?? $profile;
        }

        if (empty($profile) || !is_string($profile)) {
            $desc = strtolower(implode(' ', $assessment));

            if (strpos($desc, 'testosterone') !== false || strpos($desc, 'libido') !== false || strpos($desc, 'muscle loss') !== false) {
                return 'low-testosterone-markers';
            }
            if (strpos($desc, 'burnout') !== false || strpos($desc, 'wired') !== false || strpos($desc, 'irritab') !== false) {
                return 'stress-burnout';
            }
            if (strpos($desc, 'composition') !== false || strpos($desc, 'performance') !== false || strpos($desc, 'strength') !== false) {
                return 'body-composition';
            }

            return 'low-energy'; // Default
        }

        return strtolower($profile);
    }

    /**
     * Get phase arc description for men's health.
     */
    private function getPhaseArcDescription(): string
    {
        return "Phase 1 (Weeks 1-4): Restore — Fix sleep foundation, reduce stress drain, establish nutrition baseline, begin movement habit.
Phase 2 (Weeks 5-8): Build — Progressive strength training, optimize nutrition for vitality, deepen recovery protocols.
Phase 3 (Weeks 9-12): Optimize — Peak performance tuning, advanced protocols, long-term sustainability.";
    }

    /**
     * Default system prompt for Men's Health.
     */
    protected function getDefaultSystemPrompt(): string
    {
        return <<<'PROMPT'
# Vitale — Men's Health & Vitality Treatment Plan Generator

You are a direct, no-fluff men's vitality specialist creating personalized 90-day plans. Your approach is performance-and-vitality framed, evidence-based, and combines strength training, recovery optimization, and targeted nutrition.

## Core Philosophy
- Direct, actionable, no-nonsense communication
- Performance and vitality framing (not "wellness")
- Sleep and recovery as NON-NEGOTIABLE foundations
- Strength training as core protocol
- Labs before hormone claims — never diagnose
- NO anabolic/steroid/TRT advice
- Cardiac risk flag for older/sedentary before intense protocols

## Vitality Profile Knowledge Base

### Low Energy / Fatigue
**Markers:** Afternoon crashes, poor recovery, brain fog, motivation issues
**Root Cause:** Blood sugar instability, poor sleep quality, nutrient deficiencies, mitochondrial dysfunction
**Protocol Focus:**
- Blood sugar stability: protein-first meals, balanced macros
- Sleep optimization: non-negotiable 7-9 hours
- Adaptogens: ashwagandha (300-600mg), rhodiola, cordyceps
- B-vitamins, magnesium, CoQ10
- Movement: Start gentle, build to strength + cardio

### Low Testosterone Markers
**Markers:** Low libido, mood changes, muscle loss, belly fat gain, poor recovery
**Root Cause:** Multiple factors — stress, sleep, nutrition, age, environmental
**Protocol Focus:**
- REFER FOR LABS first — do not diagnose low T
- Strength training (compound movements)
- Sleep optimization (most T produced during sleep)
- Zinc, vitamin D, magnesium
- Healthy fats for hormone production
- Stress management (cortisol suppresses T)
- Nigerian herbs: bitter kola, moringa (evidence-aware)

### Stress / Burnout
**Markers:** Wired-tired, poor sleep despite exhaustion, irritability, performance decline
**Root Cause:** Chronic HPA axis activation → cortisol dysregulation → recovery failure
**Protocol Focus:**
- Cortisol management: sleep FIRST
- Adaptogens: ashwagandha, holy basil, rhodiola
- Recovery protocol: deload weeks, active recovery
- Magnesium for nervous system
- No aggressive training (worsens burnout)
- Breathwork, nature exposure

### Body Composition / Performance
**Markers:** Recomposition goals, performance plateaus, strength goals
**Root Cause:** Suboptimal training, nutrition gaps, recovery不足
**Protocol Focus:**
- Progressive strength training (periodized)
- Protein optimization (1.6-2.2g/kg)
- Recovery: sleep, deloads, mobility
- Performance nutrition: timing, hydration
- Track strength metrics, not just weight

## Plan Structure

Generate a comprehensive 90-day plan with these sections:

1. **Personalized Vitality Analysis** — Direct explanation of what's driving their symptoms
2. **Phase Progression Arc** — Restore → Build → Optimize
3. **7-Day Vitality Nutrition Plan** — Localized foods supporting their goal
4. **Strength/Movement Protocol (CORE)** — Progressive, periodized
5. **Sleep & Recovery Protocol (CORE)** — Non-negotiable foundation
6. **Herbal Protocol** — Vitality herbs from user's region (safety-gated)
7. **Supplement Protocol** — Evidence-based for their profile
8. **Stress/Adaptogen Protocol** — Cortisol management
9. **Tracking Guidance** — Structured: energy, libido, focus, sleep, strength, mood
10. **Shopping List** — Foods, herbs, supplements
11. **Progress Visualization** — Vitality score, streaks, strength metrics

## Output Format

Return ONLY valid JSON with this structure:
```json
{
  "summary": "Direct vitality analysis",
  "vitality_profile": "low-energy|low-testosterone-markers|stress-burnout|body-composition",
  "goals": ["goal1", "goal2", "goal3"],
  "phase_arc": {
    "phase1": {"name": "Restore", "weeks": "1-4", "focus": "..."},
    "phase2": {"name": "Build", "weeks": "5-8", "focus": "..."},
    "phase3": {"name": "Optimize", "weeks": "9-12", "focus": "..."}
  },
  "meal_plan": [
    {"day": "Monday", "meals": {"breakfast": {...}, "lunch": {...}, "dinner": {...}, "snack": {...}}, "protein_total_g": "..."}
  ],
  "strength_protocol": {
    "type": "progressive",
    "phase1": {"focus": "...", "frequency": "...", "exercises": [...]},
    "phase2": {"focus": "...", "frequency": "...", "exercises": [...]},
    "phase3": {"focus": "...", "frequency": "...", "exercises": [...]}
  },
  "sleep_recovery_protocol": {
    "sleep_hygiene": "...",
    "target_hours": "...",
    "recovery_practices": [...],
    "deload_schedule": "..."
  },
  "herbal_protocols": [{"herb": "...", "local_name": "...", "dosage": "...", "why": "...", "caution": "..."}],
  "supplements": [{"name": "...", "dosage": "...", "timing": "...", "why": "..."}],
  "stress_protocol": {"cortisol_management": "...", "adaptogens": [...], "practices": [...]},
  "tracking_guidance": [{"key": "...", "label": "...", "type": "...", "frequency": "..."}],
  "shopping_list": {"proteins": [], "vegetables": [], "carbs": [], "fats": [], "herbs": [], "supplements": []},
  "progress_visualization": {"primary_metric": "vitality_score", "streaks": true, "strength_metrics": [...]}
}
```

## Safety Rules

- REFER for labs before ANY hormone-related claims
- NO anabolic/steroid/TRT advice — ever
- Cardiac risk flag: older (>45) + sedentary → medical clearance before intense protocols
- NO libido drug advice
- Ashwagandha: max 600mg/day, avoid in hyperthyroidism
- Always include medical disclaimer
- Direct communication style — no fluff, no shame, just actionable
PROMPT;
    }

    /**
     * Default user prompt template for Men's Health.
     */
    protected function getDefaultUserPromptTemplate(): string
    {
        return <<<'PROMPT'
Create a personalized 90-day men's vitality plan for {{NAME}}.

## Assessment Data
- Age: {{AGE}}
- Gender: {{GENDER}}
- Vitality Profile: {{VITALITY_PROFILE}}
- Primary Goal: {{PRIMARY_GOAL}}
- Current Energy Level: {{ENERGY_LEVEL}}
- Libido: {{LIBIDO}}
- Focus Quality: {{FOCUS_QUALITY}}
- Stress Level: {{STRESS_LEVEL}}
- Sleep Quality: {{SLEEP_QUALITY}}
- Sleep Hours: {{SLEEP_HOURS}}
- Exercise Experience: {{EXERCISE_EXPERIENCE}}
- Training Preference: {{TRAINING_PREFERENCE}}
- Work Type: {{WORK_TYPE}}
- Alcohol: {{ALCOHOL}}
- Smoking: {{SMOKING}}
- Current Medications: {{CURRENT_MEDICATIONS}}
- Dietary Restrictions: {{DIETARY_RESTRICTIONS}}

## Phase Progression
{{PHASE_ARC}}

## Region Profile (for localization)
{{REGION_PROFILE}}

## Instructions

1. **Vitality Analysis**: Be direct. Explain what's driving {{NAME}}'s symptoms. No fluff, no shame — just facts and actionable insights.

2. **Nutrition Plan**: Create 7 days of meals using ONLY foods from the Region Profile. Focus on:
   - High protein (target 1.6-2.2g/kg if body composition goal)
   - Vitality-supporting nutrients (zinc, magnesium, healthy fats)
   - Local foods from their region
   - Use {{MEASUREMENT_SYSTEM}} units

3. **Strength Protocol**: Create a PROGRESSIVE strength training plan:
   - Phase 1: Build foundation, establish habit
   - Phase 2: Progressive overload
   - Phase 3: Performance optimization
   Include specific exercises, sets, reps, progression.

4. **Sleep & Recovery Protocol**: This is NON-NEGOTIABLE. Detail:
   - Sleep hygiene practices
   - Target hours
   - Recovery practices (mobility, deloads)
   - Pre-sleep routine

5. **Herbal Protocol**: Select vitality herbs from the Region Profile. Include local names.

6. **Tracking**: Output structured tracking guidance with keys: energy (1-10), libido, focus, sleep_hours, sleep_quality, strength_session_done, mood.

7. **Shopping List**: Organize by category.

Return ONLY valid JSON matching the schema. Use {{MEASUREMENT_SYSTEM}} units throughout.
PROMPT;
    }

    /**
     * Stricter validation for Men's plans.
     */
    protected function validatePlan(array $plan, array $assessment): void
    {
        parent::validatePlan($plan, $assessment);

        // Men's health-specific validations
        if (!isset($plan['vitality_profile'])) {
            error_log("[MensGenerator] Missing vitality_profile in plan");
        }

        if (!isset($plan['strength_protocol'])) {
            error_log("[MensGenerator] Missing strength_protocol (core module for men's)");
        }

        if (!isset($plan['sleep_recovery_protocol'])) {
            error_log("[MensGenerator] Missing sleep_recovery_protocol (core module for men's)");
        }

        // Validate strength IS included (men's has strength module)
        if (!isset($plan['strength_protocol'])) {
            error_log("[MensGenerator] Strength protocol required for men's vitality plan");
        }
    }
}