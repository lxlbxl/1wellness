<?php
require_once 'auth.php';
require_once '../classes/Database.php';

$db = Database::getInstance();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $format = $_POST['format'] ?? 'csv';

    if ($type) {
        $data = [];
        $filename = $type . '_export_' . date('Y-m-d') . '.' . $format;

        // Fetch data
        if ($db->isFileStorage()) {
            $filePath = "../database/data/{$type}.json";
            if (file_exists($filePath)) {
                $data = json_decode(file_get_contents($filePath), true) ?: [];
            }
        } else {
            try {
                // Sanitize table name (basic protection)
                $allowedTables = ['users', 'assessments', 'sales', 'contacts'];
                if (in_array($type, $allowedTables)) {
                    $stmt = $db->query("SELECT * FROM {$type}");
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }

        if (!empty($data)) {
            // Generate export file
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');

                $output = fopen('php://output', 'w');

                // Add BOM for Excel UTF-8 compatibility
                fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

                // Headers
                if (!empty($data)) {
                    fputcsv($output, array_keys($data[0]));
                }

                // Rows
                foreach ($data as $row) {
                    // Flatten arrays if any
                    foreach ($row as &$cell) {
                        if (is_array($cell)) {
                            $cell = json_encode($cell);
                        }
                    }
                    fputcsv($output, $row);
                }

                fclose($output);
                exit;
            } elseif ($format === 'json') {
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo json_encode($data, JSON_PRETTY_PRINT);
                exit;
            }
        } else {
            $error = "No data found to export for {$type}.";
        }
    }
}

$pageTitle = 'Export Data - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Data Export</h2>
        <p class="text-[#6B7C70] mt-1">Download your system data for analysis or backup</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="mb-8 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Users Export -->
    <div class="luxury-card p-6 flex flex-col h-full">
        <div class="bg-[#E3E8E1] w-12 h-12 rounded-full flex items-center justify-center text-[#2C3E35] mb-4">
            <i class="fas fa-users text-lg"></i>
        </div>
        <h3 class="text-xl font-serif text-[#2C3E35] mb-2">Users</h3>
        <p class="text-[#6B7C70] text-sm mb-6 flex-grow">Export registered users, including their profiles and
            subscription status.</p>
        <form method="POST" action="" target="_blank" class="mt-auto">
            <input type="hidden" name="type" value="users">
            <div class="flex items-center gap-2 mb-3">
                <select name="format"
                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    <option value="csv">CSV (Excel)</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            <button type="submit"
                class="w-full py-2 bg-[#2C3E35] text-white rounded-lg text-sm font-medium hover:bg-[#1a2621] transition-colors">
                Download
            </button>
        </form>
    </div>

    <!-- Assessments Export -->
    <div class="luxury-card p-6 flex flex-col h-full">
        <div class="bg-[#FDF1E8] w-12 h-12 rounded-full flex items-center justify-center text-[#D97757] mb-4">
            <i class="fas fa-clipboard-list text-lg"></i>
        </div>
        <h3 class="text-xl font-serif text-[#2C3E35] mb-2">Assessments</h3>
        <p class="text-[#6B7C70] text-sm mb-6 flex-grow">Download all health assessments, quiz results, and
            recommendations.</p>
        <form method="POST" action="" target="_blank" class="mt-auto">
            <input type="hidden" name="type" value="assessments">
            <div class="flex items-center gap-2 mb-3">
                <select name="format"
                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    <option value="csv">CSV (Excel)</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            <button type="submit"
                class="w-full py-2 bg-[#D97757] text-white rounded-lg text-sm font-medium hover:bg-[#c66545] transition-colors">
                Download
            </button>
        </form>
    </div>

    <!-- Sales Export -->
    <div class="luxury-card p-6 flex flex-col h-full">
        <div class="bg-[#E8EAF6] w-12 h-12 rounded-full flex items-center justify-center text-[#3F51B5] mb-4">
            <i class="fas fa-shopping-cart text-lg"></i>
        </div>
        <h3 class="text-xl font-serif text-[#2C3E35] mb-2">Sales</h3>
        <p class="text-[#6B7C70] text-sm mb-6 flex-grow">Export transaction history, revenue data, and product sales
            information.</p>
        <form method="POST" action="" target="_blank" class="mt-auto">
            <input type="hidden" name="type" value="sales">
            <div class="flex items-center gap-2 mb-3">
                <select name="format"
                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    <option value="csv">CSV (Excel)</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            <button type="submit"
                class="w-full py-2 bg-[#3F51B5] text-white rounded-lg text-sm font-medium hover:bg-[#303f9f] transition-colors">
                Download
            </button>
        </form>
    </div>

    <!-- Contacts Export -->
    <div class="luxury-card p-6 flex flex-col h-full">
        <div class="bg-[#FFF8E1] w-12 h-12 rounded-full flex items-center justify-center text-[#FFA000] mb-4">
            <i class="fas fa-address-book text-lg"></i>
        </div>
        <h3 class="text-xl font-serif text-[#2C3E35] mb-2">Contacts</h3>
        <p class="text-[#6B7C70] text-sm mb-6 flex-grow">Download contact form submissions and lead inquiries.</p>
        <form method="POST" action="" target="_blank" class="mt-auto">
            <input type="hidden" name="type" value="contacts">
            <div class="flex items-center gap-2 mb-3">
                <select name="format"
                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    <option value="csv">CSV (Excel)</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            <button type="submit"
                class="w-full py-2 bg-[#FFA000] text-white rounded-lg text-sm font-medium hover:bg-[#ff8f00] transition-colors">
                Download
            </button>
        </form>
    </div>
</div>

<div class="mt-12 bg-[#F2F4F1] rounded-2xl p-8 border border-[#EAEAE5]">
    <div class="flex items-start gap-4">
        <div class="bg-white p-3 rounded-full shadow-sm text-[#2C3E35]">
            <i class="fas fa-info-circle text-xl"></i>
        </div>
        <div>
            <h3 class="text-lg font-serif text-[#2C3E35] mb-2">About Data Privacy</h3>
            <p class="text-[#6B7C70] text-sm leading-relaxed">
                When exporting user data, please ensure you comply with all relevant data protection regulations (such
                as GDPR or NDPR).
                Handle exported files securely and delete them when they are no longer needed.
                The exported files contain sensitive personal information including names, emails, and health data.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>