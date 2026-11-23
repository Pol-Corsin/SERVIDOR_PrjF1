<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Sessió segura. Només la cookie de sessió (ID aleatori) viu al navegador;
 * les dades viuen al servidor. Vegeu docs/BACKEND.md.
 */
class Session
{
    public static function start(array $config): void
    {
        $inactivity = (int) $config['inactivity_time'];
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_name($config['name']);
        ini_set('session.use_strict_mode', '1');   // no accepta IDs de sessió inventats
        ini_set('session.use_only_cookies', '1');  // l'ID mai va a l'URL
        // PHP no ha d'esborrar la sessió abans que expiri la nostra inactivitat
        ini_set('session.gc_maxlifetime', (string) ($inactivity + 900));

        session_set_cookie_params([
            'lifetime' => 0,          // cookie de sessió; l'expiració real la controla el servidor
            'path'     => '/',
            'secure'   => $https,     // només per HTTPS quan n'hi ha
            'httponly' => true,       // JavaScript no pot llegir la cookie
            'samesite' => 'Lax',
        ]);
        session_start();

        // Expiració per inactivitat (45 min). L'activitat s'actualitza a CADA petició (expiració lliscant).
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivity) {
            $wasLoggedIn = isLoggedIn();
            self::destroy();
            session_start();
            if ($wasLoggedIn) {
                flash('info', 'La sessió ha caducat per inactivitat. Torna a iniciar sessió.');
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /** Tanca la sessió completament (dades + cookie). */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
    }
}
