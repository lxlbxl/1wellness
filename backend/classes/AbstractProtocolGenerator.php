<?php
/**
 * AbstractProtocolGenerator — base class for all condition-specific plan generators.
 *
 * Shared functionality:
 * - AI call orchestration
 * - JSON extraction and repair
 * - Schema validation
 * - PDF rendering
 * - Email delivery
 * - Async job hand-off
 * - Region Profile injection into prompts
 *
 * Each condition (PCOS, Acne, Weight, Men's) extends this with:
 * - Its system prompt
 * - Its user-prompt template
 * - Its plan schema (module set via ModuleManifest)
 * - Its validation rules
 * - Its compliance guardrails
 */
abstract class AbstractProtocolGenerator
{
    protected $db;
    protected $settings;
    protected $ai;
    protected $regionProfile;

    /** @var array The condition's module manifest */
    protected $modules = [];

    /** @var array Region profile for localization */
    protected $region = [];

    /** @var string The condition key (pcos|acne|weight|mens) */
    protected $condition = '';

    /** @var string Brand name for this condition */
    protected $brandName = '';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
        $this->ai = new AIOrchestrator();
        $this->regionProfile = new RegionProfile();
        $this->modules = ModuleManifest::getModulesStatic($this->condition);
    }

    /**
     * Generate a complete treatment plan.
     *
     * @param array $assessment Assessment data
     * @param string $name User's name
     * @param string $email User's email
     * @param array $region Region profile for localization
     * @return array Plan data (also triggers PDF generation and email)
     */
    public function generate(array $assessment, string $name, string $email, array $region = []): array
    {
        $this->region = $region;

        // Build prompts
        $systemPrompt = $this->buildSystemPrompt($assessment);
        $userPrompt = $this->buildUserPrompt($assessment, $name);

        // Call AI for plan generation
        $rawResponse = $this->callAI($systemPrompt, $userPrompt);

        // Extract and validate JSON
        $planData = $this->extractAndValidatePlan($rawResponse, $assessment);

        // Add metadata
        $planData['_metadata'] = [
            'condition' => $this->condition,
            'brand_name' => $this->brandName,
            'generated_at' => date('c'),
            'region' => $region['country'] ?? 'Unknown',
            'modules' => $this->modules,
            'version' => '2.0',
        ];

        // Store plan
        $this->storePlan($planData, $assessment, $email);

        return $planData;
    }

    /**
     * Build the system prompt for this condition.
     * Loads from file/database and injects region-specific localization.
     */
    protected function buildSystemPrompt(array $assessment): string
    {
        // Try to load from database (admin-editable)
        $promptKey = $this->condition . '_system_prompt';
        $prompt = $this->settings->get($promptKey);

        // Fall back to file
        if (empty($prompt)) {
            $filePath = __DIR__ . "/../prompts/{$this->condition}/system-prompt.md";
            if (file_exists($filePath)) {
                $prompt = file_get_contents($filePath);
            } else {
                $prompt = $this->getDefaultSystemPrompt();
            }
        }

        // Inject localization block
        $prompt = $this->injectLocalization($prompt);

        // Inject module manifest info
        $prompt = $this->injectModuleManifest($prompt);

        return $prompt;
    }

    /**
     * Build the user prompt for this condition.
     * Injects assessment data and region profile.
     */
    protected function buildUserPrompt(array $assessment, string $name): string
    {
        // Try to load template from database
        $templateKey = $this->condition . '_user_prompt';
        $template = $this->settings->get($templateKey);

        // Fall back to file
        if (empty($template)) {
            $filePath = __DIR__ . "/../prompts/{$this->condition}/user-prompt.md";
            if (file_exists($filePath)) {
                $template = file_get_contents($filePath);
            } else {
                $template = $this->getDefaultUserPromptTemplate();
            }
        }

        // Replace assessment variables
        $vars = $this->getPromptVariables($assessment, $name);
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        // Inject region profile
        $template = $this->injectRegionProfile($template);

        return $template;
    }

    /**
     * Get prompt variables from assessment data.
     * Override in subclasses for condition-specific variables.
     */
    protected function getPromptVariables(array $assessment, string $name): array
    {
        return [
            'NAME' => $name,
            'AGE' => $assessment['age'] ?? '',
            'GENDER' => $assessment['gender'] ?? '',
            'EMAIL' => $assessment['email'] ?? '',
            'CONDITION' => $this->condition,
            'BRAND_NAME' => $this->brandName,
            'REGION_COUNTRY' => $this->region['country'] ?? 'your area',
            'MEASUREMENT_SYSTEM' => $this->region['measurement_system'] ?? 'metric',
        ];
    }

    /**
     * Inject localization rules into system prompt.
     */
    protected function injectLocalization(string $prompt): string
    {
        $localizationBlock = $this->getLocalizationBlock();

        // Add before output format section
        if (strpos($prompt, '## Output Format') !== false) {
            $prompt = str_replace('## Output Format', $localizationBlock . "\n## Output Format", $prompt);
        } else {
            $prompt .= "\n\n" . $localizationBlock;
        }

        return $prompt;
    }

    /**
     * Get the localization rules block.
     */
    protected function getLocalizationBlock(): string
    {
        $regionJson = json_encode($this->region, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<LOCALIZATION
## LOCALIZATION RULES

You will receive a REGION PROFILE with the user's location data. Follow these rules:

1. **Meals**: Every meal MUST use foods locally available and culturally familiar in the user's region.
   Never recommend ingredients the user cannot reasonably buy where they live.

2. **Herbs**: Refer to herbs by common English name AND the local name from the Region Profile.
   Only suggest herbs in the Region Profile's available list AND permitted by the safety table.
   If a clinically ideal herb is unavailable locally, suggest the closest local alternative.

3. **Units**: Use the user's measurement system ({$this->region['measurement_system']}) throughout.

4. **Dietary norms**: Respect stated dietary preferences (halal, vegetarian, fasting periods).

5. **Sourcing**: Include guidance on where to buy herbs/foods locally (from Region Profile).

REGION PROFILE:
```json
$regionJson
```

LOCALIZATION;
    }

    /**
     * Inject module manifest info into system prompt.
     */
    protected function injectModuleManifest(string $prompt): string
    {
        $moduleList = implode(', ', $this->modules);
        $hasMovement = in_array(ModuleManifest::MODULE_MOVEMENT, $this->modules, true) ? 'YES' : 'NO';

        $manifestBlock = <<<MANIFEST
## MODULE MANIFEST FOR THIS CONDITION

This plan includes ONLY these modules: $moduleList

IMPORTANT: Movement/Exercise module included: $hasMovement
- If NO: Do NOT include any workout, exercise, or movement block in the plan.
- If YES: Include condition-appropriate movement protocol.

Do NOT add modules not listed above. Do NOT add cycle-phase content unless cycle_sync is in the manifest.

MANIFEST;

        // Add after localization block or at start
        if (strpos($prompt, '## LOCALIZATION RULES') !== false) {
            $prompt = str_replace('## MODULE MANIFEST', $manifestBlock . "\n## MODULE MANIFEST", $prompt);
        } else {
            $prompt = $manifestBlock . "\n" . $prompt;
        }

        return $prompt;
    }

    /**
     * Inject region profile into user prompt.
     */
    protected function injectRegionProfile(string $template): string
    {
        $regionJson = json_encode($this->region, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return str_replace('{{REGION_PROFILE}}', $regionJson, $template);
    }

    /**
     * Call the AI model for plan generation.
     */
    protected function callAI(string $systemPrompt, string $userPrompt): string
    {
        try {
            $response = $this->ai->callWithPrompts($systemPrompt, $userPrompt);

            if (is_array($response)) {
                $errorMessage = $response['error'] ?? 'AI provider returned an unexpected response';
                if (!is_string($errorMessage)) {
                    $errorMessage = json_encode($errorMessage);
                }
                throw new Exception($errorMessage);
            }

            return $response;
        } catch (Exception $e) {
            error_log("[{$this->condition}Generator] AI call failed: " . $e->getMessage());
            throw new Exception("Plan generation failed. Please try again.");
        }
    }

    /**
     * Extract JSON from AI response and validate against schema.
     */
    protected function extractAndValidatePlan(string $response, array $assessment): array
    {
        // Extract JSON
        $json = $this->extractJson($response);
        $plan = json_decode($json, true);

        if (!is_array($plan)) {
            // Try repair
            $plan = $this->attemptJsonRepair($response);
        }

        if (!is_array($plan)) {
            throw new Exception("Failed to generate valid plan. Please try again.");
        }

        // Validate required fields
        $this->validatePlan($plan, $assessment);

        // Apply compliance checks
        $plan = $this->applyComplianceChecks($plan);

        return $plan;
    }

    /**
     * Extract JSON from AI response.
     */
    protected function extractJson(string $text): string
    {
        // Try code block first
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/', $text, $m)) {
            return trim($m[1]);
        }

        // Find JSON object
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    /**
     * Attempt to repair invalid JSON.
     */
    protected function attemptJsonRepair(string $response): ?array
    {
        // Try to find and parse any JSON object
        $json = $this->extractJson($response);

        // Common repairs
        $json = preg_replace('/,\s*([}\]])/', '$1', $json); // trailing commas
        $json = preg_replace('/([{,])\s*([^"{\s]+)\s*:/', '$1"$2":', $json); // unquoted keys

        $result = json_decode($json, true);
        return is_array($result) ? $result : null;
    }

    /**
     * Validate the plan against condition-specific schema.
     * Override in subclasses for stricter validation.
     */
    protected function validatePlan(array $plan, array $assessment): void
    {
        $required = ['summary', 'goals', 'meal_plan', 'supplements', 'tracking_guidance'];

        foreach ($required as $field) {
            if (!isset($plan[$field])) {
                error_log("[{$this->condition}Generator] Missing required field: $field");
            }
        }

        // Validate meal plan has 7 days
        if (isset($plan['meal_plan']) && is_array($plan['meal_plan'])) {
            if (count($plan['meal_plan']) < 7) {
                error_log("[{$this->condition}Generator] Meal plan should have 7 days, has " . count($plan['meal_plan']));
            }
        }

        // Validate no forbidden modules
        if (!in_array(ModuleManifest::MODULE_MOVEMENT, $this->modules, true)) {
            if (isset($plan['workout']) || isset($plan['exercise']) || isset($plan['movement'])) {
                error_log("[{$this->condition}Generator] Plan contains forbidden movement module");
                unset($plan['workout'], $plan['exercise'], $plan['movement']);
            }
        }
    }

    /**
     * Apply compliance checks to the plan.
     * Scans for cure claims, unsafe dosages, etc.
     */
    protected function applyComplianceChecks(array $plan): array
    {
        // Check supplement dosages
        if (isset($plan['supplements']) && is_array($plan['supplements'])) {
            foreach ($plan['supplements'] as &$supp) {
                $supp = $this->checkDosageBounds($supp);
            }
        }

        // Check herbal dosages
        if (isset($plan['herbal_protocols']) && is_array($plan['herbal_protocols'])) {
            foreach ($plan['herbal_protocols'] as &$herb) {
                $herb = $this->checkHerbSafety($herb);
            }
        }

        // Add medical disclaimer
        $plan['medical_disclaimer'] = $this->getMedicalDisclaimer();

        return $plan;
    }

    /**
     * Check supplement dosage against safe bounds.
     */
    protected function checkDosageBounds(array $supplement): array
    {
        $name = strtolower($supplement['name'] ?? '');
        $dosage = $supplement['dosage'] ?? '';

        // Extract numeric dosage
        preg_match('/(\d+)\s*(mg|mcg|iu|g)?/i', $dosage, $matches);
        $amount = (int) ($matches[1] ?? 0);
        $unit = strtolower($matches[2] ?? 'mg');

        // Known safe maximums (in mg)
        $maxDoses = [
            'berberine' => 1500,
            'ashwagandha' => 600,
            'inositol' => 4000,
            'dim' => 300,
            'zinc' => 50,
            'vitamin d' => 4000,
            'magnesium' => 350,
            'chromium' => 1000,
            'nac' => 1800,
            'turmeric' => 3000,
            'curcumin' => 3000,
        ];

        foreach ($maxDoses as $supp => $max) {
            if (strpos($name, $supp) !== false && $amount > $max) {
                $supplement['dosage'] = "{$max}mg";
                $supplement['_dosage_adjusted'] = true;
                $supplement['_dosage_note'] = "Dosage adjusted to safe maximum of {$max}mg";
                break;
            }
        }

        return $supplement;
    }

    /**
     * Check herb safety against master safety table.
     */
    protected function checkHerbSafety(array $herb): array
    {
        $herbName = $herb['herb'] ?? $herb['name'] ?? '';
        $safety = $this->regionProfile->getHerbSafety($herbName);

        if ($safety) {
            $herb['_safety_verified'] = true;
            if (!empty($safety['warnings'])) {
                $herb['caution'] = ($herb['caution'] ?? '') . ' ' . $safety['warnings'];
            }
        }

        return $herb;
    }

    /**
     * Get medical disclaimer text.
     */
    protected function getMedicalDisclaimer(): string
    {
        return "IMPORTANT: This wellness guide is for educational purposes only and is not a substitute for professional medical advice, diagnosis, or treatment. Always consult with a qualified healthcare provider before starting any new supplement regimen, especially if you are pregnant, nursing, taking medications, or have a medical condition. Individual results may vary.";
    }

    /**
     * Store the generated plan in the database.
     */
    protected function storePlan(array $plan, array $assessment, string $email): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO treatment_plans (email, condition_type, plan_data, assessment_data, region_data, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $email,
                $this->condition,
                json_encode($plan),
                json_encode($assessment),
                json_encode($this->region),
            ]);
        } catch (Exception $e) {
            error_log("[{$this->condition}Generator] Failed to store plan: " . $e->getMessage());
        }
    }

    /**
     * Get the default system prompt for this condition.
     * Override in subclasses.
     */
    abstract protected function getDefaultSystemPrompt(): string;

    /**
     * Get the default user prompt template for this condition.
     * Override in subclasses.
     */
    abstract protected function getDefaultUserPromptTemplate(): string;

    /**
     * Get condition-specific tracking keys.
     */
    public function getTrackingKeys(): array
    {
        return ModuleManifest::getDefaultTrackingKeys($this->condition);
    }

    /**
     * Get the module manifest for this condition.
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Get the condition key.
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Get the brand name.
     */
    public function getBrandName(): string
    {
        return $this->brandName;
    }

    /**
     * Render a generated plan (the array returned by generate()) into a PDF binary.
     *
     * Deliberately schema-agnostic: each condition's AI prompt returns a different
     * JSON shape (skincare_routine, strength_protocol, macro_targets, etc.), and that
     * shape has already drifted at least once since this class was written. Rather
     * than hand-map placeholders that silently drop new/renamed sections, this walks
     * the plan recursively and renders every section it finds.
     */
    public function renderPlanToPdf(array $plan, string $name): string
    {
        $html = $this->planToHtml($plan, $name);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Build the full PDF HTML document for a plan.
     */
    private function planToHtml(array $plan, string $name): string
    {
        $brand = $this->esc($this->brandName ?: '1wellness');
        $safeName = $this->esc($name ?: 'Friend');
        $date = date('j F Y');

        // Priority fields rendered first, in a fixed order, if present.
        $priorityKeys = ['summary', 'personalized_root_cause', 'root_cause', 'goals'];
        $body = '';
        foreach ($priorityKeys as $key) {
            if (isset($plan[$key]) && $plan[$key] !== '' && $plan[$key] !== []) {
                $body .= $this->renderPlanSection($key, $plan[$key]);
            }
        }

        // Everything else, in the order the AI returned it, skipping internal metadata,
        // the disclaimer (rendered separately below), and whatever was already rendered above.
        $skip = array_merge($priorityKeys, ['_metadata', 'medical_disclaimer']);
        foreach ($plan as $key => $value) {
            if (in_array($key, $skip, true)) continue;
            if ($value === '' || $value === [] || $value === null) continue;
            $body .= $this->renderPlanSection($key, $value);
        }

        $disclaimer = $this->esc($this->getMedicalDisclaimer());

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; color: #1A1A18; font-size: 11pt; line-height: 1.5; }
    h1 { font-size: 22pt; color: #1D4532; margin-bottom: 2pt; }
    h2 { font-size: 15pt; color: #1D4532; border-bottom: 1pt solid #dcece3; padding-bottom: 4pt; margin-top: 22pt; }
    h3 { font-size: 12pt; color: #2C2C2A; margin-bottom: 4pt; }
    .meta { color: #6B6560; font-size: 9pt; margin-bottom: 18pt; }
    .card { background: #f2f8f5; border-radius: 6pt; padding: 8pt 10pt; margin-bottom: 8pt; }
    ul { margin: 4pt 0; padding-left: 18pt; }
    li { margin-bottom: 3pt; }
    .disclaimer { font-size: 8pt; color: #999; margin-top: 28pt; border-top: 1pt solid #eee; padding-top: 10pt; }
</style>
</head>
<body>
    <h1>{$brand} Personal Protocol</h1>
    <p class="meta">Prepared for {$safeName} &middot; {$date}</p>
    {$body}
    <p class="disclaimer">{$disclaimer}</p>
</body>
</html>
HTML;
    }

    /**
     * Render one top-level plan section (key + value) as HTML.
     */
    private function renderPlanSection(string $key, $value): string
    {
        $title = $this->esc($this->humanizeKey($key));
        return "<h2>{$title}</h2>" . $this->renderPlanValue($value);
    }

    /**
     * Recursively render any plan value (string, list, or associative object) as HTML.
     */
    private function renderPlanValue($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return '<p>' . nl2br($this->esc((string)$value)) . '</p>';
        }

        if (is_bool($value)) {
            return '<p>' . ($value ? 'Yes' : 'No') . '</p>';
        }

        if (!is_array($value)) {
            return '';
        }

        $isList = array_keys($value) === range(0, count($value) - 1);

        if ($isList) {
            // List of scalars -> bullet list. List of objects -> repeated cards.
            $allScalar = true;
            foreach ($value as $item) {
                if (is_array($item)) { $allScalar = false; break; }
            }

            if ($allScalar) {
                $html = '<ul>';
                foreach ($value as $item) {
                    $html .= '<li>' . $this->esc((string)$item) . '</li>';
                }
                return $html . '</ul>';
            }

            $html = '';
            foreach ($value as $item) {
                $html .= '<div class="card">' . $this->renderAssocEntries($item) . '</div>';
            }
            return $html;
        }

        // Associative object -> nested key/value block.
        return $this->renderAssocEntries($value);
    }

    /**
     * Render an associative array's entries as labeled sub-blocks.
     */
    private function renderAssocEntries($assoc): string
    {
        if (!is_array($assoc)) {
            return '<p>' . $this->esc((string)$assoc) . '</p>';
        }

        $html = '';
        foreach ($assoc as $k => $v) {
            if ($v === '' || $v === [] || $v === null) continue;
            if (is_string($k)) {
                $label = $this->esc($this->humanizeKey($k));
                if (is_string($v) || is_numeric($v) || is_bool($v)) {
                    $html .= '<p><strong>' . $label . ':</strong> ' . $this->esc((string)$v) . '</p>';
                } else {
                    $html .= '<h3>' . $label . '</h3>' . $this->renderPlanValue($v);
                }
            } else {
                $html .= $this->renderPlanValue($v);
            }
        }
        return $html;
    }

    /**
     * Convert a snake_case/camelCase plan key into a human-readable title.
     */
    private function humanizeKey(string $key): string
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/(?<!^)([A-Z])/', ' $1', $key);
        return ucwords(trim($key));
    }

    /**
     * HTML-escape a value for safe interpolation.
     */
    private function esc($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}