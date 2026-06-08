<?php
/**
 * Main Entry Point
 * 1wellness - Health Assessment System Backend
 */

// Define application root
define('APP_ROOT', __DIR__);

// Load configuration
require_once APP_ROOT . '/config/config.php';

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = dirname($script_name);

// Remove base path from request URI
if ($base_path !== '/') {
    $request_uri = substr($request_uri, strlen($base_path));
}

// Remove query string
$request_uri = strtok($request_uri, '?');

// Route the request
switch ($request_uri) {
    case '/':
    case '/admin':
        header('Location: /admin/login.php');
        exit;
        
    case '/admin/login':
        require_once APP_ROOT . '/admin/login.php';
        break;
        
    case '/admin/dashboard':
        require_once APP_ROOT . '/admin/dashboard.php';
        break;
        
    case '/admin/users':
        require_once APP_ROOT . '/admin/users.php';
        break;
        
    case '/admin/assessments':
        require_once APP_ROOT . '/admin/assessments.php';
        break;
        
    case '/admin/sales':
        require_once APP_ROOT . '/admin/sales.php';
        break;
        
    case '/api/submit-assessment':
        require_once APP_ROOT . '/api/submit-assessment.php';
        break;
        
    case '/api/submit-sale':
        require_once APP_ROOT . '/api/submit-sale.php';
        break;
        
    case '/api/get-stats':
        require_once APP_ROOT . '/api/get-stats.php';
        break;

    case '/api/form-handler':
        require_once APP_ROOT . '/api/form-handler.php';
        break;
        
    default:
        // Check if it's an API request
        if (strpos($request_uri, '/api/') === 0) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'API endpoint not found']);
            exit;
        } else {
            // Redirect to admin login
            header('Location: /admin/login.php');
            exit;
        }
        break;
}
?>
