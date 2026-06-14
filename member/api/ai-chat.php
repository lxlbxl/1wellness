<?php
/**
 * AI Specialist chat endpoint.
 *
 * POST body (JSON):
 *   message  string               Current user message
 *   history  [{role, content}]    Prior turns (max 20 messages sent to API)
 *
 * Response:
 *   {success, reply, flagged}
 *
 * Compliance guardrail (non-negotiable):
 *   Server-side red-flag patterns immediately return the escalation message
 *   without calling the AI API.  The system prompt also embeds the same rules.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/classes/Database.php';
require_once __DIR__ . '/../../backend/classes/MemberAuth.php';
require_once __DIR__ . '/../config/conditions.php';

$auth = new MemberAuth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$message = trim($body['message'] ?? '');
$history = is_array($body['history'] ?? null) ? $body['history'] : [];

if ($message === '' || strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid message']);
    exit;
}

// Limit history to last 20 messages to control token spend
if (count($history) > 20) {
    $history = array_slice($history, -20);
}

$user   = $auth->getCurrentUser();
$userId = $user['user_id'];
$db     = Database::getInstance();

$dbUser    = $db->fetch("SELECT condition, age, streak_count FROM users WHERE id = ?", [$userId]);
$condition = strtolower(trim($dbUser['condition'] ?? 'pcos'));
$age       = (int)($dbUser['age'] ?? 0);
$streak    = (int)($dbUser['streak_count'] ?? 0);

$conditionCfg = ConditionsRegistry::get($condition);
$persona      = $conditionCfg['ai_persona'];
$subBrand     = $conditionCfg['sub_brand'];
$concern      = $conditionCfg['terminology']['concern'];
$outcome      = $conditionCfg['terminology']['outcome'];

// --- Hard-coded red-flag escalation (non-negotiable) ---
const ESCALATION_RESPONSE = "This sounds like something you should discuss directly with your doctor or a qualified healthcare provider — they're best placed to give you safe, personalised guidance for your situation. If this is a medical emergency, please call emergency services or go to your nearest hospital immediately.";

$redFlagPatterns = [
    '/\b(suicid|self.?harm|kill myself|hurt myself|end my life)\b/i',
    '/\b(chest pain|heart attack|stroke|can.t breathe|severe bleed|unconscious|faint)\b/i',
    '/\b(drug interaction|medication interaction|combining.*med|which.*med.*safe|stop.*medication|change.*dose)\b/i',
    '/\b(pregnant|pregnancy|trying to conceive)\b.*\b(medication|drug|supplement|herb|dose|pill)\b/i',
    '/\b(medication|drug|pill|dose)\b.*\b(pregnant|pregnancy|nursing|breastfeed)\b/i',
];

foreach ($redFlagPatterns as $pattern) {
    if (preg_match($pattern, $message)) {
        echo json_encode([
            'success' => true,
            'reply'   => ESCALATION_RESPONSE,
            'flagged' => true,
        ]);
        exit;
    }
}

// --- Build system prompt ---
$memberCtx = '';
if ($age > 0) $memberCtx .= " The member is {$age} years old.";
if ($streak > 0) $memberCtx .= " They have a current daily-logging streak of {$streak} days.";

$systemPrompt = "{$persona}

You work for {$subBrand}, a natural wellness programme focused on {$concern}. Your role is to help members achieve {$outcome} through evidence-based nutrition, lifestyle, and herbal support.{$memberCtx}

COMPLIANCE RULES — strictly enforced, never override:
1. You are a wellness coach, NOT a medical professional.
2. NEVER diagnose any condition or disease.
3. NEVER claim to cure, treat, or reverse any medical condition.
4. NEVER advise on prescription medication dosages or drug interactions.
5. For pregnancy, severe symptoms, medication questions, or any medical emergency, ALWAYS respond: \"This is something to discuss with your doctor or healthcare provider — they can give you safe, personalised guidance.\"
6. You may discuss general nutrition, lifestyle habits, sleep hygiene, stress management, and well-evidenced supplements (e.g., inositol, spearmint tea, zinc, vitamin D) in an educational context.
7. Frame guidance as \"research suggests...\" or \"many people find that...\" — never as certainty or prescription.
8. Keep responses warm, concise, and practical — 2 to 4 short paragraphs maximum.";

// --- Build messages for API ---
$messages = [];
foreach ($history as $h) {
    $role    = ($h['role'] === 'assistant') ? 'assistant' : 'user';
    $content = trim($h['content'] ?? '');
    if ($content !== '') {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

// --- Call Claude API ---
$apiKey = ANTHROPIC_API_KEY;
if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'AI service not configured. Please contact support.']);
    exit;
}

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 600,
    'system'     => $systemPrompt,
    'messages'   => $messages,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false || $httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'AI service unavailable. Please try again shortly.']);
    exit;
}

$resp  = json_decode($raw, true);
$reply = trim($resp['content'][0]['text'] ?? '');

if ($reply === '') {
    echo json_encode(['success' => false, 'error' => 'No response received. Please try again.']);
    exit;
}

echo json_encode(['success' => true, 'reply' => $reply, 'flagged' => false]);
