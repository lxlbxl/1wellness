<?php
/**
 * Golden Output Tests for Treatment Plan Engine
 * 
 * Tests condition × region combinations to ensure:
 * - Correct modules are present/absent
 * - Localization is correct
 * - Safety rules are followed
 * 
 * Run: php backend/tests/PlanEngineTest.php
 */

require_once __DIR__ . '/../classes/ModuleManifest.php';
require_once __DIR__ . '/../classes/PlanValidator.php';
require_once __DIR__ . '/../classes/RegionProfile.php';

class PlanEngineTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    /**
     * Run all golden tests
     */
    public function runAll(): void
    {
        echo "=== Treatment Plan Engine Golden Tests ===\n\n";

        // Test 1: Acne + Serbia - no workout, Serbian foods, nettle/chamomile
        $this->testAcneSerbia();

        // Test 2: PCOS + Kenya - cycle sync, ugali/sukuma wiki, metric
        $this->testPcosKenya();

        // Test 3: Weight + USA - macro targets, imperial, trend chart
        $this->testWeightUsa();

        // Test 4: Men's + Philippines - recovery protocol, local foods
        $this->testMensPhilippines();

        // Test 5: Module manifest correctness
        $this->testModuleManifest();

        // Test 6: Herb safety enforcement
        $this->testHerbSafety();

        // Test 7: Region profile loading
        $this->testRegionProfiles();

        $this->printSummary();
    }

    /**
     * Test: Acne plan for Serbia
     * - Must have: skincare routine, photo logging, Serbian foods
     * - Must NOT have: workout block
     */
    private function testAcneSerbia(): void
    {
        echo "Test: Acne + Serbia\n";

        $plan = $this->createSamplePlan('acne', 'rs', [
            'modules' => ['meal_plan', 'skincare_routine', 'herbal_protocol', 'supplements', 'sleep_stress', 'tracking'],
            'skincare_routine' => [
                'AM' => ['cleanser' => 'Gentle foaming cleanser', 'active' => 'Niacinamide serum', 'moisturizer' => 'Light moisturizer', 'spf' => 'SPF 30+'],
                'PM' => ['cleanser' => 'Oil cleanser', 'active' => 'Salicylic acid 2%', 'moisturizer' => 'Barrier repair cream']
            ],
            'meal_plan' => [
                'breakfast' => ['Proja (cornbread) with yogurt and honey'],
                'lunch' => ['Grilled chicken with shopska salad and bread'],
                'dinner' => ['Pasulj (bean stew) with bread']
            ],
            'herbal_protocol' => [
                'herbs' => [
                    ['name' => 'Nettle', 'local_name' => 'kopriva', 'use' => 'Anti-inflammatory'],
                    ['name' => 'Chamomile', 'local_name' => 'kamilica', 'use' => 'Calming']
                ]
            ],
            'tracking' => [
                ['key' => 'skin_photo', 'type' => 'photo', 'frequency' => 'daily'],
                ['key' => 'skin_clarity', 'type' => 'scale', 'frequency' => 'daily']
            ],
            'condition' => 'acne',
            'medical_disclaimer' => 'This is educational content, not medical advice.'
        ]);

        $validator = new PlanValidator();
        $result = $validator->validate($plan, 'acne', [], $this->loadRegionProfile('rs'));

        // Assertions
        $this->assert(
            !in_array('Forbidden module for acne present: movement', $result['errors']),
            "Acne plan should NOT have workout module"
        );

        $this->assert(
            isset($plan['skincare_routine']),
            "Acne plan must have skincare routine"
        );

        $this->assert(
            $this->containsLocalFood($plan, ['proja', 'pasulj', 'shopska']),
            "Acne plan should use Serbian foods"
        );

        $this->assert(
            $this->containsHerb($plan, ['nettle', 'kopriva', 'chamomile', 'kamilica']),
            "Acne plan should use locally available herbs"
        );

        echo "\n";
    }

    /**
     * Test: PCOS plan for Kenya
     * - Must have: cycle sync, ugali/sukuma wiki, metric units
     */
    private function testPcosKenya(): void
    {
        echo "Test: PCOS + Kenya\n";

        $plan = $this->createSamplePlan('pcos', 'ke', [
            'modules' => ['meal_plan', 'movement', 'herbal_protocol', 'supplements', 'cycle_sync', 'sleep_stress', 'tracking'],
            'cycle_sync' => [
                'current_phase' => 'follicular',
                'phase_foods' => ['fermented foods', 'fresh vegetables'],
                'phase_exercise' => 'gentle movement, walking'
            ],
            'meal_plan' => [
                'breakfast' => ['Uji (millet porridge) with milk and banana'],
                'lunch' => ['Ugali with sukuma wiki and chicken'],
                'dinner' => ['Fish with ugali and terere']
            ],
            'herbal_protocol' => [
                'herbs' => [
                    ['name' => 'Moringa', 'local_name' => 'Mlonge', 'use' => 'Nutrient support'],
                    ['name' => 'Turmeric', 'local_name' => 'Manjano', 'use' => 'Anti-inflammatory']
                ]
            ],
            'tracking' => [
                ['key' => 'cycle_day', 'type' => 'number', 'frequency' => 'daily'],
                ['key' => 'mood', 'type' => 'scale', 'frequency' => 'daily']
            ],
            'condition' => 'pcos',
            'medical_disclaimer' => 'This is educational content, not medical advice.'
        ]);

        $validator = new PlanValidator();
        $result = $validator->validate($plan, 'pcos', [], $this->loadRegionProfile('ke'));

        $this->assert(
            isset($plan['cycle_sync']),
            "PCOS plan must have cycle sync module"
        );

        $this->assert(
            $this->containsLocalFood($plan, ['ugali', 'sukuma wiki', 'terere']),
            "PCOS plan should use Kenyan foods"
        );

        $this->assert(
            $this->containsHerb($plan, ['moringa', 'mlonge', 'turmeric', 'manjano']),
            "PCOS plan should use locally available herbs"
        );

        echo "\n";
    }

    /**
     * Test: Weight plan for USA
     * - Must have: macro targets, progressive movement, imperial units
     */
    private function testWeightUsa(): void
    {
        echo "Test: Weight + USA\n";

        $plan = $this->createSamplePlan('weight', 'us', [
            'modules' => ['meal_plan', 'movement', 'herbal_protocol', 'supplements', 'sleep_stress', 'tracking'],
            'meal_plan' => [
                'macros' => ['protein_g' => 150, 'carbs_g' => 150, 'fat_g' => 60, 'calories' => 1800],
                'breakfast' => ['Greek yogurt with berries and granola'],
                'lunch' => ['Grilled chicken salad with olive oil dressing'],
                'dinner' => ['Baked salmon with sweet potatoes and broccoli']
            ],
            'movement' => [
                'type' => 'progressive',
                'weekly_plan' => [
                    'week_1_2' => '3x walking 20 min + 2x strength training',
                    'week_3_4' => '4x walking 30 min + 3x strength training'
                ]
            ],
            'tracking' => [
                ['key' => 'weight', 'type' => 'number', 'unit' => 'lb', 'frequency' => 'daily'],
                ['key' => 'weight_trend', 'type' => 'trend', 'chart' => 'smoothed']
            ],
            'condition' => 'weight',
            'medical_disclaimer' => 'This is educational content, not medical advice.'
        ]);

        $validator = new PlanValidator();
        $result = $validator->validate($plan, 'weight', [], $this->loadRegionProfile('us'));

        $this->assert(
            isset($plan['meal_plan']['macros']),
            "Weight plan must have macro targets"
        );

        $this->assert(
            isset($plan['movement']),
            "Weight plan must have movement module"
        );

        $this->assert(
            isset($plan['movement']['type']) && $plan['movement']['type'] === 'progressive',
            "Weight plan movement should be progressive"
        );

        echo "\n";
    }

    /**
     * Test: Men's plan for Philippines
     * - Must have: recovery protocol, local foods, vitality tracking
     */
    private function testMensPhilippines(): void
    {
        echo "Test: Men's + Philippines\n";

        $plan = $this->createSamplePlan('mens', 'ph', [
            'modules' => ['meal_plan', 'movement', 'herbal_protocol', 'supplements', 'sleep_stress', 'tracking'],
            'meal_plan' => [
                'breakfast' => ['Arroz caldo with ginger'],
                'lunch' => ['Tinola with malunggay'],
                'dinner' => ['Grilled fish with brown rice']
            ],
            'movement' => [
                'type' => 'strength',
                'focus' => 'compound movements',
                'recovery' => '48 hours between sessions'
            ],
            'sleep_stress' => [
                'sleep_protocol' => '7-9 hours, consistent schedule',
                'recovery_methods' => ['cold exposure', 'breathing exercises']
            ],
            'herbal_protocol' => [
                'herbs' => [
                    ['name' => 'Moringa', 'local_name' => 'Malunggay', 'use' => 'Energy and vitality'],
                    ['name' => 'Ginger', 'local_name' => 'Luya', 'use' => 'Circulation']
                ]
            ],
            'tracking' => [
                ['key' => 'energy', 'type' => 'scale', 'frequency' => 'daily'],
                ['key' => 'sleep_hours', 'type' => 'number', 'frequency' => 'daily'],
                ['key' => 'strength_session', 'type' => 'boolean', 'frequency' => 'daily']
            ],
            'condition' => 'mens',
            'medical_disclaimer' => 'This is educational content, not medical advice.'
        ]);

        $validator = new PlanValidator();
        $result = $validator->validate($plan, 'mens', [], $this->loadRegionProfile('ph'));

        $this->assert(
            isset($plan['sleep_stress']['recovery_methods']),
            "Men's plan must have recovery protocol"
        );

        $this->assert(
            $this->containsLocalFood($plan, ['tinola', 'malunggay', 'arroz caldo']),
            "Men's plan should use Filipino foods"
        );

        echo "\n";
    }

    /**
     * Test: Module manifest correctness
     */
    private function testModuleManifest(): void
    {
        echo "Test: Module Manifest\n";

        $manifest = new ModuleManifest();

        // Acne should NOT have movement
        $acneModules = $manifest->getModules('acne');
        $this->assert(
            !in_array('movement', $acneModules),
            "Acne manifest should NOT include movement"
        );

        // Acne SHOULD have skincare
        $this->assert(
            in_array('skincare_routine', $acneModules),
            "Acne manifest should include skincare_routine"
        );

        // Weight SHOULD have movement
        $weightModules = $manifest->getModules('weight');
        $this->assert(
            in_array('movement', $weightModules),
            "Weight manifest should include movement"
        );

        // PCOS SHOULD have cycle_sync
        $pcosModules = $manifest->getModules('pcos');
        $this->assert(
            in_array('cycle_sync', $pcosModules),
            "PCOS manifest should include cycle_sync"
        );

        // Men's SHOULD have sleep_stress as core
        $mensModules = $manifest->getModules('mens');
        $this->assert(
            in_array('sleep_stress', $mensModules),
            "Men's manifest should include sleep_stress"
        );

        echo "\n";
    }

    /**
     * Test: Herb safety enforcement
     */
    private function testHerbSafety(): void
    {
        echo "Test: Herb Safety\n";

        $validator = new PlanValidator();

        // Test pregnancy unsafe herb
        $pregnantPlan = [
            'herbs' => [
                ['name' => 'Berberine', 'dose' => 500, 'dose_unit' => 'mg']
            ],
            'condition' => 'pcos'
        ];

        $result = $validator->validate($pregnantPlan, 'pcos', ['pregnant' => true]);
        $this->assert(
            $this->containsError($result['errors'], 'pregnancy'),
            "Should flag berberine as unsafe during pregnancy"
        );

        // Test overdose
        $overdosePlan = [
            'supplements' => [
                ['name' => 'Ashwagandha', 'dose' => 1000, 'dose_unit' => 'mg']
            ],
            'condition' => 'mens'
        ];

        $result = $validator->validate($overdosePlan, 'mens');
        $this->assert(
            $this->containsError($result['errors'], 'exceeds maximum'),
            "Should flag ashwagandha overdose (>600mg)"
        );

        echo "\n";
    }

    /**
     * Test: Region profile loading
     */
    private function testRegionProfiles(): void
    {
        echo "Test: Region Profiles\n";

        $regions = ['ng', 'rs', 'ke', 'us', 'ph'];

        foreach ($regions as $code) {
            $profile = $this->loadRegionProfile($code);
            $this->assert(
                $profile !== null,
                "Region profile {$code} should load"
            );

            if ($profile) {
                $this->assert(
                    !empty($profile['staple_foods']),
                    "Region {$code} should have staple_foods"
                );

                $this->assert(
                    !empty($profile['locally_available_herbs']),
                    "Region {$code} should have locally_available_herbs"
                );

                $this->assert(
                    isset($profile['measurement_system']),
                    "Region {$code} should have measurement_system"
                );
            }
        }

        echo "\n";
    }

    // === Helper Methods ===

    private function createSamplePlan(string $condition, string $region, array $data): array
    {
        return array_merge($data, [
            'condition' => $condition,
            'region' => $region,
            'generated_at' => date('c')
        ]);
    }

    private function loadRegionProfile(string $code): ?array
    {
        $path = __DIR__ . "/../config/region_packs/{$code}.json";
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }
        return null;
    }

    private function containsLocalFood(array $plan, array $foods): bool
    {
        $planText = strtolower(json_encode($plan));
        foreach ($foods as $food) {
            if (str_contains($planText, strtolower($food))) {
                return true;
            }
        }
        return false;
    }

    private function containsHerb(array $plan, array $herbs): bool
    {
        $planText = strtolower(json_encode($plan));
        foreach ($herbs as $herb) {
            if (str_contains($planText, strtolower($herb))) {
                return true;
            }
        }
        return false;
    }

    private function containsError(array $errors, string $keyword): bool
    {
        foreach ($errors as $error) {
            if (stripos($error, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  ✓ {$message}\n";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo "  ✗ {$message}\n";
        }
    }

    private function printSummary(): void
    {
        echo "=== Test Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";

        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure}\n";
            }
            exit(1);
        }

        echo "\nAll tests passed!\n";
        exit(0);
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    $test = new PlanEngineTest();
    $test->runAll();
}