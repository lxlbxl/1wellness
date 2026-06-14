<?php

require_once dirname(__DIR__, 2) . '/classes/Database.php';
require_once dirname(__DIR__, 2) . '/classes/Settings.php';

/**
 * Central consent + suppression check.
 *
 * All outbound sends must pass through canSend() before dispatch.
 * Suppression precedence: opted_out > bounced > complained > no_record.
 * Transactional journeys (F-series) bypass marketing caps but never bypass
 * hard suppressions (opted_out, bounced, complained).
 */
class ConsentManager
{
    const TRANSACTIONAL_JOURNEYS = ['purchase_confirm', 'onboarding', 'pdf_delivery', 'order_bump_fulfil'];

    private $db;
    private $settings;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
    }

    /**
     * @param string $journeyKey
     * @param string $channel   'email' | 'whatsapp' | 'sms'
     * @param string $email
     * @param string $phone
     * @return array{ok:bool, reason:string}
     */
    public function canSend(string $journeyKey, string $channel, string $email, string $phone = ''): array
    {
        $isTransactional = in_array($journeyKey, self::TRANSACTIONAL_JOURNEYS, true);

        // Hard suppression always applies
        $suppressed = $this->isSuppressed($channel, $email, $phone);
        if ($suppressed !== null) {
            return ['ok' => false, 'reason' => $suppressed];
        }

        if ($isTransactional) {
            return ['ok' => true, 'reason' => 'transactional'];
        }

        // Marketing: require explicit opt-in for WhatsApp/SMS; email is opt-out model
        if ($channel !== 'email') {
            $consent = $this->getConsent($channel, $email, $phone);
            if ($consent !== 'opted_in') {
                return ['ok' => false, 'reason' => 'no_consent'];
            }
        }

        // Frequency cap (marketing only)
        $capResult = $this->checkCaps($channel, $email, $phone);
        if (!$capResult['ok']) {
            return $capResult;
        }

        return ['ok' => true, 'reason' => 'ok'];
    }

    /**
     * Record a consent event.
     * @param string $status 'opted_in' | 'opted_out' | 'bounced' | 'complained'
     */
    public function recordConsent(string $channel, string $email, string $status, string $source, string $phone = ''): void
    {
        $this->db->upsert('notification_consent', [
            'email'      => $email ?: null,
            'phone'      => $phone ?: null,
            'channel'    => $channel,
            'status'     => $status,
            'source'     => $source,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['email', 'channel']);
    }

    /** Cancel all pending marketing sends for an email/journey. */
    public function cancelPendingForEmail(string $email, array $journeyKeys = []): int
    {
        if (empty($email)) return 0;
        try {
            if (!empty($journeyKeys)) {
                $placeholders = implode(',', array_fill(0, count($journeyKeys), '?'));
                $params = array_merge([$email, 'cancelled'], $journeyKeys);
                return (int) $this->db->execute(
                    "UPDATE notification_queue SET status='cancelled', cancelled_reason='journey_cancel'
                     WHERE email = ? AND status = 'pending' AND journey_key IN ($placeholders)",
                    $params
                );
            }
            return (int) $this->db->execute(
                "UPDATE notification_queue SET status='cancelled', cancelled_reason='journey_cancel'
                 WHERE email = ? AND status = 'pending'",
                [$email]
            );
        } catch (Exception $e) {
            error_log('ConsentManager cancelPendingForEmail: ' . $e->getMessage());
            return 0;
        }
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function isSuppressed(string $channel, string $email, string $phone): ?string
    {
        $hardStatuses = ['opted_out', 'bounced', 'complained'];
        $row = null;
        if ($email) {
            $row = $this->db->fetch(
                "SELECT status FROM notification_consent WHERE email = ? AND channel = ?",
                [$email, $channel]
            );
        }
        if (!$row && $phone) {
            $row = $this->db->fetch(
                "SELECT status FROM notification_consent WHERE phone = ? AND channel = ?",
                [$phone, $channel]
            );
        }
        if ($row && in_array($row['status'], $hardStatuses, true)) {
            return $row['status'];
        }
        return null;
    }

    private function getConsent(string $channel, string $email, string $phone): string
    {
        $row = null;
        if ($email) {
            $row = $this->db->fetch(
                "SELECT status FROM notification_consent WHERE email = ? AND channel = ?",
                [$email, $channel]
            );
        }
        if (!$row && $phone) {
            $row = $this->db->fetch(
                "SELECT status FROM notification_consent WHERE phone = ? AND channel = ?",
                [$phone, $channel]
            );
        }
        return $row ? (string) $row['status'] : 'no_record';
    }

    private function checkCaps(string $channel, string $email, string $phone): array
    {
        $dailyCap = (int) $this->settings->get('notify_daily_cap_marketing', 1);
        $weeklyCap = (int) $this->settings->get('notify_weekly_cap_marketing', 4);

        $identifier = $email ?: $phone;
        if (!$identifier) return ['ok' => true, 'reason' => 'ok'];

        $todayStart  = date('Y-m-d 00:00:00');
        $weekStart   = date('Y-m-d 00:00:00', strtotime('monday this week'));

        try {
            $field = $email ? 'email' : 'phone';
            $dayCount = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM notification_log
                 WHERE {$field} = ? AND channel = ? AND status = 'sent' AND created_at >= ?",
                [$identifier, $channel, $todayStart]
            )['c'] ?? 0);

            if ($dayCount >= $dailyCap) {
                return ['ok' => false, 'reason' => 'daily_cap'];
            }

            $weekCount = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM notification_log
                 WHERE {$field} = ? AND channel = ? AND status = 'sent' AND created_at >= ?",
                [$identifier, $channel, $weekStart]
            )['c'] ?? 0);

            if ($weekCount >= $weeklyCap) {
                return ['ok' => false, 'reason' => 'weekly_cap'];
            }
        } catch (Exception $e) {
            // table may not exist on first request — allow send
        }

        return ['ok' => true, 'reason' => 'ok'];
    }
}
