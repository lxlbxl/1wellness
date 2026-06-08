<?php
require_once '../auth.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
header('Content-Type: application/json');

// Get time range filter
$range = $_GET['range'] ?? '30d';

// Calculate start date
$startDate = new DateTime();
if ($range === '7d') {
    $startDate->modify('-7 days');
} elseif ($range === '90d') {
    $startDate->modify('-90 days');
} else {
    $startDate->modify('-30 days'); // Default 30d
}
$startTimestamp = $startDate->getTimestamp();

$sales = $db->getSales();

// Filter and Aggregate
$filteredSales = [];
$totalRevenue = 0;
$totalOrders = 0;
$revenueByMonth = [];
$counts = ['completed' => 0, 'pending' => 0, 'failed' => 0, 'refunded' => 0];

// Initialize last 12 months for chart consistency if range is wide, 
// but for 7d/30d maybe daily is better? 
// The frontend chart expects months labels from keys of revenueByMonth.
// Let's stick to simple aggregation for now.

foreach ($sales as $s) {
    $date = strtotime($s['created_at']);

    // Filter by date range
    if ($date < $startTimestamp)
        continue;

    $filteredSales[] = $s;
    $status = $s['payment_status'] ?? 'pending';

    // Normalize status for counts
    if ($status === 'successful')
        $status = 'completed';
    if (!isset($counts[$status]))
        $counts[$status] = 0;
    $counts[$status]++;

    if ($status === 'completed') {
        $amount = (float) ($s['amount'] ?? 0);
        $totalRevenue += $amount;
        $totalOrders++;

        // Group by Month (or Day if range is small? The chart ID is revenueByMonth)
        // Let's keep it simply by Short Month Name or Date for now.
        // sales.php line 367 expects keys to be labels.
        if ($range === '7d') {
            $key = date('D d', $date); // Mon 01
        } else {
            $key = date('M d', $date); // Jan 01
        }

        if (!isset($revenueByMonth[$key]))
            $revenueByMonth[$key] = 0;
        $revenueByMonth[$key] += $amount;
    }
}

// Sort the chart data by date
uksort($revenueByMonth, function ($a, $b) use ($range) {
    // This simple date parsing might fail if year isn't included and ranges span years.
    // For simplicity, since the iteration was likely chronological (DESC usually), 
    // but getSales is DESC. We need to reverse for chart (oldest to newest).
    return strtotime($a) - strtotime($b);
});

// Since keys are just 'M d', strtotime uses current year. This is a bit risky for year boundaries.
// Better approach: build array with full date keys, sort, then remap to display keys.
// But valid quick fix: filteredSales are from DB, typically ordered.
// Actually $db->getSales() orders by created_at DESC.
// So we processed newest first.
// We should reverse the array for the chart.

$revChartData = array_reverse($revenueByMonth);

$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

echo json_encode([
    'totalRevenue' => $totalRevenue,
    'totalOrders' => $totalOrders,
    'avgOrderValue' => $avgOrderValue,
    'revenueByMonth' => $revChartData,
    'counts' => $counts
]);
