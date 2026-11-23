<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Controlador base: renderitza vistes dins del layout, redirigeix, controla l'accés i mostra errors HTTP.
 */
class Controller
{
    /**
     * Renderitza app/Views/<vista>.php dins del layout principal.
     * Les claus de $data esdevenen variables a la vista.
     */
    protected function view(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        extract($data, EXTR_SKIP);

        ob_start();
        require ROOT_PATH . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();

        require ROOT_PATH . '/app/Views/layouts/main.php';
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    /** Exigeix sessió iniciada; si no n'hi ha, envia a /login amb un missatge. */
    protected function requireLogin(): void
    {
        if (!isLoggedIn()) {
            flash('info', 'Inicia sessió per accedir a aquesta pàgina.');
            $this->redirect('/login');
        }
    }

    /** Només per a peticions POST: comprova el token CSRF o mostra un 403. */
    protected function requireValidCsrf(): void
    {
        if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->abort(403, 'La sol·licitud no és vàlida o ha caducat. Torna enrere, recarrega la pàgina i prova-ho de nou.');
            exit;
        }
    }

    /** Mostra una pàgina d'error (403, 404...). */
    public function abort(int $status, ?string $message = null): void
    {
        $this->view('errors/' . $status, [
            'title'   => $status === 404 ? 'Pàgina no trobada' : 'Accés denegat',
            'message' => $message,
        ], $status);
    }
}
