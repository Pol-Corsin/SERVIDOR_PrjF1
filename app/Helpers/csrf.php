<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Protecció CSRF. En aquesta fase només es deixa preparada; s'usarà als formularis
 * de registre, login, logout i edició (fases 3, 4 i 5).
 */

/** Retorna el token de la sessió (el crea si no existeix). */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Camp ocult per posar dins de cada <form method="post">. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Comprova el token rebut amb una comparació segura. */
function verifyCsrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
