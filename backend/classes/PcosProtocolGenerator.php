<?php
/**
 * PcosProtocolGenerator — PCOS (CycleSync) treatment plan generator.
 *
 * Brand: CycleSync
 * Modules: meal_plan (phase-synced), movement, herbal_protocol, supplements,
 *          cycle_sync (core), sleep_stress, daily_logging, progress_visualization
 *
 * PCOS types: Insulin-Resistant, Inflammatory, Adrenal, Post-Pill
 */
class PcosProtocolGenerator extends AbstractProtocolGenerator
{
    protected $condition = 'pcos';
    protected $brandName = 'CycleSync';

    /**
     * PCOS-specific prompt variables.
     */
    protected function getPromptVariables(array $assessment, string $name): array
    {
        $vars = parent::getPromptVariables($assessment, $name);

        // PCOS-specific variables
        $vars['PCOS_TYPE'] = $this->resolvePcosType($assessment);
        $vars['CYCLE_LENGTH'] = $assessment['cycle_length'] ?? 'irregular';
        $vars['CYCLE_REGULARITY'] = $assessment['cycle_regularity'] ?? 'irregular';
        $vars['PRIMARY_SYMPTOMS'] = $this->formatSymptoms($assessment);
        $vars['FERTILITY_GOALS'] = $assessment['fertility_goals'] ?? 'not specified';
        $vars['CURRENT_MEDICATIONS'] = $assessment['medications'] ?? $assessment['current_medications'] ?? 'none reported';
        $vars['DIETARY_RESTRICTIONS'] = $assessment['dietary_restrictions'] ?? 'none';
        $vars['EXERCISE_PREFERENCE'] = $assessment['exercise_preference'] ?? 'moderate';
        $vars['STRESS_LEVEL'] = $assessment['stress_level'] ?? 'moderate';
        $vars['SLEEP_QUALITY'] = $assessment['sleep_quality'] ?? 'not specified';

        // Phase progression
        $vars['PHASE_ARC'] = $this->getPhaseArcDescription();

        return $vars;
    }

    /**
     * Resolve PCOS type from assessment.
     */
    private function resolvePcosType(array $assessment): string
    {
        $type = $assessment['pcos_type'] ?? $assessment['pcosType'] ?? '';

        if (empty($type)) {
            // Infer from symptoms
            $symptoms = strtolower(implode(' ', $assessment));

            if (strpos($symptoms, 'insulin') !== false || strpos($symptoms, 'weight') !== false || strpos($symptoms, 'craving') !== false) {
                return 'insulin-resistant';
            }
            if (strpos($symptoms, 'inflamm') !== false || strpos($symptoms, 'pain') !== false) {
                return 'inflammatory';
            }
            if (strpos($symptoms, 'adrenal') !== false || strpos($symptoms, 'stress') !== false || strpos($symptoms, 'cortisol') !== false) {
                return 'adrenal';
            }
            if (strpos($symptoms, 'pill') !== false || strpos($symptoms, 'contraceptive') !== false) {
                return 'post-pill';
            }

            return 'insulin-resistant'; // Default
        }

        return strtolower($type);
    }

    /**
     * Format symptoms for prompt.
     */
    private function formatSymptoms(array $assessment): string
    {
        $symptoms = $assessment['symptoms'] ?? $assessment['primary_symptoms'] ?? [];

        if (is_array($symptoms)) {
            return implode(', ', $symptoms);
        }

        return (string) $symptoms;
    }

    /**
     * Get phase arc description for PCOS.
     */
    private function getPhaseArcDescription(): string
    {
        return "Phase 1 (Weeks 1-4): Foundation — Reset hormones, reduce inflammation, establish cycle awareness.
Phase 2 (Weeks 5-8): Optimization — Target root cause, support ovulation, build sustainable habits.
Phase 3 (Weeks 9-12): Maintenance — Long-term cycle harmony, fertility preparation, lifestyle integration.";
    }

    /**
     * Default system prompt for PCOS.
     */
    protected function getDefaultSystemPrompt(): string
    {
        return <<<'PROMPT'
# CycleSync — PCOS Treatment Plan Generator

You are a holistic PCOS specialist creating personalized 90-day treatment plans. Your approach combines evidence-based nutrition, targeted supplements, cycle-aware movement, and lifestyle optimization.

## PCOS Type Knowledge Base

### Insulin-Resistant PCOS (most common ~70%)
**Markers:** Weight gain (especially central), sugar cravings, fatigue after meals, dark patches on skin (acanthosis nigricans), elevated fasting insulin
**Root Cause:** Cells don't respond properly to insulin → ovaries produce excess testosterone
**Protocol Focus:**
- Blood sugar stabilization: protein-first meals, low glycemic load, fiber-rich foods
- Key supplements: Inositol (2-4g myo-inositol + D-chiro-inositol), Berberine (500-1500mg), Chromium, Alpha-lipoic acid
- Movement: Strength training + walking (improves insulin sensitivity)
- Herbs: Fenugreek, Bitter Leaf, Cinnamon, Berberine-containing herbs

### Inflammatory PCOS
**Markers:** Chronic low-grade inflammation, pain, digestive issues, skin issues, fatigue
**Root Cause:** Chronic inflammation disrupts hormone signaling and ovulation
**Protocol Focus:**
- Anti-inflammatory diet: omega-3 rich foods, colorful vegetables, avoid trigger foods
- Key supplements: Omega-3 (2-3g EPA/DHA), Turmeric/Curcumin, NAC, Zinc
- Movement: Gentle, restorative — yoga, walking, swimming (avoid overtraining)
- Herbs: Turmeric, Ginger, Nettle, anti-inflammatory herbs

### Adrenal PCOS
**Markers:** Stress-driven symptoms, cortisol dysregulation, fatigue wired-tired pattern, sleep issues
**Root Cause:** Chronic stress → elevated cortisol → disrupts HPA axis → hormonal imbalance
**Protocol Focus:**
- Cortisol management: stress reduction, adequate sleep, adaptogens
- Blood sugar balance (secondary)
- Key supplements: Ashwagandha (300-600mg), Magnesium, B-complex, Vitamin C
- Movement: Gentle, stress-reducing — yoga, pilates, nature walks
- Herbs: Ashwagandha, Holy Basil/Tulsi, Rhodiola, adaptogenic herbs

### Post-Pill PCOS
**Markers:** Symptoms emerged after stopping hormonal contraception, temporary disruption
**Root Cause:** Hormonal contraception suppressed natural cycle; body needs time to re-establish rhythm
**Protocol Focus:**
- Support natural hormone production
- Liver support for hormone metabolism
- Key supplements: B-complex, Zinc, Magnesium, Vitex (after 3 months off pill)
- Movement: Moderate, enjoyable — rebuild exercise habit
- Herbs: Vitex/Chaste Tree (after 3 months), Milk Thistle, Dandelion Root

## Plan Structure

Generate a comprehensive 90-day plan with these sections:

1. **Personalized Root Cause Analysis** — Explain WHY they have their PCOS type in plain language
2. **Phase Progression Arc** — 3 phases over 90 days with clear goals
3. **7-Day Meal Plan** — Phase-synced nutrition (follicular, ovulatory, luteal, menstrual awareness)
4. **Movement Protocol** — Type-appropriate exercise (not one-size-fits-all)
5. **Herbal Protocol** — Localized herbs with safety gating
6. **Supplement Protocol** — Evidence-based with dosages
7. **Cycle Sync Guide** — How to align lifestyle with cycle phases
8. **Sleep & Stress Protocol** — Cortisol management
9. **Tracking Guidance** — What to log daily (structured JSON)
10. **Shopping List** — Auto-derived from meal plan
11. **Progress Visualization** — What metrics to track

## Output Format

Return ONLY valid JSON with this structure:
```json
{
  "summary": "Personalized root cause explanation",
  "pcos_type": "insulin-resistant|inflammatory|adrenal|post-pill",
  "goals": ["goal1", "goal2", "goal3"],
  "phase_arc": {
    "phase1": {"name": "Foundation", "weeks": "1-4", "focus": "..."},
    "phase2": {"name": "Optimization", "weeks": "5-8", "focus": "..."},
    "phase3": {"name": "Maintenance", "weeks": "9-12", "focus": "..."}
  },
  "meal_plan": [
    {"day": "Monday", "meals": {"breakfast": {...}, "lunch": {...}, "dinner": {...}, "snack": {...}}}
  ],
  "movement_protocol": {"type": "...", "frequency": "...", "details": "..."},
  "herbal_protocols": [{"herb": "...", "local_name": "...", "dosage": "...", "why": "...", "caution": "..."}],
  "supplements": [{"name": "...", "dosage": "...", "timing": "...", "why": "..."}],
  "cycle_sync_guide": {"follicular": "...", "ovulatory": "...", "luteal": "...", "menstrual": "..."},
  "sleep_stress_protocol": {"sleep_hygiene": "...", "stress_management": "...", "adaptogens": "..."},
  "tracking_guidance": [{"key": "...", "label": "...", "type": "...", "frequency": "..."}],
  "shopping_list": {"proteins": [], "vegetables": [], "herbs": [], "supplements": []},
  "progress_visualization": {"primary_metric": "...", "secondary_metrics": [...]}
}
```

## Safety Rules

- Berberine: max 1500mg/day, NOT in pregnancy
- Ashwagandha: max 600mg/day, avoid in hyperthyroidism
- Inositol: max 4000mg/day, generally safe
- Vitex: NOT with hormonal contraceptives, NOT in pregnancy
- Always include medical disclaimer
- Flag medical referral triggers: severe symptoms, suspected thyroid issues, fertility concerns
PROMPT;
    }

    /**
     * Default user prompt template for PCOS.
     */
    protected function getDefaultUserPromptTemplate(): string
    {
        return <<<'PROMPT'
Create a personalized 90-day PCOS treatment plan for {{NAME}}.

## Assessment Data
- Age: {{AGE}}
- Gender: {{GENDER}}
- PCOS Type: {{PCOS_TYPE}}
- Cycle Length: {{CYCLE_LENGTH}}
- Cycle Regularity: {{CYCLE_REGULARITY}}
- Primary Symptoms: {{PRIMARY_SYMPTOMS}}
- Fertility Goals: {{FERTILITY_GOALS}}
- Current Medications: {{CURRENT_MEDICATIONS}}
- Dietary Restrictions: {{DIETARY_RESTRICTIONS}}
- Exercise Preference: {{EXERCISE_PREFERENCE}}
- Stress Level: {{STRESS_LEVEL}}
- Sleep Quality: {{SLEEP_QUALITY}}

## Phase Progression
{{PHASE_ARC}}

## Region Profile (for localization)
{{REGION_PROFILE}}

## Instructions

1. **Root Cause Analysis**: Explain in plain, empathetic language WHY {{NAME}} has {{PCOS_TYPE}} PCOS based on their symptoms. Make it personal and actionable.

2. **Meal Plan**: Create 7 days of meals using ONLY foods from the Region Profile's staple_foods and common_proteins. Each meal should include:
   - Specific foods with portions
   - Why this helps their PCOS type
   - Preparation notes

3. **Movement Protocol**: Recommend {{EXERCISE_PREFERENCE}} exercise appropriate for {{PCOS_TYPE}} PCOS. Include frequency, duration, and progression.

4. **Herbal Protocol**: Select herbs from the Region Profile's locally_available_herbs that are appropriate for {{PCOS_TYPE}} PCOS. Include local names and safety notes.

5. **Supplements**: Recommend evidence-based supplements with specific dosages for {{PCOS_TYPE}} PCOS.

6. **Cycle Sync**: Provide guidance on aligning nutrition, movement, and rest with cycle phases (if cycles are present).

7. **Tracking**: Output structured tracking guidance as JSON array with keys: cycle_day, mood, energy, cravings, symptom_flare, weight.

8. **Shopping List**: Derive from meal plan, organized by category.

Return ONLY valid JSON matching the schema. Use {{MEASUREMENT_SYSTEM}} units throughout.
PROMPT;
    }

    /**
     * Stricter validation for PCOS plans.
     */
    protected function validatePlan(array $plan, array $assessment): void
    {
        parent::validatePlan($plan, $assessment);

        // PCOS-specific validations
        if (!isset($plan['pcos_type'])) {
            error_log("[PcosGenerator] Missing pcos_type in plan");
        }

        if (!isset($plan['cycle_sync_guide'])) {
            error_log("[PcosGenerator] Missing cycle_sync_guide (core module for PCOS)");
        }

        if (!isset($plan['phase_arc'])) {
            error_log("[PcosGenerator] Missing phase_arc (90-day progression)");
        }

        // Validate movement is included (PCOS has movement module)
        if (!isset($plan['movement_protocol'])) {
            error_log("[PcosGenerator] Missing movement_protocol (PCOS includes movement)");
        }
    }
}