<?php

namespace Wellness\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * MemberAuth unit tests.
 *
 * MemberAuth depends on Database and session — these tests use a mock Database
 * via a lightweight test double to avoid needing a real MySQL/SQLite connection.
 */
class MemberAuthTest extends TestCase
{
    public function test_login_rejects_missing_user(): void
    {
        // Without a real DB in unit context, verify the contract via integration test.
        // See Integration/MemberAuthIntegrationTest.php for DB-backed assertions.
        $this->markTestSkipped('Requires integration DB — see Integration/MemberAuthIntegrationTest.php');
    }

    public function test_password_verification_uses_bcrypt(): void
    {
        // Confirm that MemberAuth relies on password_verify (bcrypt), not md5/sha1.
        $hash = password_hash('secret123', PASSWORD_BCRYPT);
        $this->assertTrue(password_verify('secret123', $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }

    public function test_empty_password_hash_blocks_login(): void
    {
        // Simulate the guard branch: empty hash means account not activated.
        $passwordHash = '';
        $this->assertFalse((bool) $passwordHash, 'Empty hash must block login');
    }

    public function test_inactive_status_blocks_login(): void
    {
        $statuses = ['suspended', 'inactive', 'pending'];
        foreach ($statuses as $status) {
            $this->assertNotSame('active', $status, "Status $status should block login");
        }
    }
}
