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

/**
 * Comprova que l'usuari de la sessió ENCARA EXISTEIX a la base de dades (una consulta per petició amb sessió).
 * Si el compte ja no hi és (per exemple, perquè s'ha tornat a importar el SQL o s'ha esborrat l'usuari), la sessió
 * s'invalida: si no, es provaria d'inserir scores amb un user_id inexistent i fallaria la clau forana (error 500).
 * També manté el nom d'usuari de la sessió sincronitzat amb la BD.
 */
function validateSessionUser(): void
{
    if (!isLoggedIn()) {
        return;
    }
    $name = User::usernameById((int) $_SESSION['user_id']);
    if ($name === null) {
        logoutUser();
        flash('error', 'El teu compte ja no existeix (la base de dades s\'ha tornat a crear o s\'ha esborrat l\'usuari). Torna a iniciar sessió o registra\'t de nou.');
        return;
    }
    $_SESSION['username'] = $name;
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
