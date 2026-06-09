<?php
require_once 'auth.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Helpers
function getFunnels($db)
{
    if ($db->isFileStorage()) {
        $trackingFile = '../database/data/tracking.json';
        $tracking = (file_exists($trackingFile) ? json_decode(file_get_contents($trackingFile), true) : []) ?: [];
        $funnels = array_unique(array_column($tracking, 'funnel_name'));
        return $funnels;
    } else {
        try {
            $stmt = $db->query("SELECT DISTINCT funnel_name FROM funnel_tracking ORDER BY funnel_name");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }
}

// Filters
$funnel_filter = $_GET['funnel'] ?? '';
$event_filter = $_GET['event'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$logs = [];
$total_logs = 0;

if ($db->isFileStorage()) {
    $all_logs = [];
    $trackingFile = '../database/data/tracking.json';
    if (file_exists($trackingFile)) {
        $all_logs = json_decode(file_get_contents($trackingFile), true) ?: [];
    }

    // Sort by date desc
    usort($all_logs, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Filter
    if ($funnel_filter) {
        $all_logs = array_filter($all_logs, function ($l) use ($funnel_filter) {
            return $l['funnel_name'] === $funnel_filter;
        });
    }
    if ($event_filter) {
        $all_logs = array_filter($all_logs, function ($l) use ($event_filter) {
            return $l['event_type'] === $event_filter;
        });
    }

    $total_logs = count($all_logs);
    $logs = array_slice($all_logs, $offset, $per_page);

} else {
    try {
        $where = ["1=1"];
        $params = [];

        if ($funnel_filter) {
            $where[] = "funnel_name = :funnel";
            $params[':funnel'] = $funnel_filter;
        }
        if ($event_filter) {
            $where[] = "event_type = :event";
            $params[':event'] = $event_filter;
        }
        if ($date_filter) {
            $where[] = "DATE(created_at) = :date";
            $params[':date'] = $date_filter;
        }

        $whereClause = implode(" AND ", $where);

        // Count
        $countSql = "SELECT COUNT(*) FROM funnel_tracking WHERE $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total_logs = $stmt->fetchColumn();

        // Fetch
        $sql = "SELECT t.*, u.email as user_email 
                FROM funnel_tracking t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE $whereClause 
                ORDER BY t.created_at DESC 
                LIMIT :limit OFFSET :offset";

        // bindValue for limit/offset
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$total_pages = ceil($total_logs / $per_page);
$funnels = getFunnels($db);

$pageTitle = 'Funnel Tracking - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Funnel Analytics</h2>
        <p class="text-[#6B7C70] mt-1">Deep dive into user behavioral paths</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="mb-8 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="luxury-card p-6 mb-8">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-[#2C3E35] mb-2">Funnel</label>
            <div class="relative">
                <select name="funnel" onchange="this.form.submit()"
                    class="luxury-input w-full pl-4 pr-10 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] bg-white text-sm appearance-none">
                    <option value="">All Funnels</option>
                    <?php foreach ($funnels as $f): ?>
                        <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $funnel_filter === $f ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($f)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-[#6B7C70]">
                    <i class="fas fa-chevron-down text-xs"></i>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#2C3E35] mb-2">Event Type</label>
            <div class="relative">
                <select name="event" onchange="this.form.submit()"
                    class="luxury-input w-full pl-4 pr-10 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] bg-white text-sm appearance-none">
                    <option value="">All Events</option>
                    <option value="view" <?php echo $event_filter === 'view' ? 'selected' : ''; ?>>Page View</option>
                    <option value="conversion" <?php echo $event_filter === 'conversion' ? 'selected' : ''; ?>>Conversion
                    </option>
                    <option value="SalesVisit" <?php echo $event_filter === 'SalesVisit' ? 'selected' : ''; ?>>Sales Visit
                    </option>
                    <option value="PurchaseIntent" <?php echo $event_filter === 'PurchaseIntent' ? 'selected' : ''; ?>>
                        Purchase Intent</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-[#6B7C70]">
                    <i class="fas fa-chevron-down text-xs"></i>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#2C3E35] mb-2">Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                onchange="this.form.submit()"
                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm">
        </div>

        <div class="flex items-center gap-2">
            <a href="funnel-tracking.php"
                class="px-4 py-2 text-[#D97757] text-sm font-medium hover:text-[#2C3E35] transition-colors">
                Clear Filters
            </a>
        </div>
    </form>
</div>

<!-- Tracking Data -->
<div class="luxury-card overflow-hidden">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
        <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
            <i class="fas fa-shoe-prints mr-2 text-[#D97757] text-sm"></i>Visitor Logs
        </h3>
        <span class="text-sm text-[#6B7C70]"><?php echo $total_logs; ?> records found</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[#F9FAF9]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Funnel
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Step</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Event</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Details
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#EAEAE5]">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-[#6B7C70]">
                            <i class="fas fa-search text-3xl mb-3 text-[#A4B4A6]"></i>
                            <p>No tracking logs found matching your criteria</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-[#FDFCF8] transition-colors">
                            <td class="px-6 py-4 text-sm text-[#6B7C70] whitespace-nowrap">
                                <?php echo date('M j, H:i', strtotime($log['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#E3E8E1] text-[#2C3E35]">
                                    <?php echo htmlspecialchars(ucfirst($log['funnel_name'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-[#2C3E35]">
                                <?php echo htmlspecialchars($log['step_name']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $badgeClass = 'bg-[#F2F4F1] text-[#6B7C70]';
                                if ($log['event_type'] === 'conversion')
                                    $badgeClass = 'bg-[#E3E8E1] text-[#2C3E35]';
                                if ($log['event_type'] === 'PurchaseIntent')
                                    $badgeClass = 'bg-[#FFF8E1] text-[#FFA000]';
                                if ($log['event_type'] === 'SalesVisit')
                                    $badgeClass = 'bg-[#E8EAF6] text-[#3F51B5]';
                                ?>
                                <span
                                    class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $badgeClass; ?> border border-transparent">
                                    <?php echo htmlspecialchars($log['event_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-[#6B7C70]">
                                <?php echo htmlspecialchars($log['user_email'] ?? $log['user_id'] ?? 'Guest'); ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-[#A4B4A6] font-mono max-w-xs truncate"
                                title="<?php echo htmlspecialchars($log['metadata']); ?>">
                                <?php
                                $meta = is_string($log['metadata']) ? json_decode($log['metadata'], true) : $log['metadata'];
                                if ($meta)
                                    echo htmlspecialchars(json_encode($meta));
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-[#EAEAE5] flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&funnel=<?php echo urlencode($funnel_filter); ?>&event=<?php echo urlencode($event_filter); ?>"
                        class="px-4 py-2 border border-[#EAEAE5] rounded-lg text-sm font-medium text-[#6B7C70] hover:bg-[#F2F4F1]">
                        Previous
                    </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&funnel=<?php echo urlencode($funnel_filter); ?>&event=<?php echo urlencode($event_filter); ?>"
                        class="px-4 py-2 border border-[#EAEAE5] rounded-lg text-sm font-medium text-[#6B7C70] hover:bg-[#F2F4F1]">
                        Next
                    </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-[#6B7C70]">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span
                            class="font-medium"><?php echo min($total_logs, $offset + $per_page); ?></span> of <span
                            class="font-medium"><?php echo $total_logs; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php
                        $range = 2;
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                                $activeClass = $i === $page ? 'z-10 bg-[#2C3E35] text-white border-[#2C3E35]' : 'bg-white border-[#EAEAE5] text-[#6B7C70] hover:bg-[#F2F4F1]';
                                ?>
                                <a href="?page=<?php echo $i; ?>&funnel=<?php echo urlencode($funnel_filter); ?>&event=<?php echo urlencode($event_filter); ?>"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $activeClass; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)): ?>
                                <span
                                    class="relative inline-flex items-center px-4 py-2 border border-[#EAEAE5] bg-white text-sm font-medium text-[#A4B4A6]">...</span>
                            <?php endif;
                        endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>