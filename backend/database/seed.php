<?php
require_once __DIR__ . '/database.php';

class Seeder
{
    public static function run()
    {
        $pdo = Database::getPDO();

        // Clear existing data
        $pdo->exec('DELETE FROM users');
        $pdo->exec('DELETE FROM assessments');
        $pdo->exec('DELETE FROM sales');
        $pdo->exec('DELETE FROM admins');

        // Create Admin User
        $adminUsername = 'admin';
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$adminUsername, $adminPassword]);
        echo "Admin user created: username='admin', password='admin123'\n";

        // Create Dummy Users
        $users = [
            ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'phone' => '1234567890', 'user_type' => 'customer'],
            ['name' => 'Bob Williams', 'email' => 'bob@example.com', 'phone' => '0987654321', 'user_type' => 'lead']
        ];
        $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, user_type) VALUES (?, ?, ?, ?)');
        foreach ($users as $user) {
            $stmt->execute([$user['name'], $user['email'], $user['phone'], $user['user_type']]);
        }
        echo count($users) . " dummy users created.\n";

        // Create Dummy Assessments
        $assessments = [
            [
                'user_id' => 1,
                'assessment_type' => 'pcos',
                'assessment_data' => json_encode(['question1' => 'answer1', 'question2' => 'answer2'])
            ]
        ];
        $stmt = $pdo->prepare('INSERT INTO assessments (user_id, assessment_type, assessment_data) VALUES (?, ?, ?)');
        foreach ($assessments as $assessment) {
            $stmt->execute([$assessment['user_id'], $assessment['assessment_type'], $assessment['assessment_data']]);
        }
        echo count($assessments) . " dummy assessments created.\n";

        // Create Dummy Sales
        $sales = [
            [
                'user_id' => 1,
                'product_name' => 'Herbal Tea',
                'amount' => 25.00,
                'currency' => 'USD',
                'payment_status' => 'paid',
                'transaction_id' => 'txn_12345'
            ]
        ];
        $stmt = $pdo->prepare('INSERT INTO sales (user_id, product_name, amount, currency, payment_status, transaction_id) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($sales as $sale) {
            $stmt->execute([$sale['user_id'], $sale['product_name'], $sale['amount'], $sale['currency'], $sale['payment_status'], $sale['transaction_id']]);
        }
        echo count($sales) . " dummy sales created.\n";

        echo "Database seeded successfully.\n";
    }
}

if (php_sapi_name() === 'cli') {
    Seeder::run();
}
?>