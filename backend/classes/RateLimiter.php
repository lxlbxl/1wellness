<?php

/**
 * Login-attempt lockout + generic IP rate limiter.
 *
 * login_attempts table is created lazily on first call.
 * Keyed by (identifier, ip) — blocks on EITHER exceeding the threshold.
 */
class RateLimiter
{
    const MAX_ATTEMPTS   = 5;
    const WINDOW_SECONDS = 900;   // 15 minutes
    const IP_RPM         = 60;    // generic API: 60 req/min per IP

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    // -----------------------------------------------------------------------
    // Login-attempt tracking
    // -----------------------------------------------------------------------

    /** Returns true if the identifier or IP is locked out. */
    public function isLockedOut(string $identifier, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        try {
            $byId = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM login_attempts
                 WHERE identifier = ? AND attempted_at >= ?",
                [$identifier, $since]
            )['c'] ?? 0);
            if ($byId >= self::MAX_ATTEMPTS) return true;

            $byIp = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM login_attempts
                 WHERE ip = ? AND attempted_at >= ?",
                [$ip, $since]
            )['c'] ?? 0);
            return $byIp >= self::MAX_ATTEMPTS;
        } catch (Exception $e) {
            return false; // fail open on DB error rather than locking everyone out
        }
    }

    /** Record a failed login attempt. */
    public function recordFailure(string $identifier, string $ip): void
    {
        try {
            $this->db->insert('login_attempts', [
                'identifier'   => $identifier,
                'ip'           => $ip,
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('RateLimiter recordFailure: ' . $e->getMessage());
        }
    }

    /** Clear attempts on successful login. */
    public function clearAttempts(string $identifier): void
    {
        try {
            $this->db->execute(
                "DELETE FROM login_attempts WHERE identifier = ?",
                [$identifier]
            );
        } catch (Exception $e) { /* non-fatal */ }
    }

    /** Remaining attempts before lockout (for UX). */
    public function remainingAttempts(string $identifier, string $ip): int
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        try {
            $byId = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM login_attempts WHERE identifier = ? AND attempted_at >= ?",
                [$identifier, $since]
            )['c'] ?? 0);
            $byIp = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND attempted_at >= ?",
                [$ip, $since]
            )['c'] ?? 0);
            return max(0, self::MAX_ATTEMPTS - max($byId, $byIp));
        } catch (Exception $e) {
            return self::MAX_ATTEMPTS;
        }
    }

    // -----------------------------------------------------------------------
    // Generic IP rate limiter (token bucket per IP, keyed in login_attempts)
    // -----------------------------------------------------------------------

    /**
     * Returns true if the IP has exceeded IP_RPM requests in the last minute.
     * Uses identifier='__ip_rpm__' to separate from login attempts.
     */
    public function isIpThrottled(string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - 60);
        try {
            $count = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM login_attempts
                 WHERE ip = ? AND identifier = '__ip_rpm__' AND attempted_at >= ?",
                [$ip, $since]
            )['c'] ?? 0);
            return $count >= self::IP_RPM;
        } catch (Exception $e) {
            return false;
        }
    }

    public function recordIpHit(string $ip): void
    {
        try {
            $this->db->insert('login_attempts', [
                'identifier'   => '__ip_rpm__',
                'ip'           => $ip,
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) { /* non-fatal */ }
    }

    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    private function ensureTable(): void
    {
        if ($this->db->isFileStorage()) return;
        try {
            $pdo    = $this->db->getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = ($driver === 'mysql')
                ? "CREATE TABLE IF NOT EXISTS login_attempts (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       identifier VARCHAR(255) NOT NULL,
                       ip VARCHAR(45) NOT NULL,
                       attempted_at DATETIME NOT NULL,
                       INDEX idx_la_id_time (identifier, attempted_at),
                       INDEX idx_la_ip_time (ip, attempted_at)
                   )"
                : "CREATE TABLE IF NOT EXISTS login_attempts (
                       id INTEGER PRIMARY KEY AUTOINCREMENT,
                       identifier VARCHAR(255) NOT NULL,
                       ip VARCHAR(45) NOT NULL,
                       attempted_at DATETIME NOT NULL
                   )";
            $pdo->exec($sql);
            if ($driver === 'sqlite') {
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_la_id_time ON login_attempts(identifier, attempted_at)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_la_ip_time ON login_attempts(ip, attempted_at)");
            }
        } catch (Exception $e) {
            error_log('RateLimiter ensureTable: ' . $e->getMessage());
        }
    }
}
