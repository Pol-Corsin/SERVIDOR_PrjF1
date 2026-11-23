<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Funcions d'autenticació. L'usuari autenticat es guarda a la SESSIÓ (al servidor):
 * només l'ID de sessió viatja al navegador dins d'una cookie. Vegeu docs/BACKEND.md.
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

function currentUsername(): ?string
{
    return isLoggedIn() ? (string) ($_SESSION['username'] ?? '') : null;
}

/**
 * Inicia la sessió de l'usuari un cop verificada la contrasenya.
 * session_regenerate_id(true) canvia l'ID de sessió i esborra l'antic (evita la fixació de sessió).
 */
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // token nou per a la sessió nova
}

/** Tanca la sessió: esborra dades i cookie, i en crea una de buida per mostrar el missatge. */
function logoutUser(): void
{
    Session::destroy();
    session_start();
    $_SESSION['last_activity'] = time();
}

/** Color d'avatar (0-5) estable per a cada nom d'usuari: només decoratiu. */
function avatarTone(string $username): int
{
    return crc32($username) % 6;
}
