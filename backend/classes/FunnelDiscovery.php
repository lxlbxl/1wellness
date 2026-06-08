<?php
/**
 * Funnel Discovery Service
 * Automatically scans the root directory for funnel folders and registers them
 */

class FunnelDiscovery
{
    private $db;
    private $settings;
    private $rootPath;

    // Patterns that identify a directory as a funnel
    private $funnelIndicators = [
        'assessment.html',
        'index.html',
        'results.html'
    ];

    // Directories to exclude from scanning
    private $excludedDirs = [
        'backend',
        'js',
        'shared',
        'mysql-tester',
        '.trae',
        '.git',
        'node_modules',
        'vendor'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
        $this->rootPath = dirname(APP_ROOT); // Parent of backend folder
    }

    /**
     * Scan root directory for funnels
     */
    public function scanForFunnels()
    {
        $discoveredFunnels = [];

        // Get all directories in root
        $items = scandir($this->rootPath);

        foreach ($items as $item) {
            // Skip hidden files and parent directories
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }

            $fullPath = $this->rootPath . '/' . $item;

            // Check if it's a directory and not excluded
            if (is_dir($fullPath) && !in_array($item, $this->excludedDirs)) {
                if ($this->isFunnel($fullPath)) {
                    $funnelData = $this->analyzeFunnel($item, $fullPath);
                    $discoveredFunnels[] = $funnelData;
                }
            }
        }

        return $discoveredFunnels;
    }

    /**
     * Check if a directory is a funnel
     */
    private function isFunnel($path)
    {
        $files = scandir($path);
        $foundIndicators = 0;

        foreach ($this->funnelIndicators as $indicator) {
            if (in_array($indicator, $files)) {
                $foundIndicators++;
            }
        }

        // Must have at least 2 of the indicator files
        return $foundIndicators >= 2;
    }

    /**
     * Analyze a funnel directory to extract metadata and plans
     */
    private function analyzeFunnel($dirName, $path)
    {
        $files = scandir($path);

        // Extract funnel metadata
        $funnel = [
            'id' => $this->normalizeFunnelId($dirName),
            'directory' => $dirName,
            'name' => $this->generateFunnelName($dirName),
            'plans' => [],
            'has_assessment' => in_array('assessment.html', $files),
            'has_results' => in_array('results.html', $files),
            'has_thank_you' => in_array('thank-you.html', $files),
            'has_sales' => in_array('sales.html', $files),
            'discovered_at' => date('Y-m-d H:i:s')
        ];

        // 1. Look for explicit plan files (e.g., 30-day-plan.html)
        $foundPlanFiles = false;
        foreach ($files as $file) {
            if (preg_match('/(\d+)-day-plan\.html/', $file, $matches)) {
                $days = $matches[1];
                $planData = $this->parsePlanFile($path . '/' . $file);

                if ($planData) {
                    // Use parsed data
                    $funnel['plans'][] = array_merge($planData, [
                        'duration' => intval($days)
                    ]);
                } else {
                    // Fallback if parsing fails
                    $funnel['plans'][] = [
                        'id' => $days . '-day',
                        'file' => $file,
                        'duration' => intval($days)
                    ];
                }
                $foundPlanFiles = true;
            }
        }

        // 2. If no explicit plan files found, check sales.html
        if (!$foundPlanFiles && $funnel['has_sales']) {
            $planData = $this->parsePlanFile($path . '/sales.html');

            if ($planData) {
                // Found a single plan in sales.html (e.g. Egbon)
                $funnel['plans'][] = array_merge($planData, [
                    'duration' => 30 // Default duration assumption if not specified
                ]);
            } else {
                // Fallback: Create standard default plans if parsing failed
                $funnel['plans'][] = [
                    'id' => '30-day',
                    'file' => 'sales.html',
                    'duration' => 30
                ];
                $funnel['plans'][] = [
                    'id' => '90-day',
                    'file' => 'sales.html',
                    'duration' => 90
                ];
            }
        }

        return $funnel;
    }

    /**
     * Parse an HTML file to extract payment data and features
     */
    private function parsePlanFile($filePath)
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $plan = [];

        // Extract Payment Data from JS object
        // Matches: const paymentData = { ... };
        if (preg_match('/(?:const|let|var)\s+paymentData\s*=\s*({[\s\S]*?});/', $content, $match)) {
            $jsObject = $match[1];

            // Extract fields from the JS object string, handling escaped quotes
            // We use stripslashes() to handle escaped characters like \' in the extracted string
            if (preg_match("/plan:\s*(['\"])((?:[^\\\\\\1]|\\\\.)*?)\\1/", $jsObject, $m))
                $plan['id'] = stripslashes($m[2]);
            if (preg_match("/amount:\s*(\d+)/", $jsObject, $m))
                $plan['price'] = $m[1];
            if (preg_match("/title:\s*(['\"])((?:[^\\\\\\1]|\\\\.)*?)\\1/", $jsObject, $m))
                $plan['name'] = stripslashes($m[2]);
            if (preg_match("/description:\s*(['\"])((?:[^\\\\\\1]|\\\\.)*?)\\1/", $jsObject, $m))
                $plan['description'] = stripslashes($m[2]);
        }

        // Extract Features
        $features = [];

        // Strategy 1: Contextual Search (Look for "Includes" or "Getting" header followed by UL)
        // This is prioritized to avoid picking up comparison tables or other lists
        if (preg_match_all('/(<h[23456][^>]*>.*?(?:Getting|Includes|Included).*?<\/h[23456]>)(?:[\s\S]*?)<ul[^>]*>(.*?)<\/ul>/is', $content, $matches)) {
            // Iterate through matches to find the best candidate (usually the last one near the order form)
            foreach ($matches[2] as $ulContent) {
                if (preg_match_all('/<li[^>]*>(.*?)<\/li>/s', $ulContent, $liMatches)) {
                    $items = array_map(function ($item) {
                        $text = trim(strip_tags($item));
                        // Remove checkmarks (unicode and ascii) and other common bullets
                        // \x{2713} = ✓, \x{2714} = ✔, \x{2611} = ☑
                        $text = preg_replace('/^[\x{2713}\x{2714}\x{2611}vV\-\*]\s*/u', '', $text);
                        return $text;
                    }, $liMatches[1]);

                    $items = array_filter($items); // Remove empty items

                    if (count($items) > 0) {
                        $features = $items;
                        // Keep looping, as the last one (often near checkout) is usually the most accurate summary
                    }
                }
            }
        }

        // Strategy 2: Global Checkmark Search (Fallback if Strategy 1 failed)
        if (empty($features)) {
            if (preg_match_all('/<li[^>]*>.*?✓\s*(.*?)<\/li>/s', $content, $matches)) {
                $features = array_map(function ($item) {
                    $text = trim(strip_tags($item));
                    $text = preg_replace('/^[\x{2713}\x{2714}\x{2611}vV\-\*]\s*/u', '', $text);
                    return $text;
                }, $matches[1]);
            }
        }

        if (!empty($plan)) {
            $plan['features'] = array_values(array_filter($features)); // Remove empty entries
            $plan['currency'] = 'USD';
            $plan['file'] = basename($filePath);
            return $plan;
        }

        return null;
    }

    /**
     * Normalize funnel directory name to ID
     */
    private function normalizeFunnelId($dirName)
    {
        $id = str_replace('-funnel', '', $dirName);
        $id = strtolower($id);
        $id = preg_replace('/[^a-z0-9]+/', '-', $id);
        return trim($id, '-');
    }

    /**
     * Generate human-readable funnel name
     */
    private function generateFunnelName($dirName)
    {
        $name = str_replace('-funnel', '', $dirName);
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        return $name;
    }

    /**
     * Sync discovered funnels with database
     * @param bool $force If true, overwrites existing plans with discovered data
     */
    public function syncFunnels($force = false)
    {
        $discovered = $this->scanForFunnels();

        // Group by ID to handle collisions
        $grouped = [];
        foreach ($discovered as $funnel) {
            $id = $funnel['id'];
            if (!isset($grouped[$id])) {
                $grouped[$id] = [];
            }
            $grouped[$id][] = $funnel;
        }

        // Resolve collisions
        $finalFunnels = [];
        foreach ($grouped as $id => $candidates) {
            if (count($candidates) === 1) {
                $finalFunnels[] = $candidates[0];
            } else {
                // Collision resolution: Prefer directory with explicit plans or '-funnel' suffix
                usort($candidates, function ($a, $b) {
                    // 1. Prefer explicit plans (not just sales.html default)
                    $aHasPlans = !empty($a['plans']) && $a['plans'][0]['file'] !== 'sales.html';
                    $bHasPlans = !empty($b['plans']) && $b['plans'][0]['file'] !== 'sales.html';

                    if ($aHasPlans && !$bHasPlans)
                        return -1;
                    if (!$aHasPlans && $bHasPlans)
                        return 1;

                    // 2. Prefer '-funnel' suffix in directory name
                    $aIsFunnel = strpos($a['directory'], '-funnel') !== false;
                    $bIsFunnel = strpos($b['directory'], '-funnel') !== false;

                    if ($aIsFunnel && !$bIsFunnel)
                        return -1;
                    if (!$aIsFunnel && $bIsFunnel)
                        return 1;

                    return 0;
                });

                $finalFunnels[] = $candidates[0];
            }
        }

        $currentPlans = $this->settings->get('payment_plans', []);
        $funnelRegistry = $this->settings->get('funnel_registry', []);
        $updated = false;

        foreach ($finalFunnels as $funnel) {
            $funnelId = $funnel['id'];

            // Register funnel if not already registered OR if directory changed
            if (!isset($funnelRegistry[$funnelId]) || $funnelRegistry[$funnelId]['directory'] !== $funnel['directory']) {
                $funnelRegistry[$funnelId] = [
                    'name' => $funnel['name'],
                    'directory' => $funnel['directory'],
                    'discovered_at' => $funnel['discovered_at'],
                    'status' => $funnelRegistry[$funnelId]['status'] ?? 'active'
                ];
                $updated = true;
            }

            // Check for structure mismatch (keys different)
            $existingPlans = $currentPlans[$funnelId] ?? [];
            $discoveredPlanIds = array_column($funnel['plans'], 'id');
            $existingPlanIds = array_keys($existingPlans);

            // If the set of plan IDs has changed (e.g. 30-day/90-day vs egbon-single), we should update
            $structureChanged = !empty(array_diff($existingPlanIds, $discoveredPlanIds)) ||
                !empty(array_diff($discoveredPlanIds, $existingPlanIds));

            // Create or Update pricing
            // We update if:
            // 1. Force is true (manual sync)
            // 2. It doesn't exist or is empty
            // 3. The structure of plans has changed
            if ($force || !isset($currentPlans[$funnelId]) || empty($currentPlans[$funnelId]) || $structureChanged) {
                $currentPlans[$funnelId] = [];

                foreach ($funnel['plans'] as $plan) {
                    $planId = $plan['id'];

                    // Use parsed data if available, otherwise fallback to defaults
                    $currentPlans[$funnelId][$planId] = [
                        'name' => $plan['name'] ?? ($plan['duration'] . '-Day ' . $funnel['name'] . ' Plan'),
                        'price' => $plan['price'] ?? $this->getDefaultPrice($plan['duration']),
                        'currency' => $plan['currency'] ?? 'USD',
                        'description' => $plan['description'] ?? ('Complete ' . $plan['duration'] . '-day ' . strtolower($funnel['name']) . ' program'),
                        'features' => !empty($plan['features']) ? $plan['features'] : $this->getDefaultFeatures($plan['duration']),
                        'file' => $plan['file'] ?? 'sales.html' // Store the file name for future updates
                    ];
                }

                $updated = true;
            }
        }

        if ($updated) {
            $this->settings->set('funnel_registry', $funnelRegistry, 'json', 'Discovered funnels registry');
            $this->settings->set('payment_plans', $currentPlans, 'json', 'Payment plans configuration');
        }

        return [
            'success' => true,
            'discovered' => count($finalFunnels),
            'funnels' => $finalFunnels,
            'updated' => $updated
        ];
    }

    /**
     * Update a plan's price in the actual HTML file
     */
    public function updatePlanPrice($funnelId, $planId, $newPrice)
    {
        $registry = $this->getRegisteredFunnels();
        if (!isset($registry[$funnelId]))
            return false;

        $funnelDir = $registry[$funnelId]['directory'];
        $plans = $this->settings->get('payment_plans', []);

        // Try to get the file from the stored plan data
        $planFile = $plans[$funnelId][$planId]['file'] ?? null;

        // Fallback if file not recorded in DB
        if (!$planFile) {
            if (preg_match('/^(\d+)-day/', $planId, $m)) {
                $planFile = $m[1] . '-day-plan.html';
            } else {
                $planFile = 'sales.html';
            }
        }

        $filePath = $this->rootPath . '/' . $funnelDir . '/' . $planFile;

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $formattedPrice = number_format((float) $newPrice);

            // 1. Update JS paymentData amount
            $content = preg_replace(
                '/(amount:\s*)\d+/',
                '$1' . $newPrice,
                $content
            );

            // 2. Update Display Price (Hero Section only - text-5xl)
            // Matches <div class="...text-5xl...">₦18,000</div>
            $content = preg_replace(
                '/(<div[^>]*class="[^"]*text-5xl[^"]*"[^>]*>)\s*₦\s*[\d,]+\s*(<\/div>)/u',
                '$1₦' . $formattedPrice . '$2',
                $content
            );

            // 3. Update Button Text (e.g. Get Egbon Now - ₦15,000)
            // Matches " - ₦15,000" pattern inside a button or link
            $content = preg_replace(
                '/(\s*-\s*)₦\s*[\d,]+/',
                '$1₦' . $formattedPrice,
                $content
            );

            // 4. Update Schema Price
            $content = preg_replace(
                '/("price":\s*")\d+(")/',
                '$1' . $newPrice . '$2',
                $content
            );

            file_put_contents($filePath, $content);
            return true;
        }

        return false;
    }

    /**
     * Get default price based on duration (Fallback)
     */
    private function getDefaultPrice($days)
    {
        $priceMap = [
            30 => 18000,
            60 => 35000,
            90 => 45000,
            120 => 55000
        ];

        return $priceMap[$days] ?? ($days * 500);
    }

    /**
     * Get default features based on duration (Fallback)
     */
    private function getDefaultFeatures($days)
    {
        $baseFeatures = [
            'Personalized meal plan',
            'Email support',
            'Progress tracking'
        ];

        if ($days >= 60) {
            $baseFeatures[] = 'Weekly check-ins';
            $baseFeatures[] = 'WhatsApp support group';
        }

        if ($days >= 90) {
            $baseFeatures[] = 'Direct specialist access';
            $baseFeatures[] = 'Custom supplement recommendations';
            $baseFeatures[] = 'Lifetime resource access';
        }

        return $baseFeatures;
    }

    /**
     * Get all registered funnels
     */
    public function getRegisteredFunnels()
    {
        return $this->settings->get('funnel_registry', []);
    }

    /**
     * Get funnel by ID
     */
    public function getFunnel($funnelId)
    {
        $registry = $this->getRegisteredFunnels();
        return $registry[$funnelId] ?? null;
    }

    /**
     * Update funnel status
     */
    public function updateFunnelStatus($funnelId, $status)
    {
        $registry = $this->getRegisteredFunnels();

        if (isset($registry[$funnelId])) {
            $registry[$funnelId]['status'] = $status;
            $registry[$funnelId]['updated_at'] = date('Y-m-d H:i:s');

            return $this->settings->set('funnel_registry', $registry, 'json', 'Discovered funnels registry');
        }

        return false;
    }
}
