<?php
/**
 * Get Pricing API for Member Area
 * Returns current pricing configuration and public payment keys for renewals
 */

require_once __DIR__ . '/../../backend/config/config.php';

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

// Allow credentials for session-based auth
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
                    'description' => 'Complete 90-day PCOS management program with personalized meal plans, supplements guide, and ongoing support.',
                    'features' => ['Personalized meal plan', 'Supplements protocol', 'Support group access', 'Weekly check-ins', 'AI-tailored recommendations']
                ],
                '30-day' => [
                    'name' => '30-Day PCOS Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day PCOS starter program to kickstart your healing journey.',
                    'features' => ['Starter meal plan', 'Basic supplements guide', 'Community access', 'Daily protocol tracking']
                ]
            ],
            'acne' => [
                '90-day' => [
                    'name' => '90-Day Acne Clear Plan',
                    'price' => 197,
                    'currency' => 'USD',
                    'description' => 'Complete 90-day acne treatment program with skincare routine, dietary guide, and specialist support.',
                    'features' => ['Skincare routine', 'Dietary guide', 'Dermatologist support', 'Progress tracking', 'AI-tailored recommendations']
                ],
                '30-day' => [
                    'name' => '30-Day Acne Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day acne starter program to begin your clear skin journey.',
                    'features' => ['Basic skincare routine', 'Starter guide', 'Community access', 'Daily protocol tracking']
                ]
            ],
            'weight' => [
                '90-day' => [
                    'name' => '90-Day Weight Loss Complete Plan',
                    'price' => 197,
                    'currency' => 'USD',
                    'description' => 'Complete 90-day weight loss transformation with meal plans, exercise guide, and specialist support.',
                    'features' => ['Personalized meal plan', 'Exercise guide', 'Specialist support', 'Progress tracking', 'AI-tailored recommendations']
                ],
                '30-day' => [
                    'name' => '30-Day Weight Loss Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day weight loss starter program to jumpstart your transformation.',
                    'features' => ['Starter meal plan', 'Basic exercise guide', 'Community access', 'Daily protocol tracking']
                ]
            ],
            'egbon' => [
                '90-day' => [
                    'name' => '90-Day Men\'s Vitality Complete Plan',
                    'price' => 197,
                    'currency' => 'USD',
                    'description' => 'Complete 90-day men\'s vitality program with personalized nutrition, supplements, and wellness coaching.',
                    'features' => ['Personalized nutrition plan', 'Supplements protocol', 'Wellness coaching', 'Progress tracking', 'AI-tailored recommendations']
                ],
                '30-day' => [
                    'name' => '30-Day Men\'s Vitality Starter Plan',
                    'price' => 97,
                    'currency' => 'USD',
                    'description' => 'Essential 30-day men\'s vitality starter program to boost your energy and wellness.',
                    'features' => ['Starter nutrition plan', 'Basic supplements guide', 'Community access', 'Daily protocol tracking']
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
