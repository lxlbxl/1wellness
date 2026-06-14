<?php
/**
 * Signed one-time credential lookup.
 *
 * Callers must supply ?tx_ref=X&token=T&expiry=E
 * Token = HMAC-SHA256("tx_ref|expiry", JWT_SECRET), valid for 15 min.
 * Use CredentialToken::generate($txRef) to produce a signed link.
 */
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$txRef  = trim($_GET['tx_ref'] ?? '');
$token  = trim($_GET['token'] ?? '');
$expiry = (int) ($_GET['expiry'] ?? 0);

// Both tx_ref and a valid signed token are required
if (!$txRef || !$token || !$expiry) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing tx_ref, token, or expiry']);
    exit;
}

if (time() > $expiry) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token expired']);
    exit;
}

$expected = hash_hmac('sha256', "{$txRef}|{$expiry}", JWT_SECRET);
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

try {
    $db = Database::getInstance();
    if ($db->isFileStorage()) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Database unavailable']);
        exit;
    }

    $pdo = $db->getConnection();

    $stmt = $pdo->prepare(
        "SELECT s.user_id, s.email, s.name FROM sales s
         WHERE s.tx_ref = ? AND s.payment_status = 'completed' LIMIT 1"
    );
    $stmt->execute([$txRef]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale || !$sale['user_id']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No completed sale found']);
        exit;
    }

    // password_hash intentionally excluded from SELECT
    $stmt = $pdo->prepare(
        "SELECT id, email, username, name, plan_duration, plan_start_date, plan_end_date
         FROM users WHERE id = ?"
    );
    $stmt->execute([$sale['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    echo json_encode([
        'success'         => true,
        'user_id'         => $user['id'],
        'email'           => $user['email'],
        'username'        => $user['username'],
        'name'            => $user['name'],
        'plan_duration'   => $user['plan_duration'],
        'plan_start_date' => $user['plan_start_date'],
        'plan_end_date'   => $user['plan_end_date'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    error_log('get-credentials.php: ' . $e->getMessage());
}
