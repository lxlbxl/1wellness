<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$txRef = $_GET['tx_ref'] ?? '';
$email = $_GET['email'] ?? '';

if (!$txRef && !$email) {
    echo json_encode(['success' => false, 'error' => 'Missing tx_ref or email']);
    exit;
}

try {
    $db = Database::getInstance();
    if ($db->isFileStorage()) {
        echo json_encode(['success' => false, 'error' => 'Database unavailable']);
        exit;
    }

    $pdo = $db->getConnection();

    if ($txRef) {
        $stmt = $pdo->prepare("SELECT s.user_id, s.email, s.name FROM sales s WHERE s.tx_ref = ? AND s.payment_status = 'completed' LIMIT 1");
        $stmt->execute([$txRef]);
    } else {
        $stmt = $pdo->prepare("SELECT s.user_id, s.email, s.name FROM sales s WHERE s.email = ? AND s.payment_status = 'completed' ORDER BY s.created_at DESC LIMIT 1");
        $stmt->execute([$email]);
    }

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale || !$sale['user_id']) {
        echo json_encode(['success' => false, 'error' => 'No completed sale found']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, email, username, password_hash, name, plan_duration, plan_start_date, plan_end_date FROM users WHERE id = ?");
    $stmt->execute([$sale['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user_id' => $user['id'],
        'email' => $user['email'],
        'username' => $user['username'],
        'name' => $user['name'],
        'plan_duration' => $user['plan_duration'],
        'plan_start_date' => $user['plan_start_date'],
        'plan_end_date' => $user['plan_end_date']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
