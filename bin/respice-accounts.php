<?php
// Remap each account's colour to the nearest colour in the current palette (by hue),
// so existing accounts pick up the spicier palette while keeping their hue family.
// Run: php bin/respice-accounts.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Accounts\AccountRepository;
use Barua\Accounts\ColorPalette;
use Barua\Database;

function hexHue(string $hex): float
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b); $d = $max - $min;
    if ($d == 0) return -1; // greyscale: no hue
    if ($max == $r) $h = fmod((($g - $b) / $d), 6);
    elseif ($max == $g) $h = (($b - $r) / $d) + 2;
    else $h = (($r - $g) / $d) + 4;
    $h *= 60; if ($h < 0) $h += 360;
    return $h;
}

function hueDist(float $a, float $b): float
{
    $d = abs($a - $b); return min($d, 360 - $d);
}

$palette = ColorPalette::all();
$db = Database::connection();
$stmt = $db->prepare('UPDATE accounts SET colour = ? WHERE id = ?');

foreach (AccountRepository::all() as $acc) {
    $curHue = hexHue($acc['colour']);
    $best = $palette[0]; $bestD = 999;
    foreach ($palette as $c) {
        $d = $curHue < 0 ? 0 : hueDist($curHue, hexHue($c));
        if ($d < $bestD) { $bestD = $d; $best = $c; }
    }
    if (strcasecmp($best, $acc['colour']) !== 0) {
        $stmt->execute([$best, (int) $acc['id']]);
        printf("%-26s %s -> %s\n", $acc['label'], $acc['colour'], $best);
    } else {
        printf("%-26s %s (unchanged)\n", $acc['label'], $acc['colour']);
    }
}
