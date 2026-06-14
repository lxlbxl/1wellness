<?php
// Initializing additional columns if missing
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    if ($conn !== null) {
        $hasTrigger = false;
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'mysql') {
                $stmt = $conn->query("SHOW COLUMNS FROM daily_plans LIKE 'trigger_type'");
                $hasTrigger = ($stmt->fetch() !== false);
            } elseif ($driver === 'pgsql') {
                $stmt = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='daily_plans' AND column_name='trigger_type'");
                $hasTrigger = ($stmt->fetch() !== false);
            } else {
                $cols = $db->fetchAll("PRAGMA table_info(daily_plans)");
                foreach ($cols as $c)
                    if ($c['name'] === 'trigger_type')
                        $hasTrigger = true;
            }

            if (!$hasTrigger) {
                $db->query("ALTER TABLE daily_plans ADD COLUMN trigger_type VARCHAR(20) DEFAULT 'auto'");
            }
        } catch (Exception $e) {
            // Schema migration errors are non-fatal
        }
    }
} catch (Exception $e) {
    // Ignore errors if table doesn't exist yet or other schema issues
}

class MealPlanner
{
    private $db;
    private $ai;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ai = new AIOrchestrator();
    }

    public function calculateCyclePhase($lastPeriodDate, $cycleLength = 28, $targetDate = null)
    {
        if (!$lastPeriodDate || $lastPeriodDate === '0000-00-00') {
            return ['phase' => 'General', 'day' => 1];
        }
        $lastPeriod = new DateTime($lastPeriodDate);
        $now = new DateTime($targetDate ?? 'now');
        $dayOfCycle = ($now->diff($lastPeriod)->days % ($cycleLength ?: 28)) + 1;

        if ($dayOfCycle <= 5)
            return ['phase' => 'Menstrual', 'day' => $dayOfCycle];
        if ($dayOfCycle <= 14)
            return ['phase' => 'Follicular', 'day' => $dayOfCycle];
        if ($dayOfCycle <= 17)
            return ['phase' => 'Ovulatory', 'day' => $dayOfCycle];
        return ['phase' => 'Luteal', 'day' => $dayOfCycle];
    }

    public function getFunnelPhase($condition, $profile)
    {
        switch ($condition) {
            case 'acne':
                return ['phase' => 'Skin Healing', 'focus' => 'Reduce inflammation, balance microbiome'];
            case 'weight':
                return ['phase' => 'Metabolic Activation', 'focus' => 'Boost metabolism, regulate appetite'];
            case 'mens':
                return ['phase' => 'Vitality Optimization', 'focus' => 'Support testosterone, increase energy'];
            default:
                return $this->calculateCyclePhase(
                    $profile['last_period_date'] ?? null,
                    $profile['cycle_length'] ?? 28
                );
        }
    }

    public function getTodayPlan($userId, $dateStr = null)
    {
        $today = $dateStr ?? date('Y-m-d');
        // Check DB first
        $sql = "SELECT * FROM daily_plans WHERE user_id = :uid AND plan_date = :date";
        $plan = $this->db->fetch($sql, [':uid' => $userId, ':date' => $today]);

        if ($plan) {
            return json_decode($plan['plan_data'], true);
        }

        // if not exists, generate one
        return $this->generateDailyPlan($userId, $today);
    }

    public function generateDailyPlan($userId, $date, $triggerType = 'auto')
    {
        $startTime = microtime(true);
        $logId = $this->db->insert('ai_generation_logs', [
            'user_id' => $userId,
            'action' => 'daily_plan',
            'target_date' => $date,
            'status' => 'generating',
            'metadata' => json_encode(['trigger' => $triggerType])
        ]);

        try {
            // 1. Get User Profile
            $profile = $this->db->fetch("SELECT * FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);
            if (!$profile) {
                $profile = ['pcos_type' => 'General', 'allergies' => 'None', 'dietary_preferences' => 'None', 'condition_type' => 'pcos'];
            }

            // Calculate Context Variables
            $programStart = new DateTime($profile['start_date'] ?? 'now');
            $now = new DateTime($date);
            $daysIn = $now->diff($programStart)->days + 1;
            $programWeek = ceil($daysIn / 7);

            // Funnel-specific context
            $conditionType = $profile['condition_type'] ?? 'pcos';
            if (!$conditionType)
                $conditionType = 'pcos'; // Fallback if empty string

            $vars = [
                'CONDITION_TYPE' => strtoupper($conditionType),
                'ALLERGIES' => !empty($profile['allergies']) ? $profile['allergies'] : 'None',
                'PREFERENCES' => !empty($profile['dietary_preferences']) ? $profile['dietary_preferences'] : 'None',
                'PROGRAM_WEEK' => "Week $programWeek",
                'DATE' => $date
            ];

            // Specific context for PCOS
            if ($conditionType === 'pcos') {
                $cycleData = $this->calculateCyclePhase($profile['last_period_date'] ?? 'now', $profile['cycle_length'] ?? 28);
                $vars['CYCLE_PHASE'] = $cycleData['phase'];
                $vars['PCOS_TYPE'] = !empty($profile['pcos_type']) ? $profile['pcos_type'] : 'General';
            }

            // 3. Prepare Prompt
            $promptKey = $conditionType . '_meal_planner';

            // Try to get specific prompt, fallback to pcos_meal_planner
            $systemPrompt = $this->db->fetch("SELECT prompt_text FROM system_prompts WHERE prompt_key = ?", [$promptKey]);
            if (!$systemPrompt) {
                $promptKey = 'pcos_meal_planner';
            }

            // Prepare personal context string
            $contextStr = "Profile Context:\n";
            $contextStr .= "- Condition: " . ($vars['CONDITION_TYPE'] ?? 'General') . "\n";
            if (isset($vars['PCOS_TYPE']))
                $contextStr .= "- PCOS Type: " . $vars['PCOS_TYPE'] . "\n";
            if (isset($vars['CYCLE_PHASE']))
                $contextStr .= "- Cycle Phase: " . $vars['CYCLE_PHASE'] . "\n";
            $contextStr .= "- Allergies: " . ($vars['ALLERGIES'] ?? 'None') . "\n";
            $contextStr .= "- Preferences: " . ($vars['PREFERENCES'] ?? 'None') . "\n";
            $contextStr .= "- Program Week: " . ($vars['PROGRAM_WEEK'] ?? '1') . "\n";
            $contextStr .= "- Location/Cuisine: Nigerian (unless preferences state otherwise)\n";

            // Build funnel-specific prompt
            $pcosTypeStr = $vars['PCOS_TYPE'] ?? 'General';
            $cyclePhaseStr = $vars['CYCLE_PHASE'] ?? 'Follicular';
            $cycleDayStr = isset($cycleData['day']) ? $cycleData['day'] . "/28" : "Day 1";

            $funnelName = strtoupper($conditionType);

            // Define funnel-specific prompt templates
            $funnelPrompts = [
                'pcos' => "
            Generate a personalized daily PCOS meal plan for this user:
            - Profile: {$pcosTypeStr} Type PCOS
            - Cycle Phase: {$cyclePhaseStr} ({$cycleDayStr})
            - Goal: Support hormonal balance
            
            CRITICAL REQUIREMENTS:
            1. MEALS: All meals MUST be Nigerian/West African dishes (e.g., Moi Moi, Egusi, Jollof with brown rice, Pepper Soup).
            2. FRUIT PROTOCOL: Instead of supplements, recommend ONE specific Nigerian fruit for the day (e.g., Garden Egg, Udara, African Star Apple, Papaya, Guava).
               - Include 'why_it_works' explaining the hormonal benefit.
            3. HERBAL TEA: You must include 2 herbal tea recommendations (Morning & Evening).
            4. MOVEMENT: Suggest a walking-based routine. Include step counts (min 6000 steps).
            5. TIME RANGES: You MUST provide a 'time_start' and 'time_end' for EVERY meal and activity.

            Required JSON Structure:
            {
              \"meals\": {
                \"breakfast\": { \"name\": \"Dish Name\", \"description\": \"Short desc\", \"calories\": 500, \"time_start\": \"06:30\", \"time_end\": \"08:00\", \"ingredients\": [{\"item\": \"Name\", \"quantity\": \"Qty\"}], \"instructions\": [\"Step 1\", \"Step 2\"] },
                \"lunch\": { ... },
                \"dinner\": { ... },
                \"snack\": { \"name\": \"Healthy snack\", \"description\": \"...\", \"time_start\": \"15:30\", \"time_end\": \"16:30\" }
              },
              \"fruit_ritual\": { \"name\": \"Garden Egg\", \"portion\": \"2 medium\", \"benefits\": \"...\", \"why_it_works\": \"...\", \"time_start\": \"10:00\", \"time_end\": \"11:00\" },
              \"herbal_tea\": { \"morning\": { \"name\": \"Morning Blend\", \"time_start\": \"10:00\", \"time_end\": \"10:30\", \"benefits\": \"...\", \"product_key\": \"pcos_morning_blend\" }, \"evening\": { ... } },
              \"workout\": { \"name\": \"Morning and Evening Walk\", \"description\": \"Brisk walking\", \"intensity\": \"Moderate\", \"duration\": \"30-45 mins\", \"time_start\": \"06:00\", \"time_end\": \"06:30\", \"steps_target\": 6000, \"activities\": [{\"name\": \"Morning Walk\", \"steps\": 3000, \"duration\": \"20 mins\", \"time\": \"06:00 - 06:30\"}, {\"name\": \"Evening Walk\", \"steps\": 3000, \"duration\": \"20 mins\", \"time\": \"18:00 - 18:30\"}] },
              \"shopping_list\": [{ \"item\": \"Ingredient\", \"quantity\": \"Qty\", \"category\": \"Produce/Meat/Pantry\" }],
              \"hydration_goal\": \"8 glasses (2 liters)\",
              \"daily_quote\": \"Motivational quote for the day\"
            }",

                'acne' => "
            Generate a personalized daily anti-acne / skin health meal plan for this user:
            - Goal: Reduce inflammation, balance skin microbiome, clear acne
            - Focus on low-glycemic, anti-inflammatory foods

            CRITICAL REQUIREMENTS:
            1. MEALS: Nigerian/West African dishes with skin-healthy ingredients (leafy greens, fatty fish, citrus, turmeric). Avoid high-glycemic dishes.
            2. FRUIT PROTOCOL: Recommend ONE Nigerian fruit for skin health (e.g., Pawpaw, Orange, Ugiri, Pineapple) with why_it_works.
            3. HERBAL TEA: Morning detox + evening calming. Include product_key: 'acne_morning_blend' / 'acne_evening_blend'.
            4. TIME RANGES on all meals.
            DO NOT include a workout or movement block. Acne is not managed by exercise protocols. Omit the 'workout' key entirely from your JSON response.
            Same JSON structure as PCOS plan but without the workout field.",

                'weight' => "
            Generate a personalized daily weight management meal plan for this user:
            - Goal: Calorie-controlled, metabolism-boosting, appetite regulation
            - Focus on protein-rich, high-fiber, low-calorie Nigerian dishes

            CRITICAL REQUIREMENTS:
            1. MEALS: Nigerian dishes portion-controlled for weight loss (grilled fish with vegetables, vegetable soups, brown rice).
            2. FRUIT PROTOCOL: Low-sugar Nigerian fruit (Garden Egg, Udara, Lime) with why_it_works for weight loss.
            3. HERBAL TEA: Metabolism-boosting morning tea + evening detox. product_key: 'weight_morning_blend' / 'weight_evening_blend'.
            4. MOVEMENT: Walking + bodyweight exercises. Min 7000 steps.
            5. TIME RANGES on all meals/activities.
            Same JSON structure as PCOS plan, but calories should be 300-400 per meal.",

                'mens' => "
            Generate a personalized daily men's vitality meal plan for this user:
            - Goal: Support testosterone, increase energy, build strength
            - Focus on zinc-rich, protein-packed Nigerian dishes

            CRITICAL REQUIREMENTS:
            1. MEALS: High-protein Nigerian dishes (grilled meats, fish, eggs, beans, nuts). Energy-dense.
            2. FRUIT PROTOCOL: Testosterone-supporting Nigerian fruit (Banana, Avocado, Dates, Coconut).
            3. HERBAL TEA: Energy-boosting morning tea + evening recovery. product_key: 'mens_morning_blend' / 'mens_evening_blend'.
            4. MOVEMENT: Strength-focused routine (push-ups, squats, walking). Min 6000 steps.
            5. TIME RANGES on all meals/activities.
            Same JSON structure as PCOS plan, but calories should be 500-700 per meal."
            ];

            $userPrompt = $funnelPrompts[$conditionType] ?? $funnelPrompts['pcos'];
            $userPrompt .= "\n\nAdditional context:\n- Allergies: " . ($vars['ALLERGIES'] ?? 'None') . "\n- Preferences: " . ($vars['PREFERENCES'] ?? 'None') . "\n- Program Week: " . ($vars['PROGRAM_WEEK'] ?? 'Week 1') . "\n- Date: " . $date;


            $maxRetries = 1;
            $attempt = 0;
            $planData = null;

            while ($attempt <= $maxRetries) {
                $response = $this->ai->generateResponse($promptKey, $userPrompt, $vars);

                if (is_string($response)) {
                    $cleanJson = $this->cleanJson($response);
                    $planData = json_decode($cleanJson, true);
                }

                if ($planData && isset($planData['meals']) && !empty($planData['meals'])) {
                    $planData['generated_at'] = date('Y-m-d H:i:s');
                    $planData['retries'] = $attempt;
                    break;
                }

                $attempt++;
                if ($attempt <= $maxRetries) {
                    error_log("Retrying AI generation for user $userId on $date (Attempt $attempt)");
                    usleep(500000); // 0.5s pause before retry
                }
            }

            if (!$planData) {
                // Final Fallback if all retries fail
                $planData = [
                    'raw_content' => $response ?? 'No response',
                    'error' => 'Failed to generate valid JSON plan after retries',
                    'meals' => [],
                    'generated_at' => date('Y-m-d H:i:s')
                ];
            }

            // 5. Save to DB
            $existing = $this->db->fetch("SELECT id FROM daily_plans WHERE user_id = :uid AND plan_date = :date", [':uid' => $userId, ':date' => $date]);

            if ($existing) {
                $this->db->update('daily_plans', [
                    'plan_data' => json_encode($planData),
                    'trigger_type' => $triggerType
                ], "id = :id", [':id' => $existing['id']]);
            } else {
                $this->db->insert('daily_plans', [
                    'user_id' => $userId,
                    'plan_date' => $date,
                    'plan_data' => json_encode($planData),
                    'trigger_type' => $triggerType
                ]);
            }

            // Update Log
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->db->update('ai_generation_logs', [
                'status' => 'success',
                'duration_ms' => $duration
            ], "id = :id", [':id' => $logId]);

            return $planData;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->db->update('ai_generation_logs', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => $duration
            ], "id = :id", [':id' => $logId]);
            throw $e;
        }
    }

    private function cleanJson($text)
    {
        if (!is_string($text))
            return '';
        if (preg_match('/```json(.*?)```/s', $text, $matches)) {
            return trim($matches[1]);
        }
        return $text;
    }

    public function getShoppingList($userId, $startDate, $endDate)
    {
        $sql = "SELECT plan_data FROM daily_plans WHERE user_id = :uid AND plan_date BETWEEN :start AND :end";
        $rows = $this->db->fetchAll($sql, [
            ':uid' => $userId,
            ':start' => $startDate,
            ':end' => $endDate
        ]);

        $shoppingList = [];

        foreach ($rows as $row) {
            $data = json_decode($row['plan_data'], true);
            if (isset($data['shopping_list']) && is_array($data['shopping_list'])) {
                foreach ($data['shopping_list'] as $item) {
                    $name = $item['item'] ?? 'Unknown Item';
                    $key = strtolower(trim($name));

                    if (!isset($shoppingList[$key])) {
                        $shoppingList[$key] = [
                            'item' => $name,
                            'category' => $item['category'] ?? 'Other',
                            'qty' => []
                        ];
                    }
                    if (!empty($item['quantity'])) {
                        $shoppingList[$key]['qty'][] = $item['quantity'];
                    }
                }
            }
        }

        return $shoppingList;
    }

    public function swapMeal($userId, $mealType)
    {
        $today = date('Y-m-d');
        $plan = $this->db->fetch("SELECT id, plan_data FROM daily_plans WHERE user_id = :uid AND plan_date = :date", [':uid' => $userId, ':date' => $today]);
        if (!$plan)
            return ['success' => false, 'error' => 'No plan found for today'];

        $planData = json_decode($plan['plan_data'], true);
        $currentMeal = $planData['meals'][$mealType] ?? null;
        if (!$currentMeal)
            return ['success' => false, 'error' => 'Meal type not found'];

        // Get Profile for AI context
        $profile = $this->db->fetch("SELECT * FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);
        $conditionType = $profile['condition_type'] ?? 'pcos';

        $prompt = "The user ($conditionType) wants to swap their $mealType: '{$currentMeal['name']}'.
            Condition: {$conditionType}.
            Allergies: {$profile['allergies']}.
            Preferences: {$profile['dietary_preferences']}.

            Suggest ONE alternative meal that fits their protocol.
            Output ONLY valid JSON in this format:
            { \"name\": \"Meal Name\", \"description\": \"Short description\", \"calories\": 0, \"shopping_list\": [{
            \"item\": \"...\", \"category\": \"...\", \"quantity\": \"...\" }] }";

        $response = $this->ai->generateResponse($conditionType . '_meal_planner', $prompt, []);
        $newMeal = null;
        if (is_string($response)) {
            $newMeal = json_decode($this->cleanJson($response), true);
        }

        if ($newMeal) {
            // Update the plan
            $planData['meals'][$mealType] = [
                'name' => $newMeal['name'],
                'description' => $newMeal['description'],
                'calories' => $newMeal['calories']
            ];
            // Merge shopping list
            if (isset($newMeal['shopping_list'])) {
                $planData['shopping_list'] = array_merge($planData['shopping_list'] ?? [], $newMeal['shopping_list']);
            }

            $this->db->update('daily_plans', ['plan_data' => json_encode($planData)], "id = :id", [':id' => $plan['id']]);
            return ['success' => true, 'meal' => $planData['meals'][$mealType]];
        }

        return ['success' => false, 'error' => 'AI failed to generate alternative'];
    }

    public function generateWeeklyPlanRange($userId, $startDate, $endDate)
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($start <= $end) {
            $currentDate = $start->format('Y-m-d');

            // Check if plan exists (basic check)
            $exists = $this->db->fetch(
                "SELECT id FROM daily_plans WHERE user_id = :uid AND plan_date = :date",
                [':uid' => $userId, ':date' => $currentDate]
            );

            if (!$exists) {
                // Generate
                try {
                    $this->generateDailyPlan($userId, $currentDate, 'manual_bulk');
                } catch (Exception $e) {
                    error_log("Failed to generate plan for $currentDate: " . $e->getMessage());
                }
                // Respect API rate limits slightly if doing bulk
                usleep(500000); // 0.5s pause
            }

            $start->modify('+1 day');
        }
    }

    public function ensurePlansExist($userId, $days = 3)
    {
        $today = new DateTime();
        $results = [];

        for ($i = 0; $i <= $days; $i++) {
            $date = clone $today;
            if ($i > 0)
                $date->modify("+$i day");
            $dateStr = $date->format('Y-m-d');

            // Check if REAL plan exists (not just a log/placeholder)
            $sql = "SELECT plan_data FROM daily_plans WHERE user_id = :uid AND plan_date = :date";
            $row = $this->db->fetch($sql, [':uid' => $userId, ':date' => $dateStr]);

            $hasValidPlan = false;
            if ($row) {
                $data = json_decode($row['plan_data'], true);
                if (isset($data['meals']) && !empty($data['meals'])) {
                    $hasValidPlan = true;
                }
            }

            if (!$hasValidPlan) {
                try {
                    $this->generateDailyPlan($userId, $dateStr, 'proactive');
                    $results[$dateStr] = 'generated';
                } catch (Exception $e) {
                    $results[$dateStr] = 'failed: ' . $e->getMessage();
                }
            } else {
                $results[$dateStr] = 'exists';
            }
        }
        return $results;
    }
}