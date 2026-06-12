<?php
/**
 * WebhookDispatcher — outbound webhook subscriptions & delivery
 *
 * Admin registers webhook URLs and selects which events fire them
 * (backend/admin/webhooks.php or the REST API backend/api/webhooks-api.php).
 *
 * dispatch($event, $data) fans the event out to every active webhook
 * subscribed to it by enqueueing rows in `webhook_queue`; the existing
 * cron worker (backend/cron/process_webhooks.php) delivers with retries
 * and exponential backoff. deliver() performs a single synchronous HTTP
 * delivery (used for admin "Test" and for cron processing).
 *
 * Payload contract (see docs/WEBHOOKS.md):
 *   { "event": "...", "timestamp": "ISO-8601", "webhook_id": "...", "data": { ... } }
 * Signed with X-Webhook-Signature: HMAC-SHA256(body, secret) when a
 * secret is configured.
 */

class WebhookDispatcher
{
    private $db;

    /** Canonical event catalog: key => [label, description] */
    const EVENTS = [
        'assessment.completed' => [
            'label' => 'Assessment Completed',
            'description' => 'A visitor finished a funnel assessment (lead captured).',
        ],
        'user.registered' => [
            'label' => 'User Registered',
            'description' => 'A new member account was created.',
        ],
        'sale.completed' => [
            'label' => 'Sale Completed',
            'description' => 'A payment was confirmed and a sale recorded (includes amount, currency, product).',
        ],
        'funnel.purchase' => [
            'label' => 'Funnel Purchase (Attributed)',
            'description' => 'Server-confirmed purchase with funnel + A/B variant attribution and revenue.',
        ],
        'experiment.started' => [
            'label' => 'Experiment Started',
            'description' => 'An A/B experiment entered burn-in / began serving traffic.',
        ],
        'experiment.concluded' => [
            'label' => 'Experiment Concluded',
            'description' => 'An A/B experiment reached a decision; payload includes the winning variant.',
        ],
        'experiment.variant_killed' => [
            'label' => 'Variant Killed',
            'description' => 'A losing variant was killed (manually or by guardrails).',
        ],
        'experiment.challenger_proposed' => [
            'label' => 'AI Challenger Proposed',
            'description' => 'The AI generated a challenger variant awaiting human approval.',
        ],
        'experiment.insight_ready' => [
            'label' => 'AI Insight Ready',
            'description' => 'The weekly AI diagnostic produced a new insight report.',
        ],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        if (!class_exists('ABSchema')) {
            require_once __DIR__ . '/ABSchema.php';
        }
        ABSchema::ensure();
    }

    /** Full event catalog for admin UIs / API discovery. */
    public static function eventCatalog()
    {
        return self::EVENTS;
    }

    public static function isValidEvent($event)
    {
        return isset(self::EVENTS[$event]);
    }

    // ------------------------------------------------------------------
    // Subscription CRUD (DB-backed, file-storage fallback)
    // ------------------------------------------------------------------

    public function listWebhooks()
    {
        if ($this->db->isFileStorage()) {
            return $this->fileLoad();
        }
        $rows = $this->db->fetchAll("SELECT * FROM webhooks ORDER BY created_at DESC");
        foreach ($rows as &$r) {
            $r['events'] = json_decode($r['events'], true) ?: [];
            $r['headers'] = $r['headers'] ? (json_decode($r['headers'], true) ?: []) : [];
        }
        return $rows;
    }

    public function getWebhook($id)
    {
        if ($this->db->isFileStorage()) {
            foreach ($this->fileLoad() as $w) {
                if ($w['id'] === $id) return $w;
            }
            return null;
        }
        $r = $this->db->fetch("SELECT * FROM webhooks WHERE id = :id", [':id' => $id]);
        if ($r) {
            $r['events'] = json_decode($r['events'], true) ?: [];
            $r['headers'] = $r['headers'] ? (json_decode($r['headers'], true) ?: []) : [];
        }
        return $r ?: null;
    }

    /**
     * Create a webhook subscription.
     * @param array $data name, url, events[], secret?, headers?, method?, status?
     * @return array created webhook (incl. generated id/secret) or ['error' => ...]
     */
    public function createWebhook(array $data)
    {
        $err = $this->validate($data);
        if ($err) {
            return ['error' => $err];
        }

        $webhook = [
            'id' => 'wh_' . bin2hex(random_bytes(8)),
            'name' => trim($data['name']),
            'url' => trim($data['url']),
            'events' => array_values(array_intersect($data['events'], array_keys(self::EVENTS))),
            'secret' => !empty($data['secret']) ? $data['secret'] : bin2hex(random_bytes(16)),
            'headers' => isset($data['headers']) && is_array($data['headers']) ? $data['headers'] : [],
            'method' => strtoupper($data['method'] ?? 'POST'),
            'status' => in_array($data['status'] ?? 'active', ['active', 'paused']) ? ($data['status'] ?? 'active') : 'active',
            'success_count' => 0,
            'failure_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->db->isFileStorage()) {
            $all = $this->fileLoad();
            $all[] = $webhook;
            $this->fileSave($all);
        } else {
            $row = $webhook;
            $row['events'] = json_encode($webhook['events']);
            $row['headers'] = json_encode($webhook['headers']);
            $this->db->insert('webhooks', $row);
        }
        return $webhook;
    }

    public function updateWebhook($id, array $data)
    {
        $existing = $this->getWebhook($id);
        if (!$existing) {
            return ['error' => 'Webhook not found'];
        }

        $merged = array_merge($existing, array_intersect_key($data, array_flip([
            'name', 'url', 'events', 'secret', 'headers', 'method', 'status'
        ])));
        $err = $this->validate($merged);
        if ($err) {
            return ['error' => $err];
        }
        $merged['events'] = array_values(array_intersect($merged['events'], array_keys(self::EVENTS)));
        $merged['updated_at'] = date('Y-m-d H:i:s');

        if ($this->db->isFileStorage()) {
            $all = $this->fileLoad();
            foreach ($all as &$w) {
                if ($w['id'] === $id) {
                    $w = $merged;
                }
            }
            $this->fileSave($all);
        } else {
            $this->db->update('webhooks', [
                'name' => $merged['name'],
                'url' => $merged['url'],
                'events' => json_encode($merged['events']),
                'secret' => $merged['secret'],
                'headers' => json_encode($merged['headers']),
                'method' => $merged['method'],
                'status' => $merged['status'],
                'updated_at' => $merged['updated_at'],
            ], 'id = :id', [':id' => $id]);
        }
        return $merged;
    }

    public function deleteWebhook($id)
    {
        if ($this->db->isFileStorage()) {
            $all = array_values(array_filter($this->fileLoad(), function ($w) use ($id) {
                return $w['id'] !== $id;
            }));
            $this->fileSave($all);
            return true;
        }
        $this->db->delete('webhooks', 'id = :id', [':id' => $id]);
        // Drop pending deliveries for this webhook
        try {
            $this->db->delete('webhook_queue', "webhook_id = :id AND status IN ('pending','failed')", [':id' => $id]);
        } catch (Exception $e) { /* queue table variations */ }
        return true;
    }

    private function validate($data)
    {
        if (empty($data['name'])) {
            return 'Name is required';
        }
        if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return 'A valid URL is required';
        }
        if (!preg_match('#^https?://#i', $data['url'])) {
            return 'URL must use http(s)';
        }
        if (empty($data['events']) || !is_array($data['events'])) {
            return 'Select at least one event';
        }
        foreach ($data['events'] as $e) {
            if (!self::isValidEvent($e)) {
                return "Unknown event: $e";
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Dispatch & delivery
    // ------------------------------------------------------------------

    /**
     * Fan an event out to all subscribed active webhooks (queued, async).
     * Never throws — webhook failures must not break business flows.
     *
     * @return int number of deliveries enqueued
     */
    public function dispatch($event, array $data)
    {
        try {
            if (!self::isValidEvent($event)) {
                error_log("WebhookDispatcher: unknown event '$event'");
                return 0;
            }

            $enqueued = 0;
            foreach ($this->listWebhooks() as $webhook) {
                if (($webhook['status'] ?? 'active') !== 'active') {
                    continue;
                }
                if (!in_array($event, $webhook['events'] ?? [], true)) {
                    continue;
                }
                $this->enqueue($webhook, $event, $data);
                $enqueued++;
            }
            return $enqueued;
        } catch (Exception $e) {
            error_log('WebhookDispatcher dispatch error: ' . $e->getMessage());
            return 0;
        }
    }

    private function enqueue($webhook, $event, array $data)
    {
        $payload = $this->buildPayload($webhook['id'], $event, $data);

        if ($this->db->isFileStorage()) {
            $file = APP_ROOT . '/database/data/webhook_queue.json';
            $queue = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
            $queue[] = [
                'id' => uniqid('whq_'),
                'webhook_id' => $webhook['id'],
                'event' => $event,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
                'next_attempt' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            file_put_contents($file, json_encode($queue, JSON_PRETTY_PRINT));
            return;
        }

        $this->db->insert('webhook_queue', [
            'webhook_id' => $webhook['id'],
            'event' => $event,
            'payload' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function buildPayload($webhookId, $event, array $data)
    {
        return [
            'event' => $event,
            'timestamp' => date('c'),
            'webhook_id' => $webhookId,
            'data' => $data,
        ];
    }

    /**
     * Synchronous single delivery (used by admin Test and the cron worker).
     *
     * @param array $webhook webhook config (with decoded events/headers)
     * @param array $payload full payload envelope
     * @return array ['success' => bool, 'http_code' => int, 'response' => string, 'error' => string|null]
     */
    public function deliver(array $webhook, array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $headers = ['Content-Type: application/json', 'User-Agent: 1wellness-Webhooks/1.0'];
        if (!empty($webhook['secret'])) {
            $headers[] = 'X-Webhook-Signature: ' . hash_hmac('sha256', $body, $webhook['secret']);
        }
        $custom = $webhook['headers'] ?? [];
        if (is_string($custom)) {
            $custom = json_decode($custom, true) ?: [];
        }
        foreach ($custom as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhook['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => defined('WEBHOOK_TIMEOUT') ? WEBHOOK_TIMEOUT : 10,
            CURLOPT_CUSTOMREQUEST => $webhook['method'] ?? 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => (defined('APP_ENV') && APP_ENV === 'production'),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'http_code' => 0, 'response' => '', 'error' => $error];
        }
        $ok = $httpCode >= 200 && $httpCode < 300;
        return [
            'success' => $ok,
            'http_code' => $httpCode,
            'response' => is_string($response) ? substr($response, 0, 2000) : '',
            'error' => $ok ? null : "HTTP $httpCode",
        ];
    }

    /** Send a sample payload right now (admin "Test" button / API test action). */
    public function sendTest($id)
    {
        $webhook = $this->getWebhook($id);
        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook not found'];
        }
        $event = $webhook['events'][0] ?? 'sale.completed';
        $payload = $this->buildPayload($webhook['id'], $event, [
            'test' => true,
            'message' => 'This is a test delivery from 1wellness.',
            'sample' => $this->samplePayload($event),
        ]);
        $result = $this->deliver($webhook, $payload);
        $this->recordResult($id, $result['success']);
        return $result;
    }

    /** Representative sample data per event (for tests & docs). */
    public function samplePayload($event)
    {
        $samples = [
            'assessment.completed' => ['email' => 'jane@example.com', 'name' => 'Jane D.', 'funnel' => 'pcos', 'assessment_type' => 'pcos', 'score' => 72],
            'user.registered' => ['user_id' => 123, 'email' => 'jane@example.com', 'name' => 'Jane D.', 'funnel' => 'pcos'],
            'sale.completed' => ['sale_id' => 'ORD_abc123', 'user_id' => 123, 'email' => 'jane@example.com', 'amount' => 97, 'currency' => 'USD', 'product_type' => 'pcos', 'product_name' => 'PCOS 90-Day Plan'],
            'funnel.purchase' => ['session_id' => 'sess_xyz', 'funnel' => 'pcos', 'amount' => 97, 'currency' => 'USD', 'experiment_id' => 1, 'variant_id' => 2],
            'experiment.started' => ['experiment_id' => 1, 'name' => 'PCOS hero headline', 'funnel' => 'pcos', 'stage' => 'landing', 'variants' => 3],
            'experiment.concluded' => ['experiment_id' => 1, 'name' => 'PCOS hero headline', 'winner_variant_id' => 2, 'winner_name' => 'B: urgency headline', 'p_best' => 0.972, 'lift' => '+18.4%'],
            'experiment.variant_killed' => ['experiment_id' => 1, 'variant_id' => 3, 'variant_name' => 'C: long form', 'reason' => 'manual'],
            'experiment.challenger_proposed' => ['experiment_id' => 1, 'variant_id' => 4, 'variant_name' => 'D: AI challenger', 'compliance_status' => 'compliant'],
            'experiment.insight_ready' => ['insight_id' => 9, 'funnel' => 'pcos', 'summary' => 'Largest leak: results -> plan_select (-34% vs baseline)'],
        ];
        return $samples[$event] ?? ['event' => $event];
    }

    /** Update success/failure counters after a delivery attempt. */
    public function recordResult($id, $success)
    {
        try {
            if ($this->db->isFileStorage()) {
                $all = $this->fileLoad();
                foreach ($all as &$w) {
                    if ($w['id'] === $id) {
                        $key = $success ? 'success_count' : 'failure_count';
                        $w[$key] = ($w[$key] ?? 0) + 1;
                        $w['last_triggered'] = date('Y-m-d H:i:s');
                    }
                }
                $this->fileSave($all);
                return;
            }
            $col = $success ? 'success_count' : 'failure_count';
            $this->db->query(
                "UPDATE webhooks SET $col = $col + 1, last_triggered = :t WHERE id = :id",
                [':t' => date('Y-m-d H:i:s'), ':id' => $id]
            );
        } catch (Exception $e) {
            error_log('WebhookDispatcher recordResult: ' . $e->getMessage());
        }
    }

    /** Recent deliveries for a webhook (admin detail view). */
    public function recentDeliveries($id, $limit = 20)
    {
        if ($this->db->isFileStorage()) {
            $file = APP_ROOT . '/database/data/webhook_queue.json';
            $queue = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
            $rows = array_values(array_filter($queue, function ($q) use ($id) {
                return ($q['webhook_id'] ?? '') === $id;
            }));
            return array_slice(array_reverse($rows), 0, $limit);
        }
        $limit = (int) $limit;
        return $this->db->fetchAll(
            "SELECT id, event, status, attempts, next_attempt, created_at, updated_at
             FROM webhook_queue WHERE webhook_id = :id ORDER BY id DESC LIMIT $limit",
            [':id' => $id]
        );
    }

    // ------------------------------------------------------------------
    // File-storage fallback helpers
    // ------------------------------------------------------------------

    private function filePath()
    {
        return APP_ROOT . '/database/data/webhooks.json';
    }

    private function fileLoad()
    {
        $f = $this->filePath();
        return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
    }

    private function fileSave($webhooks)
    {
        file_put_contents($this->filePath(), json_encode(array_values($webhooks), JSON_PRETTY_PRINT));
    }
}
