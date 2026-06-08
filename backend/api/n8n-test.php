<?php
// backend/api/n8n-test.php
// A simple test file to verify if n8n can reach your server and parse JSON.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = [
    'status' => 'success',
    'message' => 'Connection successful!',
    'received_headers' => getallheaders(),
    'received_params' => $_GET,
    'server_time' => date('Y-m-d H:i:s')
];

echo json_encode($response);
exit;
