<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Eina de desenvolupament: comprova el contrast WCAG 2.x dels DOS temes (fosc i clar).
 * Llegeix els colors directament de public/css/base.css (única font de veritat), per tant
 * qualsevol canvi de color es verifica sense duplicar valors aquí.
 *
 * Ús:  php tools/contrast.php          (resum)
 *      php tools/contrast.php --md     (taula en Markdown per a docs/DESIGN.md)
 * Surt amb codi 1 si algun parell no compleix o si els blocs del tema clar no són idèntics.
 */
$md = in_array('--md', $argv ?? [], true);
$css = file_get_contents(dirname(__DIR__) . '/public/css/base.css');

/** Extreu --nom: #hex d'un bloc CSS. */
function tokens(string $block): array
{
    preg_match_all('/--([\w-]+):\s*(#[0-9a-fA-F]{6})\b/', $block, $m, PREG_SET_ORDER);
    $t = [];
    foreach ($m as $x) { $t[$x[1]] = strtoupper($x[2]); }
    return $t;
}
function block(string $css, string $pattern): string
{
    if (!preg_match($pattern, $css, $m)) { fwrite(STDERR, "No trobo el bloc: $pattern\n"); exit(2); }
    return $m[1];
}

$darkRaw   = block($css, '/:root,\s*:root\[data-theme="dark"\],\s*\.score--has-image\s*\{(.*?)\n\}/s');
$lightARaw = block($css, '/@media \(prefers-color-scheme: light\)\s*\{\s*:root:not\(\[data-theme="dark"\]\)\s*\{(.*?)\n    \}/s');
$lightBRaw = block($css, '/:root\[data-theme="light"\]\s*\{(.*?)\n\}/s');
$dark   = tokens($darkRaw);
$lightA = tokens($lightARaw);
$lightB = tokens($lightBRaw);
$strip = fn(string $b) => preg_replace('/\s+/', '', $b);     // compara TOT el bloc (també els brillants rgb()), sense espais

$exit = 0;
if ($strip($lightARaw) !== $strip($lightBRaw)) {
    echo "ERROR: els dos blocs del tema clar (media query i data-theme) NO són idèntics.\n";
    foreach (array_unique(array_merge(array_keys($lightA), array_keys($lightB))) as $k) {
        if (($lightA[$k] ?? null) !== ($lightB[$k] ?? null)) { echo "  --$k: " . ($lightA[$k] ?? '-') . " vs " . ($lightB[$k] ?? '-') . "\n"; }
    }
    $exit = 1;
} else {
    echo "OK    Els dos blocs del tema clar són idèntics (" . count($lightA) . " tokens)\n";
}

/** Llegeix --glow-N: rgb(R G B / A) d'un bloc. */
function glows(string $block): array
{
    preg_match_all('/--glow-\d:\s*rgb\((\d+) (\d+) (\d+) \/ ([\d.]+)\)/', $block, $m, PREG_SET_ORDER);
    return array_map(fn($x) => [(int) $x[1], (int) $x[2], (int) $x[3], (float) $x[4]], $m);
}
/** Composita un brillant (R,G,B,alfa) sobre un color de fons [R,G,B]. */
function mix(array $bgRgb, array $g): array
{
    return array_map(fn($i) => $g[3] * $g[$i] + (1 - $g[3]) * $bgRgb[$i], [0, 1, 2]);
}
function hexToRgb(string $h): array { return array_map('hexdec', str_split(ltrim($h, '#'), 2)); }
function rgbToHex(array $c): string { return sprintf('#%02X%02X%02X', ...array_map(fn($v) => (int) round($v), $c)); }

function lum(string $hex): float
{
    $c = array_map(fn($h) => hexdec($h) / 255, str_split(ltrim($hex, '#'), 2));
    $c = array_map(fn($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function ratio(string $a, string $b): float
{
    [$l1, $l2] = [lum($a), lum($b)];
    if ($l1 < $l2) { [$l1, $l2] = [$l2, $l1]; }
    return ($l1 + 0.05) / ($l2 + 0.05);
}

// Colors d'avatar (iguals als dos temes): la lletra sempre és --avatar-ink
$tones = ['#FF5CA8', '#4FD6FF', '#6BE28C', '#C9A2FF', '#FFD43B', '#FF9A5C'];

// [primer pla, fons, mínim, descripció]
$pairs = [
    ['text', 'bg', 4.5, 'Text / fons'], ['text', 'card', 4.5, 'Text / targeta'], ['text', 'well', 4.5, 'Text / panell interior'],
    ['muted', 'bg', 4.5, 'Text secundari / fons'], ['muted', 'card', 4.5, 'Text secundari / targeta'], ['muted', 'well', 4.5, 'Text secundari / panell interior'],
    ['link', 'bg', 4.5, 'Enllaç / fons'], ['link', 'card', 4.5, 'Enllaç / targeta'],
    ['pink', 'bg', 4.5, 'Rosa (text) / fons'], ['pink', 'well', 4.5, 'Rosa (PP) / xip'],
    ['pink-ink', 'pink', 4.5, 'Text del botó principal'], ['pink-ink', 'pink-hover', 4.5, 'Text del botó principal (hover)'],
    ['line', 'bg', 3, 'Vora de camp / fons'], ['line', 'card', 3, 'Vora de camp / targeta'], ['line', 'well', 3, 'Vora de camp / panell interior'],
    ['focus', 'bg', 3, 'Anell de focus / fons'], ['focus', 'card', 3, 'Anell de focus / targeta'], ['focus', 'well', 3, 'Anell de focus / panell interior'],
    ['gold', 'card', 4.5, 'Selecció i estrelles / targeta'], ['gold', 'well', 4.5, 'Estrelles / panell interior'], ['gold', 'bg', 3, 'Contorn destacat / fons'],
    ['success', 'card', 4.5, 'Èxit / targeta'], ['danger', 'card', 4.5, 'Error / targeta'], ['danger', 'well', 4.5, 'Error / camp'], ['info', 'card', 4.5, 'Informació / targeta'],
    ['hit-300', 'well', 4.5, 'Cops de 300'], ['hit-100', 'well', 4.5, 'Cops de 100'], ['hit-50', 'well', 4.5, 'Cops de 50'], ['hit-miss', 'well', 4.5, 'Fallades'],
    ['acc', 'well', 4.5, 'Precisió'],
    ['rank-ss', 'well', 3, 'Rang SS'], ['rank-s', 'well', 3, 'Rang S'], ['rank-a', 'well', 3, 'Rang A'],
    ['rank-b', 'well', 3, 'Rang B'], ['rank-c', 'well', 3, 'Rang C'], ['rank-d', 'well', 3, 'Rang D'],
];

foreach (['fosc' => [$dark, $darkRaw], 'clar' => [$lightB, $lightBRaw]] as $name => [$t, $raw]) {
    echo ($md ? "\n### Tema $name\n\n| Parell | Colors | Ràtio | Mínim |\n|---|---|---|---|\n" : "\n== Tema $name ==\n");
    $fail = 0;
    $rows = [];
    foreach ($pairs as [$fg, $bg, $min, $desc]) {
        if (!isset($t[$fg], $t[$bg])) { echo "ERROR: falta el token $fg o $bg\n"; $exit = 1; continue; }
        $rows[] = [$desc, $t[$fg], $t[$bg], ratio($t[$fg], $t[$bg]), $min];
    }
    foreach ($tones as $tone) {
        $rows[] = ['Inicial de l\'avatar sobre ' . $tone, $t['avatar-ink'], $tone, ratio($t['avatar-ink'], $tone), 4.5];
    }
    // Fons animat: PIT cas = tots els brillants compositats un sobre l'altre damunt del color de fons
    $worst = hexToRgb($t['bg']);
    foreach (glows($raw) as $g) { $worst = mix($worst, $g); }
    $worstHex = rgbToHex($worst);
    foreach ([['text', 'Text'], ['muted', 'Text secundari'], ['link', 'Enllaç'], ['pink', 'Rosa (text)'], ['danger', 'Error']] as [$tok, $lbl]) {
        $rows[] = ["$lbl sobre el fons animat (pitjor cas)", $t[$tok], $worstHex, ratio($t[$tok], $worstHex), 4.5];
    }
    foreach ($rows as [$desc, $fg, $bg, $r, $min]) {
        $ok = $r >= $min; $fail += !$ok;
        if ($md) { printf("| %s%s | %s sobre %s | %.2f:1 | %.1f:1 |\n", $desc, $ok ? '' : ' **(NO compleix)**', $fg, $bg, $r, $min); }
        else { printf("%-4s %5.2f:1 (min %.1f)  %-40s %s on %s\n", $ok ? 'OK' : 'FAIL', $r, $min, $desc, $fg, $bg); }
    }
    if ($fail) { $exit = 1; }
    echo ($md ? '' : ($fail ? "\n$fail parell(s) del tema $name NO compleixen\n" : "\nTema $name: tots els parells compleixen WCAG 2.2 AA (" . count($rows) . ")\n"));
}
// ------------------------------------------------------------------------------------------
// Escala de dificultat 0-10 (app/Helpers/stars.php): per a cada valor (pas 0,05) el color del número/contorn
// s'ha de llegir sobre el fons de la pastilla. És text GRAN i en negreta (≥ 18,66 px): mínim 3:1.
// La pastilla és independent del tema, així que es comprova una sola vegada.
// ------------------------------------------------------------------------------------------
require_once dirname(__DIR__) . '/app/Helpers/stars.php';
$minRatio = 99; $minAt = 0; $minFill = ''; $darkFills = 0; $lightFills = 0; $total = 0;
for ($i = 0; $i <= 200; $i++) {
    $stars = $i / 20;
    $st = starStyle($stars);
    $total++;
    if ($st['fill'] === STAR_FILL_DARK) { $darkFills++; } else { $lightFills++; }
    if ($st['ratio'] < $minRatio) { $minRatio = $st['ratio']; $minAt = $stars; $minFill = $st['fill']; }
}
$starOk = $minRatio >= 3.0;
echo ($md ? "\n### Escala de dificultat 0–10\n\n" : "\n== Escala de dificultat 0-10 ==\n");
printf("%s %d valors (0,00 a 10,00, pas 0,05): contrast mínim %.2f:1 a %.2f estrelles sobre %s (mínim 3:1, text gran); fons fosc a %d valors, clar a %d\n",
    $md ? '' : ($starOk ? 'OK   ' : 'FAIL '), $total, $minRatio, $minAt, $minFill, $darkFills, $lightFills);
if (!$starOk) { $exit = 1; }
foreach (STAR_STOPS as $n => $hex) {
    $st = starStyle($n);
    printf($md ? "- %d estrelles: %s sobre %s = %.2f:1\n" : "      %2d estrelles  %s sobre %s = %.2f:1\n", $n, $st['color'], $st['fill'], $st['ratio']);
}
exit($exit);
