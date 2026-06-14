<?php

/**
 * Async job queue.
 *
 * Enqueue slow work (email, AI generation, PDF, WhatsApp) so HTTP handlers
 * return immediately. Worker: backend/cron/worker.php
 *
 * Usage:
 *   JobQueue::dispatch('send_email', ['to' => $email, 'template' => 'welcome']);
 *   JobQueue::dispatch('generate_plan', ['user_id' => 42], priority: 8, runAfter: '+5 minutes');
 */
class JobQueue
{
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_DONE       = 'done';
    const STATUS_FAILED     = 'failed';

    const DEFAULT_MAX_ATTEMPTS = 3;
    const BATCH_SIZE           = 20;
    const PROCESSING_TIMEOUT   = 300; // 5 min — release stuck jobs after this

    // Registered handlers: type => callable
    private static $handlers = [];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    // -----------------------------------------------------------------------
    // Enqueue
    // -----------------------------------------------------------------------

    /**
     * @param string      $type      Job type key (e.g. 'send_email')
     * @param array       $payload   Arbitrary data for the handler
     * @param int         $priority  Higher = processed first (1-10, default 5)
     * @param string|null $runAfter  Strtotime-compatible delay, e.g. '+5 minutes'
     * @param int         $maxAttempts
     */
    public static function dispatch(
        string $type,
        array  $payload      = [],
        int    $priority     = 5,
        ?string $runAfter    = null,
        int    $maxAttempts  = self::DEFAULT_MAX_ATTEMPTS
    ): ?int {
        try {
            $jq = new self();
            return $jq->enqueue($type, $payload, $priority, $runAfter, $maxAttempts);
        } catch (Exception $e) {
            error_log('JobQueue::dispatch: ' . $e->getMessage());
            return null;
        }
    }

    public function enqueue(
        string $type,
        array  $payload,
        int    $priority,
        ?string $runAfter,
        int    $maxAttempts
    ): int {
        $runAfterTs = $runAfter ? date('Y-m-d H:i:s', strtotime($runAfter)) : date('Y-m-d H:i:s');
        return (int) $this->db->insert('jobs', [
            'type'         => $type,
            'payload'      => json_encode($payload),
            'status'       => self::STATUS_PENDING,
            'priority'     => min(10, max(1, $priority)),
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'run_after'    => $runAfterTs,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    // -----------------------------------------------------------------------
    // Handler registration
    // -----------------------------------------------------------------------

    /** Register a handler for a job type. Called by worker bootstrap. */
    public static function register(string $type, callable $handler): void
    {
        self::$handlers[$type] = $handler;
    }

    // -----------------------------------------------------------------------
    // Worker loop (called by cron/worker.php)
    // -----------------------------------------------------------------------

    public function runBatch(): array
    {
        $this->releaseStuck();

        $pdo    = $this->db->getConnection();
        $now    = date('Y-m-d H:i:s');

        // Claim a batch atomically
        $rows = $pdo->query(
            "SELECT * FROM jobs
             WHERE status = 'pending' AND run_after <= '$now'
             ORDER BY priority DESC, run_after ASC
             LIMIT " . self::BATCH_SIZE
        )->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['processed' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($rows as $job) {
            // Mark processing first (prevents double-pickup)
            $claimed = $pdo->exec(
                "UPDATE jobs SET status='processing', started_at='$now', attempts=attempts+1
                 WHERE id={$job['id']} AND status='pending'"
            );
            if (!$claimed) {
                $counts['skipped']++;
                continue;
            }

            try {
                $this->runJob($job);
                $pdo->exec(
                    "UPDATE jobs SET status='done', finished_at='" . date('Y-m-d H:i:s') . "', error=NULL
                     WHERE id={$job['id']}"
                );
                $counts['processed']++;
            } catch (Exception $e) {
                $error     = addslashes(substr($e->getMessage(), 0, 500));
                $attempts  = (int)$job['attempts']; // before increment
                $maxAttempts = (int)$job['max_attempts'];
                if ($attempts >= $maxAttempts - 1) {
                    $pdo->exec(
                        "UPDATE jobs SET status='failed', finished_at='" . date('Y-m-d H:i:s') . "', error='$error'
                         WHERE id={$job['id']}"
                    );
                } else {
                    $backoff = [300, 900, 2700, 7200];
                    $delay   = $backoff[min($attempts, count($backoff) - 1)];
                    $retry   = date('Y-m-d H:i:s', time() + $delay);
                    $pdo->exec(
                        "UPDATE jobs SET status='pending', run_after='$retry', error='$error'
                         WHERE id={$job['id']}"
                    );
                }
                $counts['failed']++;
                error_log("JobQueue job#{$job['id']} type={$job['type']} failed: " . $e->getMessage());
            }
        }

        return $counts;
    }

    private function runJob(array $job): void
    {
        $type    = $job['type'];
        $payload = json_decode($job['payload'] ?? '{}', true) ?: [];

        if (!isset(self::$handlers[$type])) {
            throw new RuntimeException("No handler registered for job type '$type'");
        }

        call_user_func(self::$handlers[$type], $payload, $job);
    }

    private function releaseStuck(): void
    {
        try {
            $cutoff = date('Y-m-d H:i:s', time() - self::PROCESSING_TIMEOUT);
            $this->db->execute(
                "UPDATE jobs SET status='pending', started_at=NULL
                 WHERE status='processing' AND started_at < ?",
                [$cutoff]
            );
        } catch (Exception $e) { /* non-fatal */ }
    }

    // -----------------------------------------------------------------------
    // Stats
    // -----------------------------------------------------------------------

    public function stats(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT status, COUNT(*) AS c FROM jobs GROUP BY status"
            ) ?: [];
            $out = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0];
            foreach ($rows as $r) {
                $out[$r['status']] = (int)$r['c'];
            }
            return $out;
        } catch (Exception $e) {
            return [];
        }
    }

    public function recentFailures(int $limit = 20): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, type, payload, error, attempts, created_at
                 FROM jobs WHERE status='failed' ORDER BY created_at DESC LIMIT ?",
                [(int)$limit]
            ) ?: [];
        } catch (Exception $e) {
            return [];
        }
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
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS jobs (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    type         VARCHAR(80)  NOT NULL,
                    payload      TEXT         NOT NULL DEFAULT '{}',
                    status       VARCHAR(20)  NOT NULL DEFAULT 'pending',
                    priority     TINYINT      NOT NULL DEFAULT 5,
                    attempts     TINYINT      NOT NULL DEFAULT 0,
                    max_attempts TINYINT      NOT NULL DEFAULT 3,
                    run_after    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    started_at   DATETIME,
                    finished_at  DATETIME,
                    error        TEXT,
                    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_jobs_runnable (status, priority DESC, run_after)
                )");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS jobs (
                    id           INTEGER PRIMARY KEY AUTOINCREMENT,
                    type         VARCHAR(80)  NOT NULL,
                    payload      TEXT         NOT NULL DEFAULT '{}',
                    status       VARCHAR(20)  NOT NULL DEFAULT 'pending',
                    priority     SMALLINT     NOT NULL DEFAULT 5,
                    attempts     SMALLINT     NOT NULL DEFAULT 0,
                    max_attempts SMALLINT     NOT NULL DEFAULT 3,
                    run_after    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    started_at   DATETIME,
                    finished_at  DATETIME,
                    error        TEXT,
                    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_jobs_runnable ON jobs(status, priority DESC, run_after)");
            }
        } catch (Exception $e) {
            error_log('JobQueue ensureTable: ' . $e->getMessage());
        }
    }
}
