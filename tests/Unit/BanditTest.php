<?php

namespace Wellness\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/backend/classes/Bandit.php';

class BanditTest extends TestCase
{
    private \Bandit $bandit;

    protected function setUp(): void
    {
        $this->bandit = new \Bandit();
    }

    public function test_burn_in_returns_random_variant(): void
    {
        $experiment = ['status' => 'burn_in', 'min_exposure_floor' => 0.1, 'reward_type' => 'conversion'];
        $variants = [
            ['id' => 1, 'exposures' => 0, 'alpha' => 1.0, 'beta' => 1.0],
            ['id' => 2, 'exposures' => 0, 'alpha' => 1.0, 'beta' => 1.0],
        ];

        $result = $this->bandit->assign($experiment, $variants);

        $this->assertContains($result['id'], [1, 2]);
    }

    public function test_single_variant_always_returned(): void
    {
        $experiment = ['status' => 'running', 'min_exposure_floor' => 0.1, 'reward_type' => 'conversion'];
        $variants = [['id' => 42, 'exposures' => 100, 'alpha' => 10.0, 'beta' => 2.0]];

        $result = $this->bandit->assign($experiment, $variants);

        $this->assertSame(42, $result['id']);
    }

    public function test_starved_variant_gets_forced(): void
    {
        $experiment = ['status' => 'running', 'min_exposure_floor' => 0.1, 'reward_type' => 'conversion'];
        // Variant 2 has 0 exposures vs 1000 — is starved
        $variants = [
            ['id' => 1, 'exposures' => 1000, 'alpha' => 50.0, 'beta' => 5.0],
            ['id' => 2, 'exposures' => 0,    'alpha' => 1.0,  'beta' => 1.0],
        ];

        // Run many times — starved variant must win at least once
        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $ids[] = $this->bandit->assign($experiment, $variants)['id'];
        }

        $this->assertContains(2, $ids, 'Starved variant should be force-served');
    }

    public function test_decision_stats_probabilities_sum_to_one(): void
    {
        $experiment = ['status' => 'running', 'reward_type' => 'conversion'];
        $variants = [
            ['id' => 1, 'exposures' => 500, 'alpha' => 30.0, 'beta' => 20.0],
            ['id' => 2, 'exposures' => 500, 'alpha' => 25.0, 'beta' => 25.0],
        ];

        $stats = $this->bandit->decisionStats($experiment, $variants, 5000);

        $pBestSum = array_sum(array_column($stats, 'p_best'));
        $this->assertEqualsWithDelta(1.0, $pBestSum, 0.01, 'p_best values must sum to ~1.0');
    }

    public function test_winning_variant_has_higher_p_best(): void
    {
        $experiment = ['status' => 'running', 'reward_type' => 'conversion'];
        $variants = [
            ['id' => 1, 'exposures' => 1000, 'alpha' => 200.0, 'beta' => 800.0], // 20% CVR
            ['id' => 2, 'exposures' => 1000, 'alpha' => 400.0, 'beta' => 600.0], // 40% CVR — clear winner
        ];

        $stats = $this->bandit->decisionStats($experiment, $variants, 10000);

        $this->assertGreaterThan(0.99, $stats[2]['p_best'], 'Clear winner should have p_best near 1');
        $this->assertLessThan(0.01, $stats[1]['p_best'], 'Clear loser should have p_best near 0');
    }
}
