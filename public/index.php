<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Front controller: ÚNIC punt d'entrada de l'aplicació.
 * Apache envia aquí totes les peticions (vegeu els .htaccess).
 */

declare(strict_types=1);

// Arrel del projecte (una carpeta per sobre de public/)
define('ROOT_PATH', dirname(__DIR__));

// Mai mostrem errors al navegador; es registren a storage/logs/error.log
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/storage/logs/error.log');
error_reporting(E_ALL);

// Helpers (funcions reutilitzables)
require ROOT_PATH . '/app/Helpers/escape.php';
require ROOT_PATH . '/app/Helpers/url.php';
require ROOT_PATH . '/app/Helpers/format.php';
require ROOT_PATH . '/app/Helpers/csrf.php';
require ROOT_PATH . '/app/Helpers/flash.php';
require ROOT_PATH . '/app/Helpers/auth.php';
require ROOT_PATH . '/app/Helpers/validation.php';
require ROOT_PATH . '/app/Helpers/ownership.php';
require ROOT_PATH . '/app/Helpers/stars.php';
require ROOT_PATH . '/app/Helpers/upload.php';

// Autoload senzill: busca la classe a Core, Models i Controllers
spl_autoload_register(function (string $class): void {
    foreach (['Core', 'Models', 'Controllers'] as $dir) {
        $file = ROOT_PATH . "/app/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

try {
    $config = require ROOT_PATH . '/config/config.php';
    Database::configure($config['db']);
    Session::start($config['session']);
    validateSessionUser();   // si el compte de la sessió ja no existeix a la BD, la sessió s'invalida

    // Capçaleres de seguretat bàsiques
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    // Amb sessió iniciada, el navegador no guarda les pàgines (el botó "enrere" no mostra dades després del logout)
    if (isLoggedIn()) {
        header('Cache-Control: no-store');
    }

    $router = new Router();
    $router->get('/', 'HomeController@index');
    $router->get('/scores', 'ScoreController@index');
    $router->get('/scores/new', 'ScoreController@create');
    $router->post('/scores/new', 'ScoreController@store');
    $router->get('/scores/{id}', 'ScoreController@show');
    $router->get('/guia-estils', 'HomeController@styleguide');
    $router->get('/login', 'AuthController@showLogin');
    $router->post('/login', 'AuthController@login');
    $router->get('/register', 'AuthController@showRegister');
    $router->post('/register', 'AuthController@register');
    $router->post('/logout', 'AuthController@logout');
    $router->get('/my-scores', 'ScoreController@mine');
    $router->get('/scores/{id}/edit', 'ScoreController@edit');
    $router->post('/scores/{id}/edit', 'ScoreController@update');
    $router->get('/scores/{id}/delete', 'ScoreController@confirmDelete');
    $router->post('/scores/{id}/delete', 'ScoreController@destroy');
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', requestPath());
} catch (Throwable $e) {
    // L'error detallat va al log; l'usuari veu un missatge genèric
    error_log($e::class . ': ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    if (!headers_sent()) {
        http_response_code(500);
    }
    $title = 'Error del servidor';
    ob_start();
    require ROOT_PATH . '/app/Views/errors/500.php';
    $content = ob_get_clean();
    require ROOT_PATH . '/app/Views/layouts/main.php';
}
