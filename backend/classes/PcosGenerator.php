<?php
/**
 * PcosGenerator — Generates personalized 90-Day PCOS Protocol PDFs
 *
 * Replaces the Node.js service entirely.
 * Uses AIOrchestrator for AI calls, dompdf for PDF generation, Mailer for email.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PcosGenerator
{
    private $ai;
    private $settings;
    private $maxRetries;
    private $retryDelaySec = 2;
    private $promptsDir;
    private $templatesDir;

    // PCOS type code → human-readable label
    private $typeMap = [
        'insulin' => 'Insulin-Resistant',
        'inflammatory' => 'Inflammatory',
        'adrenal' => 'Adrenal',
        'postPill' => 'Post-Pill',
    ];

    public function __construct()
    {
        $this->ai = new AIOrchestrator();
        $this->settings = Settings::getInstance();
        $this->maxRetries = (int) $this->settings->get('pcos_max_retries', 3);
        $this->promptsDir = __DIR__ . '/../prompts';
        $this->templatesDir = __DIR__ . '/../templates';
    }

    // ─────────────────────────────────────────────────────
    // PUBLIC: Main entry point — returns PDF binary string
    // ─────────────────────────────────────────────────────
    public function generate(array $assessment, string $name, string $email = ''): string
    {
        $pcosTypeCode = $assessment['pcosType']['primary'] ?? 'insulin';
        $readableType = $this->typeMap[$pcosTypeCode] ?? $pcosTypeCode;

        error_log("[PcosGenerator] Starting generation for: $name ($readableType)");

        // 1. Generate AI content (with retries)
        $content = $this->generateAIContent($assessment, $name);

        // 2. Merge with fallback for any missing fields
        $fallback = $this->getFallbackContent($readableType, $name ?: 'Friend');
        $get = function ($field) use ($content, $fallback) {
            if (isset($content[$field])) {
                if (is_array($content[$field]) && count($content[$field]) > 0)
                    return $content[$field];
                if (is_string($content[$field]) && strlen($content[$field]) > 10)
                    return $content[$field];
            }
            return $fallback[$field] ?? '';
        };

        // 3. Render HTML template
        $html = $this->renderTemplate($get, $name, $readableType, $assessment);

        // 4. Generate PDF
        $pdfBinary = $this->generatePdf($html);

        error_log("[PcosGenerator] PDF generated: " . round(strlen($pdfBinary) / 1024) . " KB");

        // 5. Send email (non-blocking, best-effort) — only if enabled in settings
        if ($email && $this->settings->get('pcos_send_email', false)) {
            $this->sendEmail($email, $name, $readableType, $pdfBinary);
        }

        return $pdfBinary;
    }

    // ─────────────────────────────────────────────────────
    // AI CONTENT GENERATION with retries
    // ─────────────────────────────────────────────────────
    private function generateAIContent(array $assessment, string $name): array
    {
        $pcosTypeCode = $assessment['pcosType']['primary'] ?? 'insulin';
        $readableType = $this->typeMap[$pcosTypeCode] ?? $pcosTypeCode;

        // Load prompts from files
        $systemPrompt = file_get_contents($this->promptsDir . '/system-prompt.md');
        $userPrompt = file_get_contents($this->promptsDir . '/user-prompt.md');

        if (!$systemPrompt || !$userPrompt) {
            error_log("[PcosGenerator] ERROR: Could not load prompt files");
            return $this->getFallbackContent($readableType, $name);
        }

        // Replace template placeholders in user prompt
        $replacements = [
            '{{NAME}}' => $name ?: 'Friend',
            '{{PCOS_TYPE}}' => $readableType,
            '{{AGE}}' => $assessment['age'] ?? 'Not specified',
            '{{SYMPTOMS}}' => is_array($assessment['symptoms'] ?? null) ? implode(', ', $assessment['symptoms']) : ($assessment['symptoms'] ?? 'General hormonal imbalance'),
            '{{GOALS}}' => is_array($assessment['goals'] ?? null) ? implode(', ', $assessment['goals']) : ($assessment['goals'] ?? 'Hormonal balance and symptom relief'),
            '{{BMI}}' => $assessment['bmi'] ?? $assessment['weight'] ?? 'Not specified',
            '{{CYCLE_STATUS}}' => $assessment['cycleStatus'] ?? $assessment['menstrualCycle'] ?? 'Not specified',
            '{{DIETARY_RESTRICTIONS}}' => is_array($assessment['dietaryRestrictions'] ?? null) ? implode(', ', $assessment['dietaryRestrictions']) : 'None specified',
            '{{MEDICATIONS}}' => is_array($assessment['medications'] ?? null) ? implode(', ', $assessment['medications']) : 'None specified',
            '{{EXERCISE_LEVEL}}' => $assessment['exerciseLevel'] ?? 'Not specified',
            '{{SLEEP_QUALITY}}' => $assessment['sleepQuality'] ?? 'Not specified',
            '{{STRESS_LEVEL}}' => $assessment['stressLevel'] ?? 'Not specified',
        ];

        foreach ($replacements as $placeholder => $value) {
            $userPrompt = str_replace($placeholder, $value, $userPrompt);
        }

        // Retry loop
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                error_log("[PcosGenerator] API attempt $attempt/{$this->maxRetries}...");

                $response = $this->ai->generateResponse('pcos_plan', $userPrompt, []);

                // AIOrchestrator returns the raw string from the API
                // We need to override the system prompt — call API directly
                $response = $this->callAIDirect($systemPrompt, $userPrompt);

                if (is_array($response) && isset($response['error'])) {
                    throw new Exception('AI API error: ' . ($response['error'] ?? 'Unknown'));
                }

                // Parse JSON from the response string
                $responseStr = is_string($response) ? $response : json_encode($response);

                // Try to extract JSON from the response (may be wrapped in markdown code blocks)
                $jsonStr = $this->extractJson($responseStr);
                $content = json_decode($jsonStr, true);

                if (!$content || !is_array($content)) {
                    throw new Exception('Failed to parse AI response as JSON');
                }

                // Validate
                $errors = $this->validateAIResponse($content);
                if (!empty($errors)) {
                    error_log("[PcosGenerator] Validation issues (attempt $attempt): " . implode(', ', $errors));
                    if ($attempt < $this->maxRetries) {
                        sleep($this->retryDelaySec * $attempt);
                        continue;
                    }
                    // Last attempt — proceed with partial content
                    error_log("[PcosGenerator] Proceeding with partial content after all retries.");
                }

                error_log("[PcosGenerator] AI content generated successfully.");
                return $content;

            } catch (Exception $e) {
                error_log("[PcosGenerator] API error (attempt $attempt): " . $e->getMessage());
                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelaySec * $attempt);
                }
            }
        }

        // All retries failed — use fallback
        error_log("[PcosGenerator] All API attempts failed. Using fallback content.");
        return $this->getFallbackContent($readableType, $name ?: 'Friend');
    }

    // ─────────────────────────────────────────────────────
    // Direct AI call — bypasses AIOrchestrator's DB-based system prompt
    // ─────────────────────────────────────────────────────
    private function callAIDirect(string $systemPrompt, string $userPrompt)
    {
        $settings = $this->settings;
        $provider = $settings->get('ai_provider', 'openrouter');
        $apiKey = $settings->get('ai_api_key', '');
        $model = $settings->get('ai_model', 'google/gemini-2.0-flash-exp:free');
        $maxTokens = (int) $settings->get('pcos_max_tokens', 16000);
        $temperature = (float) $settings->get('pcos_temperature', 0.7);

        if (empty($apiKey)) {
            return ['error' => 'API Key missing. Configure ai_api_key in Settings.'];
        }

        $url = '';
        $headers = [];
        $body = [];

        if ($provider === 'openrouter') {
            $url = 'https://openrouter.ai/api/v1/chat/completions';
            $headers = [
                "Authorization: Bearer $apiKey",
                "HTTP-Referer: https://1wellness.club",
                "X-Title: 1wellness PCOS Generator",
                "Content-Type: application/json"
            ];
            $body = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ];
        } elseif ($provider === 'openai') {
            $url = 'https://api.openai.com/v1/chat/completions';
            $headers = [
                "Authorization: Bearer $apiKey",
                "Content-Type: application/json"
            ];
            $body = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ];
        } else {
            // Fallback to OpenRouter-compatible format for other providers
            $url = 'https://openrouter.ai/api/v1/chat/completions';
            $headers = [
                "Authorization: Bearer $apiKey",
                "HTTP-Referer: https://1wellness.club",
                "Content-Type: application/json"
            ];
            $body = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ];
        }

        // Make HTTP request
        $jsonBody = json_encode($body);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minute timeout for long generations

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => 'Curl error: ' . $error];
        }
        curl_close($ch);

        $json = json_decode($response, true);
        if (isset($json['choices'][0]['message']['content'])) {
            return $json['choices'][0]['message']['content'];
        }

        // Try Gemini format
        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return $json['candidates'][0]['content']['parts'][0]['text'];
        }

        return ['error' => 'Unexpected API response format: ' . substr($response, 0, 500)];
    }

    // ─────────────────────────────────────────────────────
    // Extract JSON from AI response (handles markdown wrapping)
    // ─────────────────────────────────────────────────────
    private function extractJson(string $text): string
    {
        // If it starts with {, it's already JSON
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        // Try to extract from ```json ... ``` blocks
        if (preg_match('/```(?:json)?\s*(\{.+?\})\s*```/s', $text, $m)) {
            return $m[1];
        }

        // Try to find first { to last }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    // ─────────────────────────────────────────────────────
    // VALIDATION: Check AI response has all required fields
    // ─────────────────────────────────────────────────────
    private function validateAIResponse(array $content): array
    {
        $requiredStrings = [
            'summary',
            'root_cause',
            'encouragement',
            'phase_1_title',
            'phase_1_focus',
            'phase_1_description',
            'phase_2_title',
            'phase_2_focus',
            'phase_2_description',
            'phase_3_title',
            'phase_3_focus',
            'phase_3_description',
        ];

        $requiredArrays = [
            'goals',
            'morning_routine',
            'afternoon_routine',
            'evening_routine',
            'meal_plan',
            'supplements',
            'herbal_protocols',
            'lifestyle_tips',
            'tracking_guidance',
            'phase_1_weekly_actions',
            'phase_2_weekly_actions',
            'phase_3_weekly_actions',
        ];

        $errors = [];

        foreach ($requiredStrings as $field) {
            if (empty($content[$field]) || !is_string($content[$field]) || strlen($content[$field]) < 20) {
                $errors[] = "Missing or too short: $field";
            }
        }

        foreach ($requiredArrays as $field) {
            if (empty($content[$field]) || !is_array($content[$field])) {
                $errors[] = "Missing or empty array: $field";
            }
        }

        if (isset($content['meal_plan']) && count($content['meal_plan']) < 7) {
            $errors[] = "meal_plan has " . count($content['meal_plan']) . " days, need 7";
        }
        if (isset($content['supplements']) && count($content['supplements']) < 5) {
            $errors[] = "supplements has " . count($content['supplements']) . " items, need at least 5";
        }

        return $errors;
    }

    // ─────────────────────────────────────────────────────
    // TEMPLATE RENDERING: Merge data into HTML template
    // ─────────────────────────────────────────────────────
    private function renderTemplate(callable $get, string $name, string $pcosType, array $assessment): string
    {
        $templatePath = $this->templatesDir . '/plan-template.html';
        $html = file_get_contents($templatePath);

        if (!$html) {
            throw new Exception('Could not load PDF template from: ' . $templatePath);
        }

        $r = function ($placeholder, $value) use (&$html) {
            $html = str_replace($placeholder, $value ?? '', $html);
        };

        // Meta
        $r('{{NAME}}', $this->esc($name ?: 'Friend'));
        $r('{{PCOS_TYPE}}', $this->esc($pcosType));
        $r('{{AGE}}', $this->esc((string) ($assessment['age'] ?? 'N/A')));
        $r('{{DATE}}', date('j F Y'));
        $r('{{YEAR}}', date('Y'));

        // Summary & Root Cause
        $r('{{SUMMARY}}', $this->esc($get('summary')));
        $r('{{ROOT_CAUSE}}', $this->esc($get('root_cause')));
        $r('{{GOALS}}', $this->renderGoals($get('goals')));

        // Phases
        for ($i = 1; $i <= 3; $i++) {
            $r("{{PHASE_{$i}_TITLE}}", $this->esc($get("phase_{$i}_title")));
            $r("{{PHASE_{$i}_FOCUS}}", $this->esc($get("phase_{$i}_focus")));
            $r("{{PHASE_{$i}_DESCRIPTION}}", $this->esc($get("phase_{$i}_description")));
            $r("{{PHASE_{$i}_WEEKS}}", $this->renderWeeklyActions($get("phase_{$i}_weekly_actions")));
        }

        // Routines
        $r('{{MORNING_ROUTINE}}', $this->renderRoutine($get('morning_routine')));
        $r('{{AFTERNOON_ROUTINE}}', $this->renderRoutine($get('afternoon_routine')));
        $r('{{EVENING_ROUTINE}}', $this->renderRoutine($get('evening_routine')));

        // Meal Plan (split across two pages)
        $mealPlan = $get('meal_plan');
        if (is_array($mealPlan)) {
            $r('{{MEAL_PLAN_DAYS_1_4}}', $this->renderMealDays(array_slice($mealPlan, 0, 4)));
            $r('{{MEAL_PLAN_DAYS_5_7}}', $this->renderMealDays(array_slice($mealPlan, 4, 3)));
        } else {
            $r('{{MEAL_PLAN_DAYS_1_4}}', '');
            $r('{{MEAL_PLAN_DAYS_5_7}}', '');
        }

        // Supplements & Herbs
        $r('{{SUPPLEMENTS}}', $this->renderSupplements($get('supplements')));
        $r('{{HERBAL_PROTOCOLS}}', $this->renderHerbalProtocols($get('herbal_protocols')));

        // Lifestyle & Tracking
        $r('{{LIFESTYLE_TIPS}}', $this->renderLifestyleTips($get('lifestyle_tips')));
        $r('{{TRACKING_GUIDANCE}}', $this->renderTrackingGuidance($get('tracking_guidance')));

        // Encouragement
        $r('{{ENCOURAGEMENT}}', $this->renderEncouragement($get('encouragement')));

        return $html;
    }

    // ─────────────────────────────────────────────────────
    // PDF GENERATION via dompdf
    // ─────────────────────────────────────────────────────
    private function generatePdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultMediaType', 'print');
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    // ─────────────────────────────────────────────────────
    // EMAIL: Send PDF to client
    // ─────────────────────────────────────────────────────
    private function sendEmail(string $email, string $name, string $pcosType, string $pdfBinary): void
    {
        try {
            $mailer = new Mailer();
            $subject = "Your 90-Day PCOS Protocol — $pcosType Type";
            $body = "<p>Hi " . $this->esc($name) . ",</p>"
                . "<p>Your personalized 90-Day PCOS Protocol is attached.</p>"
                . "<p>Remember: consistency is the key. Follow the plan one day at a time, and you WILL see results.</p>"
                . "<p>With love,<br>1wellness Team</p>";

            // Save PDF temporarily for attachment
            $tempFile = tempnam(sys_get_temp_dir(), 'pcos_') . '.pdf';
            file_put_contents($tempFile, $pdfBinary);

            $mailer->send($email, $subject, $body, true, $tempFile, '90_Day_PCOS_Protocol.pdf');
            error_log("[PcosGenerator] Email sent to: $email with attachment");

            @unlink($tempFile);
        } catch (Exception $e) {
            error_log("[PcosGenerator] Email failed (non-blocking): " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────
    // UTILITY: Escape HTML
    // ─────────────────────────────────────────────────────
    private function esc($text): string
    {
        if (!is_string($text))
            return (string) $text;
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ─────────────────────────────────────────────────────
    // RENDER HELPERS: structured data → HTML
    // ─────────────────────────────────────────────────────
    private function renderGoals($goals): string
    {
        if (!is_array($goals))
            return '';
        $html = '';
        foreach ($goals as $g) {
            $html .= '<li style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px;">'
                . '<span style="color: #C77D63; font-weight: 700; font-size: 16px;">&#10022;</span> '
                . '<span>' . $this->esc($g) . '</span></li>';
        }
        return $html;
    }

    private function renderWeeklyActions($weeks): string
    {
        if (!is_array($weeks))
            return '';
        $html = '';
        foreach ($weeks as $w) {
            $actions = '';
            if (is_array($w['actions'] ?? null)) {
                foreach ($w['actions'] as $a) {
                    $actions .= '<li>' . $this->esc($a) . '</li>';
                }
            }
            $html .= '<tr>'
                . '<td class="week-num">Week ' . $this->esc($w['week'] ?? '') . '</td>'
                . '<td style="font-weight: 600; color: #0f3922;">' . $this->esc($w['focus'] ?? '') . '</td>'
                . '<td><ul style="margin: 0; padding-left: 16px; line-height: 1.8;">' . $actions . '</ul></td>'
                . '<td class="milestone">' . $this->esc($w['milestone'] ?? '') . '</td>'
                . '</tr>';
        }
        return $html;
    }

    private function renderRoutine($items): string
    {
        if (!is_array($items))
            return '';
        $html = '';
        foreach ($items as $item) {
            $html .= '<tr>'
                . '<td class="routine-time">' . $this->esc($item['time'] ?? '') . '</td>'
                . '<td><strong>' . $this->esc($item['action'] ?? '') . '</strong><br>'
                . '<span class="routine-why">' . $this->esc($item['why'] ?? '') . '</span></td>'
                . '</tr>';
        }
        return $html;
    }

    private function renderMealDays($days): string
    {
        if (!is_array($days))
            return '';
        $html = '';
        foreach ($days as $day) {
            $mealTypes = [
                ['key' => 'breakfast', 'emoji' => '&#127749;', 'label' => 'Breakfast'],
                ['key' => 'lunch', 'emoji' => '&#9728;&#65039;', 'label' => 'Lunch'],
                ['key' => 'dinner', 'emoji' => '&#127769;', 'label' => 'Dinner'],
                ['key' => 'snack', 'emoji' => '&#127822;', 'label' => 'Snack'],
            ];
            $mealsHtml = '';
            foreach ($mealTypes as $mt) {
                $meal = $day[$mt['key']] ?? [];
                $mealsHtml .= '<div class="meal-item">'
                    . '<div class="meal-type">' . $mt['emoji'] . ' ' . $mt['label'] . '</div>'
                    . '<div class="meal-name">' . $this->esc($meal['meal'] ?? '') . '</div>'
                    . '<div class="meal-desc">' . $this->esc($meal['description'] ?? '') . '</div>'
                    . '<div class="meal-benefit">' . $this->esc($meal['benefit'] ?? '') . '</div>'
                    . '</div>';
            }
            $html .= '<div class="meal-day" style="page-break-inside: avoid;">'
                . '<div class="meal-day-header">Day ' . $this->esc($day['day'] ?? '') . '</div>'
                . '<div class="meal-grid">' . $mealsHtml . '</div>'
                . '</div>';
        }
        return $html;
    }

    private function renderSupplements($supplements): string
    {
        if (!is_array($supplements))
            return '';
        $html = '';
        foreach ($supplements as $s) {
            $note = !empty($s['note'])
                ? '<div class="supp-caution">&#9888; ' . $this->esc($s['note']) . '</div>'
                : '';
            $html .= '<div class="supp-card" style="page-break-inside: avoid;">'
                . '<div class="supp-icon supp-icon-supplement">&#128138;</div>'
                . '<div style="flex: 1;">'
                . '<div class="supp-name">' . $this->esc($s['name'] ?? '') . '</div>'
                . '<div style="margin-top: 6px;">'
                . '<span class="supp-dosage">' . $this->esc($s['dosage'] ?? '') . '</span> '
                . '<span class="supp-timing">' . $this->esc($s['timing'] ?? '') . '</span>'
                . '</div>'
                . '<div class="supp-benefit">&#10022; ' . $this->esc($s['benefit'] ?? '') . '</div>'
                . $note
                . '</div></div>';
        }
        return $html;
    }

    private function renderHerbalProtocols($herbs): string
    {
        if (!is_array($herbs))
            return '';
        $html = '';
        foreach ($herbs as $h) {
            $yoruba = !empty($h['yoruba_name'])
                ? '<div class="supp-yoruba">Local name: ' . $this->esc($h['yoruba_name']) . '</div>'
                : '';
            $caution = !empty($h['caution'])
                ? '<div class="supp-caution">&#9888; ' . $this->esc($h['caution']) . '</div>'
                : '';
            $html .= '<div class="supp-card" style="page-break-inside: avoid;">'
                . '<div class="supp-icon supp-icon-herb">&#127807;</div>'
                . '<div style="flex: 1;">'
                . '<div class="supp-name">' . $this->esc($h['herb'] ?? '') . '</div>'
                . $yoruba
                . '<div class="supp-detail"><strong>Preparation:</strong> ' . $this->esc($h['preparation'] ?? '') . '</div>'
                . '<div style="margin-top: 4px;"><span class="supp-dosage">' . $this->esc($h['dosage'] ?? '') . '</span></div>'
                . '<div class="supp-benefit">&#10022; ' . $this->esc($h['benefit'] ?? '') . '</div>'
                . $caution
                . '</div></div>';
        }
        return $html;
    }

    private function renderLifestyleTips($tips): string
    {
        if (!is_array($tips))
            return '';
        $html = '';
        foreach ($tips as $t) {
            $html .= '<div class="tip-card" style="page-break-inside: avoid;">'
                . '<span class="tip-category">' . $this->esc($t['category'] ?? '') . '</span>'
                . '<div>'
                . '<div class="tip-text">' . $this->esc($t['tip'] ?? '') . '</div>'
                . '<div class="tip-detail">' . $this->esc($t['detail'] ?? '') . '</div>'
                . '</div></div>';
        }
        return $html;
    }

    private function renderTrackingGuidance($items): string
    {
        if (!is_array($items))
            return '';
        $html = '';
        foreach ($items as $t) {
            $html .= '<tr>'
                . '<td style="font-weight: 600; color: #0f3922;">' . $this->esc($t['what'] ?? '') . '</td>'
                . '<td><span style="background: #EBF5FB; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;">' . $this->esc($t['frequency'] ?? '') . '</span></td>'
                . '<td>' . $this->esc($t['how'] ?? '') . '</td>'
                . '<td style="font-style: italic; color: #666;">' . $this->esc($t['why'] ?? '') . '</td>'
                . '</tr>';
        }
        return $html;
    }

    private function renderEncouragement($text): string
    {
        if (!is_string($text))
            return '';
        $paragraphs = array_filter(explode("\n", $text), function ($p) {
            return trim($p) !== ''; });
        $html = '';
        foreach ($paragraphs as $p) {
            $html .= '<p>' . $this->esc(trim($p)) . '</p>';
        }
        return $html;
    }

    // ─────────────────────────────────────────────────────
    // FALLBACK CONTENT per PCOS type
    // ─────────────────────────────────────────────────────
    private function getFallbackContent(string $pcosType, string $name): array
    {
        $fallbacks = [
            'Insulin-Resistant' => [
                'summary' => "$name, your body is showing signs of insulin resistance — the most common driver of PCOS, affecting about 70% of women with this condition. Your cells have become less responsive to insulin, causing your pancreas to produce more. This excess insulin signals your ovaries to produce more testosterone than needed, disrupting your cycle and causing symptoms like weight gain, fatigue, and cravings. Our 90-day protocol focuses on restoring insulin sensitivity through strategic nutrition, targeted supplements, and lifestyle changes that have helped hundreds of women reclaim their hormonal balance.",
                'root_cause' => "Insulin-resistant PCOS develops when your cells become less responsive to the hormone insulin. Think of insulin as a key that unlocks your cells to allow glucose (energy) in. When the lock gets \"stiff,\" your body produces more keys (insulin), flooding your bloodstream.\n\nThis excess insulin does something unexpected — it tells your ovaries to produce more testosterone. This is why you may experience symptoms like weight gain around the belly, acne, hair thinning, and irregular periods. It's not your fault — it's a metabolic pattern that can be reversed.\n\nThe good news? Insulin-resistant PCOS is the most treatable type. By changing what you eat, when you eat, and adding targeted supplements, you can restore insulin sensitivity within weeks. Many women see their first improvements in energy and cravings within 7-14 days.",
                'goals' => ['Restore insulin sensitivity and stabilize blood sugar', 'Reduce excess androgen (testosterone) levels', 'Re-establish regular menstrual cycles', 'Reduce belly fat and inflammation', 'Build sustainable eating habits that support hormonal balance'],
            ],
            'Inflammatory' => [
                'summary' => "$name, your symptoms point to inflammatory PCOS — a type driven by chronic low-grade inflammation that disrupts your entire hormonal system. Your body's immune system is in a state of constant alert, producing inflammatory molecules that interfere with ovulation and drive androgen production. This explains symptoms like fatigue, joint pain, skin issues, and digestive problems. Our protocol targets the root inflammation through anti-inflammatory nutrition, gut healing, and calming herbal remedies.",
                'root_cause' => "Inflammatory PCOS is driven by chronic, low-grade inflammation — your body's immune system is turned up too high, like a thermostat stuck on maximum. This constant inflammatory state disrupts ovulation and triggers the ovaries to produce excess androgens.\n\nThe inflammation can come from many sources: gut imbalances, food sensitivities, environmental toxins, chronic stress, or poor sleep. Common signs include elevated CRP on blood tests, headaches, joint pain, skin rashes, and persistent fatigue that sleep doesn't fix.\n\nThe key to healing inflammatory PCOS is calming the immune system. This means removing inflammatory triggers (certain foods, toxins), adding powerful anti-inflammatory foods and herbs, and healing the gut — where 70% of your immune system lives.",
                'goals' => ['Reduce systemic inflammation markers', 'Heal gut lining and restore microbiome', 'Eliminate dietary inflammation triggers', 'Reduce androgen overproduction', 'Restore regular ovulation and cycles'],
            ],
            'Adrenal' => [
                'summary' => "$name, your profile suggests adrenal PCOS — a type driven by chronic stress overwhelming your adrenal glands. Unlike other types, your elevated androgens come primarily from your adrenal glands, not your ovaries. This means your DHEA-S is likely elevated while testosterone may be normal. The constant stress response has thrown your cortisol rhythm off balance, disrupting sleep, energy, and reproductive hormones. Our protocol focuses on deep stress recovery, adrenal nourishment, and gentle restoration.",
                'root_cause' => "Adrenal PCOS is unique — it's driven by stress, not insulin or inflammation. Your adrenal glands, which sit on top of your kidneys, produce stress hormones (cortisol) and androgens (DHEA-S). When you've been under chronic stress — whether emotional, physical, or environmental — these glands go into overdrive.\n\nThe excess DHEA-S disrupts your reproductive hormones just like testosterone would, causing PCOS symptoms. But crucially, your ovaries may be functioning relatively normally. This is why standard PCOS treatments often don't work for this type.\n\nHealing adrenal PCOS requires a fundamentally different approach: aggressive stress reduction, gentle movement (no intense exercise), nourishing your adrenals with adaptogenic herbs, and rebuilding your sleep-wake cycle. This type responds beautifully to the right protocol.",
                'goals' => ['Restore healthy cortisol rhythm', 'Reduce DHEA-S to normal range', 'Rebuild deep, restorative sleep', 'Calm the nervous system through adaptogens', 'Re-establish regular menstrual cycles'],
            ],
            'Post-Pill' => [
                'summary' => "$name, your symptoms align with post-pill PCOS — a temporary hormonal disruption that occurs after stopping hormonal birth control. Your body was relying on synthetic hormones for cycle regulation, and now it's struggling to restart its own production. This causes a temporary surge in androgens, leading to acne, hair changes, and irregular periods. The great news? This type is typically the most temporary and responsive to targeted support.",
                'root_cause' => "Post-pill PCOS occurs when your body's natural hormone production was suppressed by hormonal contraceptives and now struggles to restart. While on the pill, your ovaries were essentially \"sleeping\" — the synthetic hormones handled everything.\n\nWhen you stop, your body experiences a withdrawal effect. Your ovaries wake up and often overshoot, producing excess androgens temporarily. Your liver also needs to clear the accumulated synthetic hormones. This combination creates PCOS-like symptoms even if you never had hormonal issues before.\n\nThe key indicator is timing: if your symptoms started after stopping birth control, this is likely your type. Post-pill PCOS typically resolves within 3-12 months WITH the right support. Without support, it can persist longer as the body struggles to find its rhythm.",
                'goals' => ['Support the body\'s natural hormone restart', 'Clear residual synthetic hormones via liver support', 'Reduce temporary androgen surge', 'Restore natural ovulation within 90 days', 'Rebuild healthy menstrual cycles'],
            ],
        ];

        $base = $fallbacks[$pcosType] ?? $fallbacks['Insulin-Resistant'];

        return array_merge($base, [
            'phase_1_title' => 'Foundation & Reset',
            'phase_1_focus' => 'Building the base',
            'phase_1_description' => 'The first 30 days focus on resetting your body\'s baseline. You\'ll eliminate inflammatory triggers, establish core daily habits, and begin your supplement protocol. Expect some adjustment during the first week — this is your body recalibrating.',
            'phase_1_weekly_actions' => [
                ['week' => 1, 'focus' => 'Clean Start', 'actions' => ['Remove processed sugar and refined carbs', 'Start morning hydration ritual', 'Begin sleep hygiene protocol', 'Set up tracking journal'], 'milestone' => 'Established new morning routine'],
                ['week' => 2, 'focus' => 'Building Habits', 'actions' => ['Introduce first supplement', 'Start gentle movement daily', 'Meal prep for the week', 'Practice evening wind-down'], 'milestone' => 'Consistent daily routine'],
                ['week' => 3, 'focus' => 'Deepening', 'actions' => ['Add herbal tea protocol', 'Increase vegetable intake', 'Begin stress-reduction practice', 'Track energy patterns'], 'milestone' => 'Noticing energy improvements'],
                ['week' => 4, 'focus' => 'Consolidation', 'actions' => ['Full supplement stack active', 'Consistent meal timing', 'Regular movement pattern', 'Review and adjust'], 'milestone' => 'Foundation habits locked in'],
            ],
            'phase_2_title' => 'Acceleration & Healing',
            'phase_2_focus' => 'Deepening the healing',
            'phase_2_description' => 'With your foundation set, Phase 2 intensifies the protocol. Your body is now adapted to the new rhythm, and you can push deeper into hormonal restoration. This is where most women start seeing visible changes.',
            'phase_2_weekly_actions' => [
                ['week' => 5, 'focus' => 'Intensification', 'actions' => ['Introduce advanced herbal protocol', 'Increase movement intensity slightly', 'Optimize meal timing for hormones', 'Deeper stress management'], 'milestone' => 'Visible symptom improvement'],
                ['week' => 6, 'focus' => 'Optimization', 'actions' => ['Fine-tune supplement dosages', 'Add cycle-syncing awareness', 'Increase anti-inflammatory foods', 'Sleep optimization'], 'milestone' => 'Energy noticeably better'],
                ['week' => 7, 'focus' => 'Expansion', 'actions' => ['Try new hormone-friendly recipes', 'Increase daily movement duration', 'Deepen mindfulness practice', 'Social support building'], 'milestone' => 'Feeling stronger and more confident'],
                ['week' => 8, 'focus' => 'Integration', 'actions' => ['All protocols running smoothly', 'Adjust based on tracked data', 'Plan Phase 3 goals', 'Celebrate progress'], 'milestone' => 'Protocols feel natural'],
            ],
            'phase_3_title' => 'Transformation & Sustainability',
            'phase_3_focus' => 'Locking in results',
            'phase_3_description' => 'The final phase is about locking in your results and building habits that last beyond the 90 days. By now your body is responding to the protocol, and the goal is to make this your new normal.',
            'phase_3_weekly_actions' => [
                ['week' => 9, 'focus' => 'Mastery', 'actions' => ['Advanced meal planning skills', 'Peak supplement optimization', 'Consistent exercise routine', 'Stress resilience building'], 'milestone' => 'Confident in the protocol'],
                ['week' => 10, 'focus' => 'Fine-Tuning', 'actions' => ['Personalize based on results', 'Adjust herbs for maintenance', 'Build social accountability', 'Long-term planning'], 'milestone' => 'Protocol feels like lifestyle'],
                ['week' => 11, 'focus' => 'Sustainability', 'actions' => ['Create maintenance plan', 'Identify what works best for you', 'Build emergency backup plans', 'Prepare for beyond 90 days'], 'milestone' => 'Independence from strict protocol'],
                ['week' => 12, 'focus' => 'Celebration', 'actions' => ['Full progress review', 'Compare Day 1 vs Day 90', 'Set next 90-day goals', 'Share your transformation'], 'milestone' => 'Transformed and empowered!'],
            ],
            'morning_routine' => [
                ['time' => '6:00 AM', 'action' => 'Drink warm water with lemon and a pinch of cinnamon', 'why' => 'Kickstarts metabolism and supports insulin sensitivity'],
                ['time' => '6:15 AM', 'action' => '5 minutes of deep breathing or prayer/meditation', 'why' => 'Lowers cortisol before the day begins'],
                ['time' => '6:30 AM', 'action' => 'Take morning supplements with a light snack', 'why' => 'Better absorption with food'],
                ['time' => '7:00 AM', 'action' => 'Eat a protein-rich breakfast', 'why' => 'Stabilizes blood sugar for the morning'],
                ['time' => '7:15 AM', 'action' => '10-minute gentle walk or stretch', 'why' => 'Gets blood flowing and improves mood'],
                ['time' => '7:30 AM', 'action' => 'Apply seed cycling protocol if applicable', 'why' => 'Supports estrogen/progesterone balance'],
            ],
            'afternoon_routine' => [
                ['time' => '12:00 PM', 'action' => 'Balanced lunch with protein, fiber, and healthy fat', 'why' => 'Prevents afternoon energy crash'],
                ['time' => '12:30 PM', 'action' => 'Short walk after lunch (even 10 minutes)', 'why' => 'Dramatically improves post-meal blood sugar'],
                ['time' => '2:00 PM', 'action' => 'Herbal tea break (specific to your type)', 'why' => 'Therapeutic herbs work best with consistent timing'],
                ['time' => '3:30 PM', 'action' => 'Healthy snack if hungry, avoid sugary options', 'why' => 'Keeps blood sugar stable through afternoon'],
            ],
            'evening_routine' => [
                ['time' => '6:00 PM', 'action' => 'Dinner — largest vegetable portion of the day', 'why' => 'Anti-inflammatory nutrients support overnight repair'],
                ['time' => '7:00 PM', 'action' => 'Take evening supplements (magnesium, etc.)', 'why' => 'Magnesium supports sleep and hormone production'],
                ['time' => '8:30 PM', 'action' => 'Screens off — dim lights', 'why' => 'Blue light disrupts melatonin, which affects all hormones'],
                ['time' => '9:00 PM', 'action' => 'Warm bath or gentle stretching', 'why' => 'Signals nervous system to prepare for sleep'],
                ['time' => '9:30 PM', 'action' => 'Journaling or gratitude practice, then sleep', 'why' => 'Reduces cortisol, improves sleep quality'],
            ],
            'meal_plan' => [
                ['day' => 1, 'breakfast' => ['meal' => 'Boiled Plantain with Egg Sauce', 'description' => 'Ripe plantain boiled with scrambled eggs in tomato and pepper sauce', 'benefit' => 'Protein-first breakfast stabilizes blood sugar'], 'lunch' => ['meal' => 'Beans Porridge with Plantain', 'description' => 'Honey beans cooked with palm oil, peppers, and green plantain', 'benefit' => 'High fiber and plant protein for sustained energy'], 'dinner' => ['meal' => 'Grilled Fish with Efo Riro', 'description' => 'Fresh tilapia with spinach stew and a small portion of amala', 'benefit' => 'Omega-3 from fish reduces inflammation'], 'snack' => ['meal' => 'Tiger Nuts and Coconut', 'description' => 'A handful of tiger nuts with dried coconut chips', 'benefit' => 'Healthy fats and prebiotic fiber']],
                ['day' => 2, 'breakfast' => ['meal' => 'Moi Moi', 'description' => 'Steamed bean pudding with boiled eggs and vegetables', 'benefit' => 'High-protein, low-glycemic start'], 'lunch' => ['meal' => 'Ofada Rice with Ayamase', 'description' => 'Small portion of ofada rice with designer pepper sauce', 'benefit' => 'Unrefined grain with metabolism-boosting peppers'], 'dinner' => ['meal' => 'Pepper Soup with Goat Meat', 'description' => 'Light spicy broth with lean goat meat and herbs', 'benefit' => 'Anti-inflammatory spices support healing'], 'snack' => ['meal' => 'Garden Eggs with Peanut Butter', 'description' => 'Fresh garden eggs with natural groundnut paste', 'benefit' => 'Low-calorie, high-nutrient snack']],
                ['day' => 3, 'breakfast' => ['meal' => 'Oats with Banana and Groundnuts', 'description' => 'Rolled oats topped with sliced banana and crushed groundnuts', 'benefit' => 'Slow-releasing carbs with healthy fats'], 'lunch' => ['meal' => 'Vegetable Yam Porridge', 'description' => 'Yam porridge loaded with spinach, ugwu, and crayfish', 'benefit' => 'Iron-rich vegetables support energy'], 'dinner' => ['meal' => 'Grilled Chicken with Salad', 'description' => 'Seasoned chicken breast with fresh vegetable salad', 'benefit' => 'Lean protein supports muscle and hormone production'], 'snack' => ['meal' => 'Roasted Groundnuts', 'description' => 'A small handful of roasted groundnuts', 'benefit' => 'Magnesium-rich, hormone-supporting snack']],
                ['day' => 4, 'breakfast' => ['meal' => 'Akara with Pap', 'description' => 'Bean cakes with light millet pap', 'benefit' => 'Traditional protein-rich breakfast'], 'lunch' => ['meal' => 'Jollof Rice with Vegetables', 'description' => 'Tomato jollof with mixed vegetables and grilled chicken', 'benefit' => 'Lycopene from tomatoes fights inflammation'], 'dinner' => ['meal' => 'Ukwa (Breadfruit)', 'description' => 'Cooked breadfruit with palm oil and spices', 'benefit' => 'High fiber, supports gut health'], 'snack' => ['meal' => 'Watermelon and Seeds', 'description' => 'Fresh watermelon with a sprinkle of pumpkin seeds', 'benefit' => 'Hydration plus zinc from seeds']],
                ['day' => 5, 'breakfast' => ['meal' => 'Boiled Eggs with Sweet Potato', 'description' => 'Two boiled eggs with roasted sweet potato', 'benefit' => 'Balanced macros, slow-releasing energy'], 'lunch' => ['meal' => 'Gbegiri with Ewedu and Amala', 'description' => 'Bean soup with jute leaf soup and small amala', 'benefit' => 'Traditional combination rich in fiber and protein'], 'dinner' => ['meal' => 'Baked Fish with Steamed Vegetables', 'description' => 'Whole baked mackerel with steamed cabbage and carrots', 'benefit' => 'Omega-3 fatty acids support hormone balance'], 'snack' => ['meal' => 'Fresh Coconut', 'description' => 'Fresh coconut pieces', 'benefit' => 'MCT fats support metabolism']],
                ['day' => 6, 'breakfast' => ['meal' => 'Smoothie Bowl', 'description' => 'Banana, spinach, groundnut smoothie with seeds', 'benefit' => 'Nutrient-dense, easy to digest'], 'lunch' => ['meal' => 'Fried Rice with Grilled Turkey', 'description' => 'Vegetable fried rice with lean turkey', 'benefit' => 'Balanced meal with protein and vegetables'], 'dinner' => ['meal' => 'Ogbono Soup with Fish', 'description' => 'Light ogbono soup with stockfish and vegetables', 'benefit' => 'Healthy fats and gut-friendly mucilage'], 'snack' => ['meal' => 'Dates and Almonds', 'description' => 'Three dates with a few almonds', 'benefit' => 'Natural sweetness with healthy fats']],
                ['day' => 7, 'breakfast' => ['meal' => 'Plantain Pancakes', 'description' => 'Mashed ripe plantain blended with eggs and fried lightly', 'benefit' => 'Gluten-free, nutrient-rich breakfast'], 'lunch' => ['meal' => 'Coconut Rice with Chicken', 'description' => 'Rice cooked in coconut milk with grilled chicken', 'benefit' => 'Healthy coconut fats support satiety'], 'dinner' => ['meal' => 'Efo Riro with Assorted Meat', 'description' => 'Rich spinach stew with lean beef and stockfish', 'benefit' => 'Iron and protein-rich for rebuilding'], 'snack' => ['meal' => 'Fruit Salad', 'description' => 'Mixed fruits — papaya, pineapple, watermelon', 'benefit' => 'Enzymes support digestion']],
            ],
            'supplements' => [
                ['name' => 'Inositol (Myo + D-Chiro)', 'dosage' => '2000mg Myo + 50mg D-Chiro daily', 'timing' => 'Morning and evening, split dose', 'benefit' => 'The #1 evidence-based supplement for PCOS — improves insulin sensitivity and ovulation', 'note' => 'Take consistently for at least 3 months'],
                ['name' => 'Vitamin D3', 'dosage' => '2000-4000 IU daily', 'timing' => 'Morning with a fatty meal', 'benefit' => 'Most women with PCOS are deficient; supports immune function and hormone production', 'note' => 'Get blood levels tested if possible'],
                ['name' => 'Magnesium Glycinate', 'dosage' => '300-400mg daily', 'timing' => 'Evening before bed', 'benefit' => 'Supports sleep, reduces anxiety, helps with insulin sensitivity', 'note' => 'Start with 200mg and increase gradually'],
                ['name' => 'Omega-3 Fish Oil', 'dosage' => '1000-2000mg EPA+DHA daily', 'timing' => 'With meals', 'benefit' => 'Powerful anti-inflammatory, supports brain health and hormone balance', 'note' => 'Choose a quality brand tested for mercury'],
                ['name' => 'Zinc', 'dosage' => '15-30mg daily', 'timing' => 'With dinner', 'benefit' => 'Supports immune function, reduces androgens, helps with skin', 'note' => 'Take with food to avoid nausea'],
            ],
            'herbal_protocols' => [
                ['herb' => 'Bitter Leaf', 'yoruba_name' => 'Ewuro', 'preparation' => 'Wash and squeeze leaves, drink the juice or brew as tea', 'dosage' => '1 cup of bitter leaf juice or tea, 2-3 times per week', 'benefit' => 'Traditionally used for blood sugar regulation and liver detox', 'caution' => 'Very bitter — can be diluted with water'],
                ['herb' => 'Moringa', 'yoruba_name' => 'Ewe Igbale', 'preparation' => 'Use dried moringa powder in smoothies, teas, or with meals', 'dosage' => '1-2 teaspoons daily', 'benefit' => 'Rich in antioxidants, supports blood sugar balance and inflammation', 'caution' => 'Generally safe; start with small amounts'],
                ['herb' => 'Fenugreek Seeds', 'yoruba_name' => 'Ewedu Seed / Hulba', 'preparation' => 'Soak seeds overnight, drink the water and chew the seeds in the morning', 'dosage' => '1 tablespoon soaked seeds daily', 'benefit' => 'Improves insulin sensitivity and may help with milk production', 'caution' => 'Can cause mild digestive discomfort initially'],
                ['herb' => 'Turmeric', 'yoruba_name' => 'Ata-ile pupa', 'preparation' => 'Fresh turmeric grated into warm water with black pepper, or added to soups', 'dosage' => '1-2 teaspoons daily with black pepper (for absorption)', 'benefit' => 'Powerful anti-inflammatory — reduces CRP and supports liver function', 'caution' => 'May interact with blood thinners'],
            ],
            'lifestyle_tips' => [
                ['category' => 'Sleep', 'tip' => 'Go to bed by 10 PM every night', 'detail' => 'Growth hormone and reproductive hormones are produced during deep sleep between 10 PM and 2 AM. Missing this window disrupts your entire hormonal cascade.'],
                ['category' => 'Stress', 'tip' => 'Practice 5-minute deep breathing twice daily', 'detail' => 'Activates the parasympathetic nervous system, lowering cortisol. Try box breathing: inhale 4 seconds, hold 4, exhale 4, hold 4.'],
                ['category' => 'Movement', 'tip' => 'Walk for 30 minutes after your largest meal', 'detail' => 'Post-meal walking reduces blood sugar spikes by up to 30%. This is one of the most powerful insulin-sensitizing habits you can build.'],
                ['category' => 'Hydration', 'tip' => 'Drink 2-3 liters of water daily', 'detail' => 'Dehydration concentrates hormones and toxins. Add lemon or cucumber for extra benefits.'],
                ['category' => 'Environment', 'tip' => 'Switch to natural cleaning and beauty products', 'detail' => 'Many commercial products contain endocrine disruptors (xenoestrogens) that worsen PCOS. Switch gradually to natural alternatives.'],
                ['category' => 'Social', 'tip' => 'Connect with other women on this journey', 'detail' => 'Isolation worsens stress. Find a PCOS support group online or share your journey with a trusted friend.'],
                ['category' => 'Mindset', 'tip' => 'Track wins, not just symptoms', 'detail' => 'Your brain is wired to notice negatives. Actively recording daily wins rewires your mindset and keeps motivation high.'],
                ['category' => 'Cycle', 'tip' => 'Begin tracking your menstrual cycle', 'detail' => 'Even if irregular, noting any spotting, cramps, or mood changes helps you see patterns and progress over the 90 days.'],
            ],
            'tracking_guidance' => [
                ['what' => 'Energy Level', 'frequency' => 'Daily', 'how' => 'Rate 1-10 each morning and evening', 'why' => 'First indicator of metabolic improvement'],
                ['what' => 'Menstrual Cycle', 'frequency' => 'Daily', 'how' => 'Note any bleeding, spotting, cramps, or PMS symptoms', 'why' => 'Shows ovulation is returning'],
                ['what' => 'Sleep Quality', 'frequency' => 'Daily', 'how' => 'Rate 1-10; note time to bed and wake', 'why' => 'Sleep quality directly affects hormone production'],
                ['what' => 'Weight & Measurements', 'frequency' => 'Weekly', 'how' => 'Same conditions — morning, before eating, same clothes', 'why' => 'Tracks body composition changes beyond the scale'],
                ['what' => 'Mood & Stress', 'frequency' => 'Daily', 'how' => 'Brief journal entry or 1-10 rating', 'why' => 'Cortisol patterns show stress management progress'],
                ['what' => 'Skin & Hair Changes', 'frequency' => 'Weekly', 'how' => 'Take photos; note acne, oiliness, hair texture', 'why' => 'Visible androgen markers that show hormonal improvement'],
            ],
            'encouragement' => "$name, you've already done something powerful — you've decided to take control of your health. That decision alone separates you from those who wait and hope things get better on their own.\n\nThe next 90 days won't always be easy. There will be days when you feel frustrated, days when cravings are strong, and days when you wonder if it's working. In those moments, remember this: your body WANTS to heal. Every meal plan you follow, every supplement you take, every night you sleep on time — you're sending your body the signal that it's safe to restore balance.\n\nAt Day 90, imagine waking up with steady energy, clearer skin, and a cycle that's starting to find its rhythm. That future is not a dream — it's what happens when you show up consistently. You are stronger than your PCOS, and you have an entire protocol designed just for you. Let's do this together.",
        ]);
    }
}
