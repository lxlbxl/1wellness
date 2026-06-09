<?php
/**
 * Database Creation Script
 * Creates the SQLite database and initializes the schema
 */

// Set the database path
$dbPath = __DIR__ . '/1wellness.db';
$schemaPath = __DIR__ . '/schema.sql';

try {
    echo "1wellness Database Creation\n";
    echo "============================\n\n";
    
    // Remove existing database if it exists
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "Removed existing database file.\n";
    }
    
    // Create new SQLite database
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Created new SQLite database: " . basename($dbPath) . "\n";
    
    // Read and execute schema
    if (!file_exists($schemaPath)) {
        throw new Exception("Schema file not found: " . $schemaPath);
    }
    
    $schema = file_get_contents($schemaPath);
    
    // Split schema into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    echo "Executing " . count($statements) . " SQL statements...\n";
    
    foreach ($statements as $i => $statement) {
        if (!empty(trim($statement))) {
            try {
                $pdo->exec($statement);
                echo "✓ Statement " . ($i + 1) . " executed successfully\n";
            } catch (PDOException $e) {
                echo "✗ Error in statement " . ($i + 1) . ": " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    // Create default admin user
    echo "\nCreating default admin user...\n";
    
    $adminStmt = $pdo->prepare("
        INSERT INTO admin_users (username, email, password, full_name, role, permissions, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $permissions = json_encode(['read', 'write', 'delete', 'manage_users', 'manage_admins']);
    
    $adminStmt->execute([
        'admin',
        'admin@1wellness.club',
        $defaultPassword,
        'System Administrator',
        'super_admin',
        $permissions,
        'active'
    ]);
    
    echo "✓ Default admin user created\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "  Email: admin@1wellness.club\n";
    
    // Verify database structure
    echo "\nVerifying database structure...\n";
    
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Created tables:\n";
    foreach ($tables as $table) {
        if ($table !== 'sqlite_sequence') {
            $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            echo "  - {$table} ({$count} records)\n";
        }
    }
    
    echo "\n✓ Database created successfully!\n";
    echo "Database file: " . $dbPath . "\n";
    echo "File size: " . number_format(filesize($dbPath)) . " bytes\n";
    
} catch (Exception $e) {
    echo "\n✗ Database creation failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>