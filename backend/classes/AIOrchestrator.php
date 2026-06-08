<?php
class AIOrchestrator
{
    private $db;
    private $settings;
    private $provider; // 'openai', 'anthropic', 'openrouter', 'gemini'
    private $apiKey;
    private $model;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadSettings();
    }

    private function loadSettings()
    {
        require_once __DIR__ . '/Settings.php';
        $settings = new Settings();

        $this->provider = $settings->get('ai_provider', 'openrouter');
        $this->apiKey = $settings->get('ai_api_key', '');
        $this->model = $settings->get('ai_model', 'google/gemini-2.0-flash-exp:free');
    }

    public function generateResponse($systemPromptKey, $userPrompt, $variables = [])
    {
        // 1. Get System Prompt
        $systemPrompt = $this->getSystemPrompt($systemPromptKey);

        // 2. Inject Variables into System Prompt
        foreach ($variables as $key => $val) {
            $systemPrompt = str_replace("[$key]", $val, $systemPrompt);
        }

        // 3. Call API
        return $this->callStartAPI($systemPrompt, $userPrompt);
    }

    private function getSystemPrompt($key)
    {
        $row = $this->db->fetch("SELECT prompt_text FROM system_prompts WHERE prompt_key = :key", [':key' => $key]);
        return $row ? $row['prompt_text'] : "You are a helpful assistant.";
    }

    private function callStartAPI($system, $user)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API Key missing'];
        }

        $url = '';
        $headers = [];
        $body = [];

        // Simplified implementation focusing on OpenRouter/OpenAI compatible format
        // Most providers (DeepSeek, Fireworks, etc) support OpenAI format too.

        if ($this->provider === 'openrouter') {
            $url = 'https://openrouter.ai/api/v1/chat/completions';
            $headers = [
                "Authorization: Bearer " . $this->apiKey,
                "HTTP-Referer: https://1wellness.club", // Required by OpenRouter
                "X-Title: 1wellness",
                "Content-Type: application/json"
            ];
        } elseif ($this->provider === 'openai') {
            $url = 'https://api.openai.com/v1/chat/completions';
            $headers = [
                "Authorization: Bearer " . $this->apiKey,
                "Content-Type: application/json"
            ];
        } elseif ($this->provider === 'gemini') {
            // Google uses a different format usually, but let's assume OpenAI compat if possible or implement direct.
            // For now, let's stick to OpenRouter which hosts Gemini.
            // If direct Gemini:
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
            // payload differs. 
            // TO KEEP IT SIMPLE: I recommend using OpenRouter for multiple models including Gemini.
            // But if specific Gemini Direct implementation is needed:
            return $this->callGeminiDirect($system, $user);
        }

        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user]
            ]
        ];

        $response = $this->httpRequest($url, $headers, $body);

        if (isset($response['error']))
            return $response;

        $json = json_decode($response, true);
        if (isset($json['choices'][0]['message']['content'])) {
            return $json['choices'][0]['message']['content'];
        }

        return $json; // Debug info or error
    }

    private function callGeminiDirect($system, $user)
    {
        // Gemini Direct Implementation
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        // Combine system and user into one prompt or use system_instruction if supported by model version
        $fullPrompt = $system . "\n\nUser Request: " . $user;

        $body = [
            'contents' => [
                ['parts' => [['text' => $fullPrompt]]]
            ]
        ];

        $response = $this->httpRequest($url, ["Content-Type: application/json"], $body);
        if (isset($response['error']))
            return $response;

        $json = json_decode($response, true);

        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return $json['candidates'][0]['content']['parts'][0]['text'];
        }

        return $json;
    }

    private function httpRequest($url, $headers, $body)
    {
        $jsonBody = json_encode($body);

        // Try cURL if available
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local environments with cert issues
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                return ['error' => 'Curl error: ' . $error];
            }
            curl_close($ch);
            return $response;
        }

        // Fallback to file_get_contents (stream context)
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $jsonBody,
                'ignore_errors' => true,
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]
        ];

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['error' => 'HTTP request failed (no cURL, no file_get_contents support)'];
        }

        return $response;
    }
}
