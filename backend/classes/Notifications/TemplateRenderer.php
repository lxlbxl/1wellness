<?php

require_once dirname(__DIR__, 2) . '/classes/Database.php';
require_once dirname(__DIR__, 2) . '/classes/Settings.php';

/**
 * Renders notification templates from the notification_templates table.
 *
 * Merge vars: {{name}}, {{funnel}}, {{type}}, {{resume_link}}, {{portal_link}},
 * {{plan_link}}, {{streak_days}}, {{support_email}}, {{site_url}}, {{unsubscribe_link}},
 * plus any keys in the $payload array passed at render time.
 */
class TemplateRenderer
{
    private $db;
    private $settings;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = Settings::getInstance();
    }

    /**
     * Fetch and render a template.
     *
     * @param string $templateKey  e.g. 'purchase_confirm_1'
     * @param string $channel      'email' | 'whatsapp' | 'sms'
     * @param array  $payload      Merge vars
     * @param string $funnel       'pcos' | 'acne' | 'weight' | 'mens' | 'all'
     * @return array{subject:string, body:string}|null  null if template not found
     */
    public function render(string $templateKey, string $channel, array $payload, string $funnel = 'all'): ?array
    {
        // Try funnel-specific first, then fall back to 'all'
        $row = $this->db->fetch(
            "SELECT subject, body, wa_template_name FROM notification_templates
             WHERE template_key = ? AND channel = ? AND funnel = ? AND active = 1",
            [$templateKey, $channel, $funnel]
        );
        if (!$row) {
            $row = $this->db->fetch(
                "SELECT subject, body, wa_template_name FROM notification_templates
                 WHERE template_key = ? AND channel = ? AND funnel = 'all' AND active = 1",
                [$templateKey, $channel]
            );
        }
        if (!$row) {
            return null;
        }

        $vars = $this->buildVars($payload, $funnel);
        return [
            'subject'          => $this->replace($row['subject'] ?? '', $vars),
            'body'             => $this->replace($row['body'] ?? '', $vars),
            'wa_template_name' => $row['wa_template_name'] ?? '',
        ];
    }

    /** Return list of all available template keys for a channel. */
    public function listKeys(string $channel = ''): array
    {
        if ($channel) {
            $rows = $this->db->fetchAll(
                "SELECT template_key, channel, funnel, subject FROM notification_templates WHERE channel = ? AND active = 1",
                [$channel]
            ) ?: [];
        } else {
            $rows = $this->db->fetchAll(
                "SELECT template_key, channel, funnel, subject FROM notification_templates WHERE active = 1"
            ) ?: [];
        }
        return $rows;
    }

    private function buildVars(array $payload, string $funnel): array
    {
        $siteUrl = rtrim($this->settings->get('site_url', 'https://1wellness.club'), '/');
        $supportEmail = $this->settings->get('admin_email', 'support@1wellness.club');
        $fromName = $this->settings->get('smtp_from_name', '1wellness');

        $email = $payload['email'] ?? '';
        $unsubBase = $siteUrl . '/backend/api/unsubscribe.php?email=' . rawurlencode($email);

        $funnelLabels = ['pcos' => 'PCOS', 'acne' => 'Acne', 'weight' => 'Weight Loss', 'mens' => 'Vitality'];

        $defaults = [
            'site_url'          => $siteUrl,
            'site_name'         => $fromName,
            'support_email'     => $supportEmail,
            'portal_link'       => $siteUrl . '/member/login.php',
            'plan_link'         => $siteUrl . '/member/login.php',
            'resume_link'       => $siteUrl . '/' . $funnel . '/assessment.html?resume=1',
            'unsubscribe_link'  => $unsubBase . '&channel=email',
            'unsub_all_link'    => $unsubBase . '&channel=all',
            'funnel_label'      => $funnelLabels[$funnel] ?? ucfirst($funnel),
            'year'              => date('Y'),
        ];

        return array_merge($defaults, $payload);
    }

    private function replace(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (!is_scalar($value)) continue;
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }
}
