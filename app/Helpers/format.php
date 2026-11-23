<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Funcions per formatar dades a la interfície (format català).
 */

/** 24185320 -> "24.185.320" */
function formatScore(int|string $score): string
{
    return number_format((float) $score, 0, ',', '.');
}

/** 98.7 -> "98,70 %" */
function formatAccuracy(float|string $accuracy): string
{
    return number_format((float) $accuracy, 2, ',', '.') . ' %';
}

/** 412.6 -> "412,60" */
function formatPp(float|string $pp): string
{
    return number_format((float) $pp, 2, ',', '.');
}

/** "2026-09-02 18:20:00" -> "02/09/2026" */
function formatDate(string $datetime): string
{
    $ts = strtotime($datetime);
    return $ts === false ? '' : date('d/m/Y', $ts);
}

/** 4.0 -> "4"; 9.3 -> "9,3" (CS, AR) */
function formatTrim(float|string $value, int $decimals = 1): string
{
    $s = number_format((float) $value, $decimals, ',', '.');
    return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
}

/** 5.3 -> "5,30" */
function formatStars(float|string $stars): string
{
    return number_format((float) $stars, 2, ',', '.');
}
