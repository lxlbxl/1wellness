<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../config/config.php';
require_once '../classes/Database.php';

$db = Database::getInstance();
$admin = new Admin();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'delete':
            $id = $_POST['id'] ?? '';
            if ($id) {
                if ($db->isFileStorage()) {
                    $assessments = $db->getAssessments();
                    $assessments = array_filter($assessments, function ($assessment) use ($id) {
                        return $assessment['id'] !== $id;
                    });
                    file_put_contents($db->getDataPath() . '/assessments.json', json_encode(array_values($assessments), JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("DELETE FROM assessments WHERE id = ?");
                    $stmt->execute([$id]);
                }
                $admin->logActivity($_SESSION['admin_id'], 'delete_assessment', "Deleted assessment ID: $id");
                $message = 'Assessment deleted successfully!';
            }
            break;

        case 'update_status':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            if ($id && $status) {
                if ($db->isFileStorage()) {
                    $assessments = $db->getAssessments();
                    foreach ($assessments as &$assessment) {
                        if ($assessment['id'] === $id) {
                            $assessment['status'] = $status;
                            $assessment['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    file_put_contents($db->getDataPath() . '/assessments.json', json_encode($assessments, JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("UPDATE assessments SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $id]);
                }
                $admin->logActivity($_SESSION['admin_id'], 'update_assessment_status', "Updated assessment ID: $id status to $status");
                $message = 'Assessment status updated successfully!';
            }
            break;

        case 'add_note':
            $id = $_POST['id'] ?? '';
            $note = $_POST['note'] ?? '';
            if ($id && $note) {
                if ($db->isFileStorage()) {
                    $assessments = $db->getAssessments();
                    foreach ($assessments as &$assessment) {
                        if ($assessment['id'] === $id) {
                            if (!isset($assessment['notes'])) {
                                $assessment['notes'] = [];
                            }
                            $assessment['notes'][] = [
                                'note' => $note,
                                'created_at' => date('Y-m-d H:i:s'),
                                'created_by' => $_SESSION['admin_username'] ?? 'Admin'
                            ];
                            $assessment['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    file_put_contents($db->getDataPath() . '/assessments.json', json_encode($assessments, JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("UPDATE assessments SET notes = JSON_ARRAY_APPEND(COALESCE(notes, JSON_ARRAY()), '$', JSON_OBJECT('note', ?, 'created_at', NOW(), 'created_by', ?)), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$note, $_SESSION['admin_username'] ?? 'Admin', $id]);
                }
                $admin->logActivity($_SESSION['admin_id'], 'add_assessment_note', "Added note to assessment ID: $id");
                $message = 'Note added successfully!';
            }
            break;
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// Get assessments
$assessments = $db->getAssessments();

// Apply filters
if ($filter_type) {
    $assessments = array_filter($assessments, function ($assessment) use ($filter_type) {
        return ($assessment['assessment_type'] ?? 'general') === $filter_type;
    });
}

if ($filter_status) {
    $assessments = array_filter($assessments, function ($assessment) use ($filter_status) {
        return ($assessment['status'] ?? 'pending') === $filter_status;
    });
}

if ($search) {
    $assessments = array_filter($assessments, function ($assessment) use ($search) {
        $searchFields = [
            $assessment['name'] ?? '',
            $assessment['email'] ?? '',
            $assessment['phone'] ?? '',
            json_encode($assessment['assessment_data'] ?? [])
        ];
        return stripos(implode(' ', $searchFields), $search) !== false;
    });
}

// Sort by created_at descending
usort($assessments, function ($a, $b) {
    return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
});

// Pagination
$total_assessments = count($assessments);
$total_pages = ceil($total_assessments / $per_page);
$offset = ($page - 1) * $per_page;
$assessments = array_slice($assessments, $offset, $per_page);

// Get statistics
$stats = [
    'total' => $db->getAssessmentCount(),
    'pending' => $db->getAssessmentCount('pending'),
    'completed' => $db->getAssessmentCount('completed'),
    'follow_up' => $db->getAssessmentCount('follow_up')
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Management - 1wellness Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-green-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-xl font-bold">1wellness Admin</a>
                <span class="text-green-200">/ Assessment Management</span>
            </div>
            <div class="flex items-center space-x-4">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="bg-green-700 px-3 py-1 rounded hover:bg-green-800">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-clipboard-list text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Assessments</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['pending']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['completed']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-user-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Follow Up</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['follow_up']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assessment Type</label>
                    <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">All Types</option>
                        <option value="pcos" <?php echo $filter_type === 'pcos' ? 'selected' : ''; ?>>PCOS</option>
                        <option value="acne" <?php echo $filter_type === 'acne' ? 'selected' : ''; ?>>Acne</option>
                        <option value="weight" <?php echo $filter_type === 'weight' ? 'selected' : ''; ?>>Weight Loss
                        </option>
                        <option value="egbon" <?php echo $filter_type === 'egbon' ? 'selected' : ''; ?>>Egbon</option>
                        <option value="general" <?php echo $filter_type === 'general' ? 'selected' : ''; ?>>General
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>
                            Completed</option>
                        <option value="follow_up" <?php echo $filter_status === 'follow_up' ? 'selected' : ''; ?>>Follow
                            Up</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by name, email, phone..."
                        class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 mr-2">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="assessments.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        <i class="fas fa-times mr-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Assessments Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    Assessments (<?php echo $total_assessments; ?> total)
                </h3>
            </div>

            <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                                Created
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($assessments as $assessment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div
                                                class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                                <i class="fas fa-user text-green-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($assessment['name'] ?? 'N/A'); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($assessment['email'] ?? 'N/A'); ?>
                                            </div>
                                            <!-- Show type on mobile -->
                                            <div class="text-sm text-gray-500 sm:hidden">
                                                <?php echo htmlspecialchars(ucfirst($assessment['type'] ?? 'general')); ?>
                                            </div>
                                            <?php if (!empty($assessment['phone'])): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($assessment['phone']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        $type = $assessment['assessment_type'] ?? 'general';
                                        switch ($type) {
                                            case 'pcos':
                                                echo 'bg-pink-100 text-pink-800';
                                                break;
                                            case 'acne':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'weight':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo strtoupper($type); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        $status = $assessment['status'] ?? 'pending';
                                        switch ($status) {
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'follow_up':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden lg:table-cell">
                                    <?php echo date('M j, Y g:i A', strtotime($assessment['created_at'] ?? 'now')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-1">
                                        <button onclick="viewAssessment('<?php echo $assessment['id']; ?>')"
                                            class="text-blue-600 hover:text-blue-900 p-1" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="updateStatus('<?php echo $assessment['id']; ?>')"
                                            class="text-green-600 hover:text-green-900 p-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="addNote('<?php echo $assessment['id']; ?>')"
                                            class="text-purple-600 hover:text-purple-900 p-1" title="Add Note">
                                            <i class="fas fa-sticky-note"></i>
                                        </button>
                                        <button onclick="deleteAssessment('<?php echo $assessment['id']; ?>')"
                                            class="text-red-600 hover:text-red-900 p-1" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                                <span class="font-medium"><?php echo min($offset + $per_page, $total_assessments); ?></span>
                                of
                                <span class="font-medium"><?php echo $total_assessments; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                        class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                              <?php echo $i === $page ? 'z-10 bg-green-50 border-green-500 text-green-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals -->
    <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Assessment Details</h3>
                    <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="assessmentDetails" class="space-y-4">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="statusAssessmentId">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Update Status</h3>
                        <button type="button" onclick="closeModal('statusModal')"
                            class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="follow_up">Follow Up</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeModal('statusModal')"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            Update Status
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="noteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <form method="POST">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="id" id="noteAssessmentId">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Add Note</h3>
                        <button type="button" onclick="closeModal('noteModal')"
                            class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                        <textarea name="note" rows="4" class="w-full border border-gray-300 rounded-md px-3 py-2"
                            placeholder="Enter your note here..." required></textarea>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeModal('noteModal')"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            Add Note
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewAssessment(id) {
            // Fetch assessment details via AJAX
            fetch(`../api/get-assessment.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAssessmentDetails(data.assessment);
                        document.getElementById('viewModal').classList.remove('hidden');
                    } else {
                        alert('Error loading assessment details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading assessment details');
                });
        }

        function displayAssessmentDetails(assessment) {
            const container = document.getElementById('assessmentDetails');
            let html = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Personal Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong>Name:</strong> ${assessment.name || 'N/A'}</p>
                            <p><strong>Email:</strong> ${assessment.email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${assessment.phone || 'N/A'}</p>
                            <p><strong>Age:</strong> ${assessment.age || 'N/A'}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Assessment Info</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong>Type:</strong> ${(assessment.assessment_type || 'general').toUpperCase()}</p>
                            <p><strong>Status:</strong> ${assessment.status || 'pending'}</p>
                            <p><strong>Created:</strong> ${new Date(assessment.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                </div>
            `;

            if (assessment.assessment_data) {
                html += `
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-2">Assessment Data</h4>
                        <div class="bg-gray-50 p-4 rounded-md">
                            <pre class="text-sm text-gray-700 whitespace-pre-wrap">${JSON.stringify(assessment.assessment_data, null, 2)}</pre>
                        </div>
                    </div>
                `;
            }

            if (assessment.notes && assessment.notes.length > 0) {
                html += `
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-2">Notes</h4>
                        <div class="space-y-2">
                `;
                assessment.notes.forEach(note => {
                    html += `
                        <div class="bg-yellow-50 p-3 rounded-md border-l-4 border-yellow-400">
                            <p class="text-sm text-gray-700">${note.note}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                By ${note.created_by} on ${new Date(note.created_at).toLocaleString()}
                            </p>
                        </div>
                    `;
                });
                html += `
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }

        function updateStatus(id) {
            document.getElementById('statusAssessmentId').value = id;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function addNote(id) {
            document.getElementById('noteAssessmentId').value = id;
            document.getElementById('noteModal').classList.remove('hidden');
        }

        function deleteAssessment(id) {
            if (confirm('Are you sure you want to delete this assessment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function (event) {
            const modals = ['viewModal', 'statusModal', 'noteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });
    </script>
</body>

</html>