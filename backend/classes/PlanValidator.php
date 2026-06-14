<?php
/**
 * PlanValidator - Validates generated treatment plans against schemas and safety rules
 * 
 * Ensures:
 * - Required modules are present for the condition
 * - Forbidden modules are absent (e.g., no workout for acne)
 * - Herb dosages are within safe limits
 * - Pregnancy/breastfeeding warnings are included where needed
 * - Localization is correct (foods/herbs from user's region)
 */

class PlanValidator
{
    private array $errors = [];
    private array $warnings = [];
    private ?array $herbSafety = null;
    private ?ModuleManifest $manifest = null;

    public function __construct()
    {
        $this->loadHerbSafety();
        $this->manifest = new ModuleManifest();
    }

    /**
     * Load the global herb safety configuration
     */
    private function loadHerbSafety(): void
    {
        $path = __DIR__ . '/../config/herb_safety.json';
        if (file_exists($path)) {
            $this->herbSafety = json_decode(file_get_contents($path), true);
        } else {
            $this->herbSafety = ['herbs' => [], 'universal_rules' => []];
        }
    }

    /**
     * Validate a complete plan
     * 
     * @param array $plan The generated plan
     * @param string $condition The condition (pcos|acne|weight|mens)
     * @param array $userProfile User context (pregnant, medications, etc.)
     * @param array|null $regionProfile User's region for localization validation
     * @return array ['valid' => bool, 'errors' => [], 'warnings' => []]
     */
    public function validate(array $plan, string $condition, array $userProfile = [], ?array $regionProfile = null): array
    {
        $this->errors = [];
        $this->warnings = [];

        // 1. Validate required modules are present
        $this->validateRequiredModules($plan, $condition);

        // 2. Validate forbidden modules are absent
        $this->validateForbiddenModules($plan, $condition);

        // 3. Validate herb/supplement safety
        $this->validateHerbSafety($plan, $userProfile);

        // 4. Validate localization if region provided
        if ($regionProfile) {
            $this->validateLocalization($plan, $regionProfile);
        }

        // 5. Validate plan structure
        $this->validatePlanStructure($plan);

        // 6. Validate compliance (no cure claims, etc.)
        $this->validateCompliance($plan);

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    /**
     * Validate that all required modules for the condition are present
     */
    private function validateRequiredModules(array $plan, string $condition): void
    {
        $required = $this->manifest->getRequiredModules($condition);

        foreach ($required as $module) {
            if (!$this->hasModule($plan, $module)) {
                $this->errors[] = "Missing required module for {$condition}: {$module}";
            }
        }
    }

    /**
     * Validate that forbidden modules are not present
     */
    private function validateForbiddenModules(array $plan, string $condition): void
    {
        $forbidden = $this->manifest->getForbiddenModules($condition);

        foreach ($forbidden as $module) {
            if ($this->hasModule($plan, $module)) {
                $this->errors[] = "Forbidden module for {$condition} present: {$module}";
            }
        }
    }

    /**
     * Check if plan has a specific module
     */
    private function hasModule(array $plan, string $module): bool
    {
        // Check in modules array
        if (isset($plan['modules']) && in_array($module, $plan['modules'])) {
            return true;
        }

        // Check by content presence
        $moduleKeys = [
            'meal_plan' => ['meals', 'meal_plan', 'breakfast', 'lunch', 'dinner'],
            'movement' => ['exercise', 'workout', 'movement', 'activity'],
            'skincare_routine' => ['skincare', 'skin_care', 'AM_routine', 'PM_routine', 'topical'],
            'herbal_protocol' => ['herbs', 'herbal', 'botanical'],
            'supplements' => ['supplements', 'supplement'],
            'cycle_sync' => ['cycle', 'phase', 'menstrual'],
            'sleep_stress' => ['sleep', 'stress', 'cortisol', 'recovery'],
            'tracking' => ['tracking', 'logging', 'metrics']
        ];

        if (!isset($moduleKeys[$module])) {
            return false;
        }

        foreach ($moduleKeys[$module] as $key) {
            if ($this->arrayContainsKey($plan, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively check if array contains a key
     */
    private function arrayContainsKey(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }
        foreach ($array as $value) {
            if (is_array($value) && $this->arrayContainsKey($value, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate herb and supplement safety
     */
    private function validateHerbSafety(array $plan, array $userProfile): void
    {
        $isPregnant = $userProfile['pregnant'] ?? false;
        $isBreastfeeding = $userProfile['breastfeeding'] ?? false;
        $medications = $userProfile['medications'] ?? [];

        // Extract all herbs/supplements from plan
        $herbs = $this->extractHerbs($plan);

        foreach ($herbs as $herb) {
            $herbName = strtolower($herb['name'] ?? '');
            $dose = $herb['dose'] ?? null;
            $doseUnit = $herb['dose_unit'] ?? 'mg';

            // Check if herb is in safety database
            $safetyKey = $this->findHerbInSafety($herbName);

            if ($safetyKey && isset($this->herbSafety['herbs'][$safetyKey])) {
                $safety = $this->herbSafety['herbs'][$safetyKey];

                // Check pregnancy safety
                if ($isPregnant && ($safety['pregnancy_unsafe'] ?? false)) {
                    $this->errors[] = "SAFETY: {$herb['name']} is unsafe during pregnancy";
                }

                // Check breastfeeding safety
                if ($isBreastfeeding && ($safety['breastfeeding_unsafe'] ?? false)) {
                    $this->errors[] = "SAFETY: {$herb['name']} is unsafe while breastfeeding";
                }

                // Check dosage limits
                if ($dose && $this->exceedsMaxDose($safetyKey, $dose, $doseUnit)) {
                    $maxDose = $this->getMaxDose($safetyKey, $doseUnit);
                    $this->errors[] = "SAFETY: {$herb['name']} dose ({$dose}{$doseUnit}) exceeds maximum ({$maxDose}{$doseUnit})";
                }

                // Check drug interactions
                if (!empty($medications) && !empty($safety['drug_interactions'])) {
                    foreach ($medications as $med) {
                        foreach ($safety['drug_interactions'] as $interaction) {
                            if (stripos($med, $interaction) !== false || stripos($interaction, $med) !== false) {
                                $this->warnings[] = "INTERACTION: {$herb['name']} may interact with {$med}";
                            }
                        }
                    }
                }
            } else {
                // Herb not in safety database - warn
                $this->warnings[] = "UNKNOWN: {$herb['name']} not in safety database - verify safety";
            }
        }
    }

    /**
     * Extract all herbs/supplements from plan
     */
    private function extractHerbs(array $plan): array
    {
        $herbs = [];

        // Look in herbal_protocol
        if (isset($plan['herbal_protocol']['herbs'])) {
            $herbs = array_merge($herbs, $plan['herbal_protocol']['herbs']);
        }
        if (isset($plan['herbs'])) {
            $herbs = array_merge($herbs, $plan['herbs']);
        }

        // Look in supplements
        if (isset($plan['supplements'])) {
            foreach ($plan['supplements'] as $supp) {
                if (isset($supp['name'])) {
                    $herbs[] = $supp;
                }
            }
        }

        return $herbs;
    }

    /**
     * Find herb in safety database (fuzzy match)
     */
    private function findHerbInSafety(string $herbName): ?string
    {
        $herbName = strtolower($herbName);

        // Direct match
        if (isset($this->herbSafety['herbs'][$herbName])) {
            return $herbName;
        }

        // Partial match
        foreach (array_keys($this->herbSafety['herbs']) as $key) {
            if (str_contains($herbName, $key) || str_contains($key, $herbName)) {
                return $key;
            }
        }

        // Common aliases
        $aliases = [
            'curcumin' => 'turmeric_curcumin',
            'turmeric' => 'turmeric_curcumin',
            'vitamin d' => 'vitamin_D',
            'd3' => 'vitamin_D',
            'fish oil' => 'omega_3',
            'epa' => 'omega_3',
            'dha' => 'omega_3'
        ];

        foreach ($aliases as $alias => $canonical) {
            if (str_contains($herbName, $alias)) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Check if dose exceeds maximum
     */
    private function exceedsMaxDose(string $herbKey, float $dose, string $unit): bool
    {
        $safety = $this->herbSafety['herbs'][$herbKey] ?? [];

        // Find the max dose for the given unit
        foreach ($safety as $key => $value) {
            if (str_starts_with($key, 'max_daily_dose_')) {
                $maxUnit = str_replace('max_daily_dose_', '', $key);
                if ($this->unitsMatch($unit, $maxUnit) && $dose > $value) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get maximum dose for unit
     */
    private function getMaxDose(string $herbKey, string $unit): ?float
    {
        $safety = $this->herbSafety['herbs'][$herbKey] ?? [];

        foreach ($safety as $key => $value) {
            if (str_starts_with($key, 'max_daily_dose_')) {
                $maxUnit = str_replace('max_daily_dose_', '', $key);
                if ($this->unitsMatch($unit, $maxUnit)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Check if units match (with common variations)
     */
    private function unitsMatch(string $unit1, string $unit2): bool
    {
        $unit1 = strtolower($unit1);
        $unit2 = strtolower($unit2);

        if ($unit1 === $unit2) {
            return true;
        }

        // Common variations
        $variations = [
            'mg' => ['mg', 'milligram'],
            'g' => ['g', 'gram', 'grams'],
            'mcg' => ['mcg', 'µg', 'microgram'],
            'IU' => ['iu', 'international_unit']
        ];

        foreach ($variations as $canonical => $vars) {
            if (
                (in_array($unit1, $vars) || $unit1 === $canonical) &&
                (in_array($unit2, $vars) || $unit2 === $canonical)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate localization (foods/herbs from user's region)
     */
    private function validateLocalization(array $plan, array $regionProfile): void
    {
        $availableHerbs = array_column($regionProfile['locally_available_herbs'] ?? [], 'name');
        $availableHerbsLower = array_map('strtolower', $availableHerbs);

        // Check herbs are from region
        $herbs = $this->extractHerbs($plan);
        foreach ($herbs as $herb) {
            $herbName = strtolower($herb['name'] ?? '');
            $found = false;

            foreach ($availableHerbsLower as $available) {
                if (str_contains($herbName, $available) || str_contains($available, $herbName)) {
                    $found = true;
                    break;
                }
            }

            // Also check local names
            foreach ($regionProfile['locally_available_herbs'] ?? [] as $herbData) {
                $localName = strtolower($herbData['local_name'] ?? '');
                if (str_contains($herbName, $localName) || str_contains($localName, $herbName)) {
                    $found = true;
                    break;
                }
            }

            if (!$found && !empty($availableHerbs)) {
                $this->warnings[] = "LOCALIZATION: {$herb['name']} may not be available in {$regionProfile['country']}";
            }
        }

        // Check measurement system is used correctly
        $expectedUnit = $regionProfile['measurement_system'] ?? 'metric';
        // This would require deeper analysis of plan content for units
    }

    /**
     * Validate overall plan structure
     */
    private function validatePlanStructure(array $plan): void
    {
        // Must have basic structure
        if (empty($plan['condition'])) {
            $this->warnings[] = "STRUCTURE: Plan missing 'condition' field";
        }

        if (empty($plan['personalized_root_cause']) && empty($plan['root_cause'])) {
            $this->warnings[] = "STRUCTURE: Plan missing root cause explanation";
        }

        // Must have medical disclaimer
        if (empty($plan['medical_disclaimer']) && empty($plan['disclaimer'])) {
            $this->warnings[] = "COMPLIANCE: Plan should include medical disclaimer";
        }
    }

    /**
     * Validate compliance (no cure claims, guaranteed outcomes, etc.)
     */
    private function validateCompliance(array $plan): void
    {
        $planText = json_encode($plan);

        // Check for cure claims
        $curePatterns = [
            '/\bcure[sd]?\b/i',
            '/\bguaranteed?\b/i',
            '/\bwill cure\b/i',
            '/\b100% effective\b/i',
            '/\bmiracle\b/i',
            '/\bmiraculous\b/i'
        ];

        foreach ($curePatterns as $pattern) {
            if (preg_match($pattern, $planText)) {
                $this->errors[] = "COMPLIANCE: Plan contains cure/guarantee language";
                break;
            }
        }

        // Check for unsafe medical advice
        $unsafePatterns = [
            '/\bprescribe\b/i',
            '/\bdosage for\b.*\bmg per\b/i', // Specific prescription-style dosing
            '/\bstop taking\b.*\bmedication\b/i'
        ];

        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $planText)) {
                $this->warnings[] = "COMPLIANCE: Plan may contain prescriptive language - review needed";
                break;
            }
        }
    }

    /**
     * Quick validation for regeneration decision
     * Returns true if plan has critical errors that require regeneration
     */
    public function needsRegeneration(array $plan, string $condition): bool
    {
        $result = $this->validate($plan, $condition);

        // Regenerate if there are safety errors or missing required modules
        foreach ($result['errors'] as $error) {
            if (str_starts_with($error, 'SAFETY:') || str_starts_with($error, 'Missing required')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get validation summary for logging
     */
    public function getSummary(array $validationResult): string
    {
        $status = $validationResult['valid'] ? 'PASS' : 'FAIL';
        $errorCount = count($validationResult['errors']);
        $warningCount = count($validationResult['warnings']);

        return "Validation: {$status} | Errors: {$errorCount} | Warnings: {$warningCount}";
    }
}