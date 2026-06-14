<?php
/**
 * ProtocolGeneratorFactory — routes to the correct condition-specific generator.
 *
 * Usage:
 *   $generator = ProtocolGeneratorFactory::for('acne');
 *   $plan = $generator->generate($assessment, $name, $email, $region);
 */
class ProtocolGeneratorFactory
{
    /**
     * Map of condition keys to generator class names.
     */
    private const GENERATOR_MAP = [
        'pcos' => 'PcosProtocolGenerator',
        'acne' => 'AcneProtocolGenerator',
        'weight' => 'WeightProtocolGenerator',
        'mens' => 'MensProtocolGenerator',
    ];

    /**
     * Get a generator instance for a condition.
     *
     * @param string $condition pcos|acne|weight|mens
     * @return AbstractProtocolGenerator
     * @throws InvalidArgumentException if condition is invalid
     */
    public static function for(string $condition): AbstractProtocolGenerator
    {
        $condition = strtolower(trim($condition));

        if (!ModuleManifest::isValidCondition($condition)) {
            throw new InvalidArgumentException("Invalid condition: $condition. Valid: " . implode(', ', ModuleManifest::getValidConditions()));
        }

        $className = self::GENERATOR_MAP[$condition];

        if (!class_exists($className)) {
            throw new RuntimeException("Generator class not found: $className");
        }

        return new $className();
    }

    /**
     * Resolve condition from assessment or sale data.
     *
     * @param array $assessment Assessment data
     * @param array $sale Sale/transaction data
     * @return string Condition key
     */
    public static function resolveCondition(array $assessment = [], array $sale = []): string
    {
        // Try assessment first
        $condition = $assessment['condition'] ?? $assessment['assessment_type'] ?? '';

        // Try sale/funnel
        if (empty($condition)) {
            $funnel = $sale['funnel_name'] ?? $sale['product_type'] ?? '';
            $condition = self::mapFunnelToCondition($funnel);
        }

        // Normalize
        $condition = strtolower(trim($condition));

        // Map aliases
        $aliases = [
            'pcos' => 'pcos',
            'cycle_sync' => 'pcos',
            'cyclesync' => 'pcos',
            'acne' => 'acne',
            'glowclear' => 'acne',
            'weight' => 'weight',
            'weight_management' => 'weight',
            'leanflow' => 'weight',
            'mens' => 'mens',
            'men' => 'mens',
            'mens_health' => 'mens',
            'vitale' => 'mens',
        ];

        return $aliases[$condition] ?? 'pcos'; // Default to PCOS
    }

    /**
     * Map funnel name to condition key.
     */
    private static function mapFunnelToCondition(string $funnel): string
    {
        $funnel = strtolower($funnel);

        if (strpos($funnel, 'pcos') !== false || strpos($funnel, 'cycle') !== false) {
            return 'pcos';
        }
        if (strpos($funnel, 'acne') !== false || strpos($funnel, 'glow') !== false || strpos($funnel, 'skin') !== false) {
            return 'acne';
        }
        if (strpos($funnel, 'weight') !== false || strpos($funnel, 'lean') !== false) {
            return 'weight';
        }
        if (strpos($funnel, 'men') !== false || strpos($funnel, 'vital') !== false) {
            return 'mens';
        }

        return 'pcos'; // Default
    }

    /**
     * Get all available conditions.
     */
    public static function getAvailableConditions(): array
    {
        return array_keys(self::GENERATOR_MAP);
    }

    /**
     * Check if a condition has a generator.
     */
    public static function hasGenerator(string $condition): bool
    {
        return isset(self::GENERATOR_MAP[strtolower(trim($condition))]);
    }
}