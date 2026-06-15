<?php
/**
 * POST /backend/api/forgot-password.php
 * Body: { email }
 *
 * Generates a one-time password-reset token, stores it (15-min TTL),
 * and sends a reset link by email. Always returns 200 with a generic
 * message to avoid user-enumeration.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/Settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$generic = ['success' => true, 'message' => 'If that email is registered you will receive a reset link shortly.'];

$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($generic); // don't reveal validation details
    exit;
}

try {
    $db   = Database::getInstance();
    $user = $db->fetch("SELECT id, first_name FROM users WHERE email = ?", [$email]);

    if (!$user) {
        // Deliberate timing equalisation: sleep briefly so timing attacks
        // can't detect missing accounts.
        usleep(random_int(80000, 150000));
        echo json_encode($generic);
        exit;
    }

    // Invalidate any existing unused tokens for this user
    $db->execute(
        "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0",
        [$user['id']]
    );

    // Issue new token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $db->insert('password_reset_tokens', [
        'user_id'    => $user['id'],
        'token'      => $token,
        'expires_at' => $expires,
        'used'       => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $settings  = Settings::getInstance();
    $siteUrl   = rtrim($settings->get('site_url', 'https://1wellness.club'), '/');
    $resetLink = $siteUrl . '/member/reset-password.html?token=' . urlencode($token);
    $name      = htmlspecialchars($user['first_name'] ?: 'Member');

    $subject = '1wellness — Reset Your Password';
    $body    = "
    <div style='font-family:Arial,sans-serif;color:#333;max-width:520px;margin:0 auto'>
      <div style='background:#0f3922;padding:28px;text-align:center;border-radius:12px 12px 0 0'>
        <h1 style='color:#F4F1EA;margin:0;font-size:22px'>Password Reset</h1>
      </div>
      <div style='background:#fff;padding:28px;border:1px solid #e0e0e0'>
        <p style='font-size:15px'>Hi {$name},</p>
        <p style='font-size:14px;line-height:1.6'>We received a request to reset your 1wellness password. Click the button below — this link expires in <strong>15 minutes</strong>.</p>
        <div style='text-align:center;margin:28px 0'>
          <a href='{$resetLink}' style='background:#0f3922;color:#F4F1EA;padding:14px 36px;text-decoration:none;border-radius:30px;font-size:15px;font-weight:bold;display:inline-block'>
            Reset My Password
          </a>
        </div>
        <p style='font-size:12px;color:#999'>If you did not request this, you can safely ignore this email. Your password will not change.</p>
      </div>
      <div style='background:#f8f6f2;padding:16px;text-align:center;border-radius:0 0 12px 12px;border:1px solid #e0e0e0;border-top:0'>
        <p style='margin:0;font-size:11px;color:#aaa'>1wellness — Your Natural Healing Journey</p>
      </div>
    </div>";

    (new Mailer())->send($email, $subject, $body);

} catch (Exception $e) {
    error_log('forgot-password: ' . $e->getMessage());
}

echo json_encode($generic);
