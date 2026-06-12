<?php
/**
 * Shared auth for management API endpoints.
 *
 * Accepts EITHER:
 *  - an authenticated admin session (the admin panel's AJAX calls), or
 *  - X-API-KEY header / ?api_key= matching N8N_API_KEY (n8n, scripts, CI).
 *
 * Usage: require this file, then call requireApiAuth(); it exits 401 on failure.
 */

function apiAuthOk()
{
    // 1. Admin session
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }
        session_start();
    }
    if (!empty($_SESSION['admin_id'])) {
        return true;
    }

    // 2. API key
    $validKey = defined('N8N_API_KEY') ? N8N_API_KEY : '';
    if (empty($validKey)) {
        return false;
    }
    $provided = null;
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $provided = $headers['x-api-key'] ?? null;
    }
    if (!$provided) {
        $provided = $_SERVER['HTTP_X_API_KEY'] ?? null;
    }
    if (!$provided) {
        $provided = $_GET['api_key'] ?? null;
    }
    return $provided && hash_equals($validKey, (string) $provided);
}

function requireApiAuth()
{
    if (!apiAuthOk()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized. Provide X-API-KEY header (or log in to the admin panel).',
        ]);
        exit;
    }
}
