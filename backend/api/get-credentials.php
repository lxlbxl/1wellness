<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once '../admin/auth.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

$tx_ref = $_GET['tx_ref'] ?? $_POST['tx_ref'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';

if (empty($tx_ref)) {
    echo json_encode(['success' => false, 'error' => 'Transaction reference required']);
    exit;
}

try {
    // 1. Find the sale by transaction ref
    $sale = $db->fetch("SELECT user_id, email, payment_status, created_at FROM sales WHERE tx_ref = ? OR transaction_id = ?", [$tx_ref, $tx_ref]);

    if (!$sale) {
        // Fallback: Check if we have a pending webhook record or if it's too early
        echo json_encode(['success' => false, 'error' => 'Transaction not found yet.']);
        exit;
    }

    $userId = $sale['user_id'];

    // 2. Fetch User Details
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User account not found']);
        exit;
    }

    // 3. Generate Auto-Login Token
    $token = bin2hex(random_bytes(16));
    $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $db->query(
        "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
        [$userId, $token, $expiry]
    );

    // 4. Return Data
    // Note: We cannot return the password safely if it wasn't captured in session/local storage
    // But we CAN return the username and the auto-login token which is enough to access.

    echo json_encode([
        'success' => true,
        'username' => $user['username'] ?? $user['email'], // Fallback to email if no username
        'auto_login_token' => $token,
        'email' => $user['email'],
        'name' => $user['name'] ?? $user['first_name'],
        'message' => 'Account ready'
    ]);

} catch (Exception $e) {
    error_log("Credential Fetch Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
