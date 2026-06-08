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
                    $sales = $db->getSales();
                    $sales = array_filter($sales, function($sale) use ($id) {
                        return $sale['id'] !== $id;
                    });
                    file_put_contents($db->getDataPath() . '/sales.json', json_encode(array_values($sales), JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("DELETE FROM sales WHERE id = ?");
                    $stmt->execute([$id]);
                }
                $message = 'Sale deleted successfully!';
            }
            break;
            
        case 'update_status':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            if ($id && $status) {
                if ($db->isFileStorage()) {
                    $sales = $db->getSales();
                    foreach ($sales as &$sale) {
                        if ($sale['id'] === $id) {
                            $sale['payment_status'] = $status;
                            $sale['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    file_put_contents($db->getDataPath() . '/sales.json', json_encode($sales, JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("UPDATE sales SET payment_status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $id]);
                }
                $message = 'Payment status updated successfully!';
            }
            break;
            
        case 'add_note':
            $id = $_POST['id'] ?? '';
            $note = $_POST['note'] ?? '';
            if ($id && $note) {
                if ($db->isFileStorage()) {
                    $sales = $db->getSales();
                    foreach ($sales as &$sale) {
                        if ($sale['id'] === $id) {
                            if (!isset($sale['notes'])) {
                                $sale['notes'] = [];
                            }
                            $sale['notes'][] = [
                                'note' => $note,
                                'created_at' => date('Y-m-d H:i:s'),
                                'created_by' => $_SESSION['admin_username'] ?? 'Admin'
                            ];
                            $sale['updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    file_put_contents($db->getDataPath() . '/sales.json', json_encode($sales, JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("UPDATE sales SET notes = JSON_ARRAY_APPEND(COALESCE(notes, JSON_ARRAY()), '$', JSON_OBJECT('note', ?, 'created_at', NOW(), 'created_by', ?)), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$note, $_SESSION['admin_username'] ?? 'Admin', $id]);
                }
                $message = 'Note added successfully!';
            }
            break;
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_product = $_GET['product'] ?? '';
$filter_product_type = $_GET['product_type'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_dir = strtolower($_GET['sort_dir'] ?? 'desc');
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// Get sales
$sales = $db->getSales();

// Apply filters
if ($filter_status) {
    $sales = array_filter($sales, function($sale) use ($filter_status) {
        return ($sale['payment_status'] ?? 'pending') === $filter_status;
    });
}

if ($filter_product) {
    $sales = array_filter($sales, function($sale) use ($filter_product) {
        return stripos($sale['product_name'] ?? '', $filter_product) !== false;
    });
}

if ($filter_product_type) {
    $sales = array_filter($sales, function($sale) use ($filter_product_type) {
        return stripos($sale['product_type'] ?? '', $filter_product_type) !== false;
    });
}

if ($date_start) {
    $sales = array_filter($sales, function($sale) use ($date_start) {
        return strtotime($sale['created_at'] ?? '0') >= strtotime($date_start);
    });
}

if ($date_end) {
    $sales = array_filter($sales, function($sale) use ($date_end) {
        return strtotime($sale['created_at'] ?? '0') <= strtotime($date_end . ' 23:59:59');
    });
}

if ($search) {
    $sales = array_filter($sales, function($sale) use ($search) {
        $searchFields = [
            $sale['customer_name'] ?? '',
            $sale['customer_email'] ?? '',
            $sale['customer_phone'] ?? '',
            $sale['product_name'] ?? '',
            $sale['transaction_id'] ?? ''
        ];
        return stripos(implode(' ', $searchFields), $search) !== false;
    });
}

usort($sales, function($a, $b) use ($sort_by,$sort_dir) {
    $va = $a[$sort_by] ?? '';
    $vb = $b[$sort_by] ?? '';
    if ($sort_by === 'amount') {
        $va = floatval($va);
        $vb = floatval($vb);
    } else {
        $va = strtotime($va) ?: $va;
        $vb = strtotime($vb) ?: $vb;
    }
    $res = ($va <=> $vb);
    return $sort_dir === 'asc' ? $res : -$res;
});

// Pagination
$total_sales = count($sales);
$total_pages = ceil($total_sales / $per_page);
$offset = ($page - 1) * $per_page;
$sales = array_slice($sales, $offset, $per_page);

// Get statistics
$stats = [
    'total' => $db->getSalesCount(),
    'completed' => $db->getSalesCount('completed'),
    'pending' => $db->getSalesCount('pending'),
    'failed' => $db->getSalesCount('failed'),
    'total_revenue' => $db->getTotalRevenue()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management - 1wellness Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-green-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-xl font-bold">1wellness Admin</a>
                <span class="text-green-200">/ Sales Management</span>
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
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-shopping-cart text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Sales</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total']; ?></p>
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
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-times-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Failed</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['failed']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-dollar-sign text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">₦<?php echo number_format($stats['total_revenue'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $filter_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                    <input type="text" name="product" value="<?php echo htmlspecialchars($filter_product); ?>" 
                           placeholder="Product name..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Type</label>
                    <input type="text" name="product_type" value="<?php echo htmlspecialchars($_GET['product_type'] ?? ''); ?>"
                           placeholder="e.g., pcos, acne, weight, egbon"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by customer, email, transaction ID..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="date_start" value="<?php echo htmlspecialchars($_GET['date_start'] ?? ''); ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="date_end" value="<?php echo htmlspecialchars($_GET['date_end'] ?? ''); ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 mr-2">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="sales.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        <i class="fas fa-times mr-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Revenue by Month</h3>
                <canvas id="revenueByMonth"></canvas>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Status Distribution</h3>
                <canvas id="statusDistribution"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Top Products by Revenue</h3>
            <canvas id="topProducts"></canvas>
        </div>

        <!-- Sales Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    Sales (<?php echo $total_sales; ?> total)
                </h3>
            </div>

            <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                Product
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                                Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($sales as $sale): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                                <i class="fas fa-user text-green-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($sale['customer_name'] ?? 'N/A'); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($sale['customer_email'] ?? 'N/A'); ?>
                                            </div>
                                            <!-- Show product on mobile -->
                                            <div class="text-sm text-gray-500 sm:hidden">
                                                <?php echo htmlspecialchars($sale['product_name'] ?? 'N/A'); ?>
                                            </div>
                                            <?php if (!empty($sale['customer_phone'])): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($sale['customer_phone']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($sale['product_name'] ?? 'N/A'); ?>
                                    </div>
                                    <?php if (!empty($sale['quantity'])): ?>
                                        <div class="text-sm text-gray-500">
                                            Qty: <?php echo htmlspecialchars($sale['quantity']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        ₦<?php echo number_format($sale['amount'] ?? 0, 2); ?>
                                    </div>
                                    <?php if (!empty($sale['transaction_id'])): ?>
                                        <div class="text-sm text-gray-500">
                                            ID: <?php echo htmlspecialchars($sale['transaction_id']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        $status = $sale['payment_status'] ?? 'pending';
                                        switch($status) {
                                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                                            case 'failed': echo 'bg-red-100 text-red-800'; break;
                                            case 'refunded': echo 'bg-purple-100 text-purple-800'; break;
                                            default: echo 'bg-yellow-100 text-yellow-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden lg:table-cell">
                                    <?php echo date('M j, Y g:i A', strtotime($sale['created_at'] ?? 'now')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-1">
                                        <button onclick="viewSale('<?php echo $sale['id']; ?>')" 
                                                class="text-blue-600 hover:text-blue-900 p-1" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="updateStatus('<?php echo $sale['id']; ?>')" 
                                                class="text-green-600 hover:text-green-900 p-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="addNote('<?php echo $sale['id']; ?>')" 
                                                class="text-purple-600 hover:text-purple-900 p-1" title="Add Note">
                                            <i class="fas fa-sticky-note"></i>
                                        </button>
                                        <button onclick="deleteSale('<?php echo $sale['id']; ?>')" 
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
                                <span class="font-medium"><?php echo min($offset + $per_page, $total_sales); ?></span> of 
                                <span class="font-medium"><?php echo $total_sales; ?></span> results
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
                    <h3 class="text-lg font-medium text-gray-900">Sale Details</h3>
                    <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="saleDetails" class="space-y-4">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="statusSaleId">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Update Payment Status</h3>
                        <button type="button" onclick="closeModal('statusModal')" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
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
                <input type="hidden" name="id" id="noteSaleId">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Add Note</h3>
                        <button type="button" onclick="closeModal('noteModal')" class="text-gray-400 hover:text-gray-600">
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
        function viewSale(id) {
            // Fetch sale details via AJAX
            fetch(`../api/get-sale.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySaleDetails(data.sale);
                        document.getElementById('viewModal').classList.remove('hidden');
                    } else {
                        alert('Error loading sale details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading sale details');
                });
        }

        function displaySaleDetails(sale) {
            const container = document.getElementById('saleDetails');
            let html = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Customer Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong>Name:</strong> ${sale.customer_name || 'N/A'}</p>
                            <p><strong>Email:</strong> ${sale.customer_email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${sale.customer_phone || 'N/A'}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Sale Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong>Product:</strong> ${sale.product_name || 'N/A'}</p>
                            <p><strong>Amount:</strong> ₹${parseFloat(sale.amount || 0).toFixed(2)}</p>
                            <p><strong>Quantity:</strong> ${sale.quantity || 'N/A'}</p>
                            <p><strong>Status:</strong> ${sale.payment_status || 'pending'}</p>
                            <p><strong>Transaction ID:</strong> ${sale.transaction_id || 'N/A'}</p>
                            <p><strong>Created:</strong> ${new Date(sale.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                </div>
            `;

            if (sale.sale_data) {
                html += `
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-2">Additional Data</h4>
                        <div class="bg-gray-50 p-4 rounded-md">
                            <pre class="text-sm text-gray-700 whitespace-pre-wrap">${JSON.stringify(sale.sale_data, null, 2)}</pre>
                        </div>
                    </div>
                `;
            }

            if (sale.notes && sale.notes.length > 0) {
                html += `
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-2">Notes</h4>
                        <div class="space-y-2">
                `;
                sale.notes.forEach(note => {
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
            document.getElementById('statusSaleId').value = id;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function addNote(id) {
            document.getElementById('noteSaleId').value = id;
            document.getElementById('noteModal').classList.remove('hidden');
        }

        function deleteSale(id) {
            if (confirm('Are you sure you want to delete this sale? This action cannot be undone.')) {
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
        document.addEventListener('click', function(event) {
            const modals = ['viewModal', 'statusModal', 'noteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const qs = params.toString();
            fetch(`../api/sales-stats.php${qs ? ('?' + qs) : ''}`)
                .then(r => r.json())
                .then(d => {
                    const monthLabels = Object.keys(d.revenueByMonth);
                    const monthValues = Object.values(d.revenueByMonth);
                    new Chart(document.getElementById('revenueByMonth'), {
                        type: 'line',
                        data: { labels: monthLabels, datasets: [{ label: 'Revenue (NGN)', data: monthValues, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.2)' }] },
                        options: { responsive: true, scales: { y: { beginAtZero: true } } }
                    });
                    const statuses = ['completed','pending','failed','refunded'];
                    const statusData = statuses.map(s => d.counts[s] || 0);
                    new Chart(document.getElementById('statusDistribution'), {
                        type: 'doughnut',
                        data: { labels: statuses, datasets: [{ data: statusData, backgroundColor: ['#16a34a','#f59e0b','#ef4444','#8b5cf6'] }] },
                        options: { responsive: true }
                    });
                    const prodLabels = Object.keys(d.revenueByProduct).slice(0,10);
                    const prodValues = Object.values(d.revenueByProduct).slice(0,10);
                    new Chart(document.getElementById('topProducts'), {
                        type: 'bar',
                        data: { labels: prodLabels, datasets: [{ label: 'Revenue (NGN)', data: prodValues, backgroundColor: '#0ea5e9' }] },
                        options: { responsive: true, scales: { y: { beginAtZero: true } } }
                    });
                });
        });
    </script>
</body>
</html>
