<?php
// backend/api/n8n-debug-auth.php
// Enhanced Diagnostic to trace Config loading

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$debug = [
    'script_path' => __DIR__,
    'expected_config_path' => realpath(__DIR__ . '/../config/config.php'),
    'loaded_config' => 'Not loaded yet'
];

// 1. Check if file exists
if (!file_exists('../config/config.php')) {
    echo json_encode(['error' => 'Config file not found at ../config/config.php']);
    exit;
}

// 2. Read Raw Content (Check if text is even there)
$rawContent = file_get_contents('../config/config.php');
$debug['contains_n8n_key_string'] = (strpos($rawContent, 'N8N_API_KEY') !== false);
$debug['contains_valid_key_value'] = (strpos($rawContent, 'n8n_sk_7d9f2a1b8c3e4d5f6a9b0c1d2e3f4a5b') !== false);

// 3. Load Config
require_once '../config/config.php';
$debug['loaded_config'] = 'Included successfully';

// 4. Check Constant
$debug['constant_defined'] = defined('N8N_API_KEY');
$debug['constant_value'] = defined('N8N_API_KEY') ? N8N_API_KEY : 'NOT_DEFINED';

echo json_encode($debug);
exit;
