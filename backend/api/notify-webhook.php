<?php
/**
 * Provider delivery callbacks.
 *
 * Handles inbound status webhooks from Twilio, Meta WhatsApp Cloud API, and Termii.
 * Updates notification_log status (delivered/read/failed/bounced).
 * Handles SMS STOP (Twilio: Body=STOP) → opt-out.
 *
 * URL: /backend/api/notify-webhook.php?provider=twilio|meta|termii
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

$provider = strtolower(trim($_GET['provider'] ?? ''));
$rawBody  = file_get_contents('php://input');
$db       = Database::getInstance();
$consent  = new ConsentManager();

// -----------------------------------------------------------------------
// Twilio SMS/WhatsApp status callback
// -----------------------------------------------------------------------
if ($provider === 'twilio') {
    $msgSid    = $_POST['MessageSid'] ?? '';
    $status    = strtolower($_POST['MessageStatus'] ?? '');
    $body      = strtoupper(trim($_POST['Body'] ?? ''));
    $fromRaw   = $_POST['From'] ?? '';

    // STOP → opt-out from SMS
    if ($body === 'STOP' || $body === 'STOP ALL') {
        $phone = preg_replace('/^whatsapp:/', '', $fromRaw);
        $channel = str_starts_with($fromRaw, 'whatsapp:') ? 'whatsapp' : 'sms';
        $consent->recordConsent($channel, '', 'opted_out', 'sms_stop', $phone);
        // Cancel pending sends for this phone
        try {
            $db->execute(
                "UPDATE notification_queue SET status='cancelled', cancelled_reason='sms_stop'
                 WHERE phone = ? AND status = 'pending'",
                [$phone]
            );
        } catch (Exception $e) { /* non-fatal */ }
        echo 'OK'; exit;
    }

    // Status update
    if ($msgSid && $status) {
        $logStatus = match($status) {
            'delivered' => 'delivered',
            'read'      => 'read',
            'failed', 'undelivered' => 'failed',
            default     => null,
        };
        if ($logStatus) {
            try {
                $db->execute(
                    "UPDATE notification_log SET status = ? WHERE provider_msg_id = ?",
                    [$logStatus, $msgSid]
                );
                // Handle bounce → opt-out
                if ($logStatus === 'failed') {
                    $row = $db->fetch(
                        "SELECT email, phone FROM notification_log WHERE provider_msg_id = ? LIMIT 1",
                        [$msgSid]
                    );
                    if ($row) {
                        $ch = str_starts_with($fromRaw, 'whatsapp:') ? 'whatsapp' : 'sms';
                        if (!empty($row['phone'])) {
                            $consent->recordConsent($ch, '', 'bounced', 'provider_callback', $row['phone']);
                        }
                    }
                }
            } catch (Exception $e) { /* non-fatal */ }
        }
    }
    echo 'OK'; exit;
}

// -----------------------------------------------------------------------
// Meta WhatsApp Cloud API status webhooks
// -----------------------------------------------------------------------
if ($provider === 'meta') {
    $data = json_decode($rawBody, true) ?: [];

    // Verify webhook token on initial subscription
    if (isset($_GET['hub.challenge'])) {
        $verifyToken = Settings::getInstance()->get('whatsapp_access_token', '');
        if ($_GET['hub.verify_token'] === $verifyToken) {
            echo (int) $_GET['hub.challenge'];
        }
        exit;
    }

    $entries = $data['entry'] ?? [];
    foreach ($entries as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];
            // Status updates
            foreach ($value['statuses'] ?? [] as $statusItem) {
                $msgId    = $statusItem['id'] ?? '';
                $rawStatus = strtolower($statusItem['status'] ?? '');
                $logStatus = match($rawStatus) {
                    'delivered' => 'delivered',
                    'read'      => 'read',
                    'failed'    => 'failed',
                    default     => null,
                };
                if ($msgId && $logStatus) {
                    try {
                        $db->execute(
                            "UPDATE notification_log SET status = ? WHERE provider_msg_id = ?",
                            [$logStatus, $msgId]
                        );
                    } catch (Exception $e) { /* non-fatal */ }
                }
            }
            // Inbound messages (e.g. customer replies to WA support thread)
            // We log these as 'replied' status — full inbox handling is future scope
            foreach ($value['messages'] ?? [] as $msg) {
                if (($msg['type'] ?? '') === 'text') {
                    $phone = $msg['from'] ?? '';
                    $text  = strtoupper(trim($msg['text']['body'] ?? ''));
                    if ($text === 'STOP' || $text === 'UNSUBSCRIBE') {
                        $consent->recordConsent('whatsapp', '', 'opted_out', 'wa_stop', $phone);
                    }
                }
            }
        }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// -----------------------------------------------------------------------
// Termii delivery report
// -----------------------------------------------------------------------
if ($provider === 'termii') {
    $data  = json_decode($rawBody, true) ?: [];
    $msgId = $data['messageId'] ?? $data['message_id'] ?? '';
    $raw   = strtolower($data['status'] ?? '');
    $logStatus = match($raw) {
        'delivered' => 'delivered',
        'failed', 'rejected', 'expired' => 'failed',
        default => null,
    };
    if ($msgId && $logStatus) {
        try {
            $db->execute(
                "UPDATE notification_log SET status = ? WHERE provider_msg_id = ?",
                [$logStatus, $msgId]
            );
        } catch (Exception $e) { /* non-fatal */ }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown_provider']);
