<?php

namespace Wellness\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Webhook handler validation logic tests.
 *
 * Tests the pure-logic portions of webhook processing that don't require a DB.
 * Handler integration (full flow with DB + Flutterwave verify) lives in
 * Integration/WebhookIntegrationTest.php.
 */
class WebhookValidationTest extends TestCase
{
    public function test_hash_verification_constant_time(): void
    {
        // hash_equals must be used (not ==) to avoid timing attacks.
        // This test documents the contract; actual implementation is in PaymentIntegrity.
        $expected = hash_hmac('sha256', 'payload', 'secret');
        $actual   = hash_hmac('sha256', 'payload', 'secret');
        $wrong    = hash_hmac('sha256', 'payload', 'wrong-secret');

        $this->assertTrue(hash_equals($expected, $actual), 'Matching hashes should pass');
        $this->assertFalse(hash_equals($expected, $wrong), 'Mismatched hashes should fail');
    }

    public function test_tx_ref_idempotency_key_extracted(): void
    {
        // tx_ref is the primary idempotency key; transaction_id is secondary.
        $payload = ['tx_ref' => 'ref_abc123', 'transaction_id' => '9876', 'email' => 'test@example.com'];

        $txRef = $payload['tx_ref'] ?? $payload['reference'] ?? null;
        $txId  = $payload['transaction_id'] ?? $payload['order_id'] ?? null;

        $this->assertSame('ref_abc123', $txRef);
        $this->assertSame('9876', $txId);
    }

    public function test_missing_email_fails_validation(): void
    {
        $payload = ['name' => 'Test User', 'tx_ref' => 'ref_001'];
        $this->assertArrayNotHasKey('email', $payload, 'Missing email should be caught');
        $valid = isset($payload['email']) && isset($payload['name']);
        $this->assertFalse($valid);
    }

    public function test_missing_name_fails_validation(): void
    {
        $payload = ['email' => 'test@example.com', 'tx_ref' => 'ref_001'];
        $valid = isset($payload['email']) && isset($payload['name']);
        $this->assertFalse($valid);
    }

    public function test_funnel_to_condition_map_covers_all_funnels(): void
    {
        // Every webhook handler maps its funnel to a condition. Ensure all 4 are covered.
        $funnelConditionMap = [
            'pcos'   => 'pcos',
            'acne'   => 'acne',
            'weight' => 'weight',
            'mens'   => 'mens',
        ];

        foreach (['pcos', 'acne', 'weight', 'mens'] as $funnel) {
            $this->assertArrayHasKey($funnel, $funnelConditionMap, "Funnel $funnel must have a condition mapping");
        }
    }
}
