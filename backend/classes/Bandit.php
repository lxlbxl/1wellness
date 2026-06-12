<?php
/**
 * Thompson Sampling multi-armed bandit for the A/B engine.
 *
 * - Binary reward: Beta(alpha, beta) posterior on conversion rate.
 * - Revenue reward: Beta posterior on conversion x empirical mean order
 *   value (shrinkage prior) = revenue-per-visitor draw.
 *
 * Also provides Monte Carlo decision statistics: P(best) and expected
 * loss per variant, used by the conclusion rule.
 */

class Bandit
{
    /** Default prior AOV (USD) used before a variant has any conversions. */
    const PRIOR_AOV = 100.0;

    /**
     * Pick a variant for an experiment.
     *
     * @param array $experiment row from `experiments`
     * @param array $variants   serveable rows from `variants` (status active/winner)
     * @return array the chosen variant row
     */
    public function assign(array $experiment, array $variants): array
    {
        if (count($variants) === 1) {
            return reset($variants);
        }

        // 1. Burn-in: equal random split for the first N hours
        if ($experiment['status'] === 'burn_in') {
            return $variants[array_rand($variants)];
        }

        // 2. Exposure floor: if any variant is below its floor share, force-serve it
        $totalExposures = max(1, array_sum(array_column($variants, 'exposures')));
        $floor = (float) ($experiment['min_exposure_floor'] ?? 0.1);
        foreach ($variants as $v) {
            if (((int) $v['exposures']) / $totalExposures < $floor) {
                return $v;
            }
        }

        // 3. Thompson Sampling: sample each posterior, serve the max
        $best = null;
        $bestDraw = -1.0;
        foreach ($variants as $v) {
            $draw = ($experiment['reward_type'] === 'revenue')
                ? $this->sampleRevenuePosterior($v)
                : $this->sampleBeta((float) $v['alpha'], (float) $v['beta']);
            if ($draw > $bestDraw) {
                $bestDraw = $draw;
                $best = $v;
            }
        }
        return $best;
    }

    /**
     * Monte Carlo decision statistics.
     *
     * Draws $n samples from each variant's posterior and returns, per
     * variant id: p_best (win share) and expected_loss (mean regret vs
     * the per-draw best), plus the posterior mean.
     *
     * @return array variant_id => ['p_best' => float, 'expected_loss' => float, 'mean' => float]
     */
    public function decisionStats(array $experiment, array $variants, int $n = 10000): array
    {
        $ids = [];
        $draws = [];
        foreach ($variants as $v) {
            $ids[] = $v['id'];
            $draws[$v['id']] = [];
        }
        if (count($ids) === 0) {
            return [];
        }

        $wins = array_fill_keys($ids, 0);
        $loss = array_fill_keys($ids, 0.0);
        $sum  = array_fill_keys($ids, 0.0);

        for ($i = 0; $i < $n; $i++) {
            $roundBest = -INF;
            $sample = [];
            foreach ($variants as $v) {
                $d = ($experiment['reward_type'] === 'revenue')
                    ? $this->sampleRevenuePosterior($v)
                    : $this->sampleBeta((float) $v['alpha'], (float) $v['beta']);
                $sample[$v['id']] = $d;
                $sum[$v['id']] += $d;
                if ($d > $roundBest) {
                    $roundBest = $d;
                }
            }
            foreach ($sample as $id => $d) {
                if ($d >= $roundBest) {
                    $wins[$id]++;
                }
                $loss[$id] += ($roundBest - $d);
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [
                'p_best' => $wins[$id] / $n,
                'expected_loss' => $loss[$id] / $n,
                'mean' => $sum[$id] / $n,
            ];
        }
        return $out;
    }

    public function sampleBeta(float $a, float $b): float
    {
        $a = max($a, 1e-3);
        $b = max($b, 1e-3);
        $x = $this->sampleGamma($a);
        $y = $this->sampleGamma($b);
        if ($x + $y == 0.0) {
            return 0.5;
        }
        return $x / ($x + $y);
    }

    /** Marsaglia & Tsang gamma sampler (shape only, scale 1). */
    private function sampleGamma(float $shape): float
    {
        if ($shape < 1) {
            return $this->sampleGamma($shape + 1) * pow($this->u(), 1 / $shape);
        }
        $d = $shape - 1 / 3;
        $c = 1 / sqrt(9 * $d);
        while (true) {
            do {
                $x = $this->normal();
                $v = pow(1 + $c * $x, 3);
            } while ($v <= 0);
            $u = $this->u();
            if ($u < 1 - 0.0331 * pow($x, 4)) {
                return $d * $v;
            }
            if (log($u) < 0.5 * $x * $x + $d * (1 - $v + log($v))) {
                return $d * $v;
            }
        }
    }

    /**
     * Revenue-per-visitor posterior draw:
     * RPV = P(convert) x E(order value), with the AOV shrunk towards the
     * catalog prior while conversions are few.
     */
    private function sampleRevenuePosterior(array $v): float
    {
        $pConv = $this->sampleBeta((float) $v['alpha'], (float) $v['beta']);
        $conversions = (int) $v['conversions'];
        if ($conversions > 0) {
            $empirical = ((float) $v['revenue_total']) / $conversions;
            // Shrinkage: weight empirical AOV by sample size (k=5 pseudo-obs prior)
            $k = 5;
            $aov = ($conversions * $empirical + $k * self::PRIOR_AOV) / ($conversions + $k);
        } else {
            $aov = self::PRIOR_AOV;
        }
        return $pConv * $aov;
    }

    /** Box-Muller standard normal. */
    private function normal(): float
    {
        return sqrt(-2 * log($this->u())) * cos(2 * M_PI * $this->u());
    }

    /** Uniform(0,1) exclusive of endpoints. */
    private function u(): float
    {
        return mt_rand(1, mt_getrandmax() - 1) / mt_getrandmax();
    }
}
