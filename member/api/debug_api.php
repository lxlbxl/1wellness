<?php
// member/api/debug_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../backend/config/config.php';
require_once '../../backend/classes/Database.php';
require_once '../../backend/classes/MemberAuth.php';

echo "<h1>API Context Debug</h1>";

// 1. Session Check
$auth = new MemberAuth();
$user = $auth->getCurrentUser();

echo "<h2>Session User</h2>";
if ($user) {
    echo "<pre>" . print_r($user, true) . "</pre>";
    $userId = $user['user_id'] ?? $user['id'];
} else {
    echo "NO SESSION USER FOUND. Not logged in?";
    $userId = 1; // Fallback for DB test
    echo "<br>Using Fallback ID: 1";
}

// 2. Database Connection
echo "<h2>Database Config</h2>";
echo "Defined DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'Not Defined') . "<br>";
echo "Defined DB_TYPE: " . (defined('DB_TYPE') ? DB_TYPE : 'Not Defined') . "<br>";

$db = Database::getInstance();
$conn = $db->getConnection();
$dbType = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "<strong>Actual PDO Driver:</strong> " . $dbType . "<br>";

if ($dbType === 'sqlite') {
    // Check SQLite path
    // Need to use reflection or check logic in Database.php to know which file it opened
    echo "Warning: Using SQLite. Is this expected? Production should be MySQL.<br>";
}

// 3. Data Fetch Test
echo "<h2>Data Fetch (User ID: $userId)</h2>";
$userData = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
echo "<pre>User: " . print_r($userData, true) . "</pre>";

$profile = $db->fetch("SELECT * FROM member_profiles WHERE user_id = ?", [$userId]);
echo "<pre>Profile: " . print_r($profile, true) . "</pre>";

// 4. Name Logic Verification
$firstName = $userData['first_name'] ?? '__NULL__';
$name = $userData['name'] ?? '__NULL__';
echo "<h3>Name Logic Check</h3>";
echo "first_name raw: [" . $firstName . "]<br>";
echo "name raw: [" . $name . "]<br>";
echo "Using ??: [" . ($userData['first_name'] ?? $userData['name']) . "] (Current Code)<br>";
echo "Using !empty: [" . (!empty($userData['first_name']) ? $userData['first_name'] : $userData['name']) . "] (Proposed Fix)<br>";
?>