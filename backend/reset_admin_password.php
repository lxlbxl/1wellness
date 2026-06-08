<?php
/**
 * Admin Password Reset Script
 * Upload to backend/ folder and run via browser
 * Delete after use for security
 */

// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$new_password = 'Admin@2025!';  // Change this to your desired password
$admin_username = 'admin';       // The admin username to update

echo "<h2>Admin Password Reset</h2>";

try {
    // Check if running from web or CLI
    $is_web = php_sapi_name() !== 'cli';

    if ($is_web) {
        // Security check - only allow from localhost or with secret key
        $allowed_hosts = ['localhost', '127.0.0.1', '::1'];
        $secret_key = $_GET['key'] ?? '';
        $expected_key = 'reset2025'; // Change this for security

        if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_hosts) && $secret_key !== $expected_key) {
            die("<p style='color:red;'>Access denied. Add ?key=reset2025 to URL or run from localhost.</p>");
        }
    }

    // Load database configuration
    $config_file = __DIR__ . '/config/config.php';
    if (!file_exists($config_file)) {
        // Try alternative config locations
        $config_file = __DIR__ . '/config.php';
    }

    if (file_exists($config_file)) {
        require_once $config_file;
    }

    // Determine database type and connect
    if (defined('DB_FILE')) {
        // SQLite
        $db_path = DB_FILE;
        $pdo = new PDO('sqlite:' . $db_path);
        $db_type = 'sqlite';
    } elseif (defined('DB_HOST')) {
        // MySQL
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $db_type = 'mysql';
    } else {
        // Try to auto-detect SQLite database
        $possible_paths = [
            __DIR__ . '/database/herbal_essentials.sqlite',
            __DIR__ . '/database/',
            __DIR__ . '/herbal_essentials.sqlite',
            __DIR__ . '/../database/herbal_essentials.sqlite'
        ];

        $db_found = false;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $pdo = new PDO('sqlite:' . $path);
                $db_type = 'sqlite';
                $db_found = true;
                echo "<p>Found database: $path</p>";
                break;
            }
        }

        if (!$db_found) {
            die("<p style='color:red;'>Could not find database. Please check configuration.</p>");
        }
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if admin_users table exists
    $table_exists = false;
    if ($db_type === 'sqlite') {
        $table_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
        $table_exists = $table_check->fetch();
    } else {
        // MySQL
        $table_check = $pdo->query("SHOW TABLES LIKE 'admin_users'");
        $table_exists = $table_check->fetch();
    }

    if (!$table_exists) {
        die("<p style='color:red;'>admin_users table not found!</p>");
    }

    // Check current admin user
    $stmt = $pdo->prepare("SELECT id, username, email FROM admin_users WHERE username = ?");
    $stmt->execute([$admin_username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo "<p style='color:orange;'>Admin user '$admin_username' not found. Creating new admin...</p>";

        // Create new admin user
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $email = 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        // Use appropriate datetime syntax for database type
        if ($db_type === 'sqlite') {
            $insert = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email, role, created_at) VALUES (?, ?, ?, 'super_admin', datetime('now'))");
        } else {
            // MySQL
            $insert = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email, role, created_at) VALUES (?, ?, ?, 'super_admin', NOW())");
        }
        $insert->execute([$admin_username, $hashed_password, $email]);

        echo "<p style='color:green;'><strong>✓ New admin user created successfully!</strong></p>";
    } else {
        // Update existing admin password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
        $update->execute([$hashed_password, $admin_username]);

        echo "<p style='color:green;'><strong>✓ Password updated successfully for user: {$admin['username']}</strong></p>";
        echo "<p>Email: {$admin['email']}</p>";
    }

    echo "<hr>";
    echo "<h3>Login Credentials</h3>";
    echo "<p><strong>Username:</strong> $admin_username</p>";
    echo "<p><strong>Password:</strong> $new_password</p>";
    echo "<p><strong>Login URL:</strong> <a href='/backend/admin/'>/backend/admin/</a></p>";

    echo "<hr>";
    echo "<p style='color:red;'><strong>⚠️ IMPORTANT: Delete this file (reset_admin_password.php) immediately after use!</strong></p>";

} catch (Exception $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>";
}
?>