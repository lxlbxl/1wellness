<?php
/**
 * Admin Authentication Middleware
 * Include this file at the top of protected admin pages
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../classes/Admin.php';

// Ensure session is started (config.php should usually do this)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Verify session is still valid
try {
    $admin = new Admin();
    $currentAdmin = $admin->getAdmin($_SESSION['admin_id']);

    if (!$currentAdmin || $currentAdmin['status'] !== 'active') {
        // Invalid or inactive admin, destroy session
        session_destroy();
        header('Location: login.php?error=session_expired');
        exit;
    }

    // Update session data if needed
    $_SESSION['admin_username'] = $currentAdmin['username'];
    $_SESSION['admin_name'] = $currentAdmin['full_name'];
    $_SESSION['admin_role'] = $currentAdmin['role'];
    $_SESSION['admin_permissions'] = json_decode($currentAdmin['permissions'] ?? '[]', true);

} catch (Exception $e) {
    // Database error, redirect to login
    session_destroy();
    header('Location: login.php?error=system_error');
    exit;
}

/**
 * Check if current admin has specific permission
 */
function hasPermission($permission)
{
    if ($_SESSION['admin_role'] === 'super_admin') {
        return true;
    }

    $permissions = $_SESSION['admin_permissions'] ?? [];
    return in_array($permission, $permissions);
}

/**
 * Require specific permission or redirect
 */
function requirePermission($permission, $redirectUrl = 'dashboard.php')
{
    if (!hasPermission($permission)) {
        header("Location: {$redirectUrl}?error=insufficient_permissions");
        exit;
    }
}

/**
 * Check if current admin is super admin
 */
function isSuperAdmin()
{
    return $_SESSION['admin_role'] === 'super_admin';
}

/**
 * Get current admin info
 */
function getCurrentAdmin()
{
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'name' => $_SESSION['admin_name'],
        'role' => $_SESSION['admin_role'],
        'permissions' => $_SESSION['admin_permissions']
    ];
}

/**
 * Log admin activity
 */
function logActivity($action, $details = null)
{
    try {
        $admin = new Admin();
        $admin->logActivity($_SESSION['admin_id'], $action, $details);
    } catch (Exception $e) {
        // Log error but don't break the flow
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require CSRF token for POST requests
 */
function requireCSRF()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($token)) {
            http_response_code(403);
            die('CSRF token mismatch');
        }
    }
}

// Set current admin for use in templates
$currentAdmin = getCurrentAdmin();
$csrfToken = generateCSRFToken();
?>