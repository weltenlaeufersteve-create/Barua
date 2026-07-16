<?php

namespace Barua\Accounts;

class ColorPalette
{
    /**
     * Fixed 20-colour palette (5x4), assigned automatically per account.
     * Generated in oklch with CONSTANT lightness (L=0.68) and chroma (C=0.107) —
     * only the hue walks the full circle in 18° steps, rainbow-sorted. No pure
     * colours, uniform signal strength, no two entries too close.
     */
    private const COLOURS = [
        '#D27C83', '#D27F6D', '#CD8459', '#C38C4A', '#B59443', // red → gold
        '#A29C47', '#8CA356', '#73A96A', '#57AC80', '#3CAE97', // olive → green-teal
        '#2AADAD', '#30A9BF', '#47A4CE', '#619DD7', '#7B96DB', // teal → blue
        '#918FD8', '#A688CF', '#B682C0', '#C47EAE', '#CD7C99', // violet → rose
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
