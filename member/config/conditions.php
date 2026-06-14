<?php
/**
 * Conditions registry — single source of truth.
 *
 * No component may hardcode condition-specific strings; they must call
 * ConditionsRegistry::get($condition) and read from this array.
 *
 * Keys:
 *   sub_brand        Brand name shown to member
 *   tagline          Short subtitle
 *   theme_color      Primary Tailwind class token (used in CSS vars)
 *   theme_hex        Raw hex for inline styles
 *   accent_hex       Accent colour
 *   icon             Lucide icon name for the condition
 *   dashboard_label  Label for the first stat card (condition-specific metric)
 *   terminology      Vocabulary: what we call the member's core concern
 *   modules          Ordered list of dashboard modules to render
 *   tracker_metrics  Default tracker fields shown on the tracker view
 *   ai_persona       System-prompt persona fragment for AI specialist
 */

class ConditionsRegistry
{
    private static array $registry = [

        'pcos' => [
            'sub_brand'       => 'CycleSync',
            'tagline'         => 'Hormone balance, naturally',
            'theme_color'     => 'sage',
            'theme_hex'       => '#2C3E35',
            'accent_hex'      => '#D97757',
            'icon'            => 'moon',
            'dashboard_label' => 'Cycle Phase',
            'terminology'     => [
                'primary_metric' => 'Cycle Phase',
                'concern'        => 'hormonal imbalance',
                'outcome'        => 'hormone balance',
                'unit'           => 'day',
            ],
            'modules'         => ['cycle', 'nourish', 'tracker', 'ai_chat', 'weekly'],
            'tracker_metrics' => [
                ['key' => 'mood',      'label' => 'Mood',       'type' => 'scale',   'max' => 5],
                ['key' => 'energy',    'label' => 'Energy',     'type' => 'scale',   'max' => 5],
                ['key' => 'bloating',  'label' => 'Bloating',   'type' => 'boolean'],
                ['key' => 'cramps',    'label' => 'Cramps',     'type' => 'boolean'],
                ['key' => 'sleep_hrs', 'label' => 'Sleep (h)',  'type' => 'number',  'min' => 0, 'max' => 14],
                ['key' => 'hydration', 'label' => 'Water (L)',  'type' => 'number',  'min' => 0, 'max' => 5],
            ],
            'ai_persona' => 'You are a warm, knowledgeable women\'s hormonal health coach specialising in PCOS management through nutrition, lifestyle, and herbal support.',
        ],

        'acne' => [
            'sub_brand'       => 'GlowClear',
            'tagline'         => 'Clear skin from within',
            'theme_color'     => 'rose',
            'theme_hex'       => '#B5534A',
            'accent_hex'      => '#E8A598',
            'icon'            => 'sparkles',
            'dashboard_label' => 'Skin Score',
            'terminology'     => [
                'primary_metric' => 'Skin Score',
                'concern'        => 'acne & breakouts',
                'outcome'        => 'clear skin',
                'unit'           => 'point',
            ],
            'modules'         => ['skin', 'nourish', 'tracker', 'ai_chat', 'weekly'],
            'tracker_metrics' => [
                ['key' => 'breakouts',  'label' => 'Breakouts',      'type' => 'scale',   'max' => 5],
                ['key' => 'redness',    'label' => 'Redness',        'type' => 'scale',   'max' => 5],
                ['key' => 'oiliness',   'label' => 'Oiliness',       'type' => 'scale',   'max' => 5],
                ['key' => 'hydration',  'label' => 'Water (L)',       'type' => 'number',  'min' => 0, 'max' => 5],
                ['key' => 'sugar',      'label' => 'Sugar servings',  'type' => 'number',  'min' => 0, 'max' => 10],
                ['key' => 'sleep_hrs',  'label' => 'Sleep (h)',       'type' => 'number',  'min' => 0, 'max' => 14],
            ],
            'ai_persona' => 'You are a caring skin health coach who guides clients to clear, healthy skin through evidence-based nutrition, gut health, and holistic lifestyle changes.',
        ],

        'weight' => [
            'sub_brand'       => 'LeanFlow',
            'tagline'         => 'Sustainable metabolism support',
            'theme_color'     => 'emerald',
            'theme_hex'       => '#2D6A4F',
            'accent_hex'      => '#74C69D',
            'icon'            => 'trending-down',
            'dashboard_label' => 'Progress',
            'terminology'     => [
                'primary_metric' => 'Progress',
                'concern'        => 'weight & metabolism',
                'outcome'        => 'healthy weight',
                'unit'           => 'kg',
            ],
            'modules'         => ['progress', 'nourish', 'tracker', 'ai_chat', 'weekly'],
            'tracker_metrics' => [
                ['key' => 'weight_kg',  'label' => 'Weight (kg)',    'type' => 'number',  'min' => 0, 'max' => 300],
                ['key' => 'steps',      'label' => 'Steps',          'type' => 'number',  'min' => 0, 'max' => 50000],
                ['key' => 'energy',     'label' => 'Energy',         'type' => 'scale',   'max' => 5],
                ['key' => 'hunger',     'label' => 'Hunger',         'type' => 'scale',   'max' => 5],
                ['key' => 'hydration',  'label' => 'Water (L)',       'type' => 'number',  'min' => 0, 'max' => 5],
                ['key' => 'sleep_hrs',  'label' => 'Sleep (h)',       'type' => 'number',  'min' => 0, 'max' => 14],
            ],
            'ai_persona' => 'You are a supportive metabolic health coach helping clients achieve sustainable weight loss through balanced nutrition, movement, and lifestyle optimisation.',
        ],

        'mens' => [
            'sub_brand'       => 'Vitale',
            'tagline'         => 'Peak vitality, naturally',
            'theme_color'     => 'blue',
            'theme_hex'       => '#1E4D7B',
            'accent_hex'      => '#5B9BD5',
            'icon'            => 'zap',
            'dashboard_label' => 'Vitality',
            'terminology'     => [
                'primary_metric' => 'Vitality Score',
                'concern'        => 'energy & performance',
                'outcome'        => 'peak vitality',
                'unit'           => 'point',
            ],
            'modules'         => ['vitality', 'nourish', 'tracker', 'ai_chat', 'weekly'],
            'tracker_metrics' => [
                ['key' => 'energy',    'label' => 'Energy',       'type' => 'scale',   'max' => 5],
                ['key' => 'libido',    'label' => 'Libido',       'type' => 'scale',   'max' => 5],
                ['key' => 'mood',      'label' => 'Mood',         'type' => 'scale',   'max' => 5],
                ['key' => 'sleep_hrs', 'label' => 'Sleep (h)',    'type' => 'number',  'min' => 0, 'max' => 14],
                ['key' => 'exercise',  'label' => 'Exercise min', 'type' => 'number',  'min' => 0, 'max' => 300],
                ['key' => 'hydration', 'label' => 'Water (L)',    'type' => 'number',  'min' => 0, 'max' => 5],
            ],
            'ai_persona' => 'You are a knowledgeable men\'s vitality coach guiding clients to improve energy, hormonal health, and performance through targeted nutrition and natural supplementation.',
        ],
    ];

    // Fallback when condition is unrecognised or null
    private static string $default = 'pcos';

    /** Return the full config for a condition. */
    public static function get(?string $condition): array
    {
        $key = strtolower(trim($condition ?? ''));
        return self::$registry[$key] ?? self::$registry[self::$default];
    }

    /** Return a single value by dot-path, e.g. 'terminology.outcome'. */
    public static function value(?string $condition, string $path, $fallback = null)
    {
        $cfg  = self::get($condition);
        $keys = explode('.', $path);
        $cur  = $cfg;
        foreach ($keys as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return $fallback;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }

    /** All known condition keys. */
    public static function keys(): array
    {
        return array_keys(self::$registry);
    }
}
