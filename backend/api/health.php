<?php
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json');
http_response_code(200);

$env = defined('APP_ENV') ? APP_ENV : 'development';
$status = [
    'status' => 'ok',
    'env' => $env,
    'time' => date('c'),
];

echo json_encode($status);
?>
