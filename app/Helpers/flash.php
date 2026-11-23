<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Missatges "flash": es guarden a la sessió i es mostren UNA sola vegada a la pàgina següent
 * (per exemple, després d'una redirecció).
 */

/** $type: 'success' | 'error' | 'info' */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Retorna i esborra els missatges pendents. */
function takeFlashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}
