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

require_once '../config/config.php';
require_once '../classes/Database.php';

try {
    $db = Database::getInstance();
    $id = $_GET['id'] ?? '';

    if (!$id) {
        throw new Exception('Assessment ID is required');
    }

    $assessment = null;

    if ($db->isFileStorage()) {
        $assessments = $db->getAssessments();
        foreach ($assessments as $a) {
            if ($a['id'] === $id) {
                $assessment = $a;
                break;
            }
        }
    } else {
        $stmt = $db->getConnection()->prepare("SELECT * FROM assessments WHERE id = ?");
        $stmt->execute([$id]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($assessment) {
            // Decode JSON fields
            if (isset($assessment['assessment_data'])) {
                $assessment['assessment_data'] = json_decode($assessment['assessment_data'], true);
            }
            if (isset($assessment['notes'])) {
                $assessment['notes'] = json_decode($assessment['notes'], true) ?: [];
            }
        }
    }

    if (!$assessment) {
        throw new Exception('Assessment not found');
    }

    echo json_encode([
        'success' => true,
        'assessment' => $assessment
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>