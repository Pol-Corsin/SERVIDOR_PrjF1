<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Escala de color de la dificultat (estrelles), de 0 a 10.
 *
 * 11 colors de referència (un per estrella sencera). Els valors decimals (3,45; 5,8; 9,2)
 * s'obtenen INTERPOLANT entre els dos colors veïns, de manera que el canvi és progressiu.
 *
 *   0 gris molt fosc · 1 blau clar · 2 verd · 3 groc · 4 taronja · 5 vermell
 *   6 rosa · 7 lila · 8 lila fosc · 9 lila gairebé negre · 10 negre
 *
 * LLEGIBILITAT (tots dos temes): el número i el contorn comparteixen el color de l'escala,
 * i el fons de la "pastilla" es tria entre un fons FOSC i un de CLAR segons quin doni més
 * contrast. Així els colors vius (groc, verd...) es veuen sobre fosc, i els molt foscos
 * (lila fosc, negre...) es veuen sobre clar, tant al tema clar com al fosc.
 * El número es mostra gran i en negreta (text gran de WCAG: mínim 3:1; en la pràctica es
 * supera amb escreix, vegeu tools/tests.php).
 */

const STAR_STOPS = [
    0  => '#3B3B46',
    1  => '#5EC8FF',
    2  => '#5BE37A',
    3  => '#FFD43B',
    4  => '#FF9A3C',
    5  => '#FF4B4B',
    6  => '#FF5CA8',
    7  => '#B05CFF',
    8  => '#6A2EC2',
    9  => '#2D1458',
    10 => '#000000',
];
const STAR_FILL_DARK  = '#14111C';
const STAR_FILL_LIGHT = '#F4F1FB';

/** Luminància relativa WCAG d'un color #RRGGBB. */
function starLuminance(string $hex): float
{
    $c = array_map(fn($h) => hexdec($h) / 255, str_split(ltrim($hex, '#'), 2));
    $c = array_map(fn($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** Ràtio de contrast WCAG entre dos colors. */
function starContrast(string $a, string $b): float
{
    [$l1, $l2] = [starLuminance($a), starLuminance($b)];
    if ($l1 < $l2) { [$l1, $l2] = [$l2, $l1]; }
    return ($l1 + 0.05) / ($l2 + 0.05);
}

/**
 * Color i fons de la pastilla per a una dificultat. Entrada: estrelles (es limita a 0-10).
 * Retorna ['color' => '#RRGGBB', 'fill' => '#RRGGBB', 'ratio' => float].
 */
function starStyle(float|string $stars): array
{
    $s = max(0.0, min(10.0, (float) $stars));
    $lo = (int) floor($s);
    $hi = min(10, $lo + 1);
    $t = $s - $lo;

    $rgb = [];
    $from = array_map('hexdec', str_split(ltrim(STAR_STOPS[$lo], '#'), 2));
    $to = array_map('hexdec', str_split(ltrim(STAR_STOPS[$hi], '#'), 2));
    foreach ([0, 1, 2] as $i) {
        $rgb[] = (int) round($from[$i] + ($to[$i] - $from[$i]) * $t);
    }
    $color = sprintf('#%02X%02X%02X', ...$rgb);

    $onDark = starContrast($color, STAR_FILL_DARK);
    $onLight = starContrast($color, STAR_FILL_LIGHT);
    return $onDark >= $onLight
        ? ['color' => $color, 'fill' => STAR_FILL_DARK, 'ratio' => $onDark]
        : ['color' => $color, 'fill' => STAR_FILL_LIGHT, 'ratio' => $onLight];
}

/** Atribut style="..." amb les variables CSS de la pastilla (valors generats pel servidor, no per l'usuari). */
function starStyleAttr(float|string $stars): string
{
    $st = starStyle($stars);
    return '--star: ' . $st['color'] . '; --star-fill: ' . $st['fill'];
}
