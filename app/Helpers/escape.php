<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Escapat de sortida (prevenció d'XSS). S'ha d'usar a TOTA dada que es mostri a les vistes.
 */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
