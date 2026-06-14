<?php

require_once dirname(__DIR__, 2) . '/classes/Database.php';
require_once dirname(__DIR__, 2) . '/classes/Settings.php';
require_once __DIR__ . '/NotificationService.php';

/**
 * Evaluates journey triggers and schedules notification_queue rows.
 *
 * Called by backend/cron/journeys.php every 5 minutes.
 *
 * Journey keys (from the spec):
 *   Conversion:  assessment_abandon, results_no_plan_view, checkout_abandon, nurture_long
 *   Follow-up:   purchase_confirm, onboarding, pdf_delivery, order_bump_fulfil
 *   Retention:   daily_nudge, ritual_reminders, streak_save, weekly_summary,
 *                reassessment, renewal_refill, winback, review_nps
 *
 * The cron only evaluates conversion + retention triggers that cannot be fired
 * inline (e.g. checkout_abandon needs a delay). purchase_confirm / F1 is fired
 * inline from AutomationOrchestrator on the purchase webhook for reliability.
 */
class JourneyEngine
{
    private $db;
    private $settings;
    private $ns; // NotificationService

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
        $this->ns = NotificationService::getInstance();
        $this->ns->seedTemplates();
    }

    /** Run all journey evaluations. Returns summary counts. */
    public function run(): array
    {
        $counts = [];
        $counts['assessment_abandon']     = $this->runAssessmentAbandon();
        $counts['checkout_abandon']        = $this->runCheckoutAbandon();
        $counts['results_no_plan_view']    = $this->runResultsNoPlanView();
        $counts['nurture_long']            = $this->runNurtureLong();
        $counts['onboarding']              = $this->runOnboarding();
        $counts['daily_nudge']             = $this->runDailyNudge();
        $counts['streak_save']             = $this->runStreakSave();
        $counts['weekly_summary']          = $this->runWeeklySummary();
        $counts['winback']                 = $this->runWinback();
        $counts['renewal_refill']          = $this->runRenewalRefill();
        return $counts;
    }

    // -----------------------------------------------------------------------
    // C1 — assessment_abandon
    // Trigger: assessment_start event, no assessment_complete after 1 h
    // -----------------------------------------------------------------------
    private function runAssessmentAbandon(): int
    {
        $n = 0;
        $cutoff1h = date('Y-m-d H:i:s', time() - 3600);
        $cutoff48h = date('Y-m-d H:i:s', time() - 48 * 3600);

        // Leads who started but did not complete (email known from nurture_queue)
        $rows = $this->db->fetchAll(
            "SELECT nq.email, nq.name, nq.phone, nq.funnel, nq.session_id, nq.pcos_type
             FROM nurture_queue nq
             WHERE nq.status = 'pending'
               AND nq.assessment_completed_at IS NULL
               AND nq.created_at <= ?
               AND nq.created_at >= ?
               AND nq.email IS NOT NULL AND nq.email <> ''",
            [$cutoff1h, $cutoff48h]
        ) ?: [];

        foreach ($rows as $r) {
            $email  = (string) $r['email'];
            $funnel = (string) ($r['funnel'] ?? 'pcos');
            $phone  = (string) ($r['phone'] ?? '');
            $name   = $this->firstName($r['name'] ?? '');

            $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');
            $resumeLink = $siteUrl . '/' . $funnel . '/assessment.html?resume=1';

            // Step 1 — email at 1 h
            $id = $this->ns->enqueue(
                'assessment_abandon', 1, 'lead', null, $email, $phone, $funnel,
                'assessment_abandon_1',
                ['name' => $name, 'email' => $email, 'resume_link' => $resumeLink,
                 'funnel' => $funnel, 'type' => $r['pcos_type'] ?? ''],
                'email',
                'assessment_abandon_1_' . md5($email),
                date('Y-m-d H:i:s') // already 1 h after start
            );
            if ($id) $n++;

            // Step 2 — SMS at 24 h (if phone available)
            if ($phone) {
                $this->ns->enqueue(
                    'assessment_abandon', 2, 'lead', null, $email, $phone, $funnel,
                    'assessment_abandon_2',
                    ['name' => $name, 'email' => $email, 'resume_link' => $resumeLink, 'funnel' => $funnel],
                    'sms',
                    'assessment_abandon_2_' . md5($email),
                    date('Y-m-d H:i:s', time() + 23 * 3600) // 24 h from start, we're already at 1 h
                );
            }
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // C2 — results_no_plan_view
    // Trigger: assessment_complete event, no sales-page visit after 2 h
    // -----------------------------------------------------------------------
    private function runResultsNoPlanView(): int
    {
        $n = 0;
        $cutoff2h  = date('Y-m-d H:i:s', time() - 2 * 3600);
        $cutoff72h = date('Y-m-d H:i:s', time() - 72 * 3600);

        $rows = $this->db->fetchAll(
            "SELECT nq.email, nq.name, nq.phone, nq.funnel, nq.pcos_type
             FROM nurture_queue nq
             WHERE nq.assessment_completed_at IS NOT NULL
               AND nq.assessment_completed_at <= ?
               AND nq.assessment_completed_at >= ?
               AND nq.sales_page_viewed_at IS NULL
               AND nq.status = 'pending'
               AND nq.email IS NOT NULL AND nq.email <> ''",
            [$cutoff2h, $cutoff72h]
        ) ?: [];

        foreach ($rows as $r) {
            $email  = (string) $r['email'];
            $funnel = (string) ($r['funnel'] ?? 'pcos');
            $phone  = (string) ($r['phone'] ?? '');
            $name   = $this->firstName($r['name'] ?? '');

            $siteUrl   = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');
            $planLink  = $siteUrl . '/' . $funnel . '/';

            // Step 1 — email at 2 h
            $id = $this->ns->enqueue(
                'results_no_plan_view', 1, 'lead', null, $email, $phone, $funnel,
                'results_no_plan_1',
                ['name' => $name, 'email' => $email, 'plan_link' => $planLink,
                 'funnel' => $funnel, 'type' => $r['pcos_type'] ?? ''],
                'email',
                'results_noplan_1_' . md5($email),
                date('Y-m-d H:i:s')
            );
            if ($id) $n++;

            // Step 2 — WhatsApp at 24 h
            if ($phone) {
                $this->ns->enqueue(
                    'results_no_plan_view', 2, 'lead', null, $email, $phone, $funnel,
                    'results_no_plan_1',
                    ['name' => $name, 'email' => $email, 'plan_link' => $planLink,
                     'funnel' => $funnel, 'type' => $r['pcos_type'] ?? ''],
                    'whatsapp',
                    'results_noplan_2_' . md5($email),
                    date('Y-m-d H:i:s', time() + 22 * 3600)
                );
            }

            // Step 3 — email at 72 h
            $this->ns->enqueue(
                'results_no_plan_view', 3, 'lead', null, $email, $phone, $funnel,
                'results_no_plan_1',
                ['name' => $name, 'email' => $email, 'plan_link' => $planLink,
                 'funnel' => $funnel, 'type' => $r['pcos_type'] ?? ''],
                'email',
                'results_noplan_3_' . md5($email),
                date('Y-m-d H:i:s', time() + 70 * 3600)
            );
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // C3 — checkout_abandon
    // Trigger: checkout_init in funnel_tracking, no purchase after 1 h
    // -----------------------------------------------------------------------
    private function runCheckoutAbandon(): int
    {
        $n = 0;
        $cutoff1h  = date('Y-m-d H:i:s', time() - 3600);
        $cutoff48h = date('Y-m-d H:i:s', time() - 48 * 3600);

        // Sessions that had checkout_init but no purchase
        $rows = $this->db->fetchAll(
            "SELECT ft.session_id, ft.funnel_name AS funnel, ft.email, ft.created_at,
                    nq.name, nq.phone, nq.pcos_type
             FROM funnel_tracking ft
             LEFT JOIN nurture_queue nq ON nq.email = ft.email
             WHERE ft.event_type = 'checkout_init'
               AND ft.created_at <= ?
               AND ft.created_at >= ?
               AND ft.email IS NOT NULL AND ft.email <> ''
               AND NOT EXISTS (
                   SELECT 1 FROM funnel_tracking p
                   WHERE p.session_id = ft.session_id AND p.event_type = 'purchase'
               )
             GROUP BY ft.email",
            [$cutoff1h, $cutoff48h]
        ) ?: [];

        foreach ($rows as $r) {
            $email  = (string) $r['email'];
            $funnel = (string) ($r['funnel'] ?? 'pcos');
            $phone  = (string) ($r['phone'] ?? '');
            $name   = $this->firstName($r['name'] ?? '');

            $siteUrl  = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');
            $planLink = $siteUrl . '/' . $funnel . '/';

            $payload = ['name' => $name, 'email' => $email, 'plan_link' => $planLink, 'funnel' => $funnel];

            // Step 1 — WhatsApp/Email/SMS at 1 h
            $id = $this->ns->enqueue(
                'checkout_abandon', 1, 'lead', null, $email, $phone, $funnel,
                'checkout_abandon_1', $payload,
                'whatsapp,email',
                'checkout_abandon_1_' . md5($email),
                date('Y-m-d H:i:s')
            );
            if ($id) $n++;

            // Step 2 — Email + SMS at 20 h
            $this->ns->enqueue(
                'checkout_abandon', 2, 'lead', null, $email, $phone, $funnel,
                'checkout_abandon_2', $payload,
                'email,sms',
                'checkout_abandon_2_' . md5($email),
                date('Y-m-d H:i:s', time() + 19 * 3600)
            );
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // C4 — nurture_long
    // Trigger: C2 finished without purchase (nurture_step advanced past 3)
    // Days 5, 9, 14 — email value content series
    // -----------------------------------------------------------------------
    private function runNurtureLong(): int
    {
        $n = 0;
        $rows = $this->db->fetchAll(
            "SELECT nq.email, nq.name, nq.phone, nq.funnel, nq.pcos_type,
                    nq.nurture_step, nq.assessment_completed_at
             FROM nurture_queue nq
             WHERE nq.status = 'pending'
               AND nq.assessment_completed_at IS NOT NULL
               AND nq.nurture_step >= 3
               AND nq.email IS NOT NULL AND nq.email <> ''
               AND NOT EXISTS (
                   SELECT 1 FROM sales s WHERE s.email = nq.email AND s.payment_status = 'completed'
               )"
        ) ?: [];

        $delays = [5 => 5 * 86400, 9 => 9 * 86400, 14 => 14 * 86400];
        foreach ($rows as $r) {
            $email = (string) $r['email'];
            $funnel = (string) ($r['funnel'] ?? 'pcos');
            $baseTime = strtotime($r['assessment_completed_at']);
            $name = $this->firstName($r['name'] ?? '');
            $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');

            foreach ($delays as $step => $delay) {
                $sendAt = $baseTime + $delay;
                if ($sendAt > time()) {
                    $this->ns->enqueue(
                        'nurture_long', $step, 'lead', null, $email, '', $funnel,
                        'results_no_plan_1',
                        ['name' => $name, 'email' => $email,
                         'plan_link' => $siteUrl . '/' . $funnel . '/',
                         'funnel' => $funnel, 'type' => $r['pcos_type'] ?? ''],
                        'email',
                        'nurture_long_' . $step . '_' . md5($email),
                        date('Y-m-d H:i:s', $sendAt)
                    );
                }
            }
            $n++;
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // F2 — onboarding (D1, D3, D7)
    // Trigger: purchase — scheduled inline by AutomationOrchestrator
    // This cron method handles scheduling for users who were missed at purchase time.
    // -----------------------------------------------------------------------
    private function runOnboarding(): int
    {
        $n = 0;
        // Find users with a completed sale but no onboarding_d1 queued
        $rows = $this->db->fetchAll(
            "SELECT u.id, u.email, u.first_name, u.phone,
                    COALESCE(u.pcos_type, '') AS pcos_type,
                    s.product_type AS funnel, s.created_at AS purchased_at
             FROM users u
             JOIN sales s ON s.email = u.email AND s.payment_status = 'completed'
             WHERE u.status = 'active'
               AND s.created_at >= ?
               AND NOT EXISTS (
                   SELECT 1 FROM notification_queue nq
                   WHERE nq.email = u.email AND nq.journey_key = 'onboarding'
               )
             GROUP BY u.email",
            [date('Y-m-d H:i:s', time() - 7 * 86400)]
        ) ?: [];

        foreach ($rows as $r) {
            $email   = (string) $r['email'];
            $funnel  = (string) ($r['funnel'] ?? 'pcos');
            $phone   = (string) ($r['phone'] ?? '');
            $name    = $this->firstName($r['first_name'] ?? '');
            $userId  = (int) $r['id'];
            $base    = strtotime($r['purchased_at']);
            $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');
            $payload = ['name' => $name, 'email' => $email, 'funnel' => $funnel,
                        'portal_link' => $siteUrl . '/member/login.php'];

            $steps = [
                ['tpl' => 'onboarding_d1', 'ladder' => 'whatsapp,email', 'offset' => 86400],
                ['tpl' => 'onboarding_d3', 'ladder' => 'whatsapp,email', 'offset' => 3 * 86400],
                ['tpl' => 'onboarding_d7', 'ladder' => 'email',          'offset' => 7 * 86400],
            ];
            foreach ($steps as $i => $step) {
                $sendAt = $base + $step['offset'];
                if ($sendAt > time()) {
                    $id = $this->ns->enqueue(
                        'onboarding', $i + 1, 'user', $userId,
                        $email, $phone, $funnel,
                        $step['tpl'], $payload, $step['ladder'],
                        'onboarding_' . ($i + 1) . '_' . md5($email),
                        date('Y-m-d H:i:s', $sendAt)
                    );
                    if ($id) $n++;
                }
            }
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // R1 — daily_nudge (replaces mock in daily_nudge.php)
    // Cron fires daily at 07:00; this method schedules for today if not yet queued.
    // -----------------------------------------------------------------------
    private function runDailyNudge(): int
    {
        $n = 0;
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd   = $today . ' 23:59:59';

        $users = $this->db->fetchAll(
            "SELECT u.id, u.email, u.first_name, u.phone,
                    COALESCE(s.product_type, 'pcos') AS funnel
             FROM users u
             LEFT JOIN sales s ON s.email = u.email AND s.payment_status = 'completed'
             WHERE u.status = 'active'
             GROUP BY u.email"
        ) ?: [];

        foreach ($users as $u) {
            $email  = (string) $u['email'];
            $userId = (int) $u['id'];
            $funnel = (string) ($u['funnel'] ?? 'pcos');
            $phone  = (string) ($u['phone'] ?? '');
            $name   = $this->firstName($u['first_name'] ?? '');

            $dedupe = 'daily_nudge_1_' . $today . '_' . md5($email);

            $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');
            $id = $this->ns->enqueue(
                'daily_nudge', 1, 'user', $userId, $email, $phone, $funnel,
                'daily_nudge_1',
                ['name' => $name, 'email' => $email, 'funnel' => $funnel,
                 'focus_tip' => 'Stay consistent with your protocol today.',
                 'meal_headline' => 'Your personalised meals are ready.',
                 'day_number' => $this->userDay($email),
                 'portal_link' => $siteUrl . '/member/login.php'],
                'whatsapp,email',
                $dedupe,
                $today . ' 07:00:00'
            );
            if ($id) $n++;
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // R3 — streak_save
    // Fire when user has streak ≥ 3 and hasn't logged today by 19:00 user time.
    // -----------------------------------------------------------------------
    private function runStreakSave(): int
    {
        $n = 0;
        $today = date('Y-m-d');
        $hour  = (int) date('H');
        if ($hour < 19) return 0; // not yet streak-save time

        $users = $this->db->fetchAll(
            "SELECT u.id, u.email, u.first_name, u.phone,
                    COALESCE(us.current_streak, 0) AS streak_days,
                    COALESCE(us.last_activity_date, '') AS last_activity,
                    COALESCE(s.product_type, 'pcos') AS funnel
             FROM users u
             LEFT JOIN user_streaks us ON us.user_id = u.id
             LEFT JOIN sales s ON s.email = u.email AND s.payment_status = 'completed'
             WHERE u.status = 'active'
               AND COALESCE(us.current_streak, 0) >= 3
               AND (us.last_activity_date IS NULL OR us.last_activity_date < ?)
             GROUP BY u.email",
            [$today]
        ) ?: [];

        foreach ($users as $u) {
            $email  = (string) $u['email'];
            $userId = (int) $u['id'];
            $funnel = (string) ($u['funnel'] ?? 'pcos');
            $phone  = (string) ($u['phone'] ?? '');
            $name   = $this->firstName($u['first_name'] ?? '');
            $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');

            $id = $this->ns->enqueue(
                'streak_save', 1, 'user', $userId, $email, $phone, $funnel,
                'streak_save_1',
                ['name' => $name, 'email' => $email, 'streak_days' => $u['streak_days'],
                 'portal_link' => $siteUrl . '/member/login.php'],
                'whatsapp,email',
                'streak_save_1_' . $today . '_' . md5($email),
                date('Y-m-d 19:00:00')
            );
            if ($id) $n++;
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // R4 — weekly_summary (Sunday 18:00)
    // -----------------------------------------------------------------------
    private function runWeeklySummary(): int
    {
        if (date('N') !== '7') return 0; // Sunday only

        $n = 0;
        $week = date('Y-W');
        $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');

        $users = $this->db->fetchAll(
            "SELECT u.id, u.email, u.first_name, u.phone,
                    COALESCE(us.current_streak, 0) AS streak_days,
                    COALESCE(s.product_type, 'pcos') AS funnel
             FROM users u
             LEFT JOIN user_streaks us ON us.user_id = u.id
             LEFT JOIN sales s ON s.email = u.email AND s.payment_status = 'completed'
             WHERE u.status = 'active'
             GROUP BY u.email"
        ) ?: [];

        foreach ($users as $u) {
            $email  = (string) $u['email'];
            $userId = (int) $u['id'];
            $funnel = (string) ($u['funnel'] ?? 'pcos');

            $this->ns->enqueue(
                'weekly_summary', 1, 'user', $userId, $email, (string) ($u['phone'] ?? ''), $funnel,
                'weekly_summary_1',
                ['name' => $this->firstName($u['first_name'] ?? ''), 'email' => $email,
                 'streak_days' => $u['streak_days'], 'days_logged' => 0,
                 'week_highlight' => 'Great progress this week!',
                 'portal_link' => $siteUrl . '/member/login.php'],
                'email',
                'weekly_summary_1_' . $week . '_' . md5($email),
                date('Y-m-d 18:00:00')
            );
            $n++;
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // R7 — winback (D14 + D30)
    // -----------------------------------------------------------------------
    private function runWinback(): int
    {
        $n = 0;
        $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');

        foreach ([14, 30] as $days) {
            $since = date('Y-m-d H:i:s', time() - ($days + 1) * 86400);
            $until = date('Y-m-d H:i:s', time() - $days * 86400);

            $users = $this->db->fetchAll(
                "SELECT u.id, u.email, u.first_name, u.phone,
                        COALESCE(s.product_type, 'pcos') AS funnel
                 FROM users u
                 LEFT JOIN sales s ON s.email = u.email AND s.payment_status = 'completed'
                 WHERE u.status = 'active'
                   AND (u.last_login_at IS NULL OR (u.last_login_at >= ? AND u.last_login_at < ?))
                 GROUP BY u.email",
                [$since, $until]
            ) ?: [];

            $step = ($days === 14) ? 1 : 2;
            $tpl  = ($days === 14) ? 'winback_1' : 'winback_2';

            foreach ($users as $u) {
                $email  = (string) $u['email'];
                $userId = (int) $u['id'];
                $funnel = (string) ($u['funnel'] ?? 'pcos');
                $id = $this->ns->enqueue(
                    'winback', $step, 'user', $userId, $email, (string) ($u['phone'] ?? ''), $funnel,
                    $tpl,
                    ['name' => $this->firstName($u['first_name'] ?? ''), 'email' => $email,
                     'funnel' => $funnel, 'portal_link' => $siteUrl . '/member/login.php'],
                    'email',
                    'winback_' . $step . '_' . md5($email) . '_' . date('Y-W'),
                    date('Y-m-d H:i:s')
                );
                if ($id) $n++;
            }
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // R6 — renewal_refill (D-14, D-3)
    // -----------------------------------------------------------------------
    private function runRenewalRefill(): int
    {
        $n = 0;
        $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');

        foreach ([14, 3] as $days) {
            $targetDate = date('Y-m-d', time() + $days * 86400);
            $step = ($days === 14) ? 1 : 2;

            $users = $this->db->fetchAll(
                "SELECT u.id, u.email, u.first_name, u.phone, s.product_type AS funnel,
                        s.plan_duration, s.created_at AS purchased_at
                 FROM users u
                 JOIN sales s ON s.email = u.email AND s.payment_status = 'completed'
                 WHERE u.status = 'active'
                   AND DATE(DATE(s.created_at, '+' || COALESCE(s.plan_duration, 90) || ' days')) = ?
                 GROUP BY u.email",
                [$targetDate]
            ) ?: [];

            foreach ($users as $u) {
                $email  = (string) $u['email'];
                $userId = (int) $u['id'];
                $funnel = (string) ($u['funnel'] ?? 'pcos');
                $id = $this->ns->enqueue(
                    'renewal_refill', $step, 'user', $userId, $email, (string) ($u['phone'] ?? ''), $funnel,
                    'renewal_1',
                    ['name' => $this->firstName($u['first_name'] ?? ''), 'email' => $email,
                     'funnel' => $funnel, 'plan_link' => $siteUrl . '/' . $funnel . '/'],
                    'email,whatsapp',
                    'renewal_' . $step . '_' . md5($email) . '_' . $targetDate,
                    date('Y-m-d H:i:s')
                );
                if ($id) $n++;
            }
        }
        return $n;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function firstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?: 'there';
    }

    private function userDay(string $email): int
    {
        try {
            $row = $this->db->fetch(
                "SELECT created_at FROM sales WHERE email = ? AND payment_status = 'completed' ORDER BY created_at LIMIT 1",
                [$email]
            );
            if (!$row) return 1;
            return max(1, (int) ceil((time() - strtotime($row['created_at'])) / 86400));
        } catch (Exception $e) {
            return 1;
        }
    }
}
