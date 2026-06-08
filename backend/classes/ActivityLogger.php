<?php

class ActivityLogger
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Log a user activity
     *
     * @param int|null $userId User ID (nullable if not logged in yet, e.g. failed login)
     * @param string $action The action name (e.g. 'login', 'purchase', 'profile_update')
     * @param array $details Additional details as an associative array
     */
    public function log($userId, $action, $details = [])
    {
        try {
            // Get IP and User Agent safely
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            $this->db->insert('app_activity_logs', [
                'user_id' => $userId,
                'action' => $action,
                'details' => json_encode($details),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Fail silently - logging shouldn't break the app
            error_log("Activity Logging Failed: " . $e->getMessage());
        }
    }
}
