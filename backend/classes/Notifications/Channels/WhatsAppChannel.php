<?php

require_once dirname(__DIR__, 2) . '/classes/Settings.php';

/**
 * Meta WhatsApp Cloud API channel adapter.
 *
 * Within the 24-hour customer service window: sends free-form text messages.
 * Outside the window: must use a pre-approved template (`wa_template_name` in meta).
 *
 * Set in Admin → Settings → Notifications:
 *   whatsapp_provider         = meta | twilio
 *   whatsapp_phone_number_id  = <Meta Cloud API phone number ID>
 *   whatsapp_access_token     = <Meta permanent token>
 *
 * Twilio driver: set whatsapp_provider=twilio, twilio_sid, twilio_auth_token,
 * twilio_whatsapp_from (e.g. whatsapp:+14155238886).
 */
class WhatsAppChannel implements ChannelAdapterInterface
{
    const META_API_BASE = 'https://graph.facebook.com/v18.0';

    private $settings;

    public function __construct()
    {
        $this->settings = Settings::getInstance();
    }

    public function channelName(): string { return 'whatsapp'; }

    public function isAvailable(): bool
    {
        if (!(bool) $this->settings->get('notify_whatsapp_enabled', '0')) {
            return false;
        }
        $provider = $this->settings->get('whatsapp_provider', 'meta');
        if ($provider === 'twilio') {
            return $this->settings->get('twilio_sid', '') !== ''
                && $this->settings->get('twilio_auth_token', '') !== '';
        }
        return $this->settings->get('whatsapp_phone_number_id', '') !== ''
            && $this->settings->get('whatsapp_access_token', '') !== '';
    }

    public function send(string $to, string $subject, string $body, array $meta = []): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => 'whatsapp_not_configured', 'cost_usd' => null];
        }

        $phone = $this->normalizePhone($to);
        if (!$phone) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => 'invalid_phone', 'cost_usd' => null];
        }

        $provider = $this->settings->get('whatsapp_provider', 'meta');
        return $provider === 'twilio'
            ? $this->sendViaTwilio($phone, $body, $meta)
            : $this->sendViaMeta($phone, $body, $meta);
    }

    private function sendViaMeta(string $phone, string $body, array $meta): array
    {
        $phoneNumberId = $this->settings->get('whatsapp_phone_number_id', '');
        $token = $this->settings->get('whatsapp_access_token', '');

        $templateName = $meta['wa_template_name'] ?? '';
        if ($templateName !== '') {
            // Pre-approved template message (required outside 24-hour window)
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en_US'],
                    'components' => $meta['wa_components'] ?? [],
                ],
            ];
        } else {
            // Free-form text (only within 24-hour window)
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $body],
            ];
        }

        $result = $this->postJson(
            self::META_API_BASE . '/' . $phoneNumberId . '/messages',
            $payload,
            ['Authorization: Bearer ' . $token]
        );

        if (!$result) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => 'meta_api_error', 'cost_usd' => null];
        }
        if (!empty($result['error'])) {
            $err = $result['error']['message'] ?? json_encode($result['error']);
            return ['ok' => false, 'provider_msg_id' => null, 'error' => $err, 'cost_usd' => null];
        }
        $msgId = $result['messages'][0]['id'] ?? null;
        return ['ok' => true, 'provider_msg_id' => $msgId, 'error' => null, 'cost_usd' => 0.005];
    }

    private function sendViaTwilio(string $phone, string $body, array $meta): array
    {
        $sid = $this->settings->get('twilio_sid', '');
        $token = $this->settings->get('twilio_auth_token', '');
        $from = $this->settings->get('twilio_whatsapp_from', '');

        $params = http_build_query([
            'From' => 'whatsapp:' . $from,
            'To' => 'whatsapp:' . $phone,
            'Body' => $body,
        ]);
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_USERPWD => $sid . ':' . $token,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = $resp ? json_decode($resp, true) : null;
        if (!$data || !empty($data['error_code'])) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => $data['message'] ?? 'twilio_error', 'cost_usd' => null];
        }
        return ['ok' => true, 'provider_msg_id' => $data['sid'] ?? null, 'error' => null, 'cost_usd' => 0.005];
    }

    private function postJson(string $url, array $payload, array $headers = []): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp ? json_decode($resp, true) : null;
    }

    /** Normalize to E.164 (+2348012345678). Returns null if unparseable. */
    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw);
        if ($digits === '') return null;
        if ($digits[0] !== '+') {
            $digits = '+' . $digits;
        }
        return strlen($digits) >= 8 ? $digits : null;
    }
}
