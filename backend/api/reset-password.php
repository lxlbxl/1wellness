<?php
/**
 * Password reset handler.
 *
 * GET  ?token=XXX         — validate token (returns {valid, email_hint})
 * POST {token, password}  — apply new password
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');
    if (!$token) {
        echo json_encode(['valid' => false, 'error' => 'No token provided']);
        exit;
    }

    $row = $db->fetch(
        "SELECT prt.id, prt.user_id, prt.expires_at, prt.used, u.email
         FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token = ?",
        [$token]
    );

    if (!$row || $row['used']) {
        echo json_encode(['valid' => false, 'error' => 'This link has already been used or is invalid.']);
        exit;
    }

    if (strtotime($row['expires_at']) < time()) {
        echo json_encode(['valid' => false, 'error' => 'This link has expired. Please request a new one.']);
        exit;
    }

    // Mask email: show j***@domain.com
    $parts     = explode('@', $row['email']);
    $masked    = substr($parts[0], 0, 1) . str_repeat('*', max(2, strlen($parts[0]) - 1)) . '@' . ($parts[1] ?? '');
    echo json_encode(['valid' => true, 'email_hint' => $masked]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$token || strlen($password) < 8) {
        echo json_encode(['success' => false, 'error' => 'Invalid token or password too short (min 8 chars).']);
        exit;
    }

    $row = $db->fetch(
        "SELECT id, user_id, expires_at, used FROM password_reset_tokens WHERE token = ?",
        [$token]
    );

    if (!$row || $row['used'] || strtotime($row['expires_at']) < time()) {
        echo json_encode(['success' => false, 'error' => 'Link is invalid or expired. Please request a new one.']);
        exit;
    }

    // Apply new password and consume token
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->execute("UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?", [$hash, date('Y-m-d H:i:s'), $row['user_id']]);
    $db->execute("UPDATE password_reset_tokens SET used = 1 WHERE id = ?", [$row['id']]);

    echo json_encode(['success' => true, 'message' => 'Password updated. You can now log in.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
