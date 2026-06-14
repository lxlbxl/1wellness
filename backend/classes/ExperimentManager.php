<?php
/**
 * ExperimentManager — lifecycle, assignment, posteriors & stats for the
 * A/B testing engine.
 *
 * Lifecycle:  draft -> burn_in -> active -> concluded -> archived
 *                          (paused can interleave burn_in/active)
 *
 * Assignment is sticky and server-side (assignments table + 1w_exp
 * cookie written by backend/router.php). Traffic allocation is Thompson
 * Sampling via Bandit.php with a burn-in equal split and a per-variant
 * exposure floor.
 */

require_once __DIR__ . '/ABSchema.php';
require_once __DIR__ . '/Bandit.php';

class ExperimentManager
{
    private $db;
    private $bandit;

    /** Funnel stages an experiment can target. */
    const STAGES = ['landing', 'assessment', 'results', 'pricing', 'checkout'];

    /** Fixed event taxonomy (do not improvise). */
    const EVENTS = ['view', 'assessment_start', 'assessment_complete', 'results_view', 'plan_select', 'checkout_init', 'purchase'];

    /** primary_metric => [reward event, reward type] */
    const METRICS = [
        'assessment_start'    => ['event' => 'assessment_start',    'reward' => 'binary'],
        'assessment_complete' => ['event' => 'assessment_complete', 'reward' => 'binary'],
        'results_view'        => ['event' => 'results_view',        'reward' => 'binary'],
        'plan_select'         => ['event' => 'plan_select',         'reward' => 'binary'],
        'checkout_init'       => ['event' => 'checkout_init',       'reward' => 'binary'],
        'purchase'            => ['event' => 'purchase',            'reward' => 'binary'],
        'purchase_rpv'        => ['event' => 'purchase',            'reward' => 'revenue'],
    ];

    const FUNNELS = ['pcos', 'acne', 'weight', 'mens'];

    public function __construct()
    {
        $this->db = Database::getInstance();
        ABSchema::ensure();
        $this->bandit = new Bandit();
    }

    private function webhooks()
    {
        require_once __DIR__ . '/WebhookDispatcher.php';
        return new WebhookDispatcher();
    }

    // ==================================================================
    // CRUD
    // ==================================================================

    /**
     * Create an experiment with its variants.
     *
     * @param array $data  funnel_name, name, stage, primary_metric, hypothesis?,
     *                     burn_in_hours?, min_exposure_floor?, min_samples_per_variant?,
     *                     decision_p_best?, decision_expected_loss?
     * @param array $variants list of ['name','type','directory'?,'overrides'?]
     *                        (a 'control' variant is required)
     * @return array ['id' => int] or ['error' => string]
     */
    public function createExperiment(array $data, array $variants)
    {
        if (!in_array($data['funnel_name'] ?? '', self::FUNNELS)) {
            return ['error' => 'funnel_name must be one of: ' . implode(', ', self::FUNNELS)];
        }
        if (empty($data['name'])) {
            return ['error' => 'name is required'];
        }
        if (!in_array($data['stage'] ?? '', self::STAGES)) {
            return ['error' => 'stage must be one of: ' . implode(', ', self::STAGES)];
        }
        if (!isset(self::METRICS[$data['primary_metric'] ?? ''])) {
            return ['error' => 'primary_metric must be one of: ' . implode(', ', array_keys(self::METRICS))];
        }
        if (count($variants) < 2) {
            return ['error' => 'At least 2 variants required (control + challenger)'];
        }
        $hasControl = false;
        foreach ($variants as $v) {
            $err = $this->validateVariant($v, $data['funnel_name']);
            if ($err) {
                return ['error' => $err];
            }
            if (($v['type'] ?? '') === 'control') {
                $hasControl = true;
            }
        }
        if (!$hasControl) {
            return ['error' => 'One variant must have type=control'];
        }

        // Concurrency rule: max ONE non-concluded experiment per funnel stage
        $existing = $this->db->fetch(
            "SELECT id, name FROM experiments WHERE funnel_name = :f AND stage = :s
             AND status IN ('draft','burn_in','active','paused')",
            [':f' => $data['funnel_name'], ':s' => $data['stage']]
        );
        if ($existing && empty($data['force'])) {
            return ['error' => "Experiment #{$existing['id']} \"{$existing['name']}\" already runs on the {$data['stage']} stage of this funnel. Conclude it first (or pass force=1)."];
        }

        $metric = self::METRICS[$data['primary_metric']];
        $expId = $this->db->insert('experiments', [
            'funnel_name' => $data['funnel_name'],
            'name' => $data['name'],
            'hypothesis' => $data['hypothesis'] ?? null,
            'stage' => $data['stage'],
            'primary_metric' => $data['primary_metric'],
            'reward_type' => $metric['reward'],
            'status' => 'draft',
            'burn_in_hours' => max(0, (int) ($data['burn_in_hours'] ?? 48)),
            'min_exposure_floor' => min(0.5, max(0, (float) ($data['min_exposure_floor'] ?? 0.10))),
            'min_samples_per_variant' => max(50, (int) ($data['min_samples_per_variant'] ?? 1000)),
            'decision_p_best' => min(0.999, max(0.5, (float) ($data['decision_p_best'] ?? 0.95))),
            'decision_expected_loss' => max(0, (float) ($data['decision_expected_loss'] ?? 0.005)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($variants as $v) {
            $this->insertVariant($expId, $v);
        }

        return ['id' => (int) $expId];
    }

    public function validateVariant(array $v, $funnel)
    {
        if (empty($v['name'])) {
            return 'Variant name is required';
        }
        if (!in_array($v['type'] ?? '', ['control', 'structural', 'element'])) {
            return 'Variant type must be control, structural or element';
        }
        if ($v['type'] === 'structural') {
            if (empty($v['directory']) || strpos($v['directory'], $funnel . '__') !== 0) {
                return "Structural variant directory must be named {$funnel}__<slug>";
            }
            // Repo root resolved from this file's location (APP_ROOT varies by entry point)
            $path = dirname(__DIR__, 2) . '/' . $v['directory'];
            if (!is_dir($path) || !file_exists($path . '/index.html')) {
                return "Structural directory {$v['directory']}/index.html not found";
            }
        }
        if ($v['type'] === 'element' && isset($v['overrides'])) {
            $ov = is_string($v['overrides']) ? json_decode($v['overrides'], true) : $v['overrides'];
            if (!is_array($ov)) {
                return 'Variant overrides must be valid JSON';
            }
            $allowed = ['text', 'html', 'attr', 'style', 'config'];
            foreach (array_keys($ov) as $k) {
                if (!in_array($k, $allowed)) {
                    return "Unknown override key '$k' (allowed: " . implode(', ', $allowed) . ')';
                }
            }
        }
        return null;
    }

    public function insertVariant($experimentId, array $v, $source = 'human', $status = 'active')
    {
        $overrides = $v['overrides'] ?? null;
        if (is_array($overrides)) {
            $overrides = json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $this->db->insert('variants', [
            'experiment_id' => $experimentId,
            'name' => $v['name'],
            'type' => $v['type'],
            'directory' => $v['directory'] ?? null,
            'overrides' => $overrides,
            'alpha' => 1.0,
            'beta' => 1.0,
            'status' => $v['status'] ?? $status,
            'source' => $v['source'] ?? $source,
            'ai_rationale' => $v['ai_rationale'] ?? null,
            'compliance_status' => $v['compliance_status'] ?? 'unchecked',
            'compliance_notes' => $v['compliance_notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getExperiment($id, $withVariants = true)
    {
        $exp = $this->db->fetch("SELECT * FROM experiments WHERE id = :id", [':id' => $id]);
        if ($exp && $withVariants) {
            $exp['variants'] = $this->getVariants($id);
        }
        return $exp ?: null;
    }

    public function getVariants($experimentId)
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM variants WHERE experiment_id = :id ORDER BY (type='control') DESC, id ASC",
            [':id' => $experimentId]
        );
        foreach ($rows as &$r) {
            if (!empty($r['overrides']) && is_string($r['overrides'])) {
                $decoded = json_decode($r['overrides'], true);
                if ($decoded !== null) {
                    $r['overrides'] = $decoded;
                }
            }
        }
        return $rows;
    }

    public function getVariant($variantId)
    {
        $r = $this->db->fetch("SELECT * FROM variants WHERE id = :id", [':id' => $variantId]);
        if ($r && !empty($r['overrides']) && is_string($r['overrides'])) {
            $decoded = json_decode($r['overrides'], true);
            if ($decoded !== null) {
                $r['overrides'] = $decoded;
            }
        }
        return $r ?: null;
    }

    public function listExperiments($filters = [])
    {
        $where = '1=1';
        $params = [];
        if (!empty($filters['funnel'])) {
            $where .= ' AND funnel_name = :f';
            $params[':f'] = $filters['funnel'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND status = :s';
            $params[':s'] = $filters['status'];
        } elseif (empty($filters['include_archived'])) {
            $where .= " AND status != 'archived'";
        }
        $exps = $this->db->fetchAll("SELECT * FROM experiments WHERE $where ORDER BY created_at DESC", $params);
        foreach ($exps as &$e) {
            $e['variants'] = $this->getVariants($e['id']);
        }
        return $exps;
    }

    public function updateExperiment($id, array $data)
    {
        $exp = $this->getExperiment($id, false);
        if (!$exp) {
            return ['error' => 'Experiment not found'];
        }
        $allowed = ['name', 'hypothesis', 'burn_in_hours', 'min_exposure_floor',
            'min_samples_per_variant', 'decision_p_best', 'decision_expected_loss'];
        // Stage/metric/funnel only editable while draft
        if ($exp['status'] === 'draft') {
            $allowed = array_merge($allowed, ['funnel_name', 'stage', 'primary_metric']);
            if (isset($data['primary_metric'])) {
                if (!isset(self::METRICS[$data['primary_metric']])) {
                    return ['error' => 'Invalid primary_metric'];
                }
                $data['reward_type'] = self::METRICS[$data['primary_metric']]['reward'];
                $allowed[] = 'reward_type';
            }
        }
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) {
            return ['error' => 'No editable fields supplied'];
        }
        $this->db->update('experiments', $update, 'id = :id', [':id' => $id]);
        return ['success' => true];
    }

    public function updateVariantOverrides($variantId, $overrides, $name = null)
    {
        $variant = $this->getVariant($variantId);
        if (!$variant) {
            return ['error' => 'Variant not found'];
        }
        $ov = is_string($overrides) ? json_decode($overrides, true) : $overrides;
        if (!is_array($ov)) {
            return ['error' => 'Overrides must be valid JSON'];
        }
        $update = ['overrides' => json_encode($ov, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
        if ($name) {
            $update['name'] = $name;
        }
        $this->db->update('variants', $update, 'id = :id', [':id' => $variantId]);
        return ['success' => true];
    }

    public function deleteExperiment($id)
    {
        $exp = $this->getExperiment($id, false);
        if (!$exp) {
            return ['error' => 'Experiment not found'];
        }
        if (!in_array($exp['status'], ['draft', 'archived'])) {
            return ['error' => 'Only draft or archived experiments can be deleted'];
        }
        $this->db->delete('variants', 'experiment_id = :id', [':id' => $id]);
        $this->db->delete('assignments', 'experiment_id = :id', [':id' => $id]);
        $this->db->delete('experiments', 'id = :id', [':id' => $id]);
        return ['success' => true];
    }

    // ==================================================================
    // Lifecycle transitions
    // ==================================================================

    public function startExperiment($id)
    {
        $exp = $this->getExperiment($id);
        if (!$exp) {
            return ['error' => 'Experiment not found'];
        }
        if (!in_array($exp['status'], ['draft', 'paused'])) {
            return ['error' => "Cannot start from status '{$exp['status']}'"];
        }
        $serveable = array_filter($exp['variants'], function ($v) {
            return in_array($v['status'], ['active', 'winner']);
        });
        if (count($serveable) < 2) {
            return ['error' => 'Need at least 2 active variants to start'];
        }

        $newStatus = ((int) $exp['burn_in_hours'] > 0 && !$exp['started_at']) ? 'burn_in' : 'active';
        $update = ['status' => $newStatus];
        if (!$exp['started_at']) {
            $update['started_at'] = date('Y-m-d H:i:s');
        }
        $this->db->update('experiments', $update, 'id = :id', [':id' => $id]);

        $this->webhooks()->dispatch('experiment.started', [
            'experiment_id' => (int) $id,
            'name' => $exp['name'],
            'funnel' => $exp['funnel_name'],
            'stage' => $exp['stage'],
            'primary_metric' => $exp['primary_metric'],
            'status' => $newStatus,
            'variants' => count($serveable),
        ]);
        return ['success' => true, 'status' => $newStatus];
    }

    public function pauseExperiment($id)
    {
        $exp = $this->getExperiment($id, false);
        if (!$exp || !in_array($exp['status'], ['burn_in', 'active'])) {
            return ['error' => 'Only running experiments can be paused'];
        }
        $this->db->update('experiments', ['status' => 'paused'], 'id = :id', [':id' => $id]);
        return ['success' => true];
    }

    public function archiveExperiment($id)
    {
        $exp = $this->getExperiment($id, false);
        if (!$exp || !in_array($exp['status'], ['concluded', 'draft', 'paused'])) {
            return ['error' => 'Only concluded, draft or paused experiments can be archived'];
        }
        $this->db->update('experiments', ['status' => 'archived'], 'id = :id', [':id' => $id]);
        return ['success' => true];
    }

    /**
     * Conclude an experiment and promote a winner (100% traffic).
     * Called automatically by recomputePosteriors() or manually from admin.
     */
    public function concludeExperiment($id, $winnerVariantId, $auto = false)
    {
        $exp = $this->getExperiment($id);
        if (!$exp) {
            return ['error' => 'Experiment not found'];
        }
        $winner = null;
        foreach ($exp['variants'] as $v) {
            if ((int) $v['id'] === (int) $winnerVariantId) {
                $winner = $v;
            }
        }
        if (!$winner) {
            return ['error' => 'Winner variant does not belong to this experiment'];
        }

        $this->db->update('experiments', [
            'status' => 'concluded',
            'winner_variant_id' => (int) $winnerVariantId,
            'concluded_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', [':id' => $id]);

        $this->db->update('variants', ['status' => 'winner'], 'id = :id', [':id' => $winnerVariantId]);
        $this->db->query(
            "UPDATE variants SET status = 'killed' WHERE experiment_id = :e AND id != :w AND status = 'active'",
            [':e' => $id, ':w' => $winnerVariantId]
        );

        // Lift vs control on posterior mean conversion
        $control = null;
        foreach ($exp['variants'] as $v) {
            if ($v['type'] === 'control') {
                $control = $v;
            }
        }
        $lift = null;
        if ($control && (int) $control['id'] !== (int) $winnerVariantId) {
            $crC = $this->rate($control);
            $crW = $this->rate($winner);
            if ($crC > 0) {
                $lift = round((($crW - $crC) / $crC) * 100, 1) . '%';
            }
        }

        $this->webhooks()->dispatch('experiment.concluded', [
            'experiment_id' => (int) $id,
            'name' => $exp['name'],
            'funnel' => $exp['funnel_name'],
            'stage' => $exp['stage'],
            'winner_variant_id' => (int) $winnerVariantId,
            'winner_name' => $winner['name'],
            'p_best' => (float) $winner['p_best'],
            'lift_vs_control' => $lift,
            'auto' => (bool) $auto,
        ]);
        return ['success' => true, 'winner' => $winner['name'], 'lift' => $lift];
    }

    private function rate($variant)
    {
        $e = max(1, (int) $variant['exposures']);
        return ((int) $variant['conversions']) / $e;
    }

    /** Kill a losing variant; optionally have the AI propose a challenger. */
    public function killVariant($variantId, $reason = 'manual', $generateChallenger = false)
    {
        $variant = $this->getVariant($variantId);
        if (!$variant) {
            return ['error' => 'Variant not found'];
        }
        if ($variant['type'] === 'control') {
            return ['error' => 'The control cannot be killed. Conclude the experiment with a different winner instead.'];
        }
        if ($variant['status'] !== 'active') {
            return ['error' => "Variant is '{$variant['status']}', not active"];
        }
        $this->db->update('variants', ['status' => 'killed'], 'id = :id', [':id' => $variantId]);

        $this->webhooks()->dispatch('experiment.variant_killed', [
            'experiment_id' => (int) $variant['experiment_id'],
            'variant_id' => (int) $variantId,
            'variant_name' => $variant['name'],
            'reason' => $reason,
        ]);

        $challenger = null;
        if ($generateChallenger) {
            require_once __DIR__ . '/ChallengerGenerator.php';
            $gen = new ChallengerGenerator();
            $challenger = $gen->generateForKilledVariant($variantId);
        }
        return ['success' => true, 'challenger' => $challenger];
    }

    public function approveVariant($variantId)
    {
        $variant = $this->getVariant($variantId);
        if (!$variant || $variant['status'] !== 'pending_approval') {
            return ['error' => 'Variant is not awaiting approval'];
        }
        $this->db->update('variants', ['status' => 'active'], 'id = :id', [':id' => $variantId]);
        return ['success' => true];
    }

    public function rejectVariant($variantId)
    {
        $variant = $this->getVariant($variantId);
        if (!$variant || $variant['status'] !== 'pending_approval') {
            return ['error' => 'Variant is not awaiting approval'];
        }
        $this->db->update('variants', ['status' => 'rejected'], 'id = :id', [':id' => $variantId]);
        return ['success' => true];
    }

    /** AI challengers waiting for human review. */
    public function getApprovalQueue($experimentId = null)
    {
        $where = "v.status = 'pending_approval'";
        $params = [];
        if ($experimentId) {
            $where .= ' AND v.experiment_id = :e';
            $params[':e'] = $experimentId;
        }
        $rows = $this->db->fetchAll(
            "SELECT v.*, e.name AS experiment_name, e.funnel_name, e.stage
             FROM variants v JOIN experiments e ON e.id = v.experiment_id
             WHERE $where ORDER BY v.created_at DESC",
            $params
        );
        foreach ($rows as &$r) {
            if (!empty($r['overrides']) && is_string($r['overrides'])) {
                $d = json_decode($r['overrides'], true);
                if ($d !== null) $r['overrides'] = $d;
            }
        }
        return $rows;
    }

    // ==================================================================
    // Assignment (called by router.php)
    // ==================================================================

    /** Running experiments for a funnel (burn_in + active). */
    public function getRunningExperiments($funnel)
    {
        return $this->db->fetchAll(
            "SELECT * FROM experiments WHERE funnel_name = :f AND status IN ('burn_in','active')",
            [':f' => $funnel]
        );
    }

    /** Variants eligible to receive traffic. */
    public function getServeableVariants($experimentId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM variants WHERE experiment_id = :id AND status IN ('active','winner')",
            [':id' => $experimentId]
        );
    }

    /**
     * Resolve sticky assignments for a session on a funnel.
     *
     * @param string $sessionId
     * @param string $funnel
     * @param array  $cookieMap existing experiment_id => variant_id from the 1w_exp cookie
     * @return array experiment_id => variant row (with 'experiment' key embedded)
     */
    public function assignSession($sessionId, $funnel, array $cookieMap = [])
    {
        $result = [];
        foreach ($this->getRunningExperiments($funnel) as $exp) {
            $variants = $this->getServeableVariants($exp['id']);
            if (count($variants) === 0) {
                continue;
            }
            $variantsById = [];
            foreach ($variants as $v) {
                $variantsById[(int) $v['id']] = $v;
            }

            $variant = null;

            // 1. Cookie says we already assigned (fast path, survives DB resets)
            $cookieVid = isset($cookieMap[(string) $exp['id']]) ? (int) $cookieMap[(string) $exp['id']]
                : (isset($cookieMap[(int) $exp['id']]) ? (int) $cookieMap[(int) $exp['id']] : null);
            if ($cookieVid && isset($variantsById[$cookieVid])) {
                $variant = $variantsById[$cookieVid];
            }

            // 2. DB assignment (authoritative)
            if (!$variant) {
                $row = $this->db->fetch(
                    "SELECT variant_id FROM assignments WHERE session_id = :s AND experiment_id = :e",
                    [':s' => $sessionId, ':e' => $exp['id']]
                );
                if ($row && isset($variantsById[(int) $row['variant_id']])) {
                    $variant = $variantsById[(int) $row['variant_id']];
                }
            }

            // 3. New assignment via Thompson Sampling
            if (!$variant) {
                $variant = $this->bandit->assign($exp, array_values($variants));
                try {
                    $this->db->insert('assignments', [
                        'session_id' => $sessionId,
                        'experiment_id' => $exp['id'],
                        'variant_id' => $variant['id'],
                        'assigned_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (Exception $e) {
                    // Unique-key race: another request assigned first — use theirs
                    $row = $this->db->fetch(
                        "SELECT variant_id FROM assignments WHERE session_id = :s AND experiment_id = :e",
                        [':s' => $sessionId, ':e' => $exp['id']]
                    );
                    if ($row && isset($variantsById[(int) $row['variant_id']])) {
                        $variant = $variantsById[(int) $row['variant_id']];
                    }
                }
            }

            $variant['experiment'] = $exp;
            $result[(int) $exp['id']] = $variant;
        }
        return $result;
    }

    /** All current assignments for a session (for event attribution). */
    public function getSessionAssignments($sessionId)
    {
        return $this->db->fetchAll(
            "SELECT a.experiment_id, a.variant_id, e.funnel_name, e.stage
             FROM assignments a JOIN experiments e ON e.id = a.experiment_id
             WHERE a.session_id = :s AND e.status IN ('burn_in','active')",
            [':s' => $sessionId]
        );
    }

    /**
     * Log a server-side exposure (view) event, deduped per session+experiment+day.
     */
    public function logExposure($sessionId, $experimentId, $variantId, $funnel, $url = '', $ip = '', $ua = '')
    {
        try {
            $today = date('Y-m-d');
            $dupe = $this->db->fetch(
                "SELECT id FROM funnel_tracking
                 WHERE session_id = :s AND experiment_id = :e AND event_type = 'view'
                   AND created_at >= :d LIMIT 1",
                [':s' => $sessionId, ':e' => $experimentId, ':d' => $today . ' 00:00:00']
            );
            if ($dupe) {
                return false;
            }
            $this->db->insert('funnel_tracking', [
                'session_id' => $sessionId,
                'funnel_name' => $funnel,
                'step_name' => 'exposure',
                'event_type' => 'view',
                'experiment_id' => $experimentId,
                'variant_id' => $variantId,
                'url' => $url,
                'ip_address' => $ip,
                'user_agent' => mb_substr($ua, 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            // Live counter so the bandit's exposure-floor logic works
            // between hourly recomputes (which overwrite with exact
            // distinct-session counts).
            $this->db->query("UPDATE variants SET exposures = exposures + 1 WHERE id = :id", [':id' => $variantId]);
            return true;
        } catch (Exception $e) {
            error_log('logExposure: ' . $e->getMessage());
            return false;
        }
    }

    // ==================================================================
    // Posterior recompute & auto-conclusion (hourly cron)
    // ==================================================================

    /**
     * Refresh per-variant counters from funnel_tracking, update Beta
     * posteriors, roll daily metrics, run status transitions and the
     * auto-conclusion rule. Returns a report array.
     */
    public function recomputePosteriors()
    {
        $report = [];
        $experiments = $this->db->fetchAll(
            "SELECT * FROM experiments WHERE status IN ('burn_in','active')"
        );

        foreach ($experiments as $exp) {
            $entry = ['experiment_id' => (int) $exp['id'], 'name' => $exp['name'], 'actions' => []];

            // burn_in -> active transition
            if ($exp['status'] === 'burn_in' && $exp['started_at']) {
                $elapsed = (time() - strtotime($exp['started_at'])) / 3600;
                if ($elapsed >= (int) $exp['burn_in_hours']) {
                    $this->db->update('experiments', ['status' => 'active'], 'id = :id', [':id' => $exp['id']]);
                    $exp['status'] = 'active';
                    $entry['actions'][] = 'burn_in -> active';
                }
            }

            $metricEvent = self::METRICS[$exp['primary_metric']]['event'] ?? 'purchase';
            $variants = $this->getVariants($exp['id']);

            foreach ($variants as &$v) {
                $exposures = (int) $this->scalar(
                    "SELECT COUNT(DISTINCT session_id) c FROM funnel_tracking
                     WHERE variant_id = :v AND event_type = 'view'", [':v' => $v['id']]
                );
                $conversions = (int) $this->scalar(
                    "SELECT COUNT(DISTINCT session_id) c FROM funnel_tracking
                     WHERE variant_id = :v AND event_type = :e", [':v' => $v['id'], ':e' => $metricEvent]
                );
                $conversions = min($conversions, $exposures); // guard against attribution drift
                $revenue = (float) $this->scalar(
                    "SELECT COALESCE(SUM(revenue),0) c FROM funnel_tracking
                     WHERE variant_id = :v AND event_type = 'purchase'", [':v' => $v['id']]
                );

                $v['exposures'] = $exposures;
                $v['conversions'] = $conversions;
                $v['revenue_total'] = $revenue;
                $v['alpha'] = 1 + $conversions;
                $v['beta'] = 1 + max(0, $exposures - $conversions);

                $this->db->update('variants', [
                    'exposures' => $exposures,
                    'conversions' => $conversions,
                    'revenue_total' => $revenue,
                    'alpha' => $v['alpha'],
                    'beta' => $v['beta'],
                ], 'id = :id', [':id' => $v['id']]);

                $this->rollDailyMetrics($v['id']);
            }
            unset($v);

            // Decision stats over serveable variants only
            $serveable = array_values(array_filter($variants, function ($v) {
                return in_array($v['status'], ['active', 'winner']);
            }));
            if (count($serveable) >= 2) {
                $stats = $this->bandit->decisionStats($exp, $serveable, 10000);
                foreach ($serveable as $v) {
                    if (isset($stats[$v['id']])) {
                        $this->db->update('variants', [
                            'p_best' => round($stats[$v['id']]['p_best'], 4),
                            'expected_loss' => round($stats[$v['id']]['expected_loss'], 6),
                        ], 'id = :id', [':id' => $v['id']]);
                    }
                }

                // Auto-conclusion rule (only once out of burn-in)
                if ($exp['status'] === 'active') {
                    $minSamples = (int) $exp['min_samples_per_variant'];
                    $allSampled = true;
                    foreach ($serveable as $v) {
                        if ((int) $v['exposures'] < $minSamples) {
                            $allSampled = false;
                            break;
                        }
                    }
                    if ($allSampled) {
                        $top = null;
                        foreach ($serveable as $v) {
                            if ($top === null || $stats[$v['id']]['p_best'] > $stats[$top['id']]['p_best']) {
                                $top = $v;
                            }
                        }
                        if ($top !== null
                            && $stats[$top['id']]['p_best'] > (float) $exp['decision_p_best']
                            && $stats[$top['id']]['expected_loss'] < (float) $exp['decision_expected_loss']
                        ) {
                            $res = $this->concludeExperiment($exp['id'], $top['id'], true);
                            $entry['actions'][] = "auto-concluded, winner: {$top['name']}" .
                                (isset($res['lift']) && $res['lift'] ? " (lift {$res['lift']})" : '');
                        }
                    }
                }
            }

            $report[] = $entry;
        }
        return $report;
    }

    private function scalar($sql, $params)
    {
        $row = $this->db->fetch($sql, $params);
        return $row ? array_values($row)[0] : 0;
    }

    /** Upsert the last 7 days of per-day step counts for a variant. */
    public function rollDailyMetrics($variantId, $days = 7)
    {
        $eventCols = [
            'view' => 'exposures',
            'assessment_start' => 'assessment_starts',
            'assessment_complete' => 'assessment_completes',
            'results_view' => 'results_views',
            'plan_select' => 'plan_selects',
            'checkout_init' => 'checkout_inits',
            'purchase' => 'purchases',
        ];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $from = $date . ' 00:00:00';
            $to = $date . ' 23:59:59';

            $counts = [];
            $any = 0;
            foreach ($eventCols as $event => $col) {
                $c = (int) $this->scalar(
                    "SELECT COUNT(DISTINCT session_id) c FROM funnel_tracking
                     WHERE variant_id = :v AND event_type = :e AND created_at BETWEEN :f AND :t",
                    [':v' => $variantId, ':e' => $event, ':f' => $from, ':t' => $to]
                );
                $counts[$col] = $c;
                $any += $c;
            }
            $counts['revenue'] = (float) $this->scalar(
                "SELECT COALESCE(SUM(revenue),0) c FROM funnel_tracking
                 WHERE variant_id = :v AND event_type = 'purchase' AND created_at BETWEEN :f AND :t",
                [':v' => $variantId, ':f' => $from, ':t' => $to]
            );

            if ($any == 0 && $counts['revenue'] == 0.0) {
                continue;
            }

            $exists = $this->db->fetch(
                "SELECT id FROM variant_metrics_daily WHERE variant_id = :v AND metric_date = :d",
                [':v' => $variantId, ':d' => $date]
            );
            if ($exists) {
                $this->db->update('variant_metrics_daily', $counts, 'id = :id', [':id' => $exists['id']]);
            } else {
                $this->db->insert('variant_metrics_daily', array_merge($counts, [
                    'variant_id' => $variantId,
                    'metric_date' => $date,
                ]));
            }
        }
    }

    // ==================================================================
    // Reporting
    // ==================================================================

    /** Full stats package for the admin detail page / API. */
    public function getExperimentStats($id)
    {
        $exp = $this->getExperiment($id);
        if (!$exp) {
            return null;
        }

        $totalExposures = 0;
        foreach ($exp['variants'] as $v) {
            $totalExposures += (int) $v['exposures'];
        }

        $waterfallEvents = ['view', 'assessment_start', 'assessment_complete', 'results_view', 'plan_select', 'checkout_init', 'purchase'];
        $variants = [];
        foreach ($exp['variants'] as $v) {
            $steps = [];
            foreach ($waterfallEvents as $event) {
                $steps[$event] = (int) $this->scalar(
                    "SELECT COUNT(DISTINCT session_id) c FROM funnel_tracking WHERE variant_id = :v AND event_type = :e",
                    [':v' => $v['id'], ':e' => $event]
                );
            }
            $exposures = max((int) $v['exposures'], $steps['view']);
            $variants[] = [
                'id' => (int) $v['id'],
                'name' => $v['name'],
                'type' => $v['type'],
                'status' => $v['status'],
                'source' => $v['source'],
                'exposures' => $exposures,
                'conversions' => (int) $v['conversions'],
                'conversion_rate' => $exposures > 0 ? round($v['conversions'] / $exposures, 4) : 0,
                'revenue_total' => (float) $v['revenue_total'],
                'rpv' => $exposures > 0 ? round($v['revenue_total'] / $exposures, 2) : 0,
                'traffic_share' => $totalExposures > 0 ? round($exposures / $totalExposures, 3) : 0,
                'p_best' => (float) $v['p_best'],
                'expected_loss' => (float) $v['expected_loss'],
                'waterfall' => $steps,
                'ai_rationale' => $v['ai_rationale'],
                'compliance_status' => $v['compliance_status'],
            ];
        }

        // Daily trend (last 30 days)
        $since = date('Y-m-d', strtotime('-30 days'));
        $variantIds = array_map(function ($v) { return (int) $v['id']; }, $exp['variants']);
        $trend = [];
        if ($variantIds) {
            $in = implode(',', $variantIds);
            $trend = $this->db->fetchAll(
                "SELECT variant_id, metric_date, exposures, purchases, revenue,
                        assessment_starts, assessment_completes, results_views, plan_selects, checkout_inits
                 FROM variant_metrics_daily
                 WHERE variant_id IN ($in) AND metric_date >= :d
                 ORDER BY metric_date ASC",
                [':d' => $since]
            );
        }

        // Latest AI insight
        $insight = $this->db->fetch(
            "SELECT * FROM ai_insights WHERE experiment_id = :id ORDER BY created_at DESC LIMIT 1",
            [':id' => $id]
        );
        if ($insight && !empty($insight['content'])) {
            $decoded = json_decode($insight['content'], true);
            if ($decoded !== null) {
                $insight['content'] = $decoded;
            }
        }

        $days = $exp['started_at'] ? max(0, floor((time() - strtotime($exp['started_at'])) / 86400)) : 0;

        return [
            'experiment' => array_diff_key($exp, ['variants' => 1]),
            'days_running' => (int) $days,
            'variants' => $variants,
            'daily_trend' => $trend,
            'latest_insight' => $insight ?: null,
            'approval_queue' => $this->getApprovalQueue($id),
        ];
    }

    /** Step drop-off baseline for a funnel (for AI diagnostics). */
    public function getFunnelBaseline($funnel, $days = 30)
    {
        $since = date('Y-m-d H:i:s', strtotime("-$days days"));
        $steps = ['view', 'assessment_start', 'assessment_complete', 'results_view', 'plan_select', 'checkout_init', 'purchase'];
        $counts = [];
        foreach ($steps as $s) {
            $counts[$s] = (int) $this->scalar(
                "SELECT COUNT(DISTINCT session_id) c FROM funnel_tracking
                 WHERE funnel_name = :f AND event_type = :e AND created_at >= :d",
                [':f' => $funnel, ':e' => $s, ':d' => $since]
            );
        }
        $rates = [];
        $prev = null;
        foreach ($steps as $s) {
            if ($prev !== null && $counts[$prev] > 0) {
                $rates["$prev->$s"] = round($counts[$s] / $counts[$prev], 4);
            }
            $prev = $s;
        }
        return ['counts' => $counts, 'step_rates' => $rates, 'window_days' => $days];
    }
}
