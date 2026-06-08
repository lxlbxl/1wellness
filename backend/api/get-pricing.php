<?php
/**
 * Get Pricing API
 * Returns current pricing configuration and public payment keys
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

try {
    $settings = Settings::getInstance();

    // Get Payment Plans (Multi-funnel structure)
    $pricing = $settings->get('payment_plans');

    // Get Flutterwave Public Key
    $publicKey = $settings->get('flutterwave_public_key');

    // Default Pricing Structure (if not set)
    if (!$pricing) {
        $pricing = [
            'pcos' => [
                '90-day' => [
                    'name' => '90-Day PCOS Complete Plan',
                    'price' => 197,
                    'currency' => 'USD',
                    'description' => 'Complete 90-day PCOS management program',
                    'features' => ['Personalized meal plan', 'Supplements', 'Support group']
                ],
                '30-day' => [
                    'name' => '30-Day PCOS Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day PCOS starter program',
                    'features' => ['Meal plan', 'Basic guide']
                ]
            ],
            'acne' => [
                '90-day' => [
                    'name' => '90-Day Acne Clear Plan',
                    'price' => 197,
                    'currency' => 'USD',
                    'description' => 'Complete 90-day acne treatment program',
                    'features' => ['Skincare routine', 'Dietary guide', 'Dermatologist support']
                ],
                '30-day' => [
                    'name' => '30-Day Acne Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day acne starter program',
                    'features' => ['Basic routine', 'Starter guide']
                ]
            ],
            'weight' => [
                '90-day' => [
                    'name' => '90-Day Weight Loss Complete Plan',
                    'price' => 197,
                    'currency' => 'USD',
                    'description' => 'Complete 90-day weight loss transformation',
                    'features' => ['Meal plan', 'Exercise guide', 'Specialist support']
                ],
                '30-day' => [
                    'name' => '30-Day Weight Loss Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day weight loss starter program',
                    'features' => ['Meal plan', 'Basic guide']
                ]
            ],
            'mens' => [
                '90-day' => [
                    'name' => '90-Day Men\'s Health Plan',
                    'price' => 197,
                    'currency' => 'USD',
                    'description' => 'Complete 90-day men\'s health transformation',
                    'features' => ['Performance plan', 'Dietary guide', 'Specialist support']
                ],
                '30-day' => [
                    'name' => '30-Day Men\'s Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day men\'s starter program',
                    'features' => ['Basic routine', 'Starter guide']
                ]
            ]
        ];

        // Save default to DB so it can be edited
        $settings->set('payment_plans', $pricing, 'json', 'Payment plans configuration');
    }

    // Ensure all plans have basic fields to prevent frontend crashes
    foreach ($pricing as $funnel => $plans) {
        if (!is_array($plans))
            continue;
        foreach ($plans as $key => $plan) {
            if (empty($plan['features'])) {
                $pricing[$funnel][$key]['features'] = ['Full Digital Access', 'AI-Tailored Protocol'];
            }
            if (empty($plan['description'])) {
                $pricing[$funnel][$key]['description'] = 'Complete professional management program tailored for your health needs.';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'plans' => $pricing,
            'config' => [
                'flutterwavePublicKey' => $publicKey
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching pricing: ' . $e->getMessage()
    ]);
}
