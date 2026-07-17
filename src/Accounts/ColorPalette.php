<?php

namespace Barua\Accounts;

class ColorPalette
{
    /**
     * Fixed 20-colour palette (5x4), assigned automatically per account.
     * Generated in oklch, constant lightness L=0.66, hue walking the full circle in
     * 18° steps (rainbow-sorted). Chroma targets 0.145 (clamped to the sRGB gamut per
     * hue) — vivid "signal" colours, no pure/neon extremes, no two entries too close.
     */
    private const COLOURS = [
        '#DC6875', '#DC6C55', '#D67433', '#C97F08', '#B58C08', // red → gold
        '#9F9609', '#82A029', '#5BA74F', '#1DAC71', '#0CAA91', // olive → green-teal
        '#0CA7A7', '#0BA4BC', '#0B9FD3', '#3F97E6', '#6A8DEB', // teal → blue
        '#8A83E7', '#A47ADA', '#BA72C8', '#CA6CAF', '#D66893', // violet → rose
    ];

    /** The full 20-colour palette (for the settings swatch picker). */
    public static function all(): array
    {
        return self::COLOURS;
    }

    public static function isValid(string $colour): bool
    {
        return in_array($colour, self::COLOURS, true);
    }

    public static function pickUnused(array $usedColours): string
    {
        $available = array_values(array_diff(self::COLOURS, $usedColours));
        if (empty($available)) {
            $available = self::COLOURS;
        }
        return $available[array_rand($available)];
    }
}
