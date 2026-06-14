<?php

require_once dirname(__DIR__, 2) . '/classes/Mailer.php';
require_once dirname(__DIR__, 2) . '/classes/Settings.php';

class EmailChannel implements ChannelAdapterInterface
{
    private $settings;

    public function __construct()
    {
        $this->settings = Settings::getInstance();
    }

    public function channelName(): string { return 'email'; }

    public function isAvailable(): bool
    {
        return (bool) $this->settings->get('notify_email_enabled', '1');
    }

    public function send(string $to, string $subject, string $body, array $meta = []): array
    {
        $listUnsub = $this->settings->get('site_url', 'https://1wellness.club')
            . '/backend/api/unsubscribe.php?email=' . rawurlencode($to) . '&channel=email';

        $wrappedBody = $this->wrapHtml($body, $to, $listUnsub);

        $mailer = new Mailer();
        $ok = $mailer->send($to, $subject, $wrappedBody, true);

        if (!$ok) {
            return ['ok' => false, 'provider_msg_id' => null, 'error' => $mailer->getLastError(), 'cost_usd' => null];
        }
        return ['ok' => true, 'provider_msg_id' => null, 'error' => null, 'cost_usd' => null];
    }

    private function wrapHtml(string $body, string $email, string $unsubUrl): string
    {
        $siteName = htmlspecialchars($this->settings->get('smtp_from_name', '1wellness'));
        $siteUrl  = htmlspecialchars($this->settings->get('site_url', 'https://1wellness.club'));
        $unsubEnc = htmlspecialchars($unsubUrl);

        // If already a full HTML document, inject unsubscribe footer before </body>
        if (stripos($body, '</body>') !== false) {
            $footer = $this->footer($siteName, $siteUrl, $unsubEnc, $email);
            return str_ireplace('</body>', $footer . '</body>', $body);
        }

        // Plain fragment → wrap in minimal layout
        $footer = $this->footer($siteName, $siteUrl, $unsubEnc, $email);
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>body{font-family:sans-serif;color:#2C3E35;line-height:1.6;margin:0;padding:0;background:#f6f6f6}
.wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #eee}
.inner{padding:32px}</style></head>
<body><div class="wrap"><div class="inner">
{$body}
</div>{$footer}</div></body></html>
HTML;
    }

    private function footer(string $siteName, string $siteUrl, string $unsubUrl, string $email): string
    {
        return <<<HTML
<div style="background:#f0f0eb;padding:16px 32px;font-size:12px;color:#888;text-align:center;border-top:1px solid #e8e8e0">
<p style="margin:0 0 4px">You received this from <a href="{$siteUrl}" style="color:#D97757">{$siteName}</a>.
This message is sent to {$email}.</p>
<p style="margin:0"><a href="{$unsubUrl}" style="color:#888">Unsubscribe or manage preferences</a></p>
<p style="margin:4px 0 0;font-size:11px;color:#aaa">Medical disclaimer: this content is for wellness education
and does not constitute medical advice.</p>
</div>
HTML;
    }
}
