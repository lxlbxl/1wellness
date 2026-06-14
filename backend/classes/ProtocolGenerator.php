<?php
/**
 * ProtocolGenerator — condition-aware 90-day protocol PDF generator.
 *
 * Delegates PCOS to the existing PcosGenerator.
 * Handles acne, weight, and mens with dynamically-constructed prompts
 * drawn from the ConditionsRegistry AI persona.
 *
 * Usage:
 *   $gen = new ProtocolGenerator();
 *   $pdf = $gen->generate('acne', $assessment, $name, $email);
 */
class ProtocolGenerator
{
    private $ai;
    private $settings;
    private int $maxRetries;
    private int $retryDelaySec = 2;
    private string $templatesDir;

    /** Condition → sub-brand label for the PDF header */
    private const LABELS = [
        'pcos'   => 'CycleSync 90-Day Protocol',
        'acne'   => 'GlowClear 90-Day Protocol',
        'weight' => 'LeanFlow 90-Day Protocol',
        'mens'   => 'Vitale 90-Day Protocol',
    ];

    public function __construct()
    {
        $this->ai          = new AIOrchestrator();
        $this->settings    = Settings::getInstance();
        $this->maxRetries  = (int) $this->settings->get('pcos_max_retries', 3);
        $this->templatesDir = __DIR__ . '/../templates';
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Generate a PDF for any condition.
     *
     * @param string $condition 'pcos'|'acne'|'weight'|'mens'
     * @param array  $assessment  Assessment answers
     * @param string $name        Member's first name
     * @param string $email       Optional — triggers email delivery
     * @return string PDF binary
     */
    public function generate(string $condition, array $assessment, string $name, string $email = ''): string
    {
        $condition = strtolower(trim($condition));

        if ($condition === 'pcos') {
            // Delegate to the existing battle-tested PCOS generator
            require_once __DIR__ . '/PcosGenerator.php';
            $gen = new PcosGenerator();
            return $gen->generate($assessment, $name, $email);
        }

        return $this->generateGeneric($condition, $assessment, $name, $email);
    }

    // -----------------------------------------------------------------------
    // Generic generator (non-PCOS)
    // -----------------------------------------------------------------------

    private function generateGeneric(string $condition, array $assessment, string $name, string $email): string
    {
        $label    = self::LABELS[$condition] ?? ucfirst($condition) . ' Protocol';
        $persona  = $this->getPersona($condition);
        $content  = $this->generateAIContent($condition, $persona, $assessment, $name);
        $html     = $this->renderTemplate($content, $condition, $label, $name, $assessment);
        $pdf      = $this->generatePdf($html);

        error_log("[ProtocolGenerator] $label PDF: " . round(strlen($pdf) / 1024) . " KB for $name");

        if ($email && $this->settings->get('pcos_send_email', false)) {
            $this->sendEmail($email, $name, $label, $pdf);
        }

        return $pdf;
    }

    private function getPersona(string $condition): string
    {
        $personas = [
            'acne'   => 'You are a world-class holistic skin health specialist with 20+ years of clinical experience. You have deep expertise in Nigerian cuisine, West African herbal medicine, gut-skin axis science, and integrative dermatology. You create life-changing, highly personalized 90-day clear-skin action plans.',
            'weight' => 'You are a world-class metabolic health coach and nutritionist with 20+ years of clinical experience. You specialise in sustainable weight management through Nigerian cuisine, West African herbs, evidence-based nutrition, and lifestyle medicine.',
            'mens'   => 'You are a world-class men\'s health and vitality specialist with 20+ years of clinical experience. You combine expertise in Nigerian cuisine, West African herbal medicine, and integrative men\'s health to create powerful 90-day vitality protocols.',
        ];
        return $personas[$condition] ?? $personas['acne'];
    }

    private function generateAIContent(string $condition, string $persona, array $assessment, string $name): array
    {
        $label    = self::LABELS[$condition] ?? ucfirst($condition) . ' Protocol';
        $symptoms = $this->formatList($assessment['symptoms'] ?? $assessment['concerns'] ?? []);
        $goals    = $this->formatList($assessment['goals'] ?? []);
        $diet     = $this->formatList($assessment['dietaryRestrictions'] ?? []);

        $systemPrompt = $persona . "\n\n"
            . "## Response Format\n"
            . "Return ONLY a valid JSON object with these exact keys:\n"
            . "introduction (string), root_causes (array of strings), protocol_phases (array of {phase, focus, actions[]}), "
            . "nutrition_plan (array of strings), herbs_supplements (array of {name, benefit, dosage}), "
            . "lifestyle_tips (array of strings), success_metrics (array of strings), motivational_closing (string)\n\n"
            . "## Compliance Guardrail (non-negotiable)\n"
            . "- No diagnosis, no medical cure claims, no medication advice.\n"
            . "- Hard-code escalation to 'consult your doctor' for red-flag symptoms (pregnancy, severe symptoms, medication interactions, self-harm).\n"
            . "- Frame all recommendations as lifestyle support, not treatment.";

        $userPrompt = "Create a personalized $label for {$name} (or 'Friend' if no name).\n\n"
            . "Client profile:\n"
            . "- Primary concerns: $symptoms\n"
            . "- Goals: $goals\n"
            . "- Age: " . ($assessment['age'] ?? 'not specified') . "\n"
            . "- Dietary restrictions: $diet\n"
            . "- Exercise level: " . ($assessment['exerciseLevel'] ?? 'not specified') . "\n"
            . "- Sleep quality: " . ($assessment['sleepQuality'] ?? 'not specified') . "\n"
            . "- Stress level: " . ($assessment['stressLevel'] ?? 'not specified') . "\n\n"
            . "Incorporate Nigerian/West African foods and herbs where relevant. "
            . "Be specific, actionable, and empowering. Return only valid JSON.";

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->callAIDirect($systemPrompt, $userPrompt);
                $json     = $this->extractJson(is_string($response) ? $response : json_encode($response));
                $content  = json_decode($json, true);
                if (is_array($content) && isset($content['introduction'])) {
                    return $content;
                }
            } catch (Exception $e) {
                error_log("[ProtocolGenerator] attempt $attempt failed: " . $e->getMessage());
                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelaySec * $attempt);
                }
            }
        }

        return $this->getFallbackContent($condition, $name);
    }

    // -----------------------------------------------------------------------
    // Helpers copied / adapted from PcosGenerator
    // -----------------------------------------------------------------------

    private function callAIDirect(string $systemPrompt, string $userPrompt)
    {
        $provider = $this->settings->get('ai_provider', 'openrouter');
        $apiKey   = $this->settings->get('ai_api_key', '');

        if (!$apiKey) {
            throw new RuntimeException('AI API key not configured');
        }

        $payload = [
            'model'    => $this->settings->get('ai_model', 'anthropic/claude-3-haiku'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens'  => 3000,
            'temperature' => 0.7,
        ];

        $endpoint = $provider === 'anthropic'
            ? 'https://api.anthropic.com/v1/messages'
            : 'https://openrouter.ai/api/v1/chat/completions';

        $headers = $provider === 'anthropic'
            ? ['x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01', 'content-type: application/json']
            : ['Authorization: Bearer ' . $apiKey, 'content-type: application/json', 'HTTP-Referer: ' . (APP_URL ?? '')];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        if (!$body) throw new RuntimeException('AI API request failed');

        $decoded = json_decode($body, true);
        // OpenRouter / Anthropic response structure
        return $decoded['choices'][0]['message']['content']
            ?? $decoded['content'][0]['text']
            ?? '';
    }

    private function extractJson(string $text): string
    {
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/', $text, $m)) {
            return $m[1];
        }
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false) {
            return substr($text, $start, $end - $start + 1);
        }
        return $text;
    }

    private function formatList($val): string
    {
        if (is_array($val)) return implode(', ', $val) ?: 'Not specified';
        return $val ?: 'Not specified';
    }

    private function getFallbackContent(string $condition, string $name): array
    {
        $label = self::LABELS[$condition] ?? ucfirst($condition) . ' Protocol';
        return [
            'introduction'         => "Dear {$name}, welcome to your personalized $label. This 90-day plan will support your wellness journey with evidence-based nutrition and natural approaches.",
            'root_causes'          => ['Nutritional deficiencies', 'Lifestyle factors', 'Hormonal imbalances', 'Gut health'],
            'protocol_phases'      => [
                ['phase' => 'Phase 1 (Days 1-30)',  'focus' => 'Foundation', 'actions' => ['Establish daily routine', 'Begin core nutrition protocol', 'Start herbal support']],
                ['phase' => 'Phase 2 (Days 31-60)', 'focus' => 'Optimisation', 'actions' => ['Deepen nutritional changes', 'Track progress metrics', 'Adjust supplements']],
                ['phase' => 'Phase 3 (Days 61-90)', 'focus' => 'Consolidation', 'actions' => ['Maintain improvements', 'Plan long-term habits', 'Celebrate wins']],
            ],
            'nutrition_plan'       => ['Eat whole, unprocessed Nigerian foods', 'Include Moringa and local greens daily', 'Reduce refined sugar and processed foods', 'Stay hydrated with 2+ litres of water'],
            'herbs_supplements'    => [
                ['name' => 'Moringa', 'benefit' => 'Nutrient-dense superfood', 'dosage' => '1 tsp powder daily'],
                ['name' => 'Turmeric', 'benefit' => 'Anti-inflammatory support', 'dosage' => '500mg with black pepper'],
                ['name' => 'Vitamin D', 'benefit' => 'Immune and hormonal health', 'dosage' => '2000 IU daily'],
            ],
            'lifestyle_tips'       => ['Sleep 7-9 hours nightly', 'Move daily — walking counts', 'Manage stress with breathwork', 'Connect with your support community'],
            'success_metrics'      => ['Improved energy levels', 'Better sleep quality', 'Reduced symptoms', 'Positive mood shifts'],
            'motivational_closing' => "You have everything it takes to transform your health. Trust the process, stay consistent, and remember: small daily actions create extraordinary results.",
        ];
    }

    private function renderTemplate(array $content, string $condition, string $label, string $name, array $assessment): string
    {
        $get = function ($key) use ($content) {
            return $content[$key] ?? '';
        };

        $phases = $get('protocol_phases');
        $phasesHtml = '';
        foreach ((array)$phases as $p) {
            $actionsHtml = '';
            foreach ((array)($p['actions'] ?? []) as $a) {
                $actionsHtml .= '<li>' . htmlspecialchars($a) . '</li>';
            }
            $phasesHtml .= '<div class="phase"><h4>' . htmlspecialchars($p['phase'] ?? '') . ' — ' . htmlspecialchars($p['focus'] ?? '') . '</h4><ul>' . $actionsHtml . '</ul></div>';
        }

        $herbsHtml = '';
        foreach ((array)$get('herbs_supplements') as $h) {
            $herbsHtml .= '<tr><td><strong>' . htmlspecialchars($h['name'] ?? '') . '</strong></td>'
                . '<td>' . htmlspecialchars($h['benefit'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($h['dosage'] ?? '') . '</td></tr>';
        }

        $listHtml = function (array $items): string {
            $out = '';
            foreach ($items as $i) { $out .= '<li>' . htmlspecialchars((string)$i) . '</li>'; }
            return $out;
        };

        $colors = [
            'pcos'   => '#2C3E35',
            'acne'   => '#B5534A',
            'weight' => '#2D6A4F',
            'mens'   => '#1E4D7B',
        ];
        $primary = $colors[$condition] ?? '#2C3E35';

        $dateStr    = date('d M Y');
        $introStr   = htmlspecialchars((string)$get('introduction'));
        $rootHtml   = $listHtml((array)$get('root_causes'));
        $nutHtml    = $listHtml((array)$get('nutrition_plan'));
        $lifHtml    = $listHtml((array)$get('lifestyle_tips'));
        $metHtml    = $listHtml((array)$get('success_metrics'));
        $closeStr   = htmlspecialchars((string)$get('motivational_closing'));

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<style>'
            . "body{font-family:Georgia,serif;color:#333;margin:0;padding:0}"
            . ".cover{background:{$primary};color:#fff;padding:60px 40px;text-align:center}"
            . ".cover h1{font-size:2.2em;margin:0 0 10px}"
            . ".cover p{margin:0;opacity:.85;font-size:1.1em}"
            . ".section{padding:40px;border-bottom:1px solid #eee}"
            . "h2{color:{$primary};border-bottom:2px solid {$primary};padding-bottom:8px}"
            . "h3,h4{color:{$primary}}"
            . ".phase{margin:16px 0;padding:16px;background:#f9f9f9;border-left:4px solid {$primary}}"
            . "table{width:100%;border-collapse:collapse;margin:16px 0}"
            . "th{background:{$primary};color:#fff;padding:8px;text-align:left}"
            . "td{padding:8px;border-bottom:1px solid #eee}"
            . "ul{padding-left:20px}li{margin:6px 0}"
            . ".closing{background:{$primary};color:#fff;padding:30px 40px;font-size:1.1em;font-style:italic}"
            . '</style></head><body>'
            . "<div class=\"cover\"><h1>{$label}</h1>"
            . "<p>Prepared exclusively for {$name} &bull; 1wellness</p>"
            . "<p style=\"font-size:.9em;opacity:.7\">Generated: {$dateStr}</p></div>"
            . "<div class=\"section\"><h2>Your Personal Introduction</h2><p>{$introStr}</p></div>"
            . "<div class=\"section\"><h2>Root Causes We're Addressing</h2><ul>{$rootHtml}</ul></div>"
            . "<div class=\"section\"><h2>Your 90-Day Protocol</h2>{$phasesHtml}</div>"
            . "<div class=\"section\"><h2>Nutrition Plan</h2><ul>{$nutHtml}</ul></div>"
            . "<div class=\"section\"><h2>Herbs &amp; Supplements</h2>"
            . "<table><tr><th>Supplement</th><th>Benefit</th><th>Dosage</th></tr>{$herbsHtml}</table></div>"
            . "<div class=\"section\"><h2>Lifestyle Protocol</h2><ul>{$lifHtml}</ul></div>"
            . "<div class=\"section\"><h2>How to Measure Your Success</h2><ul>{$metHtml}</ul></div>"
            . "<div class=\"closing\"><p>{$closeStr}</p></div>"
            . '</body></html>';
    }

    private function generatePdf(string $html): string
    {
        if (!class_exists('Dompdf\Dompdf')) {
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
        }
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    private function sendEmail(string $email, string $name, string $label, string $pdf): void
    {
        try {
            $mailer = new Mailer();
            $mailer->sendWithAttachment(
                $email,
                "Your {$label} is ready!",
                "<p>Hi {$name},</p><p>Your personalized {$label} is attached. Start your journey today!</p>",
                $pdf,
                str_replace(' ', '-', $label) . '.pdf',
                'application/pdf'
            );
        } catch (Exception $e) {
            error_log("[ProtocolGenerator] Email failed: " . $e->getMessage());
        }
    }
}
