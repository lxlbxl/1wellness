<?php
header('Content-Type: application/json');

require_once '../config/config.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
}

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../classes/Database.php';

try {
    $db = Database::getInstance();
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        throw new Exception('Sale ID is required');
    }
    
    $sale = null;
    
    if ($db->isFileStorage()) {
        $sales = $db->getSales();
        foreach ($sales as $s) {
            if ($s['id'] === $id) {
                $sale = $s;
                break;
            }
        }
    } else {
        $stmt = $db->getConnection()->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->execute([$id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sale) {
            // Decode JSON fields
            if (isset($sale['sale_data'])) {
                $sale['sale_data'] = json_decode($sale['sale_data'], true);
            }
            if (isset($sale['notes'])) {
                $sale['notes'] = json_decode($sale['notes'], true) ?: [];
            }
        }
    }
    
    if (!$sale) {
        throw new Exception('Sale not found');
    }
    
    echo json_encode([
        'success' => true,
        'sale' => $sale
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>