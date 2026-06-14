<?php

namespace Wellness\Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/backend/classes/Database.php';
require_once dirname(__DIR__, 2) . '/backend/classes/ExperimentManager.php';

/**
 * Integration tests for the track-event endpoint logic.
 * Tests the DB-layer operations that track-event.php performs,
 * without executing the HTTP handler directly.
 */
class TrackEventTest extends TestCase
{
    private static \Database $db;
    private string $sessionId;

    public static function setUpBeforeClass(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2) . '/backend');
        }
        self::$db = \Database::getInstance();
    }

    protected function setUp(): void
    {
        $this->sessionId = 'it_' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (!self::$db->isFileStorage()) {
            self::$db->query(
                "DELETE FROM funnel_tracking WHERE session_id = :s",
                [':s' => $this->sessionId]
            );
        }
    }

    public function test_event_inserted_with_correct_fields(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        $now = date('Y-m-d H:i:s');
        self::$db->insert('funnel_tracking', [
            'session_id' => $this->sessionId,
            'funnel_name' => 'acne',
            'step_name' => 'assessment_start',
            'event_type' => 'assessment_start',
            'metadata' => null,
            'created_at' => $now,
        ]);

        $row = self::$db->fetch(
            "SELECT * FROM funnel_tracking WHERE session_id = :s AND event_type = 'assessment_start' LIMIT 1",
            [':s' => $this->sessionId]
        );

        $this->assertNotNull($row);
        $this->assertSame('acne', $row['funnel_name']);
        $this->assertSame('assessment_start', $row['event_type']);
    }

    public function test_assessment_progress_depth_dedup(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        $depth = 3;
        $metadata = json_encode(['depth' => $depth]);

        // Insert first event at depth 3
        self::$db->insert('funnel_tracking', [
            'session_id' => $this->sessionId,
            'funnel_name' => 'pcos',
            'step_name' => 'assessment_progress',
            'event_type' => 'assessment_progress',
            'metadata' => $metadata,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Check depth-based dedup query (mirrors track-event.php logic)
        $dupe = self::$db->fetch(
            "SELECT id FROM funnel_tracking
             WHERE session_id = :s AND event_type = 'assessment_progress' AND funnel_name = :f
             AND JSON_EXTRACT(metadata, '$.depth') = :d LIMIT 1",
            [':s' => $this->sessionId, ':f' => 'pcos', ':d' => $depth]
        );

        $this->assertNotNull($dupe, 'Should find the first depth=3 event as a dupe candidate');
    }

    public function test_different_depths_not_deduped(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        foreach ([2, 4] as $depth) {
            self::$db->insert('funnel_tracking', [
                'session_id' => $this->sessionId,
                'funnel_name' => 'weight',
                'step_name' => 'assessment_progress',
                'event_type' => 'assessment_progress',
                'metadata' => json_encode(['depth' => $depth]),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $rows = self::$db->fetchAll(
            "SELECT id FROM funnel_tracking WHERE session_id = :s AND event_type = 'assessment_progress'",
            [':s' => $this->sessionId]
        );

        $this->assertCount(2, $rows, 'Two different depths should produce two separate rows');
    }
}
