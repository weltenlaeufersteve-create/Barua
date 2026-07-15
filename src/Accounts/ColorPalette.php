<?php

namespace Barua\Accounts;

class ColorPalette
{
    // Fixed 20-colour palette (5x4), assigned automatically per account.
    private const COLOURS = [
        '#5B8DEF', '#F2994A', '#EC6FAE', '#3FC1BA', '#9B7BF0',
        '#4FAE7E', '#E86A5C', '#4A90D9', '#C77DFF', '#2EC4B6',
        '#FF9F1C', '#EF476F', '#06D6A0', '#8D99AE', '#F4A261',
        '#7B61FF', '#00B4D8', '#E76F51', '#43AA8B', '#B5838D',
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
