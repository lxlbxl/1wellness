<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'unauthorized']);
    exit();
}
require_once '../config/config.php';
require_once '../classes/Database.php';
$db = Database::getInstance();
header('Content-Type: application/json');
$status = $_GET['status'] ?? '';
$productType = $_GET['product_type'] ?? '';
$productName = $_GET['product_name'] ?? '';
$dateStart = $_GET['date_start'] ?? '';
$dateEnd = $_GET['date_end'] ?? '';
$sales = $db->getSales();
$f = function($s) use ($status,$productType,$productName,$dateStart,$dateEnd){
    if ($status && (($s['payment_status'] ?? 'pending') !== $status)) return false;
    if ($productType && strcasecmp($s['product_type'] ?? '', $productType) !== 0) return false;
    if ($productName && stripos($s['product_name'] ?? '', $productName) === false) return false;
    if ($dateStart) { $t = strtotime($s['created_at'] ?? '0'); if ($t < strtotime($dateStart)) return false; }
    if ($dateEnd) { $t = strtotime($s['created_at'] ?? '0'); if ($t > strtotime($dateEnd . ' 23:59:59')) return false; }
    return true;
};
$sales = array_values(array_filter($sales, $f));
$counts = ['completed'=>0,'pending'=>0,'failed'=>0,'refunded'=>0,'successful'=>0];
$revenueByMonth = [];
$revenueByProduct = [];
$countByType = [];
$now = new DateTime();
for ($i=0;$i<12;$i++){ $m = (clone $now)->modify("-{$i} months")->format('Y-m'); $revenueByMonth[$m] = 0; }
foreach ($sales as $s) {
    $st = $s['payment_status'] ?? 'pending';
    if (isset($counts[$st])) $counts[$st]++;
    $amount = floatval($s['amount'] ?? $s['total_amount'] ?? 0);
    $completed = in_array($st, ['completed','successful']);
    if ($completed) {
        $month = date('Y-m', strtotime($s['created_at'] ?? 'now'));
        if (!isset($revenueByMonth[$month])) $revenueByMonth[$month] = 0;
        $revenueByMonth[$month] += $amount;
        $pn = $s['product_name'] ?? 'Unknown';
        if (!isset($revenueByProduct[$pn])) $revenueByProduct[$pn] = 0;
        $revenueByProduct[$pn] += $amount;
        $pt = $s['product_type'] ?? 'general';
        if (!isset($countByType[$pt])) $countByType[$pt] = 0;
        $countByType[$pt]++;
    }
}
ksort($revenueByMonth);
arsort($revenueByProduct);
arsort($countByType);
echo json_encode([
    'counts' => $counts,
    'revenueByMonth' => $revenueByMonth,
    'revenueByProduct' => $revenueByProduct,
    'countByType' => $countByType
]);
?>
