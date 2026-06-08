<?php
// session_start(); // Handled by config.php
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../../database/SqliteDB.php';

// Helper function for dashboard conversion data
function processConversionData($rawData, $days)
{
    $labels = [];
    $funnels = ['pcos', 'acne', 'weight', 'egbon'];
    $datasets = [];
    $colors = [
        'pcos' => ['assessments' => 'rgba(255, 99, 132, 0.5)', 'sales' => 'rgba(255, 99, 132, 1)'],
        'acne' => ['assessments' => 'rgba(54, 162, 235, 0.5)', 'sales' => 'rgba(54, 162, 235, 1)'],
        'weight' => ['assessments' => 'rgba(255, 206, 86, 0.5)', 'sales' => 'rgba(255, 206, 86, 1)'],
        'egbon' => ['assessments' => 'rgba(75, 192, 192, 0.5)', 'sales' => 'rgba(75, 192, 192, 1)'],
    ];

    foreach ($funnels as $funnel) {
        $datasets[$funnel . '_assessments'] = [
            'label' => ucfirst($funnel) . ' Assessments',
            'data' => array_fill(0, $days, 0),
            'backgroundColor' => $colors[$funnel]['assessments'],
            'stack' => 'assessments',
        ];
        $datasets[$funnel . '_sales'] = [
            'label' => ucfirst($funnel) . ' Sales',
            'data' => array_fill(0, $days, 0),
            'backgroundColor' => $colors[$funnel]['sales'],
            'stack' => 'sales',
        ];
    }

    $dateMap = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('M d', strtotime($date));
        $dateMap[$date] = $days - 1 - $i;
    }

    if (isset($rawData['assessments'])) {
        foreach ($rawData['assessments'] as $date => $entries) {
            if (isset($dateMap[$date])) {
                $index = $dateMap[$date];
                foreach ($entries as $entry) {
                    $funnel = $entry['assessment_type'];
                    if (isset($datasets[$funnel . '_assessments'])) {
                        $datasets[$funnel . '_assessments']['data'][$index] += (int) $entry['count'];
                    }
                }
            }
        }
    }

    if (isset($rawData['sales'])) {
        foreach ($rawData['sales'] as $date => $entries) {
            if (isset($dateMap[$date])) {
                $index = $dateMap[$date];
                foreach ($entries as $entry) {
                    $funnel = $entry['assessment_type'];
                    if (isset($datasets[$funnel . '_sales'])) {
                        $datasets[$funnel . '_sales']['data'][$index] += (int) $entry['count'];
                    }
                }
            }
        }
    }

    return ['labels' => $labels, 'datasets' => array_values($datasets)];
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = new SqliteDB();

$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            $days = 7;
            $stats = $db->getDashboardStats();
            $rawConversionData = $db->getDailyConversionData($days);
            $dailyConversion = processConversionData($rawConversionData, $days);
            $recentActivities = $db->getAdminActivityLogs(10);

            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'chart_labels' => $dailyConversion['labels'],
                'daily_conversion' => $dailyConversion['datasets'],
                'recent_activity' => $recentActivities,
                'system' => [
                    'storage' => 'Database',
                    'status' => 'Online'
                ]
            ]);
            break;

        case 'users':
            $params = [
                'search' => $_GET['search'] ?? null,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100
            ];
            $users = $db->getUsers($params);
            echo json_encode(['success' => true, 'data' => $users]);
            break;

        case 'audit':
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
            $logs = $db->getAdminActivityLogs($limit);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;

        case 'user_details':
            $id = $_GET['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $user = $db->getUserById($id);
            if (!$user)
                throw new Exception('User not found');
            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'update_user':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';

            if (!$id || !$name || !$email)
                throw new Exception('Missing required fields');

            $db->updateUser($id, ['name' => $name, 'email' => $email, 'phone' => $phone, 'user_type' => $_POST['user_type']]);
            $db->logAdminActivity($_SESSION['admin_id'], 'update_user', "Updated user ID: $id");
            echo json_encode(['success' => true, 'message' => 'User updated']);
            break;

        case 'delete_user':
            $id = $_POST['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $db->deleteUser($id);
            $db->logAdminActivity($_SESSION['admin_id'], 'delete_user', "Deleted user ID: $id");
            echo json_encode(['success' => true, 'message' => 'User deleted']);
            break;

        case 'assessments':
            $params = [
                'funnel' => $_GET['funnel'] ?? null,
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100
            ];

            $assessments = $db->getAssessments($params);
            $total = $db->getAssessmentCount($params);

            echo json_encode([
                'success' => true,
                'data' => $assessments,
                'total' => $total
            ]);
            break;

        case 'assessment_details':
            $id = $_GET['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $assessment = $db->getAssessmentById($id);
            if (!$assessment)
                throw new Exception('Assessment not found');
            echo json_encode(['success' => true, 'assessment' => $assessment]);
            break;

        case 'update_assessment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST')
                throw new Exception('POST required');
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? null;
            $note = $_POST['note'] ?? null;

            if (!$id)
                throw new Exception('ID required');

            $updates = [];
            if ($status)
                $updates['status'] = $status;
            if ($note)
                $updates['note'] = [
                    'note' => $note,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $_SESSION['admin_username'] ?? 'Admin'
                ];

            if (!empty($updates)) {
                $db->updateAssessment($id, $updates);
                $db->logAdminActivity($_SESSION['admin_id'], 'update_assessment', "Updated assessment ID: $id");
            }
            break;

        case 'tracking':
            $data = $db->getTrackingData();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'delete_assessment':
            $id = $_POST['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $db->deleteAssessment($id);
            $db->logAdminActivity($_SESSION['admin_id'], 'delete_assessment', "Deleted assessment ID: $id");
            echo json_encode(['success' => true, 'message' => 'Assessment deleted']);
            break;

        case 'sales':
            $params = [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100
            ];

            $sales = $db->getSales($params);
            $total = $db->getSalesCount($params);

            echo json_encode([
                'success' => true,
                'data' => $sales,
                'total' => $total
            ]);
            break;

        case 'sale_details':
            $id = $_GET['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $sale = $db->getSaleById($id);
            if (!$sale)
                throw new Exception('Sale not found');
            echo json_encode(['success' => true, 'sale' => $sale]);
            break;

        case 'update_sale':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? null;
            $note = $_POST['note'] ?? null;

            if (!$id)
                throw new Exception('ID required');

            if ($status) {
                $db->updateSale($id, ['payment_status' => $status]);
                $db->logAdminActivity($_SESSION['admin_id'], 'update_sale_status', "Updated sale ID: $id to status: $status");
            }

            if ($note) {
                $db->addSaleNote($id, $note, $_SESSION['admin_username']);
                $db->logAdminActivity($_SESSION['admin_id'], 'add_sale_note', "Added note to sale ID: $id");
            }

            echo json_encode(['success' => true, 'message' => 'Sale updated']);
            break;

        case 'tracking_logs':
            $funnel = $_GET['funnel'] ?? null;
            $event = $_GET['event'] ?? null;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
            $logs = $db->getTrackingLogs($funnel, $event, $limit);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
