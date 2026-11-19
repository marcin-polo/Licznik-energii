<?php

declare(strict_types=1);

/** Poziom wartości względem progów (odpowiednik MeterUI.level w common.js). */
final class Levels
{
    public const SEVERITY = ['normal' => 0, 'export' => 0, 'warning' => 1, 'alarm' => 2];

    /**
     * Histereza: wyjście z poziomu wymaga cofnięcia się o 'hysteresis' poza próg,
     * dlatego potrzebny jest poprzedni poziom.
     */
    public static function level(float|int|null $value, ?array $t, string $previous = 'normal'): string
    {
        if ($value === null || !$t) {
            return 'normal';
        }
        $h = $t['hysteresis'] ?? 0;
        $hit = function (string $level, float $margin) use ($value, $t): bool {
            return (isset($t[$level]) && $value >= $t[$level] - $margin)
                || (isset($t["{$level}_low"]) && $value <= $t["{$level}_low"] + $margin);
        };
        if ($hit('alarm', $previous === 'alarm' ? $h : 0)) {
            return 'alarm';
        }
        if ($hit('warning', self::SEVERITY[$previous] >= 1 ? $h : 0)) {
            return 'warning';
        }
        return !empty($t['export']) && $value < 0 ? 'export' : 'normal';
    }

    /** Opis przekroczonego progu, np. "≥ 9000". */
    public static function describe(float|int $value, array $t, string $level): string
    {
        if (isset($t["{$level}_low"]) && $value <= $t["{$level}_low"] + ($t['hysteresis'] ?? 0)) {
            return '≤ ' . $t["{$level}_low"];
        }
        return isset($t[$level]) ? '≥ ' . $t[$level] : '';
    }
}
