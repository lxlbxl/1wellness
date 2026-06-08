<?php
require_once 'auth.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Helpers
function getAuditLogs($db)
{
    if ($db->isFileStorage()) {
        $file = '../database/data/audit_logs.json';
        if (file_exists($file)) {
            $logs = json_decode(file_get_contents($file), true) ?: [];
            // Sort desc
            usort($logs, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            return $logs;
        }
        return [];
    } else {
        try {
            $stmt = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 100");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

$logs = getAuditLogs($db);

$pageTitle = 'Audit Logs - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">System Audit Logs</h2>
        <p class="text-[#6B7C70] mt-1">Track administrative actions and security events</p>
    </div>
</div>

<!-- Logs Table -->
<div class="luxury-card overflow-hidden">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
        <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
            <i class="fas fa-shield-alt mr-2 text-[#D97757] text-sm"></i>Recent Activity
        </h3>
        <span class="text-sm text-[#6B7C70]">Last 100 events</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[#F9FAF9]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Timestamp
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">User
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Type
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Action
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Details
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">IP Address
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#EAEAE5]">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-[#6B7C70]">
                            <i class="fas fa-check-circle text-3xl mb-3 text-[#A4B4A6]"></i>
                            <p>No activity logs available</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-[#FDFCF8] transition-colors">
                            <td class="px-6 py-4 text-sm text-[#6B7C70] whitespace-nowrap font-mono">
                                <?php echo date('M j, H:i:s', strtotime($log['timestamp'] ?? $log['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-[#2C3E35]">
                                <?php
                                $identifier = $log['admin_email'] ?? $log['user_id'] ?? 'System';
                                // If strictly numeric, it's likely a user ID, maybe we could resolve name?
                                // For now, ID is fine or we augment query (but complex).
                                echo htmlspecialchars($identifier);
                                ?>
                            </td>
                            <td class="px-6 py-4 text-xs font-bold">
                                <?php
                                $isUserLog = isset($log['user_id']) && !isset($log['admin_email']);
                                echo $isUserLog
                                    ? '<span class="text-blue-600 bg-blue-50 px-2 py-1 rounded-full">User</span>'
                                    : '<span class="text-purple-600 bg-purple-50 px-2 py-1 rounded-full">Admin</span>';
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-[#2C3E35]">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-[#6B7C70] max-w-xs truncate"
                                title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-[#A4B4A6] font-mono">
                                <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>