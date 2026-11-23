<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Controlador d'autenticació: registre, inici de sessió i tancament de sessió.
 */
class AuthController extends Controller
{
    // Hash fals per fer password_verify() també quan l'usuari no existeix:
    // així el temps de resposta no revela si el nom d'usuari existeix.
    private const DUMMY_HASH = '$2y$10$yy2z8C5ya2SUytCUDn9S0.4ykAK.gzkx5RlPWTK6SH3loLzEjxHvq';

    // ---------------------------------------------------------------- Registre

    public function showRegister(): void
    {
        $this->guestOnly();
        $this->view('auth/register', ['title' => 'Registra\'t', 'values' => [], 'errors' => []]);
    }

    public function register(): void
    {
        $this->guestOnly();
        $this->requireValidCsrf();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $errors = [];

        // 1) Nom d'usuari: format i unicitat
        if ($err = validateUsername($username)) {
            $errors['username'] = $err;
        } elseif (User::usernameExists($username)) {
            $errors['username'] = 'Aquest nom d\'usuari ja està en ús. Tria\'n un altre.';
        }

        // 2) Contrasenya: força i confirmació
        if ($err = validatePasswordStrength($password)) {
            $errors['password'] = $err;
        }
        if ($confirm === '') {
            $errors['password_confirm'] = 'Repeteix la contrasenya.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'Les dues contrasenyes no coincideixen.';
        }

        // 3) Si tot és correcte: guardem NOMÉS el hash (mai el text pla)
        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (User::create($username, $hash)) {
                flash('success', 'Compte creat correctament. Ja pots iniciar sessió.');
                $this->redirect('/login');
            }
            // Un altre usuari ha agafat el nom just ara (restricció UNIQUE)
            $errors['username'] = 'Aquest nom d\'usuari ja està en ús. Tria\'n un altre.';
        }

        // Tornem al formulari: es conserva el nom d'usuari, MAI les contrasenyes
        $this->view('auth/register', [
            'title'  => 'Registra\'t',
            'values' => ['username' => $username],
            'errors' => $errors,
        ], 422);
    }

    // ------------------------------------------------------------------- Login

    public function showLogin(): void
    {
        $this->guestOnly();
        $this->view('auth/login', ['title' => 'Inicia sessió', 'values' => [], 'error' => null]);
    }

    public function login(): void
    {
        $this->guestOnly();
        $this->requireValidCsrf();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = $username !== '' ? User::findByUsername($username) : null;
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH) && $user !== null;

        if (!$valid) {
            // Missatge genèric: no diu si l'usuari existeix o no
            $this->view('auth/login', [
                'title'  => 'Inicia sessió',
                'values' => ['username' => mb_substr($username, 0, 20)],
                'error'  => 'El nom d\'usuari o la contrasenya no són correctes.',
            ], 401);
            return;
        }

        // Si PHP ha millorat l'algorisme per defecte, actualitzem el hash guardat
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        loginUser($user); // regenera l'ID de sessió
        flash('success', 'Hola, ' . $user['username'] . '! Has iniciat sessió.');
        $this->redirect('/my-scores');
    }

    // ------------------------------------------------------------------ Logout

    public function logout(): void
    {
        $this->requireValidCsrf();
        logoutUser();
        flash('success', 'Has tancat la sessió.');
        $this->redirect('/');
    }

    /** Si ja hi ha sessió, no té sentit veure login/registre. */
    private function guestOnly(): void
    {
        if (isLoggedIn()) {
            $this->redirect('/my-scores');
        }
    }
}
