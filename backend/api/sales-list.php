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
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = max(1, min(200, intval($_GET['page_size'] ?? 20)));
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortDir = strtolower($_GET['sort_dir'] ?? 'desc');
$sales = $db->getSales();
$filter = function($sale) use ($status,$productType,$productName,$dateStart,$dateEnd,$search){
    if ($status && (($sale['payment_status'] ?? 'pending') !== $status)) return false;
    if ($productType && strcasecmp($sale['product_type'] ?? '', $productType) !== 0) return false;
    if ($productName && stripos($sale['product_name'] ?? '', $productName) === false) return false;
    if ($dateStart) {
        $t = strtotime($sale['created_at'] ?? '0');
        if ($t < strtotime($dateStart)) return false;
    }
    if ($dateEnd) {
        $t = strtotime($sale['created_at'] ?? '0');
        if ($t > strtotime($dateEnd . ' 23:59:59')) return false;
    }
    if ($search) {
        $fields = [
            $sale['name'] ?? $sale['customer_name'] ?? '',
            $sale['email'] ?? $sale['customer_email'] ?? '',
            $sale['phone'] ?? $sale['customer_phone'] ?? '',
            $sale['product_name'] ?? '',
            $sale['transaction_id'] ?? '',
            $sale['tx_ref'] ?? ''
        ];
        if (stripos(implode(' ', $fields), $search) === false) return false;
    }
    return true;
};
$sales = array_values(array_filter($sales, $filter));
$cmp = function($a,$b) use ($sortBy,$sortDir){
    $va = $a[$sortBy] ?? '';
    $vb = $b[$sortBy] ?? '';
    if ($sortBy === 'amount') {
        $va = floatval($va);
        $vb = floatval($vb);
    } else {
        $va = strtotime($va) ?: $va;
        $vb = strtotime($vb) ?: $vb;
    }
    $res = ($va <=> $vb);
    return $sortDir === 'asc' ? $res : -$res;
};
usort($sales, $cmp);
$total = count($sales);
$offset = ($page - 1) * $pageSize;
$paged = array_slice($sales, $offset, $pageSize);
$map = function($s){
    return [
        'id' => $s['id'] ?? null,
        'email' => $s['email'] ?? $s['customer_email'] ?? null,
        'name' => $s['name'] ?? $s['customer_name'] ?? null,
        'phone' => $s['phone'] ?? $s['customer_phone'] ?? null,
        'product_type' => $s['product_type'] ?? null,
        'product_name' => $s['product_name'] ?? null,
        'amount' => floatval($s['amount'] ?? $s['total_amount'] ?? 0),
        'currency' => $s['currency'] ?? 'USD',
        'payment_status' => $s['payment_status'] ?? 'pending',
        'transaction_id' => $s['transaction_id'] ?? null,
        'tx_ref' => $s['tx_ref'] ?? null,
        'created_at' => $s['created_at'] ?? null
    ];
};
$data = array_map($map, $paged);
echo json_encode(['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'data' => $data]);
?>
