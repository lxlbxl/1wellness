<?php

namespace Wellness\Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/backend/classes/Database.php';
require_once dirname(__DIR__, 2) . '/backend/classes/RateLimiter.php';
require_once dirname(__DIR__, 2) . '/backend/classes/MemberAuth.php';

/**
 * Integration tests for MemberAuth against a real database.
 * Seeds a test user, exercises the login flow, then cleans up.
 */
class MemberAuthIntegrationTest extends TestCase
{
    private static \Database $db;
    private static string $testEmail = 'integration_test_user@1wellness.test';
    private static int $testUserId;

    public static function setUpBeforeClass(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2) . '/backend');
        }
        self::$db = \Database::getInstance();

        if (self::$db->isFileStorage()) {
            return;
        }

        // Seed a test user
        self::$db->query("DELETE FROM users WHERE email = :e", [':e' => self::$testEmail]);
        self::$db->insert('users', [
            'email'         => self::$testEmail,
            'username'      => 'int_test_user',
            'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
            'first_name'    => 'Integration',
            'status'        => 'active',
            'type'          => 'user',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $row = self::$db->fetch("SELECT id FROM users WHERE email = :e LIMIT 1", [':e' => self::$testEmail]);
        self::$testUserId = (int) ($row['id'] ?? 0);
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$db->isFileStorage() && self::$testUserId) {
            self::$db->query("DELETE FROM users WHERE id = :id", [':id' => self::$testUserId]);
        }
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        $auth = new \MemberAuth();
        $result = $auth->login(self::$testEmail, 'TestPass123!');

        $this->assertTrue($result['success']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        $auth = new \MemberAuth();
        $result = $auth->login(self::$testEmail, 'WrongPassword!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        $auth = new \MemberAuth();
        $result = $auth->login('nobody@nowhere.test', 'anything');

        $this->assertFalse($result['success']);
    }

    public function test_inactive_user_blocked(): void
    {
        if (self::$db->isFileStorage()) {
            $this->markTestSkipped('File storage active');
        }

        self::$db->update('users', ['status' => 'suspended'], 'id = :id', [':id' => self::$testUserId]);

        $auth = new \MemberAuth();
        $result = $auth->login(self::$testEmail, 'TestPass123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('suspend', $result['message']);

        // Restore
        self::$db->update('users', ['status' => 'active'], 'id = :id', [':id' => self::$testUserId]);
    }
}
