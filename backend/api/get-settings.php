<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance();

    if ($db->isFileStorage()) {
        echo json_encode([
            'success' => true,
            'data' => [
                'flutterwave_public_key' => 'FLWPUBK_TEST-SANDBOXDEMOKEY-X',
                'flutterwave_environment' => 'sandbox',
                'site_url' => 'https://1wellness.club',
            ]
        ]);
        exit;
    }

    $pdo = $db->getConnection();
    $stmt = $pdo->query("SELECT key, value FROM settings WHERE key IN ('flutterwave_public_key', 'flutterwave_environment', 'site_url', 'site_name')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'flutterwave_public_key' => $settings['flutterwave_public_key'] ?? 'FLWPUBK_TEST-SANDBOXDEMOKEY-X',
            'flutterwave_environment' => $settings['flutterwave_environment'] ?? 'sandbox',
            'site_url' => $settings['site_url'] ?? 'https://1wellness.club',
            'site_name' => $settings['site_name'] ?? '1Wellness',
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
