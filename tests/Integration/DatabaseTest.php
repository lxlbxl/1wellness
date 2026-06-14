<?php

namespace Wellness\Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/backend/classes/Database.php';

/**
 * Integration tests for Database class.
 * Require a real MySQL/SQLite connection (set via env or bootstrap.php).
 */
class DatabaseTest extends TestCase
{
    private static \Database $db;

    public static function setUpBeforeClass(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2) . '/backend');
        }
        self::$db = \Database::getInstance();
    }

    public function test_connection_established(): void
    {
        $this->assertInstanceOf(\Database::class, self::$db);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $db2 = \Database::getInstance();
        $this->assertSame(self::$db, $db2);
    }

    public function test_insert_and_fetch(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active — skipping SQL integration test');
        }

        // Use funnel_tracking table (created by migration)
        $sessionId = 'test_' . bin2hex(random_bytes(8));
        self::$db->insert('funnel_tracking', [
            'session_id' => $sessionId,
            'funnel_name' => 'pcos',
            'step_name' => 'view',
            'event_type' => 'view',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $row = self::$db->fetch(
            "SELECT * FROM funnel_tracking WHERE session_id = :s LIMIT 1",
            [':s' => $sessionId]
        );

        $this->assertNotNull($row);
        $this->assertSame($sessionId, $row['session_id']);
        $this->assertSame('view', $row['event_type']);

        // Clean up
        self::$db->query("DELETE FROM funnel_tracking WHERE session_id = :s", [':s' => $sessionId]);
    }

    public function test_fetch_returns_null_for_missing_row(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        $row = self::$db->fetch(
            "SELECT * FROM funnel_tracking WHERE session_id = :s LIMIT 1",
            [':s' => 'nonexistent_session_xyz']
        );

        $this->assertNull($row);
    }
}
