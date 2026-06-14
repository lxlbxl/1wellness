<?php

require_once dirname(__DIR__, 2) . '/classes/Database.php';
require_once dirname(__DIR__, 2) . '/classes/Settings.php';
require_once __DIR__ . '/TemplateRenderer.php';
require_once __DIR__ . '/ConsentManager.php';
require_once __DIR__ . '/Channels/ChannelAdapterInterface.php';
require_once __DIR__ . '/Channels/EmailChannel.php';
require_once __DIR__ . '/Channels/WhatsAppChannel.php';
require_once __DIR__ . '/Channels/SmsChannel.php';

/**
 * Public API for all notification work.
 *
 * Calling code (AutomationOrchestrator, JourneyEngine, admin) only
 * touches this class — never channel adapters directly.
 *
 * enqueue()       → write to notification_queue (idempotent on dedupe_key)
 * cancelJourney() → bulk-cancel pending rows for a recipient + journey set
 * dispatch()      → send one queue row now (called by send_notifications.php cron)
 * ensureSchema()  → idempotent table creation (called on first use)
 */
class NotificationService
{
    private static $instance = null;

    private $db;
    private $settings;
    private $renderer;
    private $consent;
    private $schemaEnsured = false;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
        $this->renderer = new TemplateRenderer();
        $this->consent = new ConsentManager();
        $this->ensureSchema();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ---------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------

    /**
     * Enqueue a notification. Idempotent: duplicate dedupe_key is silently ignored.
     *
     * @param string   $journeyKey   e.g. 'purchase_confirm'
     * @param int      $step
     * @param string   $recipientType 'lead' | 'user'
     * @param int|null $recipientId   users.id for type='user'
     * @param string   $email
     * @param string   $phone
     * @param string   $funnel       'pcos'|'acne'|'weight'|'mens'|'all'
     * @param string   $templateKey  e.g. 'purchase_confirm_1'
     * @param array    $payload      Merge vars for template
     * @param string   $channelLadder comma-separated preferred channels, e.g. 'whatsapp,email'
     * @param string   $dedupeKey    Unique per journey+step+recipient
     * @param mixed    $sendAfter    DateTime string or Unix timestamp. Default: now.
     * @return int|null Inserted row id, or null on duplicate/error.
     */
    public function enqueue(
        string $journeyKey,
        int $step,
        string $recipientType,
        ?int $recipientId,
        string $email,
        string $phone,
        string $funnel,
        string $templateKey,
        array $payload,
        string $channelLadder,
        string $dedupeKey,
        $sendAfter = null
    ): ?int {
        if (!$this->isJourneyEnabled($journeyKey)) {
            return null;
        }

        $sendAfterStr = $sendAfter
            ? (is_int($sendAfter) ? date('Y-m-d H:i:s', $sendAfter) : (string) $sendAfter)
            : date('Y-m-d H:i:s');

        try {
            return $this->db->insert('notification_queue', [
                'journey_key'    => $journeyKey,
                'step'           => $step,
                'recipient_type' => $recipientType,
                'recipient_id'   => $recipientId,
                'email'          => $email ?: null,
                'phone'          => $phone ?: null,
                'funnel'         => $funnel ?: 'all',
                'template_key'   => $templateKey,
                'payload'        => json_encode($payload),
                'channel_ladder' => $channelLadder,
                'dedupe_key'     => $dedupeKey,
                'send_after'     => $sendAfterStr,
                'status'         => 'pending',
                'attempts'       => 0,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // UNIQUE constraint violation = duplicate — silently ignore
            if (strpos($e->getMessage(), 'UNIQUE') !== false
                || strpos($e->getMessage(), 'Duplicate') !== false) {
                return null;
            }
            error_log('NotificationService enqueue: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cancel pending rows for a recipient across the given journey keys.
     * Used on purchase to stop C-series (checkout_abandon etc.).
     */
    public function cancelJourney(string $email, array $journeyKeys, string $reason = 'goal_completed'): int
    {
        if (!$email || empty($journeyKeys)) return 0;
        try {
            $placeholders = implode(',', array_fill(0, count($journeyKeys), '?'));
            $params = array_merge([$email, $reason], $journeyKeys);
            return (int) $this->db->execute(
                "UPDATE notification_queue
                 SET status = 'cancelled', cancelled_reason = ?
                 WHERE email = ? AND status = 'pending' AND journey_key IN ($placeholders)",
                array_merge([$reason, $email], $journeyKeys)
            );
        } catch (Exception $e) {
            error_log('NotificationService cancelJourney: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Attempt delivery of one queue row. Called by the send_notifications cron.
     *
     * @return array{ok:bool, channel:string|null, reason:string}
     */
    public function dispatch(array $row): array
    {
        $dryRun = (bool) $this->settings->get('notify_dry_run', '0');
        $email = (string) ($row['email'] ?? '');
        $phone = (string) ($row['phone'] ?? '');
        $funnel = (string) ($row['funnel'] ?? 'all');
        $journeyKey = (string) ($row['journey_key'] ?? '');
        $step = (int) ($row['step'] ?? 1);
        $payload = json_decode($row['payload'] ?? '{}', true) ?: [];

        $ladder = array_filter(array_map('trim', explode(',', $row['channel_ladder'] ?? 'email')));

        foreach ($ladder as $channelName) {
            $adapter = $this->makeAdapter($channelName);
            if (!$adapter || !$adapter->isAvailable()) {
                continue;
            }

            $consentCheck = $this->consent->canSend($journeyKey, $channelName, $email, $phone);
            if (!$consentCheck['ok']) {
                continue;
            }

            if (!$this->isInQuietWindow($email)) {
                // Not in quiet window — check if we should defer (marketing only)
                $isTransactional = in_array($journeyKey, ConsentManager::TRANSACTIONAL_JOURNEYS, true);
                if (!$isTransactional && $this->isQuietHours()) {
                    return ['ok' => false, 'channel' => $channelName, 'reason' => 'quiet_hours'];
                }
            }

            $to = ($channelName === 'email') ? $email : $phone;
            if (!$to) continue;

            $rendered = $this->renderer->render($row['template_key'], $channelName, $payload, $funnel);
            if (!$rendered) {
                error_log("NotificationService: template '{$row['template_key']}' not found for channel '$channelName'");
                continue;
            }

            if ($dryRun) {
                $this->logDelivery($row['id'], $row, $channelName, 'sent', null, null, null, null);
                error_log("NotificationService [DRY RUN] would send $channelName to $to: {$rendered['subject']}");
                return ['ok' => true, 'channel' => $channelName, 'reason' => 'dry_run'];
            }

            $result = $adapter->send($to, $rendered['subject'], $rendered['body'], [
                'wa_template_name' => $rendered['wa_template_name'] ?? '',
                'queue_id'         => $row['id'],
                'journey_key'      => $journeyKey,
                'step'             => $step,
            ]);

            $this->logDelivery(
                $row['id'], $row, $channelName,
                $result['ok'] ? 'sent' : 'failed',
                $adapter->channelName() === 'email' ? 'smtp' : $this->settings->get($channelName . '_provider', $channelName),
                $result['provider_msg_id'] ?? null,
                $result['error'] ?? null,
                $result['cost_usd'] ?? null
            );

            if ($result['ok']) {
                // Write in-app notification row for members
                if (!empty($row['recipient_id'])) {
                    $this->writeInAppRow($row, $rendered['subject'], $rendered['body']);
                }
                return ['ok' => true, 'channel' => $channelName, 'reason' => 'sent'];
            }
            // Failed on this channel — fall through to next in ladder
        }

        return ['ok' => false, 'channel' => null, 'reason' => 'all_channels_failed_or_suppressed'];
    }

    // ---------------------------------------------------------------
    // Helpers accessible to admin
    // ---------------------------------------------------------------

    public function queueStats(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT status, COUNT(*) AS c FROM notification_queue GROUP BY status"
            ) ?: [];
            $stats = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'suppressed' => 0];
            foreach ($rows as $r) {
                $stats[$r['status']] = (int) $r['c'];
            }
            return $stats;
        } catch (Exception $e) {
            return [];
        }
    }

    public function recentFailures(int $limit = 50): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT nq.*, nl.error, nl.channel
                 FROM notification_queue nq
                 LEFT JOIN notification_log nl ON nl.queue_id = nq.id
                 WHERE nq.status = 'failed'
                 ORDER BY nq.created_at DESC LIMIT ?",
                [$limit]
            ) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function journeyStats(int $days = 7): array
    {
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        try {
            return $this->db->fetchAll(
                "SELECT journey_key, channel, status, COUNT(*) AS c
                 FROM notification_log
                 WHERE created_at >= ?
                 GROUP BY journey_key, channel, status
                 ORDER BY journey_key, channel, status",
                [$since]
            ) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------
    // Private
    // ---------------------------------------------------------------

    private function makeAdapter(string $channelName): ?ChannelAdapterInterface
    {
        switch ($channelName) {
            case 'email':     return new EmailChannel();
            case 'whatsapp':  return new WhatsAppChannel();
            case 'sms':       return new SmsChannel();
            default:          return null;
        }
    }

    private function isJourneyEnabled(string $journeyKey): bool
    {
        $key = 'journey_' . $journeyKey . '_enabled';
        $val = $this->settings->get($key, '1');
        return $val !== '0' && $val !== false;
    }

    private function isQuietHours(): bool
    {
        $tz = $this->settings->get('notify_timezone', 'Europe/London');
        try {
            $now = new DateTime('now', new DateTimeZone($tz));
        } catch (Exception $e) {
            $now = new DateTime('now');
        }
        $timeStr = $now->format('H:i');
        $quietStart = $this->settings->get('notify_quiet_start', '21:00');
        $quietEnd   = $this->settings->get('notify_quiet_end', '08:00');

        // Handle wrap-around (21:00 → 08:00)
        if ($quietStart > $quietEnd) {
            return $timeStr >= $quietStart || $timeStr < $quietEnd;
        }
        return $timeStr >= $quietStart && $timeStr < $quietEnd;
    }

    private function isInQuietWindow(string $email): bool
    {
        // Per-member timezone from user_time_windows is future work; use server quiet hours for now
        return false;
    }

    private function logDelivery(
        $queueId, array $row, string $channel, string $status,
        ?string $provider, ?string $providerMsgId, ?string $error, ?float $cost
    ): void {
        try {
            $this->db->insert('notification_log', [
                'queue_id'        => $queueId,
                'journey_key'     => $row['journey_key'] ?? null,
                'step'            => $row['step'] ?? null,
                'channel'         => $channel,
                'provider'        => $provider,
                'provider_msg_id' => $providerMsgId,
                'email'           => $row['email'] ?? null,
                'phone'           => $row['phone'] ?? null,
                'status'          => $status,
                'error'           => $error,
                'cost_usd'        => $cost,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('NotificationService logDelivery: ' . $e->getMessage());
        }
    }

    private function writeInAppRow(array $row, string $title, string $body): void
    {
        try {
            $this->db->insert('user_notifications', [
                'user_id'           => (int) $row['recipient_id'],
                'notification_type' => $row['journey_key'] ?? 'system',
                'scheduled_date'    => date('Y-m-d'),
                'title'             => mb_substr(strip_tags($title), 0, 255),
                'message'           => mb_substr(strip_tags($body), 0, 1000),
                'channel'           => 'in_app',
                'status'            => 'delivered',
                'sent_at'           => date('Y-m-d H:i:s'),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // Non-fatal
        }
    }

    public function ensureSchema(): void
    {
        if ($this->schemaEnsured) return;
        if ($this->db->isFileStorage()) {
            $this->schemaEnsured = true;
            return;
        }
        try {
            $pdo = $this->db->getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sqlFile = dirname(__DIR__, 2) . '/database/migrations/004_notifications.sql';
            if (!file_exists($sqlFile)) {
                $this->schemaEnsured = true;
                return;
            }
            $sql = file_get_contents($sqlFile);
            if ($driver === 'mysql') {
                $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT AUTO_INCREMENT PRIMARY KEY', $sql);
                $sql = str_replace('CREATE UNIQUE INDEX IF NOT EXISTS', 'CREATE UNIQUE INDEX IF NOT EXISTS', $sql);
            }
            $statements = array_map('trim', explode(';', $sql));
            foreach ($statements as $stmt) {
                $stmt = preg_replace('/^\s*(--[^\n]*\n?)+/', '', $stmt);
                if (trim($stmt) === '') continue;
                try { $pdo->exec($stmt); } catch (Exception $e) { /* ignore pre-existing */ }
            }
            $this->schemaEnsured = true;
        } catch (Exception $e) {
            error_log('NotificationService ensureSchema: ' . $e->getMessage());
        }
    }

    /** Seed all default journey templates if table is empty. Called by JourneyEngine. */
    public function seedTemplates(): void
    {
        try {
            $count = $this->db->fetch("SELECT COUNT(*) AS c FROM notification_templates");
            if ($count && (int) $count['c'] > 0) return;
        } catch (Exception $e) {
            return;
        }
        $this->insertDefaultTemplates();
    }

    private function insertDefaultTemplates(): void
    {
        $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');
        $templates = $this->defaultTemplates($siteUrl);
        foreach ($templates as $t) {
            try {
                $this->db->insert('notification_templates', $t);
            } catch (Exception $e) {
                // ignore duplicates
            }
        }
    }

    private function defaultTemplates(string $siteUrl): array
    {
        $now = date('Y-m-d H:i:s');
        return [
            // F1 — purchase_confirm
            [
                'template_key' => 'purchase_confirm_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Welcome to 1wellness — your {{funnel_label}} plan is ready 🌿',
                'body' => '<h2>Hi {{name}},</h2>
<p>Thank you for joining 1wellness! Your <strong>{{plan}}</strong> has been created and is waiting for you.</p>
<p><a href="{{portal_link}}" style="background:#0f3922;color:#fff;padding:14px 28px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold">Go to My Dashboard</a></p>
<p>Your login email is <strong>{{email}}</strong>. If you need to reset your password, use the "Forgot password" link on the login page.</p>
<p>If you have any questions, reply to this email or contact us at <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>
<p>Here is what happens next:</p>
<ul>
<li>Day 1 — your personalised plan is ready (meals, herbal protocol, movement)</li>
<li>Day 3 — you will receive a guide to your tea ritual</li>
<li>Day 7 — first check-in + streak milestone</li>
</ul>
<p>We are rooting for you,<br>The 1wellness team</p>',
                'wa_template_name' => 'purchase_confirm', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'purchase_confirm_1', 'channel' => 'whatsapp', 'funnel' => 'all',
                'subject' => '',
                'body' => "Hi {{name}} 👋 Welcome to 1wellness!\n\nYour {{funnel_label}} plan is ready. Log in here to get started: {{portal_link}}\n\nThis is also your support thread — feel free to reply with any questions.",
                'wa_template_name' => 'purchase_confirm', 'active' => 1, 'updated_at' => $now,
            ],

            // F4 — order_bump_fulfil (Expert Access)
            [
                'template_key' => 'order_bump_fulfil_1', 'channel' => 'whatsapp', 'funnel' => 'all',
                'subject' => '',
                'body' => "Hi {{name}}, I am your 1wellness expert! 🌿\n\nYou have unlocked Expert Access — this thread is your private support channel. Feel free to ask me anything about your {{funnel_label}} protocol, meals, or progress.\n\nYour plan: {{portal_link}}",
                'wa_template_name' => 'expert_access_welcome', 'active' => 1, 'updated_at' => $now,
            ],

            // F2 — onboarding D1
            [
                'template_key' => 'onboarding_d1', 'channel' => 'whatsapp', 'funnel' => 'all',
                'subject' => '',
                'body' => "Hi {{name}} 🌿 It has been 24 hours since you joined!\n\nHave you logged in yet? Your first personalised meal plan is ready: {{portal_link}}\n\nReply if you need any help getting started.",
                'wa_template_name' => 'onboarding_day1', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'onboarding_d1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your first 1wellness day — have you checked your plan? 🌿',
                'body' => '<h2>Hi {{name}},</h2>
<p>It has been 24 hours since you joined — have you logged in yet? Your personalised plan for day 1 is ready.</p>
<p><a href="{{portal_link}}" style="background:#0f3922;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">View My Day 1 Plan</a></p>
<p>The first week sets the tone for everything. Even five minutes with your protocol today matters.</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // F2 — onboarding D3
            [
                'template_key' => 'onboarding_d3', 'channel' => 'whatsapp', 'funnel' => 'all',
                'subject' => '',
                'body' => "Hi {{name}} 🍵 Day 3! Time to set up your tea ritual reminders.\n\nVisit {{portal_link}} and go to Preferences → Reminder Times to pick your morning and evening tea windows.\n\nConsistency is what makes the herbal protocol work.",
                'wa_template_name' => 'onboarding_day3', 'active' => 1, 'updated_at' => $now,
            ],

            // F2 — onboarding D7
            [
                'template_key' => 'onboarding_d7', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your first week with 1wellness — how are you doing? 💚',
                'body' => '<h2>Hi {{name}},</h2>
<p>One full week! That is worth celebrating. How has the first week felt?</p>
<p>Log in to see your streak and progress summary: <a href="{{portal_link}}">{{portal_link}}</a></p>
<p>Common wins at week 1: more consistent energy, fewer afternoon crashes, and better sleep. If you are not seeing these yet, do not worry — most members notice the biggest shifts around week 3.</p>
<p>Keep going,<br>The 1wellness team</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // C1 — assessment_abandon
            [
                'template_key' => 'assessment_abandon_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your {{funnel_label}} results are just a few questions away',
                'body' => '<h2>Hi {{name}},</h2>
<p>You started your {{funnel_label}} assessment but did not quite finish — your personalised results are waiting.</p>
<p>The good news: your answers are saved. Pick up exactly where you left off:</p>
<p><a href="{{resume_link}}" style="background:#D97757;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold">Continue My Assessment</a></p>
<p>It only takes about 3 more minutes to get your personalised plan.</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'assessment_abandon_2', 'channel' => 'sms', 'funnel' => 'all',
                'subject' => '',
                'body' => "1wellness: your {{funnel_label}} results are saved. Finish in 3 min: {{resume_link}}",
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // C2 — results_no_plan_view
            [
                'template_key' => 'results_no_plan_1', 'channel' => 'email', 'funnel' => 'pcos',
                'subject' => 'Understanding your PCOS type — what your results mean',
                'body' => '<h2>Hi {{name}},</h2>
<p>Your assessment identified you as <strong>{{type}}</strong> PCOS type. Understanding this is the first step to a protocol that actually works for <em>your</em> body, not a generic plan.</p>
<p>Here is what {{type}} PCOS means and what your personalised plan addresses:</p>
<p><a href="{{plan_link}}" style="background:#0f3922;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">See My Results & Plan</a></p>
<p>Hundreds of women with {{type}} PCOS have seen real improvements following this protocol. You are not alone in this.</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'results_no_plan_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your {{funnel_label}} results — what they mean for you',
                'body' => '<h2>Hi {{name}},</h2>
<p>You completed your {{funnel_label}} assessment but have not yet seen your full personalised plan.</p>
<p><a href="{{plan_link}}" style="background:#0f3922;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">View My Results & Plan</a></p>
<p>Your results include a personalised protocol, meal guide, and herbal recommendations based on your specific answers.</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // C3 — checkout_abandon
            [
                'template_key' => 'checkout_abandon_1', 'channel' => 'whatsapp', 'funnel' => 'all',
                'subject' => '',
                'body' => "Hi {{name}} 👋 You started your 1wellness checkout but did not finish.\n\nYour plan is still waiting — and our 30-day money-back guarantee means there is zero risk.\n\nResume here: {{plan_link}}\n\nQuestions? Reply to this message.",
                'wa_template_name' => 'checkout_followup', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'checkout_abandon_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your 1wellness plan — still waiting for you (30-day guarantee)',
                'body' => '<h2>Hi {{name}},</h2>
<p>You were this close to starting your {{funnel_label}} journey — your personalised plan is still waiting.</p>
<h3>Why the 30-day guarantee matters</h3>
<p>We stand behind the protocol completely. If you follow it for 30 days and do not feel a difference, we will refund you in full — no questions asked.</p>
<p><a href="{{plan_link}}" style="background:#D97757;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold">Complete My Purchase</a></p>
<p>If something went wrong with the payment or you have a question, reply to this email or reach us at <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'checkout_abandon_2', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'One last note from the 1wellness team',
                'body' => '<h2>Hi {{name}},</h2>
<p>We noticed you have not completed your {{funnel_label}} plan purchase. This is the last reminder we will send — we respect your inbox.</p>
<p>If you have questions about the protocol, the guarantee, or anything else, our team is here: <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>
<p>Whenever you are ready: <a href="{{plan_link}}">{{plan_link}}</a></p>
<p>Wishing you well regardless,<br>The 1wellness team</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'checkout_abandon_2', 'channel' => 'sms', 'funnel' => 'all',
                'subject' => '',
                'body' => "1wellness: last reminder — your {{funnel_label}} plan + 30-day guarantee: {{plan_link}}",
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // R1 — daily_nudge
            [
                'template_key' => 'daily_nudge_1', 'channel' => 'whatsapp', 'funnel' => 'all',
                'subject' => '',
                'body' => "Good morning {{name}} 🌿 Today's focus: {{focus_tip}}\n\nYour meals are planned. Log in to start: {{portal_link}}",
                'wa_template_name' => 'daily_nudge', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'daily_nudge_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your 1wellness day {{day_number}} plan 🌿',
                'body' => '<h2>Good morning {{name}} 👋</h2>
<p><strong>Today\'s focus:</strong> {{focus_tip}}</p>
<p><strong>Meal headline:</strong> {{meal_headline}}</p>
<p><a href="{{portal_link}}" style="background:#0f3922;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">View Today\'s Plan</a></p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // R3 — streak_save
            [
                'template_key' => 'streak_save_1', 'channel' => 'whatsapp', 'funnel' => 'all',
                'subject' => '',
                'body' => "Hi {{name}} ⚡ You are about to break your {{streak_days}}-day streak!\n\nLog something — even just your morning tea — to keep it going: {{portal_link}}",
                'wa_template_name' => 'streak_save', 'active' => 1, 'updated_at' => $now,
            ],

            // R4 — weekly_summary
            [
                'template_key' => 'weekly_summary_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your 1wellness week in review 📊',
                'body' => '<h2>Hi {{name}},</h2>
<p>Here is your weekly progress summary:</p>
<ul>
<li><strong>Days logged:</strong> {{days_logged}} / 7</li>
<li><strong>Current streak:</strong> {{streak_days}} days</li>
<li><strong>Week highlight:</strong> {{week_highlight}}</li>
</ul>
<p><a href="{{portal_link}}" style="background:#0f3922;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">View Full Summary</a></p>
<p>Keep going — the results compound week over week.</p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // R7 — winback D14
            [
                'template_key' => 'winback_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your 1wellness plan is still here when you are ready 💚',
                'body' => '<h2>Hi {{name}},</h2>
<p>You have not logged in to your 1wellness dashboard in a while — your personalised {{funnel_label}} plan is still there, waiting.</p>
<p>Life gets busy. No judgement. Whenever you are ready to pick back up, we are here.</p>
<p><a href="{{portal_link}}" style="background:#0f3922;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">Resume My Plan</a></p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],
            [
                'template_key' => 'winback_2', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'A quick question from the 1wellness team',
                'body' => '<h2>Hi {{name}},</h2>
<p>It has been 30 days since you last logged in. We would love to know — did something not work for you, or did life just get in the way?</p>
<p>Your feedback directly shapes how we improve the protocol for others.</p>
<p><a href="{{portal_link}}/feedback" style="background:#6B7C70;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">Share Feedback (1 min)</a></p>
<p>And if you want to start fresh, your plan is ready: <a href="{{portal_link}}">{{portal_link}}</a></p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],

            // R6 — renewal_refill
            [
                'template_key' => 'renewal_1', 'channel' => 'email', 'funnel' => 'all',
                'subject' => 'Your 1wellness plan ends in 14 days — what\'s next?',
                'body' => '<h2>Hi {{name}},</h2>
<p>Your current {{funnel_label}} plan is coming to an end in 14 days. You have come so far — here is how to keep the momentum going.</p>
<p>Your next phase plan continues at the rate you earned as an existing customer.</p>
<p><a href="{{plan_link}}" style="background:#0f3922;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block">See Renewal Options</a></p>',
                'wa_template_name' => '', 'active' => 1, 'updated_at' => $now,
            ],
        ];
    }
}
