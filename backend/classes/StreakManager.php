<?php

/**
 * Streak + milestone engine.
 *
 * Streak definition: member logged at least one tracker metric (user_tracking)
 * OR completed at least one activity (activity_logs) on a calendar day.
 *
 * Called by:
 *   - log-tracker.php / member_actions.php (mark_activity_done) to keep streak live
 *   - JourneyEngine::runStreakSave() to fire the 19:00 nudge
 *   - Admin or cron to recalculate if needed
 */
class StreakManager
{
    private $db;

    // Milestone definitions: [key, label, days_threshold OR special]
    const MILESTONES = [
        ['key' => 'streak_3',          'label' => '3-Day Streak',         'streak' => 3],
        ['key' => 'streak_7',          'label' => '7-Day Streak',         'streak' => 7],
        ['key' => 'streak_14',         'label' => '14-Day Streak',        'streak' => 14],
        ['key' => 'streak_30',         'label' => '30-Day Streak',        'streak' => 30],
        ['key' => 'streak_60',         'label' => '60-Day Streak',        'streak' => 60],
        ['key' => 'streak_90',         'label' => '90-Day Streak',        'streak' => 90],
        ['key' => 'first_log',         'label' => 'First Daily Log',      'streak' => 1],
        ['key' => 'onboarding_complete','label' => 'Onboarding Complete', 'streak' => 0],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Record activity for today, recalculate streak, award milestones.
     * Returns updated streak count.
     */
    public function recordActivity(int $userId): int
    {
        $today = date('Y-m-d');

        // Update last_active_date
        try {
            $this->db->execute(
                "UPDATE users SET last_active_date = ? WHERE id = ?",
                [$today, $userId]
            );
        } catch (Exception $e) { /* column may not be migrated yet */ }

        $streak = $this->recalculateStreak($userId);
        $this->awardStreakMilestones($userId, $streak);
        return $streak;
    }

    /**
     * Recalculate current streak from activity and tracker logs.
     * Updates streak_count on users table.
     */
    public function recalculateStreak(int $userId): int
    {
        $activeDays = $this->getActiveDays($userId, 120);
        $streak     = 0;
        $check      = date('Y-m-d');

        while (in_array($check, $activeDays, true)) {
            $streak++;
            $check = date('Y-m-d', strtotime($check . ' -1 day'));
        }

        try {
            $this->db->execute(
                "UPDATE users SET streak_count = ? WHERE id = ?",
                [$streak, $userId]
            );
        } catch (Exception $e) { /* non-fatal */ }

        return $streak;
    }

    /** Get current streak from DB (fast path). */
    public function getStreak(int $userId): int
    {
        try {
            $row = $this->db->fetch("SELECT streak_count FROM users WHERE id = ?", [$userId]);
            return (int)($row['streak_count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /** Award a specific milestone by key. Returns true if newly awarded. */
    public function awardMilestone(int $userId, string $key, array $meta = []): bool
    {
        try {
            $this->db->insert('member_milestones', [
                'user_id'   => $userId,
                'milestone' => $key,
                'earned_at' => date('Y-m-d H:i:s'),
                'meta'      => $meta ? json_encode($meta) : null,
            ]);
            return true;
        } catch (Exception $e) {
            return false; // UNIQUE constraint = already awarded
        }
    }

    /** List milestones earned by a user. */
    public function getMilestones(int $userId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT milestone, earned_at, meta FROM member_milestones
                 WHERE user_id = ? ORDER BY earned_at DESC",
                [$userId]
            ) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function awardStreakMilestones(int $userId, int $streak): void
    {
        foreach (self::MILESTONES as $m) {
            if ($m['streak'] > 0 && $streak >= $m['streak']) {
                $this->awardMilestone($userId, $m['key'], ['streak' => $streak]);
            }
        }
    }

    private function getActiveDays(int $userId, int $lookback): array
    {
        $since = date('Y-m-d', strtotime("-{$lookback} days"));
        $days  = [];

        // From tracker logs
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT DATE(logged_at) AS d FROM user_tracking
                 WHERE user_id = ? AND DATE(logged_at) >= ?",
                [$userId, $since]
            ) ?: [];
            foreach ($rows as $r) { $days[] = $r['d']; }
        } catch (Exception $e) { /* table may not exist */ }

        // From activity logs
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT plan_date AS d FROM activity_logs
                 WHERE user_id = ? AND status = 'completed' AND plan_date >= ?",
                [$userId, $since]
            ) ?: [];
            foreach ($rows as $r) { $days[] = $r['d']; }
        } catch (Exception $e) { /* table may not exist */ }

        return array_unique($days);
    }
}
