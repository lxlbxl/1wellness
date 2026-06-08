<?php
// backend/api/n8n-export.php
// Rewritten to match the proven-working structure of n8n-debug-db.php

// 1. Output Control (Critical)
ob_start(); // Buffer STDOUT
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Friendly for browser testing

// 2. Error Handling (Silent)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 3. Response Container
$response = ['status' => 'error', 'message' => 'Unknown error'];
$httpCode = 200;

try {
    // 4. Dependencies
    require_once '../config/config.php';
    require_once '../classes/Database.php';

    // 5. Auth Logic
    $validKey = defined('N8N_API_KEY') ? N8N_API_KEY : '';

    // Auth Helper
    $providedKey = null;
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        $providedKey = $h['X-API-KEY'] ?? $h['x-api-key'] ?? null;
    }
    if (!$providedKey)
        $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

    if (!$providedKey || $providedKey !== $validKey) {
        $httpCode = 401;
        throw new Exception('Unauthorized: Invalid or missing API Key');
    }

    // 6. DB Connect
    $db = Database::getInstance();

    // 7. Input Params
    $type = $_GET['type'] ?? 'sales';
    $limit = intval($_GET['limit'] ?? 100);
    $page = intval($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    // Parse Dates (Allow partial or full datetime)
    $startDateStr = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 day'));
    $endDateStr = $_GET['end_date'] ?? date('Y-m-d');

    // If just date Y-m-d, append time to cover full day
    $startDate = (strlen($startDateStr) == 10) ? $startDateStr . ' 00:00:00' : $startDateStr;
    $endDate = (strlen($endDateStr) == 10) ? $endDateStr . ' 23:59:59' : $endDateStr;

    $conn = $db->getConnection();

    // 8. Queries
    if ($type === 'sales') {
        $sql = "SELECT * FROM sales 
                WHERE created_at >= :start AND created_at <= :end 
                ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'assessments') {
        $sql = "SELECT * FROM assessments 
                WHERE created_at >= :start AND created_at <= :end 
                ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'users') {
        $sql = "SELECT id, name, email, type, created_at FROM users 
                WHERE created_at >= :start AND created_at <= :end 
                ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $httpCode = 400;
        throw new Exception("Invalid type: $type");
    }

    // Success Response
    $response = [
        'status' => 'success',
        'type' => $type,
        'meta' => [
            'count' => count($data),
            'page' => $page,
            'limit' => $limit,
            'range' => ['start' => $startDate, 'end' => $endDate]
        ],
        'data' => $data
    ];

} catch (Exception $e) {
    if ($httpCode === 200)
        $httpCode = 500; // Default to 500 if undefined
    http_response_code($httpCode);
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// 9. CLEAN Output (The Magic Step)
ob_clean(); // Discard any prior output (warnings, BOM, whitespace)
echo json_encode($response);
exit;
