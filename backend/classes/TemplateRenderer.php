<?php

class TemplateRenderer
{
    /**
     * Replace {{key}} placeholders in a template string.
     * Supports {{#if key}}...{{/if}} blocks (shown only when key is truthy).
     * Unknown placeholders are left as-is so admins can spot missing vars.
     */
    public static function render(string $template, array $vars): string
    {
        // Inject standard globals
        $vars['app_url'] = defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://1wellness.club';
        if (empty($vars['unsubscribe_url'])) {
            $vars['unsubscribe_url'] = $vars['app_url'] . '/backend/api/unsubscribe.php'
                . (isset($vars['email']) ? '?email=' . urlencode($vars['email']) : '');
        }
        if (empty($vars['prefs_url'])) {
            $vars['prefs_url'] = $vars['app_url'] . '/backend/api/notification-prefs.php'
                . (isset($vars['email']) ? '?email=' . urlencode($vars['email']) : '');
        }

        // {{#if key}}...{{/if}} blocks
        $template = preg_replace_callback(
            '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s',
            function ($m) use ($vars) {
                return (!empty($vars[$m[1]])) ? $m[2] : '';
            },
            $template
        );

        // Simple {{key}} replacements
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $template = str_replace('{{' . $key . '}}', (string) $value, $template);
            }
        }

        return $template;
    }

    /** Render both subject and body, returning ['subject' => ..., 'body' => ...] */
    public static function renderTemplate(array $template, array $vars): array
    {
        return [
            'subject' => self::render($template['subject'] ?? '', $vars),
            'body'    => self::render($template['body'] ?? '', $vars),
        ];
    }
}
