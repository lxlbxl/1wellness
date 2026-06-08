<?php
session_start();
require_once '../config/config.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['admin_id'])) {
    try {
        $admin = new Admin();
        $admin->logActivity($_SESSION['admin_id'], 'logout', 'Admin logged out');
    } catch (Exception $e) {
        // Log error but continue with logout
        error_log("Failed to log logout activity: " . $e->getMessage());
    }
}

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page with success message
header('Location: login.php?message=logged_out');
exit;
?>