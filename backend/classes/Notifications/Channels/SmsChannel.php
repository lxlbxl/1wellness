<?php

require_once dirname(__DIR__, 2) . '/classes/Settings.php';

/**
 * SMS channel adapter — Twilio (default) or Termii (Nigeria-optimised).
 *
 * Settings keys:
 *   sms_provider      = twilio | termii
 *   twilio_sid / twilio_auth_token / twilio_sms_from
 *   termii_api_key / termii_sender_id
 *   notify_sms_enabled = 1
 *
 * Every outbound SMS appends "Reply STOP to opt out." per §4.4.
 */
class SmsChannel implements ChannelAdapterInterface
{
    private $settings;

    public function __construct()
    {
        $this->settings = Settings::getInstance();
    }

    public function channelName(): string { return 'sms'; }

    public function isAvailable(): bool
    {
        if (!(bool) $this->settings->get('notify_sms_enabled', '0')) {
            return false;
        }
        $provider = $this->settings->get('sms_provider', 'twilio');
        if ($provider === 'termii') {
            return $this->settings->get('termii_api_key', '') !== '';
        }
        return $this->settings->get('twilio_sid', '') !== ''
            && $this->settings->get('twilio_auth_token', '') !== '';
    }

    public function send(string $to, string $subject, string $body, array $meta = []): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => 'sms_not_configured', 'cost_usd' => null];
        }

        $phone = $this->normalizePhone($to);
        if (!$phone) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => 'invalid_phone', 'cost_usd' => null];
        }

        // Always append STOP opt-out instruction
        $text = rtrim($body, '.') . '. Reply STOP to opt out.';

        $provider = $this->settings->get('sms_provider', 'twilio');
        return $provider === 'termii'
            ? $this->sendViaTermii($phone, $text)
            : $this->sendViaTwilio($phone, $text);
    }

    private function sendViaTwilio(string $phone, string $body): array
    {
        $sid = $this->settings->get('twilio_sid', '');
        $token = $this->settings->get('twilio_auth_token', '');
        $from = $this->settings->get('twilio_sms_from', '');

        $params = http_build_query(['From' => $from, 'To' => $phone, 'Body' => $body]);
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
            return ['ok' => false, 'provider_msg_id' => null, 'error' => $data['message'] ?? 'twilio_sms_error', 'cost_usd' => null];
        }
        return ['ok' => true, 'provider_msg_id' => $data['sid'] ?? null, 'error' => null, 'cost_usd' => 0.0075];
    }

    private function sendViaTermii(string $phone, string $body): array
    {
        $apiKey = $this->settings->get('termii_api_key', '');
        $senderId = $this->settings->get('termii_sender_id', '1wellness');
        $payload = [
            'to' => ltrim($phone, '+'),
            'from' => $senderId,
            'sms' => $body,
            'type' => 'plain',
            'api_key' => $apiKey,
            'channel' => 'generic',
        ];
        $ch = curl_init('https://api.ng.termii.com/api/sms/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = $resp ? json_decode($resp, true) : null;
        if (!$data || empty($data['message_id'])) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => $data['message'] ?? 'termii_error', 'cost_usd' => null];
        }
        return ['ok' => true, 'provider_msg_id' => $data['message_id'], 'error' => null, 'cost_usd' => 0.004];
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw);
        if ($digits === '') return null;
        if ($digits[0] !== '+') $digits = '+' . $digits;
        return strlen($digits) >= 8 ? $digits : null;
    }
}
