<?php
/**
 * Funnel Sync API
 * Scans filesystem and syncs discovered funnels with database
 */

require_once '../config/config.php';

header('Content-Type: application/json');

// Check if admin is logged in (for manual triggers)
$requireAuth = isset($_GET['auth']) && $_GET['auth'] === 'required';

if ($requireAuth) {
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }
}

try {
    $discovery = new FunnelDiscovery();
    $result = $discovery->syncFunnels();

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error syncing funnels: ' . $e->getMessage()
    ]);
}
