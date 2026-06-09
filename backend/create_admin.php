<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

echo "Checking admin_users table...\n";

// Explicitly run table creation for admin_users to ensure it exists
$sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active'
)";

try {
    $conn->exec($sql);
    echo "Table verified.\n";
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage() . "\n");
}

// Check for existing admin
$stmt = $conn->prepare("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'");
$stmt->execute();
$count = $stmt->fetchColumn();

if ($count == 0) {
    echo "Creating default admin user...\n";
    $password = password_hash('admin123', PASSWORD_DEFAULT);

    $insert = $conn->prepare("INSERT INTO admin_users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    try {
        $insert->execute(['admin', 'admin@1wellness.club', $password, 'System Administrator', 'admin', 'active']);
        echo "Admin user created successfully.\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } catch (PDOException $e) {
        die("Error creating user: " . $e->getMessage() . "\n");
    }
} else {
    echo "Admin user 'admin' already exists.\n";
}