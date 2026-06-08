<?php
// backend/api/n8n-debug-db.php
// A diagnostic file to checking if including Database.php causes output.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ob_start();

try {
    require_once '../config/config.php';
    require_once '../classes/Database.php';

    // Try to get instance - this exercises the DB connection logic
    $db = Database::getInstance();
    $stats = $db->getStats();

} catch (Exception $e) {
    // Catch errors
}

$output = ob_get_contents();
ob_end_clean();

if (!empty($output)) {
    // If output is not empty, it means one of the files printed something (whitespace, BOM, warning)
    echo json_encode([
        'status' => 'error',
        'message' => 'Whitespace/Output detected in included files!',
        'output_length' => strlen($output),
        'output_preview' => substr($output, 0, 100), // First 100 chars
        'output_hex' => bin2hex(substr($output, 0, 20)) // Hex to see BOM
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'message' => 'No output leakage detected. Database loaded clean.',
        'db_stats' => $stats ?? 'failed'
    ]);
}
exit;
