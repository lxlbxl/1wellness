<?php
/**
 * Credential lookup — fallback for when the purchase webhook's synchronous
 * response was slow or missed (see pollForCredentials() in each funnel's
 * thank-you.html).
 *
 * Callers must supply ?tx_ref=X&email=Y — both the transaction reference
 * (not guessable; comes from Flutterwave) and the purchasing customer's own
 * email (which they already know) must match the same completed sale
 * record. Also bounded to a recent window so old sales can't be probed
 * indefinitely.
 */
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$txRef = trim($_GET['tx_ref'] ?? '');
$email = trim($_GET['email'] ?? '');

if (!$txRef || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing tx_ref or email']);
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
         WHERE s.tx_ref = ? AND s.payment_status = 'completed'
           AND s.created_at >= datetime('now', '-24 hours') LIMIT 1"
    );
    $stmt->execute([$txRef]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale || !$sale['user_id'] || strcasecmp($sale['email'], $email) !== 0) {
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

    // Issue a fresh auto-login token, same as the primary webhook path, so
    // this fallback gives the customer an equally complete "Go to My
    // Dashboard" experience rather than degrading to a manual-login-only link.
    $autoLoginToken = bin2hex(random_bytes(16));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$user['id'], $autoLoginToken, $tokenExpiry]);

    echo json_encode([
        'success'          => true,
        'user_id'          => $user['id'],
        'email'            => $user['email'],
        'username'         => $user['username'],
        'name'             => $user['name'],
        'plan_duration'    => $user['plan_duration'],
        'plan_start_date'  => $user['plan_start_date'],
        'plan_end_date'    => $user['plan_end_date'],
        'auto_login_token' => $autoLoginToken,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    error_log('get-credentials.php: ' . $e->getMessage());
}
