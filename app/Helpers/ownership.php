<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Comprovació de propietat d'una score. Es consulta SEMPRE a la base de dades del servidor:
 * amagar el botó "Edita" no és seguretat, aquesta comprovació sí.
 */

function userOwnsScore(int $scoreId, int $userId): bool
{
    return Score::isOwnedBy($scoreId, $userId);
}
