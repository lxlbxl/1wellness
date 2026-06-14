<?php
/**
 * One-click unsubscribe + preference centre.
 *
 * GET  ?email=...&channel=email|whatsapp|sms|all   → unsubscribe + show confirmation page
 * POST action=update_prefs  JSON body              → update per-channel opt-in/out (portal use)
 * POST action=resubscribe   ?email=...&channel=... → opt back in
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/Settings.php';
require_once dirname(__DIR__) . '/classes/Notifications/ConsentManager.php';
require_once dirname(__DIR__) . '/classes/Notifications/NotificationService.php';
require_once dirname(__DIR__) . '/classes/Notifications/Channels/ChannelAdapterInterface.php';
require_once dirname(__DIR__) . '/classes/Notifications/Channels/EmailChannel.php';
require_once dirname(__DIR__) . '/classes/Notifications/Channels/WhatsAppChannel.php';
require_once dirname(__DIR__) . '/classes/Notifications/Channels/SmsChannel.php';
require_once dirname(__DIR__) . '/classes/Notifications/TemplateRenderer.php';
require_once dirname(__DIR__) . '/classes/Mailer.php';

$consent  = new ConsentManager();
$settings = Settings::getInstance();
$siteName = $settings->get('smtp_from_name', '1wellness');

// -----------------------------------------------------------------------
// JSON POST — portal preference updates
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? 'unsubscribe';
    $email  = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $channel = in_array($input['channel'] ?? '', ['email','whatsapp','sms','all'], true)
               ? $input['channel']
               : 'email';
    $source = 'unsub_link';

    if (!$email) {
        echo json_encode(['success' => false, 'error' => 'invalid_email']);
        exit;
    }

    if ($action === 'resubscribe') {
        $channels = ($channel === 'all') ? ['email', 'whatsapp', 'sms'] : [$channel];
        foreach ($channels as $ch) {
            $consent->recordConsent($ch, $email, 'opted_in', 'admin_pref_centre');
        }
        echo json_encode(['success' => true, 'message' => 'Resubscribed to ' . $channel]);
        exit;
    }

    // Default: unsubscribe
    $channels = ($channel === 'all') ? ['email', 'whatsapp', 'sms'] : [$channel];
    foreach ($channels as $ch) {
        $consent->recordConsent($ch, $email, 'opted_out', $source);
    }
    // Cancel pending marketing sends
    NotificationService::getInstance()->cancelJourney($email, [], 'opted_out');
    echo json_encode(['success' => true, 'message' => 'Unsubscribed from ' . $channel]);
    exit;
}

// -----------------------------------------------------------------------
// GET — one-click unsubscribe from email footer link
// -----------------------------------------------------------------------
$email   = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
$channel = in_array($_GET['channel'] ?? '', ['email','whatsapp','sms','all'], true)
           ? $_GET['channel']
           : 'email';

if ($email) {
    $channels = ($channel === 'all') ? ['email', 'whatsapp', 'sms'] : [$channel];
    foreach ($channels as $ch) {
        $consent->recordConsent($ch, $email, 'opted_out', 'unsub_link');
    }
    NotificationService::getInstance()->cancelJourney($email, [], 'opted_out');
}

$channelLabel = $channel === 'all' ? 'all channels' : $channel;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Unsubscribe — <?php echo htmlspecialchars($siteName); ?></title>
<style>
body{font-family:sans-serif;background:#f6f6f0;margin:0;padding:32px 16px;color:#2C3E35}
.card{max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:40px;text-align:center;border:1px solid #e8e8e0}
h1{font-size:24px;margin:0 0 12px}
p{color:#6B7C70;line-height:1.6;margin:0 0 20px}
.btn{display:inline-block;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px}
.btn-primary{background:#0f3922;color:#fff}
.btn-secondary{background:#eee;color:#444;margin-left:8px}
form{margin-top:16px}
</style>
</head>
<body>
<div class="card">
<?php if ($email): ?>
    <h1>You have been unsubscribed</h1>
    <p>
        <strong><?php echo htmlspecialchars($email); ?></strong> has been unsubscribed from
        <strong><?php echo htmlspecialchars($channelLabel); ?></strong> notifications from
        <?php echo htmlspecialchars($siteName); ?>.
    </p>
    <p>Changed your mind?</p>
    <form method="POST" action="">
        <input type="hidden" name="action" value="resubscribe">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        <input type="hidden" name="channel" value="<?php echo htmlspecialchars($channel); ?>">
        <button type="submit" class="btn btn-secondary">Re-subscribe</button>
    </form>
<?php else: ?>
    <h1>Manage Preferences</h1>
    <p>Enter your email address to manage your notification preferences.</p>
    <form method="GET" action="">
        <input type="email" name="email" required placeholder="your@email.com"
            style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;font-size:14px">
        <input type="hidden" name="channel" value="<?php echo htmlspecialchars($channel); ?>">
        <button type="submit" class="btn btn-primary">Unsubscribe from <?php echo htmlspecialchars($channelLabel); ?></button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
