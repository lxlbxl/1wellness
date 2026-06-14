<?php

/**
 * NotificationService — single public API for the notification pipeline.
 *
 * enqueue()       Schedule a message for delivery.
 * cancelJourney() Cancel pending steps (e.g. on purchase).
 * suppress()      Record an opt-out / bounce in notification_consent.
 * isSuppressed()  Check consent before sending.
 *
 * Admin helpers: queueStats(), recentLog(), journeyStats()
 *
 * The service is self-bootstrapping: ensureTables() runs in the constructor
 * so no manual migration is needed for new deployments.
 */
class NotificationService
{
    private $db;
    private static $instance = null;

    // Journey keys whose messages are transactional (skip quiet hours + marketing caps)
    private static $transactionalKeys = [
        'purchase_confirm',
        'onboarding',
        'pdf_delivery',
        'order_bump_fulfil',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTables();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Core API
    // -------------------------------------------------------------------------

    /**
     * Schedule a notification for delivery.
     *
     * @param string      $journeyKey    e.g. 'purchase_confirm'
     * @param int         $step          Step number within the journey
     * @param string      $recipientType 'lead' | 'user'
     * @param int|null    $recipientId   users.id when type='user'
     * @param string      $email
     * @param string|null $phone
     * @param string      $funnel        pcos|acne|weight|mens|all
     * @param string      $templateKey   Matches notification_templates.template_key
     * @param array       $payload       Merge variables for TemplateRenderer
     * @param string      $channelLadder Comma-separated channel preference, e.g. 'email' or 'whatsapp,email'
     * @param string      $sendAfter     MySQL DATETIME string
     * @param string|null $dedupeKey     Auto-generated from journey+step+email if omitted
     * @return bool True if a new row was inserted, false if deduplicated
     */
    public function enqueue(
        string $journeyKey,
        int $step,
        string $recipientType,
        ?int $recipientId,
        string $email,
        ?string $phone,
        string $funnel,
        string $templateKey,
        array $payload,
        string $channelLadder,
        string $sendAfter,
        ?string $dedupeKey = null
    ): bool {
        if ($dedupeKey === null) {
            $dedupeKey = $journeyKey . '_' . $step . '_' . md5(strtolower(trim($email)));
        }

        // Idempotent: skip if dedupe_key already exists
        $existing = null;
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT id FROM notification_queue WHERE dedupe_key = ? LIMIT 1"
            );
            $stmt->execute([$dedupeKey]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("NotificationService::enqueue dedup check: " . $e->getMessage());
        }

        if ($existing) {
            return false;
        }

        try {
            $stmt = $this->db->getConnection()->prepare(
                "INSERT INTO notification_queue
                 (journey_key, step, recipient_type, recipient_id, email, phone, funnel,
                  template_key, payload, channel_ladder, dedupe_key, send_after, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pending',?)"
            );
            $stmt->execute([
                $journeyKey,
                $step,
                $recipientType,
                $recipientId,
                strtolower(trim($email)),
                $phone,
                $funnel,
                $templateKey,
                json_encode($payload),
                $channelLadder,
                $dedupeKey,
                $sendAfter,
                date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (Exception $e) {
            error_log("NotificationService::enqueue insert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel all pending steps for given journey keys + recipient email.
     *
     * @param string   $email
     * @param string[] $journeyKeys
     * @param string   $reason  cancelled_reason value (e.g. 'purchased', 'completed')
     * @return int Number of rows cancelled
     */
    public function cancelJourney(string $email, array $journeyKeys, string $reason = 'purchased'): int
    {
        if (empty($journeyKeys)) {
            return 0;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($journeyKeys), '?'));
            $params = array_merge(
                [$reason, date('Y-m-d H:i:s'), strtolower(trim($email))],
                $journeyKeys
            );
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE notification_queue
                 SET status='cancelled', cancelled_reason=?, next_attempt=?
                 WHERE email=? AND journey_key IN ($placeholders) AND status='pending'"
            );
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("NotificationService::cancelJourney: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Record a consent / suppression event in notification_consent.
     * If a record already exists for (email, channel), update it.
     */
    public function suppress(string $email, string $channel, string $source, string $status = 'opted_out'): void
    {
        $email = strtolower(trim($email));
        try {
            // Try update first, then insert
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE notification_consent SET status=?, source=?, updated_at=?
                 WHERE email=? AND channel=?"
            );
            $stmt->execute([$status, $source, date('Y-m-d H:i:s'), $email, $channel]);

            if ($stmt->rowCount() === 0) {
                $stmt = $this->db->getConnection()->prepare(
                    "INSERT INTO notification_consent (email, channel, status, source, updated_at)
                     VALUES (?,?,?,?,?)"
                );
                $stmt->execute([$email, $channel, $status, $source, date('Y-m-d H:i:s')]);
            }
        } catch (Exception $e) {
            error_log("NotificationService::suppress: " . $e->getMessage());
        }
    }

    /**
     * Returns true if the recipient has a hard suppression on this channel
     * (opted_out, bounced, or complained).
     * No record = not suppressed (permissive default until Phase 2 consent gates).
     */
    public function isSuppressed(string $email, string $channel): bool
    {
        $email = strtolower(trim($email));
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT status FROM notification_consent
                 WHERE email=? AND channel=? LIMIT 1"
            );
            $stmt->execute([$email, $channel]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            return in_array($row['status'], ['opted_out', 'bounced', 'complained'], true);
        } catch (Exception $e) {
            error_log("NotificationService::isSuppressed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a delivery attempt to notification_log (called by the send worker).
     */
    public function logDelivery(
        ?int $queueId,
        string $journeyKey,
        int $step,
        string $channel,
        string $provider,
        string $providerMsgId,
        string $email,
        ?string $phone,
        string $status,
        string $error = '',
        float $costUsd = 0.0
    ): void {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "INSERT INTO notification_log
                 (queue_id, journey_key, step, channel, provider, provider_msg_id,
                  email, phone, status, error, cost_usd, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $queueId, $journeyKey, $step, $channel, $provider, $providerMsgId,
                strtolower(trim($email)), $phone, $status,
                $error ?: null,
                $costUsd > 0 ? $costUsd : null,
                date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log("NotificationService::logDelivery: " . $e->getMessage());
        }
    }

    /**
     * Load a template row (tries funnel-specific first, falls back to 'all').
     */
    public function loadTemplate(string $templateKey, string $channel, string $funnel): ?array
    {
        try {
            // Funnel-specific first
            $stmt = $this->db->getConnection()->prepare(
                "SELECT * FROM notification_templates
                 WHERE template_key=? AND channel=? AND funnel=? AND active=1 LIMIT 1"
            );
            $stmt->execute([$templateKey, $channel, $funnel]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }

            // Fallback to 'all'
            $stmt = $this->db->getConnection()->prepare(
                "SELECT * FROM notification_templates
                 WHERE template_key=? AND channel=? AND funnel='all' AND active=1 LIMIT 1"
            );
            $stmt->execute([$templateKey, $channel]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("NotificationService::loadTemplate: " . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Admin query helpers
    // -------------------------------------------------------------------------

    public function queueStats(int $hours = 24): array
    {
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT status, COUNT(*) AS cnt FROM notification_queue
                 WHERE created_at >= ? GROUP BY status"
            );
            $stmt->execute([$since]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'suppressed' => 0];
            foreach ($rows as $r) {
                $out[$r['status']] = (int) $r['cnt'];
            }
            // Also count currently pending (regardless of created_at)
            $stmt2 = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) AS cnt FROM notification_queue WHERE status='pending'"
            );
            $stmt2->execute();
            $out['pending_total'] = (int) ($stmt2->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            return $out;
        } catch (Exception $e) {
            error_log("NotificationService::queueStats: " . $e->getMessage());
            return [];
        }
    }

    public function recentLog(int $limit = 50): array
    {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT * FROM notification_log ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("NotificationService::recentLog: " . $e->getMessage());
            return [];
        }
    }

    public function journeyStats(int $hours = 24): array
    {
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT journey_key,
                        SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) AS sent,
                        SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed
                 FROM notification_log WHERE created_at >= ?
                 GROUP BY journey_key ORDER BY sent DESC"
            );
            $stmt->execute([$since]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("NotificationService::journeyStats: " . $e->getMessage());
            return [];
        }
    }

    public function countMarketingSentToday(string $email): int
    {
        $email = strtolower(trim($email));
        $since = date('Y-m-d') . ' 00:00:00';
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) AS cnt FROM notification_log
                 WHERE email=? AND created_at >= ?
                 AND journey_key NOT IN ('purchase_confirm','onboarding','pdf_delivery','order_bump_fulfil')"
            );
            $stmt->execute([$email, $since]);
            return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function countMarketingSentThisWeek(string $email): int
    {
        $email = strtolower(trim($email));
        $since = date('Y-m-d H:i:s', strtotime('monday this week'));
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) AS cnt FROM notification_log
                 WHERE email=? AND created_at >= ?
                 AND journey_key NOT IN ('purchase_confirm','onboarding','pdf_delivery','order_bump_fulfil')"
            );
            $stmt->execute([$email, $since]);
            return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function isTransactional(string $journeyKey): bool
    {
        foreach (self::$transactionalKeys as $prefix) {
            if ($journeyKey === $prefix || strpos($journeyKey, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Dispatch (called by send_notifications.php worker per queue row)
    // -------------------------------------------------------------------------

    /**
     * Attempt to send one queue row via its channel ladder.
     *
     * Returns:
     *   ['ok' => true,  'reason' => 'sent'|'dry_run', 'channel' => string]
     *   ['ok' => false, 'reason' => string]  — reason drives worker retry/suppress/defer logic
     */
    public function dispatch(array $row): array
    {
        require_once __DIR__ . '/ChannelAdapterInterface.php';
        require_once __DIR__ . '/EmailChannel.php';
        require_once __DIR__ . '/TemplateRenderer.php';
        require_once __DIR__ . '/Settings.php';

        $settings   = Settings::getInstance();
        $dryRun     = (bool) $settings->get('notify_dry_run', 0);
        $emailOn    = (bool) $settings->get('notify_email_enabled', 1);
        $waOn       = (bool) $settings->get('notify_whatsapp_enabled', 0);
        $smsOn      = (bool) $settings->get('notify_sms_enabled', 0);

        $journeyKey = $row['journey_key'];
        $step       = (int) ($row['step'] ?? 1);
        $email      = (string) ($row['email'] ?? '');
        $phone      = (string) ($row['phone'] ?? '');
        $funnel     = $row['funnel'] ?: 'all';
        $templateKey = $row['template_key'];
        $payload    = json_decode($row['payload'] ?: '{}', true) ?: [];
        $ladder     = array_map('trim', explode(',', $row['channel_ladder']));
        $isTransact = self::isTransactional($journeyKey);

        // Quiet hours (marketing only, UTC)
        if (!$isTransact && $this->inQuietHours($settings)) {
            return ['ok' => false, 'reason' => 'quiet_hours'];
        }

        // Marketing frequency caps
        if (!$isTransact) {
            $dailyCap  = (int) $settings->get('notify_daily_cap_marketing', 1);
            $weeklyCap = (int) $settings->get('notify_weekly_cap_marketing', 4);
            if ($this->countMarketingSentToday($email) >= $dailyCap) {
                return ['ok' => false, 'reason' => 'daily_cap'];
            }
            if ($this->countMarketingSentThisWeek($email) >= $weeklyCap) {
                return ['ok' => false, 'reason' => 'weekly_cap'];
            }
        }

        // Journey kill switch
        $journeyEnabled = $settings->get('journey_' . $journeyKey . '_enabled', '1') !== '0';
        if (!$journeyEnabled) {
            return ['ok' => false, 'reason' => 'journey_disabled'];
        }

        $unsubUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://1wellness.club')
            . '/backend/api/unsubscribe.php?email=' . urlencode($email);

        // Walk channel ladder — first success wins
        $triedAtLeastOne = false;
        foreach ($ladder as $channel) {
            if (!$this->channelOn($channel, $emailOn, $waOn, $smsOn)) {
                continue;
            }
            if ($this->isSuppressed($email, $channel)) {
                continue;
            }

            $tpl = $this->loadTemplate($templateKey, $channel, $funnel);
            if (!$tpl) {
                continue;
            }

            $rendered = TemplateRenderer::renderTemplate($tpl, $payload);
            $adapter  = $this->getChannelAdapter($channel);
            if (!$adapter) {
                continue;
            }

            $triedAtLeastOne = true;

            if ($dryRun) {
                $this->logDelivery(
                    (int) ($row['id'] ?? 0), $journeyKey, $step, $channel,
                    'dry_run', 'dry_' . uniqid(), $email, $phone, 'sent'
                );
                return ['ok' => true, 'reason' => 'dry_run', 'channel' => $channel];
            }

            $result = $adapter->send(
                $channel === 'email' ? $email : $phone,
                $rendered['subject'],
                $rendered['body'],
                ['list_unsubscribe' => $unsubUrl]
            );

            $this->logDelivery(
                (int) ($row['id'] ?? 0), $journeyKey, $step, $channel,
                $channel === 'email' ? 'smtp' : $channel,
                $result['provider_msg_id'] ?? '',
                $email, $phone,
                $result['success'] ? 'sent' : 'failed',
                $result['error'] ?? ''
            );

            if ($result['success']) {
                return ['ok' => true, 'reason' => 'sent', 'channel' => $channel];
            }
        }

        if (!$triedAtLeastOne) {
            // Every channel in the ladder was suppressed or disabled
            return ['ok' => false, 'reason' => 'all_channels_failed_or_suppressed'];
        }

        return ['ok' => false, 'reason' => 'send_failed'];
    }

    // -------------------------------------------------------------------------
    // Recent failure log (admin)
    // -------------------------------------------------------------------------

    public function recentFailures(int $limit = 20): array
    {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT * FROM notification_log WHERE status='failed'
                 ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("NotificationService::recentFailures: " . $e->getMessage());
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Dispatch helpers (private)
    // -------------------------------------------------------------------------

    private function channelOn(string $channel, bool $emailOn, bool $waOn, bool $smsOn): bool
    {
        return match ($channel) {
            'email'    => $emailOn,
            'whatsapp' => $waOn,
            'sms'      => $smsOn,
            default    => false,
        };
    }

    private function getChannelAdapter(string $channel): ?ChannelAdapterInterface
    {
        return match ($channel) {
            'email' => new EmailChannel(),
            default => null, // WhatsApp/SMS adapters ship in Phase 3
        };
    }

    private function inQuietHours(Settings $settings): bool
    {
        $start = $settings->get('notify_quiet_start', '21:00');
        $end   = $settings->get('notify_quiet_end', '08:00');
        $now   = (int) date('Hi');
        $s     = (int) str_replace(':', '', $start);
        $e     = (int) str_replace(':', '', $end);
        // Crosses midnight (e.g. 21:00 → 08:00)
        if ($s > $e) {
            return $now >= $s || $now < $e;
        }
        return $now >= $s && $now < $e;
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    private function ensureTables(): void
    {
        if ($this->db->isFileStorage()) {
            return;
        }
        try {
            $conn = $this->db->getConnection();
            $conn->exec("CREATE TABLE IF NOT EXISTS notification_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                journey_key VARCHAR(60) NOT NULL,
                step INTEGER DEFAULT 1,
                recipient_type VARCHAR(10) NOT NULL,
                recipient_id INTEGER,
                email VARCHAR(255),
                phone VARCHAR(30),
                funnel VARCHAR(20),
                template_key VARCHAR(80) NOT NULL,
                payload TEXT,
                channel_ladder VARCHAR(60) NOT NULL,
                dedupe_key VARCHAR(120),
                send_after DATETIME NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                attempts INTEGER DEFAULT 0,
                next_attempt DATETIME,
                cancelled_reason VARCHAR(60),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(dedupe_key)
            )");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_nq_due ON notification_queue(status, send_after)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_nq_recipient ON notification_queue(email, journey_key)");

            $conn->exec("CREATE TABLE IF NOT EXISTS notification_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue_id INTEGER,
                journey_key VARCHAR(60),
                step INTEGER,
                channel VARCHAR(20) NOT NULL,
                provider VARCHAR(30),
                provider_msg_id VARCHAR(120),
                email VARCHAR(255),
                phone VARCHAR(30),
                status VARCHAR(20) NOT NULL,
                error TEXT,
                cost_usd DECIMAL(8,5),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_nl_channel ON notification_log(email, channel, created_at)");

            $conn->exec("CREATE TABLE IF NOT EXISTS notification_consent (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255),
                phone VARCHAR(30),
                channel VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL,
                source VARCHAR(60),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(email, channel)
            )");

            $conn->exec("CREATE TABLE IF NOT EXISTS notification_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_key VARCHAR(80) NOT NULL,
                channel VARCHAR(20) NOT NULL,
                funnel VARCHAR(20) DEFAULT 'all',
                subject VARCHAR(255),
                body TEXT NOT NULL,
                wa_template_name VARCHAR(120),
                active INTEGER DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(template_key, channel, funnel)
            )");

            $this->seedDefaultTemplates();
        } catch (Exception $e) {
            error_log("NotificationService::ensureTables: " . $e->getMessage());
        }
    }

    private function seedDefaultTemplates(): void
    {
        // Only seed if the table is empty
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) AS cnt FROM notification_templates"
            );
            $stmt->execute();
            $count = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            if ($count > 0) {
                return;
            }

            $sqlFile = dirname(__DIR__) . '/database/004_notifications.sql';
            if (!file_exists($sqlFile)) {
                return;
            }
            $sql = file_get_contents($sqlFile);
            // Extract only INSERT statements
            preg_match_all('/INSERT OR IGNORE INTO notification_templates[^;]+;/s', $sql, $matches);
            foreach ($matches[0] as $insert) {
                try {
                    $this->db->getConnection()->exec($insert);
                } catch (Exception $e) {
                    // Ignore duplicates on re-seed
                }
            }
        } catch (Exception $e) {
            error_log("NotificationService::seedDefaultTemplates: " . $e->getMessage());
        }
    }
}
