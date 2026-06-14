<?php
/**
 * A/B Engine Funnel Router
 *
 * Entry point for funnel landing traffic, wired via .htaccess:
 *   RewriteRule ^(pcos|acne|weight|mens)/?$ backend/router.php?funnel=$1 [L,QSA]
 *
 * Responsibilities:
 *  1. Read/refresh the sticky assignment cookie (1w_exp) — JSON map of
 *     experiment_id => variant_id, 90-day TTL, shared across subdomains.
 *  2. Run Thompson Sampling assignment for every running experiment on
 *     this funnel the session hasn't been assigned to yet.
 *  3. Serve the structural variant directory, or the control HTML with
 *     window.__VARIANT_OVERRIDES + an anti-flicker guard injected.
 *  4. Log a server-side `view` exposure event (deduped per session/day).
 *
 * The assignment cookie is strictly functional (no profiling) and is
 * exempt from GDPR consent gating; client event logging is not (handled
 * in js/tracking.js).
 */

// NOTE: APP_ROOT is intentionally not defined here — config/env_loader
// resolves it the same way as every other API entry point, keeping the
// SQLite path identical across endpoints in local development.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/ExperimentManager.php';

const AB_COOKIE = '1w_exp';
const AB_SESSION_COOKIE = '1w_sid';
const AB_COOKIE_TTL = 7776000; // 90 days

$funnel = preg_replace('/[^a-z]/', '', strtolower($_GET['funnel'] ?? ''));
$rootPath = dirname(__DIR__); // repo root (backend/..)

if (!in_array($funnel, ExperimentManager::FUNNELS) || !is_dir($rootPath . '/' . $funnel)) {
    http_response_code(404);
    echo 'Funnel not found';
    exit;
}

$controlFile = $rootPath . '/' . $funnel . '/index.html';

/** Serve a file verbatim and stop. */
function serveRaw($file)
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    readfile($file);
    exit;
}

/** Cookie domain: share across *.1wellness.club, plain host elsewhere. */
function abCookieDomain()
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = preg_replace('/:\d+$/', '', $host);
    if (substr($host, -strlen('1wellness.club')) === '1wellness.club') {
        return '.1wellness.club';
    }
    return ''; // localhost / IPs: host-only cookie
}

function setAbCookie($name, $value)
{
    setcookie($name, $value, [
        'expires' => time() + AB_COOKIE_TTL,
        'path' => '/',
        'domain' => abCookieDomain(),
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false, // tracking.js must read experiment/variant ids
        'samesite' => 'Lax',
    ]);
}

try {
    $db = Database::getInstance();
    if ($db->isFileStorage()) {
        // No database — degrade gracefully to the control page.
        serveRaw($controlFile);
    }

    $manager = new ExperimentManager();

    // --- 1. Session id (shared with js/webhook-manager.js localStorage id when possible)
    $sessionId = $_COOKIE[AB_SESSION_COOKIE] ?? null;
    if (!$sessionId || !preg_match('/^[A-Za-z0-9_\-\.]{8,100}$/', $sessionId)) {
        $sessionId = 'sess_' . time() . '_' . bin2hex(random_bytes(6));
        setAbCookie(AB_SESSION_COOKIE, $sessionId);
    }

    // --- 2. Existing assignment map from cookie
    $cookieMap = [];
    if (!empty($_COOKIE[AB_COOKIE])) {
        $decoded = json_decode($_COOKIE[AB_COOKIE], true);
        if (is_array($decoded)) {
            $cookieMap = $decoded;
        }
    }

    // --- 3. Assign for all running experiments on this funnel
    $assignments = $manager->assignSession($sessionId, $funnel, $cookieMap);

    // Refresh cookie (merge: other funnels' assignments preserved)
    $newMap = $cookieMap;
    foreach ($assignments as $expId => $variant) {
        $newMap[(string) $expId] = (int) $variant['id'];
    }
    if ($newMap !== $cookieMap) {
        setAbCookie(AB_COOKIE, json_encode($newMap));
    }

    if (empty($assignments)) {
        serveRaw($controlFile); // no live experiments — zero overhead path
    }

    // --- 4. Resolve what to serve & collect element overrides
    $serveFile = $controlFile;
    $mergedOverrides = [];
    $abContext = []; // exposed to tracking.js for event attribution

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $url = '/' . $funnel . '/';

    // Skip exposure logging for obvious bots (assignment still sticky)
    $isBot = (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|lighthouse|pingdom|uptime/i', $ua);

    foreach ($assignments as $expId => $variant) {
        $exp = $variant['experiment'];
        $abContext[] = [
            'experiment_id' => (int) $expId,
            'variant_id' => (int) $variant['id'],
            'stage' => $exp['stage'],
            'variant_type' => $variant['type'],
        ];

        if ($variant['type'] === 'structural' && !empty($variant['directory'])) {
            $candidate = $rootPath . '/' . basename($variant['directory']) . '/index.html';
            if (file_exists($candidate)) {
                $serveFile = $candidate;
            }
        } elseif ($variant['type'] === 'element' && !empty($variant['overrides'])) {
            $ov = is_string($variant['overrides']) ? json_decode($variant['overrides'], true) : $variant['overrides'];
            if (is_array($ov)) {
                foreach ($ov as $kind => $map) {
                    if (!isset($mergedOverrides[$kind])) {
                        $mergedOverrides[$kind] = [];
                    }
                    $mergedOverrides[$kind] = array_merge($mergedOverrides[$kind], $map);
                }
            }
        }

        if (!$isBot) {
            $manager->logExposure($sessionId, (int) $expId, (int) $variant['id'], $funnel, $url, $ip, $ua);
        }
    }

    // --- 5. Inject runtime context before </head>
    $html = file_get_contents($serveFile);

    $inject = "\n<script>\n"
        . 'window.__AB = ' . json_encode([
            'session_id' => $sessionId,
            'funnel' => $funnel,
            'assignments' => $abContext,
        ], JSON_UNESCAPED_SLASHES) . ";\n"
        . 'window.__VARIANT_OVERRIDES = ' . json_encode((object) $mergedOverrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n"
        . "</script>\n";

    if (!empty($mergedOverrides)) {
        // Anti-flicker: hide until the applier in tracking.js releases,
        // with a 300ms hard timeout fallback.
        $inject .= "<style id=\"ab-antiflicker\">html{opacity:0 !important}</style>\n"
            . "<script>window.__abReveal=function(){var s=document.getElementById('ab-antiflicker');if(s)s.parentNode.removeChild(s);};"
            . "setTimeout(window.__abReveal,300);</script>\n";
    }

    if (stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', $inject . '</head>', $html, 1);
    } else {
        $html = $inject . $html;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo $html;
} catch (Exception $e) {
    error_log('AB router error: ' . $e->getMessage());
    serveRaw($controlFile); // experiments must never take a funnel down
}
