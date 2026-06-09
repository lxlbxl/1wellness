<?php
require_once 'auth.php';
require_once '../classes/Settings.php';

$db = Database::getInstance();
$settings = Settings::getInstance();
$allUsers = $db->fetchAll("SELECT id, name, email FROM users ORDER BY name ASC");
$pricingData = $settings->get('payment_plans', []);
$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_sale':
            $id = $_POST['id'] ?? '';
            $product_name = $_POST['product_name'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $payment_status = $_POST['payment_status'] ?? '';
            $notes = $_POST['notes'] ?? '';

            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Sale ID is required']);
                exit;
            }

            try {
                if ($db->isFileStorage()) {
                    $sales = $db->getSales();
                    $updated = false;
                    foreach ($sales as &$sale) {
                        if ($sale['id'] === $id) {
                            $sale['product_name'] = $product_name;
                            $sale['amount'] = $amount;
                            $sale['payment_status'] = $payment_status;
                            $sale['notes'] = $notes;
                            $sale['updated_at'] = date('Y-m-d H:i:s');
                            $updated = true;
                            break;
                        }
                    }
                    if ($updated) {
                        file_put_contents($db->getDataPath() . '/sales.json', json_encode(array_values($sales), JSON_PRETTY_PRINT));
                    }
                } else {
                    $db->update('sales', [
                        'product_name' => $product_name,
                        'amount' => $amount,
                        'payment_status' => $payment_status,
                        'notes' => $notes,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], "id = :id", [':id' => $id]);
                }
                echo json_encode(['success' => true, 'message' => 'Sale updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating sale: ' . $e->getMessage()]);
            }
            exit;

        case 'delete_sale':
            $id = $_POST['id'] ?? '';

            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Sale ID is required']);
                exit;
            }

            try {
                if ($db->isFileStorage()) {
                    $sales = $db->getSales();
                    $sales = array_filter($sales, function ($sale) use ($id) {
                        return $sale['id'] !== $id;
                    });
                    file_put_contents($db->getDataPath() . '/sales.json', json_encode(array_values($sales), JSON_PRETTY_PRINT));
                } else {
                    $db->delete('sales', "id = :id", [':id' => $id]);
                }
                echo json_encode(['success' => true, 'message' => 'Sale deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error deleting sale: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Handle regular form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'delete':
            $id = $_POST['id'] ?? '';
            if ($id) {
                if ($db->isFileStorage()) {
                    $sales = $db->getSales();
                    $sales = array_filter($sales, function ($sale) use ($id) {
                        return $sale['id'] !== $id;
                    });
                    file_put_contents($db->getDataPath() . '/sales.json', json_encode(array_values($sales), JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("DELETE FROM sales WHERE id = ?");
                    $stmt->execute([$id]);
                }
                $message = 'Sale record deleted.';
            }
            break;

        case 'manual_sale':
            $userId = $_POST['user_id'] ?? '';
            $planKey = $_POST['plan_key'] ?? '';
            $funnelKey = $_POST['funnel_key'] ?? 'pcos';
            $amount = $_POST['amount'] ?? 0;

            $userInfo = $db->fetch("SELECT name, email FROM users WHERE id = ?", [$userId]);
            if (!$userInfo) {
                $error = "Selected user not found.";
                break;
            }

            $email = $userInfo['email'];
            $name = $userInfo['name'];

            $planName = $planKey;
            if (isset($pricingData[$funnelKey][$planKey])) {
                $planName = $pricingData[$funnelKey][$planKey]['name'];
            }

            $saleId = 'MAN_' . strtoupper(bin2hex(random_bytes(4)));
            $db->insert('sales', [
                'id' => $saleId,
                'user_id' => $userId,
                'email' => $email,
                'name' => $name,
                'product_type' => 'manual',
                'product_name' => $planName . ' (Manual)',
                'amount' => $amount,
                'payment_status' => 'completed',
                'is_manual' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $daysToAdd = (strpos($planKey, '90') !== false) ? 90 : 30;
            $profile = $db->fetch("SELECT subscription_expiry FROM member_profiles WHERE user_id = ?", [$userId]);

            $expiryDate = new DateTime();
            if ($profile && $profile['subscription_expiry']) {
                $currentExpiry = new DateTime($profile['subscription_expiry']);
                if ($currentExpiry > $expiryDate) {
                    $expiryDate = $currentExpiry;
                }
            }

            $expiryDate->modify("+$daysToAdd day");
            $newExpiry = $expiryDate->format('Y-m-d');

            if ($profile) {
                $db->update('member_profiles', [
                    'subscription_tier' => $planKey,
                    'subscription_expiry' => $newExpiry,
                    'subscription_status' => 'active',
                    'updated_at' => date('Y-m-d H:i:s')
                ], "user_id = :uid", [':uid' => $userId]);
            } else {
                $db->insert('member_profiles', [
                    'user_id' => $userId,
                    'subscription_tier' => $planKey,
                    'subscription_expiry' => $newExpiry,
                    'subscription_status' => 'active',
                    'start_date' => date('Y-m-d')
                ]);
            }
            $message = "Manual sale created for $name and subscription extended to $newExpiry.";
            break;
    }
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch sales data with pagination
$allSales = $db->getSales();

// Apply filters
if ($search) {
    $allSales = array_filter($allSales, function ($sale) use ($search) {
        $searchLower = strtolower($search);
        return
            stripos($sale['id'] ?? '', $searchLower) !== false ||
            stripos($sale['name'] ?? '', $searchLower) !== false ||
            stripos($sale['email'] ?? '', $searchLower) !== false ||
            stripos($sale['product_name'] ?? '', $searchLower) !== false;
    });
}

if ($statusFilter) {
    $allSales = array_filter($allSales, function ($sale) use ($statusFilter) {
        return ($sale['payment_status'] ?? 'pending') === $statusFilter;
    });
}

$totalSales = count($allSales);
$totalPages = ceil($totalSales / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;

// Get paginated sales
$paginatedSales = array_slice(array_values($allSales), $offset, $perPage);

$pageTitle = 'Sales Analytics - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to
            Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Sales Performance</h2>
    </div>
    <div class="flex gap-2">
        <button onclick="openManualSaleModal()"
            class="px-4 py-2 bg-[#D97757] text-white rounded-xl text-sm font-medium hover:bg-[#BF6649] transition-colors shadow-lg shadow-[#D97757]/20">
            <i class="fas fa-plus mr-2"></i> Manual Sale
        </button>
        <button onclick="exportCSV()"
            class="px-4 py-2 bg-white border border-[#EAEAE5] rounded-xl text-sm font-medium hover:bg-[#F2F4F1] transition-colors">
            <i class="fas fa-download mr-2"></i> Export CSV
        </button>
        <select id="timeRange"
            class="px-4 py-2 bg-[#2C3E35] text-white border-none rounded-xl text-sm font-medium hover:bg-[#1A2620] transition-colors cursor-pointer">
            <option value="7d">Last 7 Days</option>
            <option value="30d" selected>Last 30 Days</option>
            <option value="90d">Last 90 Days</option>
        </select>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700">
        <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">
        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12" id="statsContainer">
    <div class="luxury-card p-6 animate-pulse">
        <div class="h-4 bg-[#E3E8E1] rounded w-1/3 mb-4"></div>
        <div class="h-8 bg-[#E3E8E1] rounded w-2/3"></div>
    </div>
</div>

<!-- Charts Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
    <div class="luxury-card p-8">
        <h3 class="text-xl font-serif text-[#2C3E35] mb-6">Revenue Trajectory</h3>
        <div class="relative h-64">
            <canvas id="revenueByMonth"></canvas>
        </div>
    </div>
    <div class="luxury-card p-8">
        <div class="flex justify-between">
            <h3 class="text-xl font-serif text-[#2C3E35] mb-6">Order Status</h3>
            <div class="relative"><canvas id="statusDistribution"></canvas></div>
        </div>
    </div>
</div>

<!-- Transaction Table with Filters -->
<div class="luxury-card overflow-hidden">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8]">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h3 class="font-serif text-lg text-[#2C3E35]">Transaction History</h3>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3">
                <form method="GET" class="flex flex-col sm:flex-row gap-3" id="filterForm">
                    <input type="hidden" name="page" value="1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-[#A4B4A6]"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search transactions..."
                            class="pl-10 pr-4 py-2 border border-[#EAEAE5] rounded-xl text-sm focus:border-[#2C3E35] outline-none transition-all w-full sm:w-64">
                    </div>
                    <select name="status" onchange="document.getElementById('filterForm').submit()"
                        class="px-4 py-2 border border-[#EAEAE5] rounded-xl text-sm focus:border-[#2C3E35] outline-none transition-all bg-white">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed
                        </option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Refunded
                        </option>
                    </select>
                    <select name="per_page" onchange="document.getElementById('filterForm').submit()"
                        class="px-4 py-2 border border-[#EAEAE5] rounded-xl text-sm focus:border-[#2C3E35] outline-none transition-all bg-white">
                        <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25 per page</option>
                        <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                    <?php if ($search || $statusFilter): ?>
                        <a href="sales.php" class="px-4 py-2 text-[#D97757] hover:text-[#BF6649] text-sm font-medium">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[#F9FAF9]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Order ID
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Customer
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Product
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Amount
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#EAEAE5] bg-white" id="salesTableBody">
                <?php if (empty($paginatedSales)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-[#6B7C70]">
                            <i class="fas fa-inbox text-4xl mb-3 text-[#E3E8E1]"></i>
                            <p>No transactions found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginatedSales as $sale): ?>
                        <tr class="hover:bg-[#FDFCF8] transition-colors"
                            data-sale-id="<?php echo htmlspecialchars($sale['id']); ?>">
                            <td class="px-6 py-4 text-sm font-mono text-[#6B7C70]">
                                <?php echo substr($sale['id'], 0, 12); ?>...
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-[#2C3E35]">
                                <?php echo htmlspecialchars($sale['name'] ?? $sale['customer_name'] ?? 'Guest'); ?>
                                <div class="text-xs text-[#A4B4A6]"><?php echo htmlspecialchars($sale['email'] ?? ''); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-[#6B7C70]">
                                <?php echo htmlspecialchars($sale['product_name'] ?? $sale['product'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-serif font-bold text-[#2C3E35]">
                                $<?php echo number_format($sale['amount'] ?? 0); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-[#6B7C70]">
                                <?php echo date('M j, Y', strtotime($sale['created_at'])); ?>
                                <div class="text-xs text-[#A4B4A6]"><?php echo date('g:i A', strtotime($sale['created_at'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $status = $sale['payment_status'] ?? $sale['status'] ?? 'pending';
                                $statusColor = match ($status) {
                                    'completed', 'successful' => 'text-[#2C3E35] bg-[#E3E8E1]',
                                    'pending' => 'text-[#D97757] bg-[#FDF1E8]',
                                    'failed' => 'text-[#E57373] bg-[#FFEBEE]',
                                    'refunded' => 'text-[#6B7C70] bg-[#F2F4F1]',
                                    default => 'text-[#6B7C70] bg-[#F2F4F1]'
                                };
                                ?>
                                <span class="px-2 py-1 rounded text-xs font-bold uppercase <?php echo $statusColor; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($sale['is_manual'])): ?>
                                    <span
                                        class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-[#F2F4F1] text-[#2C3E35] border border-[#2C3E35]/10">Manual</span>
                                <?php else: ?>
                                    <span
                                        class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-white text-[#A4B4A6] border border-[#A4B4A6]/20">Online</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button
                                        onclick="openEditModal('<?php echo htmlspecialchars($sale['id']); ?>', '<?php echo htmlspecialchars(addslashes($sale['product_name'] ?? '')); ?>', <?php echo $sale['amount'] ?? 0; ?>, '<?php echo $status; ?>', '<?php echo htmlspecialchars(addslashes($sale['notes'] ?? '')); ?>')"
                                        class="p-2 text-[#6B7C70] hover:text-[#2C3E35] hover:bg-[#E3E8E1] rounded-lg transition-colors"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button
                                        onclick="openDeleteModal('<?php echo htmlspecialchars($sale['id']); ?>', '<?php echo htmlspecialchars(addslashes($sale['product_name'] ?? 'this sale')); ?>')"
                                        class="p-2 text-[#6B7C70] hover:text-[#E57373] hover:bg-[#FFEBEE] rounded-lg transition-colors"
                                        title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div
            class="px-6 py-4 border-t border-[#EAEAE5] bg-[#FAFAF8] flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-[#6B7C70]">
                Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalSales); ?> of
                <?php echo $totalSales; ?> transactions
            </div>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"
                        class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                if ($startPage > 1): ?>
                    <a href="?page=1&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"
                        class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="px-2 text-[#A4B4A6]">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="px-3 py-2 rounded-lg bg-[#2C3E35] text-white font-medium"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"
                            class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="px-2 text-[#A4B4A6]">...</span>
                    <?php endif; ?>
                    <a href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"
                        class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors"><?php echo $totalPages; ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"
                        class="px-3 py-2 rounded-lg border border-[#EAEAE5] text-[#6B7C70] hover:bg-white hover:text-[#2C3E35] transition-colors">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Sale Modal -->
<div id="editSaleModal"
    class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-[2rem] p-8 max-w-lg w-full mx-4 shadow-2xl animate-fade-in-up">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-serif text-[#2C3E35]">Edit Transaction</h3>
            <button onclick="closeEditModal()" class="text-[#A4B4A6] hover:text-[#2C3E35]">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="editSaleForm" class="space-y-6">
            <input type="hidden" name="action" value="update_sale">
            <input type="hidden" name="id" id="edit_sale_id">

            <div>
                <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-widest mb-2">Product
                    Name</label>
                <input type="text" name="product_name" id="edit_product_name" required
                    class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-widest mb-2">Amount
                        ($)</label>
                    <input type="number" name="amount" id="edit_amount" required min="0"
                        class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-widest mb-2">Status</label>
                    <select name="payment_status" id="edit_status" required
                        class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all bg-white">
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-widest mb-2">Notes</label>
                <textarea name="notes" id="edit_notes" rows="3"
                    class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all resize-none"></textarea>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeEditModal()"
                    class="flex-1 px-4 py-3 border border-[#EAEAE5] rounded-xl text-[#6B7C70] font-medium hover:bg-[#F2F4F1] transition-all">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 bg-[#2C3E35] text-white py-3 rounded-xl font-bold hover:bg-[#1a2621] transition-all">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal"
    class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-[2rem] p-8 max-w-md w-full mx-4 shadow-2xl animate-fade-in-up">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-[#FFEBEE] rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-[#E57373] text-2xl"></i>
            </div>
            <h3 class="text-2xl font-serif text-[#2C3E35] mb-2">Delete Transaction?</h3>
            <p class="text-[#6B7C70]">Are you sure you want to delete <span id="delete_sale_name"
                    class="font-medium text-[#2C3E35]"></span>? This action cannot be undone.</p>
        </div>

        <form id="deleteForm" class="flex gap-3">
            <input type="hidden" name="action" value="delete_sale">
            <input type="hidden" name="id" id="delete_sale_id">

            <button type="button" onclick="closeDeleteModal()"
                class="flex-1 px-4 py-3 border border-[#EAEAE5] rounded-xl text-[#6B7C70] font-medium hover:bg-[#F2F4F1] transition-all">
                Cancel
            </button>
            <button type="submit"
                class="flex-1 bg-[#E57373] text-white py-3 rounded-xl font-bold hover:bg-[#D32F2F] transition-all">
                <i class="fas fa-trash-alt mr-2"></i> Delete
            </button>
        </form>
    </div>
</div>

<!-- Manual Sale Modal -->
<div id="manualSaleModal"
    class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-[2rem] p-8 max-w-lg w-full mx-4 shadow-2xl animate-fade-in-up">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-serif text-[#2C3E35]">Create Manual Sale</h3>
            <button onclick="closeManualSaleModal()" class="text-[#A4B4A6] hover:text-[#2C3E35]">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="action" value="manual_sale">

            <div>
                <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-widest mb-2">Select User</label>
                <div class="relative">
                    <input type="text" id="user_search" placeholder="Search user by name or email..."
                        class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all mb-2"
                        oninput="filterUsers(this.value)">
                    <select name="user_id" id="user_select" required
                        class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all bg-white max-h-40 overflow-y-auto">
                        <option value="">-- Choose User --</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?php echo $u['id']; ?>"
                                data-search="<?php echo htmlspecialchars(strtolower($u['name'] . ' ' . $u['email'])); ?>">
                                <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label
                        class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-widest mb-2">Product/Plan</label>
                    <select name="plan_key" id="plan_select" required onchange="updateAmount(this)"
                        class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all bg-white">
                        <option value="">-- Select Plan --</option>
                        <?php foreach ($pricingData as $funnelKey => $plans): ?>
                            <optgroup label="<?php echo strtoupper($funnelKey); ?>">
                                <?php foreach ($plans as $key => $p): ?>
                                    <option value="<?php echo $key; ?>" data-funnel="<?php echo $funnelKey; ?>"
                                        data-price="<?php echo $p['price']; ?>">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="funnel_key" id="funnel_key">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-widest mb-2">Amount
                        ($)</label>
                    <input type="number" name="amount" id="manual_amount" required
                        class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:border-[#2C3E35] outline-none transition-all">
                </div>
            </div>

            <div class="pt-4">
                <button type="submit"
                    class="w-full bg-[#2C3E35] text-white py-4 rounded-xl font-bold hover:bg-[#1a2621] transition-all">
                    <i class="fas fa-plus mr-2"></i> Record Manual Sale
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Modal Functions
    function openManualSaleModal() {
        document.getElementById('manualSaleModal').classList.remove('hidden');
    }

    function closeManualSaleModal() {
        document.getElementById('manualSaleModal').classList.add('hidden');
    }

    function openEditModal(id, productName, amount, status, notes) {
        document.getElementById('edit_sale_id').value = id;
        document.getElementById('edit_product_name').value = productName;
        document.getElementById('edit_amount').value = amount;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_notes').value = notes;
        document.getElementById('editSaleModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editSaleModal').classList.add('hidden');
    }

    function openDeleteModal(id, name) {
        document.getElementById('delete_sale_id').value = id;
        document.getElementById('delete_sale_name').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // AJAX Form Submissions
    document.getElementById('editSaleForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('sales.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeEditModal();
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
            });
    });

    document.getElementById('deleteForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('sales.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeDeleteModal();
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
            });
    });

    // Notification function
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-6 py-4 rounded-xl shadow-lg z-50 animate-fade-in-up ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'
            }`;
        notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i> ${message}`;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // Export CSV function
    function exportCSV() {
        const rows = [['Order ID', 'Customer', 'Email', 'Product', 'Amount', 'Date', 'Status', 'Type']];
        const tableRows = document.querySelectorAll('#salesTableBody tr[data-sale-id]');

        tableRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 7) {
                rows.push([
                    row.getAttribute('data-sale-id'),
                    cells[1].querySelector('.font-medium')?.textContent?.trim() || '',
                    cells[1].querySelector('.text-xs')?.textContent?.trim() || '',
                    cells[2].textContent?.trim() || '',
                    cells[3].textContent?.replace('$', '').replace(/,/g, '').trim() || '',
                    cells[4].textContent?.trim() || '',
                    cells[5].querySelector('span')?.textContent?.trim() || '',
                    cells[6].querySelector('span')?.textContent?.trim() || ''
                ]);
            }
        });

        const csvContent = rows.map(e => e.join(",")).join("\n");
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "sales_export_" + new Date().toISOString().split('T')[0] + ".csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function filterUsers(val) {
        val = val.toLowerCase();
        const select = document.getElementById('user_select');
        const options = select.options;
        for (let i = 1; i < options.length; i++) {
            const search = options[i].getAttribute('data-search');
            options[i].style.display = search.includes(val) ? '' : 'none';
        }
    }

    function updateAmount(select) {
        const option = select.options[select.selectedIndex];
        if (option.value) {
            document.getElementById('manual_amount').value = option.getAttribute('data-price');
            document.getElementById('funnel_key').value = option.getAttribute('data-funnel');
        }
    }

    // Aesthetics Configuration for Chart.js
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6B7C70';
    Chart.defaults.scale.grid.color = '#F2F4F1';

    document.addEventListener('DOMContentLoaded', function () {
        const timeRangeSelect = document.getElementById('timeRange');

        function loadAnalytics(range) {
            fetch(`api/sales_analytics.php?range=${range}`)
                .then(response => response.json())
                .then(d => {
                    const statsHtml = `
                        <div class="luxury-card p-6 bg-[#2C3E35] text-white relative overflow-hidden group">
                            <div class="absolute right-[-20px] top-[-20px] w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                            <p class="text-[#A4B4A6] text-xs font-bold uppercase tracking-wider mb-2">Total Revenue</p>
                            <h3 class="text-4xl font-serif">${new Intl.NumberFormat().format(d.totalRevenue)}</h3>
                        </div>
                        <div class="luxury-card p-6 flex flex-col justify-center">
                            <p class="text-[#A4B4A6] text-xs font-bold uppercase tracking-wider mb-2">Total Orders</p>
                            <h3 class="text-3xl font-serif text-[#2C3E35]">${d.totalOrders}</h3>
                        </div>
                        <div class="luxury-card p-6 flex flex-col justify-center">
                            <p class="text-[#A4B4A6] text-xs font-bold uppercase tracking-wider mb-2">Avg. Order Value</p>
                            <h3 class="text-3xl font-serif text-[#D97757]">${new Intl.NumberFormat().format(d.avgOrderValue)}</h3>
                        </div>
                    `;
                    document.getElementById('statsContainer').innerHTML = statsHtml;

                    const monthLabels = Object.keys(d.revenueByMonth);
                    const monthValues = Object.values(d.revenueByMonth);

                    if (window.revChart) window.revChart.destroy();
                    if (window.statusChart) window.statusChart.destroy();

                    const ctxRev = document.getElementById('revenueByMonth').getContext('2d');
                    const gradient = ctxRev.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(44, 62, 53, 0.2)');
                    gradient.addColorStop(1, 'rgba(44, 62, 53, 0)');

                    window.revChart = new Chart(ctxRev, {
                        type: 'line',
                        data: {
                            labels: monthLabels,
                            datasets: [{
                                label: 'Revenue',
                                data: monthValues,
                                borderColor: '#2C3E35',
                                backgroundColor: gradient,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#2C3E35'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                    });

                    const statuses = ['completed', 'pending', 'failed', 'refunded'];
                    const statusData = statuses.map(s => d.counts[s] || 0);

                    window.statusChart = new Chart(document.getElementById('statusDistribution'), {
                        type: 'doughnut',
                        data: {
                            labels: statuses,
                            datasets: [{
                                data: statusData,
                                backgroundColor: ['#2C3E35', '#D97757', '#E57373', '#6B7C70'],
                                borderWidth: 0
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } }, cutout: '70%' }
                    });
                });
        }

        loadAnalytics('30d');
        timeRangeSelect.addEventListener('change', (e) => loadAnalytics(e.target.value));
    });
</script>

<?php include 'includes/footer.php'; ?>