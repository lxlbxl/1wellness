<?php
/**
 * ChallengerGenerator — AI-proposed challenger variants
 *
 * Triggered when a variant is killed (manually, via API, or by the
 * weekly diagnostic). Feeds the model: brand bible, sub-brand voice,
 * the control's current copy, the killed variant's overrides, and the
 * diagnostic verdict. Output is validated, run through a compliance
 * classification pass, and inserted as status=pending_approval —
 * nothing AI-generated ever serves traffic without admin approval.
 */

require_once __DIR__ . '/ABSchema.php';

class ChallengerGenerator
{
    private $db;
    private $ai;

    /** Sub-brand voice context per funnel (mirrors js/config.js). */
    const SUB_BRANDS = [
        'pcos' => 'CycleSync by 1wellness — "Understand your cycle. Own your health." Audience: women with PCOS. Voice: empowering, knowledgeable, warm; cycles and hormones framed as understandable, not broken.',
        'acne' => 'GlowClear by 1wellness — "Clear skin starts from within." Audience: adults with persistent acne. Voice: hopeful, root-cause focused, gentle.',
        'weight' => 'LeanFlow by 1wellness — "Burn smarter. Live lighter." Audience: people frustrated by dieting. Voice: practical, anti-fad, metabolism-literate.',
        'mens' => 'Vitale by 1wellness — "Built different. Fuelled natural." Audience: men focused on energy/performance. Voice: direct, confident, no fluff.',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        ABSchema::ensure();
        require_once __DIR__ . '/AIOrchestrator.php';
        $this->ai = new AIOrchestrator();
    }

    /**
     * Generate one challenger for a killed variant.
     * @return array ['variant_id' => int, 'compliance' => string] or ['error' => string]
     */
    public function generateForKilledVariant($killedVariantId)
    {
        require_once __DIR__ . '/ExperimentManager.php';
        $manager = new ExperimentManager();

        $killed = $manager->getVariant($killedVariantId);
        if (!$killed) {
            return ['error' => 'Killed variant not found'];
        }
        $experiment = $manager->getExperiment($killed['experiment_id']);
        if (!$experiment) {
            return ['error' => 'Experiment not found'];
        }
        $funnel = $experiment['funnel_name'];

        $control = null;
        foreach ($experiment['variants'] as $v) {
            if ($v['type'] === 'control') {
                $control = $v;
            }
        }

        // Latest diagnostic verdict on the killed variant, if any
        $verdict = null;
        $insight = $this->db->fetch(
            "SELECT content FROM ai_insights WHERE experiment_id = :e AND insight_type = 'diagnostic'
             ORDER BY created_at DESC LIMIT 1",
            [':e' => $experiment['id']]
        );
        if ($insight) {
            $content = json_decode($insight['content'], true);
            foreach ($content['variant_analysis'] ?? [] as $va) {
                if (stripos($va['variant'] ?? '', $killed['name']) !== false) {
                    $verdict = $va;
                }
            }
        }

        $context = [
            'funnel' => $funnel,
            'sub_brand_identity' => self::SUB_BRANDS[$funnel] ?? '',
            'experiment' => [
                'name' => $experiment['name'],
                'hypothesis' => $experiment['hypothesis'],
                'stage' => $experiment['stage'],
                'primary_metric' => $experiment['primary_metric'],
            ],
            'control_copy' => $this->extractControlCopy($funnel),
            'killed_variant' => [
                'name' => $killed['name'],
                'overrides' => $killed['overrides'],
                'exposures' => (int) $killed['exposures'],
                'conversions' => (int) $killed['conversions'],
                'diagnostic_verdict' => $verdict,
            ],
            'brand_bible_excerpt' => $this->brandBibleText(6000),
        ];

        $response = $this->ai->generateResponse(
            'ab_challenger_agent',
            "Generate a challenger variant. Context:\n" . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        $parsed = $this->parseJson($response);
        if (!$parsed || empty($parsed['overrides']) || !is_array($parsed['overrides'])) {
            error_log('ChallengerGenerator: unusable AI response: ' . substr(is_string($response) ? $response : json_encode($response), 0, 400));
            return ['error' => 'AI did not return a valid overrides object'];
        }

        // Validate against the override schema
        $candidate = [
            'name' => mb_substr($parsed['name'] ?? ('AI challenger ' . date('M j')), 0, 120),
            'type' => 'element',
            'overrides' => $parsed['overrides'],
            'ai_rationale' => mb_substr($parsed['ai_rationale'] ?? '', 0, 2000),
        ];
        $err = $manager->validateVariant($candidate, $funnel);
        if ($err) {
            return ['error' => 'AI output failed validation: ' . $err];
        }

        // Compliance pass (second, cheap classification)
        $compliance = $this->complianceCheck($parsed['overrides']);

        $variantId = $manager->insertVariant($experiment['id'], array_merge($candidate, [
            'source' => 'ai_challenger',
            'status' => 'pending_approval',
            'compliance_status' => $compliance['compliant'] ? 'compliant' : 'non_compliant',
            'compliance_notes' => $compliance['notes'],
        ]));

        // Record rationale as an insight + notify
        $this->db->insert('ai_insights', [
            'experiment_id' => $experiment['id'],
            'funnel_name' => $funnel,
            'insight_type' => 'challenger_rationale',
            'content' => json_encode([
                'variant_id' => (int) $variantId,
                'name' => $candidate['name'],
                'rationale' => $candidate['ai_rationale'],
                'replaces_killed' => $killed['name'],
                'compliance' => $compliance,
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        require_once __DIR__ . '/WebhookDispatcher.php';
        (new WebhookDispatcher())->dispatch('experiment.challenger_proposed', [
            'experiment_id' => (int) $experiment['id'],
            'experiment_name' => $experiment['name'],
            'funnel' => $funnel,
            'variant_id' => (int) $variantId,
            'variant_name' => $candidate['name'],
            'compliance_status' => $compliance['compliant'] ? 'compliant' : 'non_compliant',
            'rationale' => $candidate['ai_rationale'],
        ]);

        return [
            'variant_id' => (int) $variantId,
            'name' => $candidate['name'],
            'compliance' => $compliance['compliant'] ? 'compliant' : 'non_compliant',
        ];
    }

    /** Classify generated copy via the compliance agent. Fails closed. */
    public function complianceCheck($overrides)
    {
        $response = $this->ai->generateResponse(
            'ab_compliance_agent',
            json_encode(['proposed_copy' => $overrides], JSON_UNESCAPED_SLASHES)
        );
        $parsed = $this->parseJson($response);
        if (!is_array($parsed) || !array_key_exists('compliant', $parsed)) {
            return ['compliant' => false, 'notes' => 'Compliance classifier unavailable — failing closed. Review manually.'];
        }
        $notes = trim(implode('; ', $parsed['violations'] ?? []) . ' ' . ($parsed['notes'] ?? ''));
        return ['compliant' => (bool) $parsed['compliant'], 'notes' => $notes ?: null];
    }

    /** Current control copy: all data-exp tagged elements from the live funnel page. */
    public function extractControlCopy($funnel)
    {
        $file = dirname(APP_ROOT) . '/' . $funnel . '/index.html';
        if (!file_exists($file)) {
            return [];
        }
        $html = file_get_contents($file);
        $copy = [];
        if (preg_match_all('/<([a-z0-9]+)[^>]*data-exp=["\']([^"\']+)["\'][^>]*>(.*?)<\/\1>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $text = trim(preg_replace('/\s+/', ' ', strip_tags($match[3])));
                $copy["[data-exp='{$match[2]}']"] = mb_substr($text, 0, 300);
            }
        }
        return $copy;
    }

    /** Brand bible as plain text, cached after first strip. */
    public function brandBibleText($maxChars = 8000)
    {
        $cache = APP_ROOT . '/database/data/brand_bible.txt';
        if (file_exists($cache)) {
            return mb_substr(file_get_contents($cache), 0, $maxChars);
        }
        $source = dirname(APP_ROOT) . '/1wellness-brand-bible.html';
        if (!file_exists($source)) {
            return '';
        }
        $html = file_get_contents($source);
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\s*\n\s*/', "\n", strip_tags($html))));
        $dir = dirname($cache);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($cache, $text);
        return mb_substr($text, 0, $maxChars);
    }

    /** Tolerant JSON extraction (models love markdown fences). */
    private function parseJson($response)
    {
        if (is_array($response)) {
            return isset($response['error']) ? null : $response;
        }
        if (!is_string($response)) {
            return null;
        }
        $clean = trim($response);
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $clean);
        $parsed = json_decode($clean, true);
        if ($parsed !== null) {
            return $parsed;
        }
        // Last resort: grab the outermost JSON object
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            return json_decode($m[0], true);
        }
        return null;
    }
}
