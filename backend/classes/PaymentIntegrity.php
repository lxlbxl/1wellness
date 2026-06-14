<?php

/**
 * Payment Integrity (notification-system plan §6 Fix A)
 *
 * Receipt ledger + verification helpers for Flutterwave server webhooks and
 * the daily reconciliation cron. Every inbound payment webhook — accepted or
 * rejected — is logged here so the Admin "Payment Integrity" panel can show
 * webhook health, and reconciliation diffs are recorded for alerting.
 *
 * Works in both PDO (SQLite/MySQL) and file-storage modes of Database.
 */
class PaymentIntegrity
{
    const FLW_API_BASE = 'https://api.flutterwave.com/v3';

    private $db;
    private $settings;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
        $this->ensureTable();
    }

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

    private function ensureTable()
    {
        if ($this->db->isFileStorage()) {
            return; // file driver creates collections lazily on insert
        }
        try {
            $pdo = $this->db->getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = ($driver === 'mysql')
                ? "CREATE TABLE IF NOT EXISTS payment_webhook_log (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       source VARCHAR(30) NOT NULL,
                       event_type VARCHAR(60),
                       status VARCHAR(30) NOT NULL,
                       tx_ref VARCHAR(100),
                       transaction_id VARCHAR(100),
                       amount DECIMAL(10,2),
                       currency VARCHAR(10),
                       session_id VARCHAR(100),
                       funnel VARCHAR(20),
                       detail TEXT,
                       created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                   )"
                : "CREATE TABLE IF NOT EXISTS payment_webhook_log (
                       id INTEGER PRIMARY KEY AUTOINCREMENT,
                       source VARCHAR(30) NOT NULL,
                       event_type VARCHAR(60),
                       status VARCHAR(30) NOT NULL,
                       tx_ref VARCHAR(100),
                       transaction_id VARCHAR(100),
                       amount DECIMAL(10,2),
                       currency VARCHAR(10),
                       session_id VARCHAR(100),
                       funnel VARCHAR(20),
                       detail TEXT,
                       created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                   )";
            $pdo->exec($sql);
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pwl_status ON payment_webhook_log(status, created_at)");
        } catch (Exception $e) {
            error_log('PaymentIntegrity ensureTable: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------------

    /**
     * @param string $source 'webhook' | 'reconciliation' | 'test'
     * @param string $status 'received'|'processed'|'duplicate'|'rejected'|'verify_failed'|'error'|'recovered'|'ok'
     */
    public function log($source, $status, array $context = [])
    {
        try {
            $this->db->insert('payment_webhook_log', [
                'source' => $source,
                'event_type' => $context['event'] ?? null,
                'status' => $status,
                'tx_ref' => $context['tx_ref'] ?? null,
                'transaction_id' => isset($context['transaction_id']) ? (string) $context['transaction_id'] : null,
                'amount' => $context['amount'] ?? null,
                'currency' => $context['currency'] ?? null,
                'session_id' => $context['session_id'] ?? null,
                'funnel' => $context['funnel'] ?? null,
                'detail' => json_encode($context['detail'] ?? new stdClass()),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('PaymentIntegrity log: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Webhook authentication & transaction verification
    // ------------------------------------------------------------------

    /**
     * Constant-time check of Flutterwave's `verif-hash` header against the
     * admin-configured secret hash. Fail-closed when unconfigured.
     *
     * @return array [bool ok, string reason]
     */
    public function checkWebhookHash($headerValue)
    {
        $expected = trim((string) $this->settings->get('flutterwave_webhook_hash', ''));
        if ($expected === '') {
            return [false, 'hash_not_configured'];
        }
        if (!is_string($headerValue) || $headerValue === '') {
            return [false, 'header_missing'];
        }
        return hash_equals($expected, $headerValue)
            ? [true, 'ok']
            : [false, 'hash_mismatch'];
    }

    /**
     * Re-verify a transaction against the Flutterwave API — never trust the
     * webhook payload alone. Returns the verified `data` object or null.
     */
    public function verifyTransaction($transactionId)
    {
        $result = $this->apiGet('/transactions/' . rawurlencode((string) $transactionId) . '/verify');
        if (!$result || ($result['status'] ?? '') !== 'success') {
            return null;
        }
        $data = $result['data'] ?? null;
        if (!$data || ($data['status'] ?? '') !== 'successful') {
            return null;
        }
        return $data;
    }

    /**
     * List successful transactions in a window (reconciliation cron).
     * Returns array of transaction data rows (may span multiple pages).
     */
    public function listTransactions($fromDate, $toDate, $maxPages = 5)
    {
        $rows = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $this->apiGet('/transactions?status=successful&from=' . rawurlencode($fromDate)
                . '&to=' . rawurlencode($toDate) . '&page=' . $page);
            if (!$result || ($result['status'] ?? '') !== 'success') {
                break;
            }
            $batch = $result['data'] ?? [];
            if (empty($batch)) {
                break;
            }
            $rows = array_merge($rows, $batch);
            $totalPages = $result['meta']['page_info']['total_pages'] ?? 1;
            if ($page >= $totalPages) {
                break;
            }
        }
        return $rows;
    }

    private function apiGet($path)
    {
        $secret = trim((string) $this->settings->get('flutterwave_secret_key', ''));
        if ($secret === '') {
            error_log('PaymentIntegrity: flutterwave_secret_key not configured');
            return null;
        }
        $ch = curl_init(self::FLW_API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            error_log('PaymentIntegrity apiGet ' . $path . ': ' . $err);
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    // ------------------------------------------------------------------
    // Admin panel queries (storage-agnostic: matching done in PHP)
    // ------------------------------------------------------------------

    public function recentLogs($limit = 50)
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM payment_webhook_log ORDER BY created_at DESC LIMIT " . (int) $limit
            ) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function statusCounts($hours = 24)
    {
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        $counts = ['received' => 0, 'processed' => 0, 'duplicate' => 0, 'rejected' => 0,
                   'verify_failed' => 0, 'error' => 0, 'recovered' => 0];
        try {
            $rows = $this->db->fetchAll(
                "SELECT status FROM payment_webhook_log WHERE created_at >= ?", [$since]
            ) ?: [];
            foreach ($rows as $r) {
                $s = $r['status'] ?? '';
                if (isset($counts[$s])) {
                    $counts[$s]++;
                }
            }
        } catch (Exception $e) {
            // table may not exist yet in file mode
        }
        return $counts;
    }

    /**
     * Completed sales with no server-side purchase event carrying a session_id
     * — i.e. revenue the A/B engine and journeys cannot attribute.
     */
    public function unattributedSales($days = 30, $limit = 100)
    {
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        try {
            $sales = $this->db->fetchAll(
                "SELECT id, tx_ref, email, amount, currency, product_type, created_at
                 FROM sales WHERE payment_status = 'completed' AND created_at >= ?
                 ORDER BY created_at DESC", [$since]
            ) ?: [];
            $events = $this->db->fetchAll(
                "SELECT session_id, metadata FROM funnel_tracking
                 WHERE event_type = 'purchase' AND created_at >= ?", [$since]
            ) ?: [];

            $attributedRefs = [];
            foreach ($events as $ev) {
                $sessionId = (string) ($ev['session_id'] ?? '');
                // srv_-prefixed sessions are server-generated fallbacks, not real attribution
                if ($sessionId === '' || strpos($sessionId, 'srv_') === 0) {
                    continue;
                }
                $meta = json_decode($ev['metadata'] ?? '', true) ?: [];
                if (!empty($meta['tx_ref'])) {
                    $attributedRefs[$meta['tx_ref']] = true;
                }
            }

            $unattributed = [];
            foreach ($sales as $sale) {
                if (empty($sale['tx_ref']) || !isset($attributedRefs[$sale['tx_ref']])) {
                    $unattributed[] = $sale;
                    if (count($unattributed) >= $limit) {
                        break;
                    }
                }
            }
            return $unattributed;
        } catch (Exception $e) {
            error_log('PaymentIntegrity unattributedSales: ' . $e->getMessage());
            return [];
        }
    }

    /** Does a completed sale already exist for this tx_ref / transaction id? */
    public function saleExists($txRef, $transactionId = null)
    {
        try {
            $row = $this->db->fetch(
                "SELECT id FROM sales WHERE tx_ref = ? OR transaction_id = ?",
                [(string) $txRef, (string) ($transactionId ?: $txRef)]
            );
            return (bool) $row;
        } catch (Exception $e) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Shared mapping: Flutterwave transaction data -> orchestrator input
    // ------------------------------------------------------------------

    /** Whitelisted funnel from webhook meta, with safe fallbacks. */
    public static function resolveFunnel(array $meta, $productHint = '')
    {
        $allowed = ['pcos', 'acne', 'weight', 'mens'];
        $funnel = strtolower(trim((string) ($meta['funnel'] ?? '')));
        if (in_array($funnel, $allowed, true)) {
            return [$funnel, true];
        }
        $hint = strtolower($productHint);
        foreach ($allowed as $candidate) {
            if ($candidate !== '' && strpos($hint, $candidate) !== false) {
                return [$candidate, false];
            }
        }
        if (strpos($hint, 'vital') !== false) {
            return ['mens', false];
        }
        return ['pcos', false]; // flagged via $exact=false in the log detail
    }

    /** Build the AutomationOrchestrator orderData from verified FLW data. */
    public static function buildOrderData(array $tx, array $meta, $funnel)
    {
        $plan = (string) ($meta['plan'] ?? '');
        $labels = ['pcos' => 'PCOS', 'acne' => 'Acne', 'weight' => 'Weight Loss', 'mens' => 'Vitality'];
        $label = $labels[$funnel] ?? ucfirst($funnel);
        $product = $plan !== ''
            ? (stripos($plan, '90') !== false ? "90-Day $label Plan" : (stripos($plan, '30') !== false ? "30-Day $label Plan" : "$label Plan ($plan)"))
            : "$label Plan";

        $customer = $tx['customer'] ?? [];
        return [
            'email' => $customer['email'] ?? ($tx['email'] ?? ''),
            'name' => $customer['name'] ?? 'Customer',
            'phone' => $customer['phone_number'] ?? null,
            'transaction_id' => $tx['id'] ?? null,
            'tx_ref' => $tx['tx_ref'] ?? null,
            'amount' => $tx['amount'] ?? 0,
            'currency' => $tx['currency'] ?? 'USD',
            'product' => $product,
            'session_id' => $meta['session_id'] ?? null,
            'order_bump' => $meta['order_bump'] ?? 'none',
        ];
    }
}
