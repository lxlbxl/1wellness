<?php
/**
 * Database Initialization Script
 * Creates and sets up the SQLite database with schema
 */

// Define application root if not already defined
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load configuration
require_once APP_ROOT . '/config/config.php';

class DatabaseInitializer {
    private $db;
    private $dbPath;
    
    public function __construct() {
        $this->dbPath = DB_PATH;
        $this->createDatabaseDirectory();
    }
    
    private function createDatabaseDirectory() {
        $dbDir = dirname($this->dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
            echo "Created database directory: $dbDir\n";
        }
    }
    
    public function initialize() {
        try {
            // Create SQLite database connection
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "Connected to SQLite database: " . $this->dbPath . "\n";
            
            // Read and execute schema
            $this->executeSchema();
            
            // Insert sample data if in development mode
            if (APP_ENV === 'development') {
                $this->insertSampleData();
            }
            
            echo "Database initialization completed successfully!\n";
            
        } catch (PDOException $e) {
            die("Database initialization failed: " . $e->getMessage() . "\n");
        }
    }
    
    private function executeSchema() {
        $schemaFile = __DIR__ . '/schema.sql';
        
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: $schemaFile");
        }
        
        $schema = file_get_contents($schemaFile);
        
        // Split schema into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
            }
        );
        
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                try {
                    $this->db->exec($statement);
                } catch (PDOException $e) {
                    echo "Warning: Failed to execute statement: " . $e->getMessage() . "\n";
                    echo "Statement: " . substr($statement, 0, 100) . "...\n";
                }
            }
        }
        
        echo "Schema executed successfully!\n";
    }
    
    private function insertSampleData() {
        echo "Inserting sample data for development...\n";
        
        // Sample users
        $sampleUsers = [
            ['email' => 'jane.doe@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '+1-555-0101', 'gender' => 'female'],
            ['email' => 'john.smith@example.com', 'first_name' => 'John', 'last_name' => 'Smith', 'phone' => '+1-555-0102', 'gender' => 'male'],
            ['email' => 'sarah.wilson@example.com', 'first_name' => 'Sarah', 'last_name' => 'Wilson', 'phone' => '+1-555-0103', 'gender' => 'female'],
        ];
        
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO users (email, first_name, last_name, phone, gender) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($sampleUsers as $user) {
            $stmt->execute([$user['email'], $user['first_name'], $user['last_name'], $user['phone'], $user['gender']]);
        }
        
        // Sample PCOS assessments
        $pcosData = [
            [1, 28, 65.5, 165, 24.1, 'irregular', '["irregular_periods", "weight_gain", "acne"]', '["sedentary", "high_stress"]', 'No significant medical history', '', 75],
            [2, 32, 70.0, 160, 27.3, 'regular', '["weight_gain", "hair_loss"]', '["moderate_exercise", "balanced_diet"]', 'Family history of diabetes', '', 60],
        ];
        
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO pcos_assessments (user_id, age, weight, height, bmi, menstrual_cycle, symptoms, lifestyle_factors, medical_history, current_medications, assessment_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($pcosData as $data) {
            $stmt->execute($data);
        }
        
        // Sample Acne assessments
        $acneData = [
            [1, 25, 'oily', 'moderate', '["inflammatory", "comedonal"]', '["dairy", "stress"]', 'Topical retinoids', 'Morning and evening cleansing routine', '["high_stress", "poor_sleep"]', 65],
            [3, 22, 'combination', 'mild', '["comedonal"]', '["hormonal_changes"]', 'Over-the-counter products', 'Basic cleansing routine', '["balanced_lifestyle"]', 40],
        ];
        
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO acne_assessments (user_id, age, skin_type, acne_severity, acne_type, triggers, current_treatment, skincare_routine, lifestyle_factors, assessment_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($acneData as $data) {
            $stmt->execute($data);
        }
        
        // Sample Weight assessments
        $weightData = [
            [2, 35, 80.0, 170, 27.7, 75.0, 25.9, 'lightly_active', '["vegetarian", "low_carb"]', 'Hypothyroidism', 'Gradual weight gain over 5 years', '["lose_weight", "improve_energy"]', 70],
            [3, 29, 55.0, 155, 22.9, 60.0, 24.9, 'moderately_active', '["balanced", "organic"]', 'None', 'Stable weight', '["gain_muscle", "improve_fitness"]', 45],
        ];
        
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO weight_assessments (user_id, age, current_weight, height, current_bmi, target_weight, target_bmi, activity_level, diet_preferences, health_conditions, weight_history, goals, assessment_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($weightData as $data) {
            $stmt->execute($data);
        }
        
        // Sample sales
        $salesData = [
            [1, 'pcos', 1, 'PCOS Support Bundle', 89.99, 1, 89.99, 'completed', 'credit_card', 'TXN_001'],
            [2, 'weight', 1, 'Weight Management Kit', 129.99, 1, 129.99, 'completed', 'paypal', 'TXN_002'],
            [3, 'acne', 1, 'Acne Clear System', 69.99, 1, 69.99, 'pending', 'credit_card', 'TXN_003'],
        ];
        
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO sales (user_id, category, assessment_id, product_name, product_price, quantity, total_amount, payment_status, payment_method, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($salesData as $data) {
            $stmt->execute($data);
        }
        
        // Sample contacts
        $contactsData = [
            ['Emma Johnson', 'emma.j@example.com', '+1-555-0201', 'pcos', 'PCOS Consultation', 'I would like to learn more about PCOS management options.', 'pcos/index.html'],
            ['Michael Brown', 'michael.b@example.com', '+1-555-0202', 'acne', 'Acne Treatment', 'Looking for natural acne treatment solutions.', 'acne/index.html'],
            ['Lisa Davis', 'lisa.d@example.com', '+1-555-0203', 'weight', 'Weight Loss Program', 'Interested in your weight management program.', 'weight/index.html'],
        ];
        
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO contacts (name, email, phone, category, subject, message, source) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($contactsData as $data) {
            $stmt->execute($data);
        }
        
        echo "Sample data inserted successfully!\n";
    }
    
    public function getStats() {
        $tables = ['users', 'pcos_assessments', 'acne_assessments', 'weight_assessments', 'sales', 'contacts', 'admin_users'];
        
        echo "\nDatabase Statistics:\n";
        echo str_repeat("-", 30) . "\n";
        
        foreach ($tables as $table) {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo sprintf("%-20s: %d records\n", ucfirst(str_replace('_', ' ', $table)), $count);
        }
        
        echo str_repeat("-", 30) . "\n";
    }
}

// Run initialization if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "1wellness Database Initialization\n";
    echo "==================================\n\n";
    
    $initializer = new DatabaseInitializer();
    $initializer->initialize();
    $initializer->getStats();
    
    echo "\nDatabase is ready for use!\n";
    echo "Default admin credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    echo "\nPlease change the default password after first login!\n";
}
?>