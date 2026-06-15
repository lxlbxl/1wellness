<?php
/**
 * Referral system API.
 *
 * GET  ?action=get_link          — returns member's referral code, link, and stats
 * GET  ?action=track&code=XXX   — called from funnel pages to attribute a referral (no auth)
 * POST {action:convert, code, email} — called at purchase to mark a referral as purchased
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/MemberAuth.php';
require_once __DIR__ . '/../classes/Settings.php';

$db     = Database::getInstance();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Public: track referral click (no auth needed)
if ($action === 'track') {
    $code = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_GET['code'] ?? '')));
    if ($code) {
        $ref = $db->fetch("SELECT id, condition FROM referrals WHERE referral_code = ? AND status = 'pending'", [$code]);
        if ($ref) {
            // Store code in session/cookie so it survives funnel completion
            setcookie('1w_ref', $code, time() + 86400 * 30, '/', '', true, true);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Auth required for all other actions
$auth = new MemberAuth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$userId      = $currentUser['user_id'];

// Load user condition for same-condition referral messaging
$userRow   = $db->fetch("SELECT condition, sub_brand, first_name FROM users WHERE id = ?", [$userId]);
$condition = $userRow['condition'] ?? 'pcos';
$subBrand  = $userRow['sub_brand'] ?? 'CycleSync';
$firstName = $userRow['first_name'] ?? 'Member';

switch ($action) {
    case 'get_link':
        // Get or create referral code
        $ref = $db->fetch(
            "SELECT referral_code FROM referrals WHERE referrer_id = ? LIMIT 1",
            [$userId]
        );

        if (!$ref) {
            // Generate unique 8-char code
            do {
                $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $exists = $db->fetch("SELECT id FROM referrals WHERE referral_code = ?", [$code]);
            } while ($exists);

            $db->insert('referrals', [
                'referrer_id'   => $userId,
                'referral_code' => $code,
                'condition'     => $condition,
                'status'        => 'pending',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            $ref = ['referral_code' => $code];
        }

        $code = $ref['referral_code'];

        // Stats
        $stats = $db->fetch(
            "SELECT
                COUNT(*) AS total_referred,
                SUM(CASE WHEN status = 'purchased' THEN 1 ELSE 0 END) AS converted,
                SUM(reward_issued) AS rewards_earned
             FROM referrals WHERE referrer_id = ?",
            [$userId]
        ) ?: ['total_referred' => 0, 'converted' => 0, 'rewards_earned' => 0];

        $settings = Settings::getInstance();
        $siteUrl  = rtrim($settings->get('site_url', 'https://1wellness.club'), '/');

        // Condition-specific funnel landing pages
        $funnelPages = [
            'pcos'   => '/pcos/',
            'acne'   => '/acne/',
            'weight' => '/weight/',
            'mens'   => '/mens/',
        ];
        $funnel = $funnelPages[$condition] ?? '/';

        $referralLink = $siteUrl . $funnel . '?ref=' . $code;

        $conditionMessages = [
            'pcos'   => "I've been healing my hormones naturally with {$subBrand}. Check it out",
            'acne'   => "I've been clearing my skin naturally with {$subBrand}. Check it out",
            'weight' => "I've been managing my weight naturally with {$subBrand}. Check it out",
            'mens'   => "I've been boosting my vitality naturally with {$subBrand}. Check it out",
        ];
        $shareMessage = ($conditionMessages[$condition] ?? "I've been loving {$subBrand}") . ': ' . $referralLink;

        echo json_encode([
            'success'       => true,
            'code'          => $code,
            'link'          => $referralLink,
            'share_message' => $shareMessage,
            'sub_brand'     => $subBrand,
            'condition'     => $condition,
            'stats'         => [
                'referred'  => (int)$stats['total_referred'],
                'converted' => (int)$stats['converted'],
                'rewards'   => (int)$stats['rewards_earned'],
            ],
        ]);
        break;

    // Internal: called by AutomationOrchestrator / flutterwave-webhook after confirmed purchase
    case 'convert':
        $code    = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['code'] ?? '')));
        $email   = trim($_POST['email'] ?? '');
        $newUser = (int)($_POST['referred_user_id'] ?? 0);

        if (!$code) { echo json_encode(['success' => false]); exit; }

        $ref = $db->fetch(
            "SELECT id, referrer_id, status FROM referrals WHERE referral_code = ?",
            [$code]
        );
        if (!$ref || $ref['status'] === 'purchased') {
            echo json_encode(['success' => true, 'note' => 'already converted or not found']);
            exit;
        }

        $db->execute(
            "UPDATE referrals SET status='purchased', referred_user_id=?, referred_email=?, converted_at=? WHERE id=?",
            [$newUser ?: null, $email ?: null, date('Y-m-d H:i:s'), $ref['id']]
        );

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
