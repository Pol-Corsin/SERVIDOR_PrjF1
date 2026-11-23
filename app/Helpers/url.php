<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Helpers d'URL. Calculen la carpeta base en temps d'execució, de manera que
 * l'aplicació funciona a qualsevol carpeta de htdocs sense escriure rutes absolutes.
 *
 * Exemple: si el projecte és a C:\xampp\htdocs\osu\ la base és "/osu".
 */

/**
 * Carpeta base de l'aplicació a l'URL (sense barra final). Pot ser "" si és a l'arrel.
 */
function basePath(): string
{
    // SCRIPT_NAME acaba sempre en "/public/index.php" (p. ex. /osu/public/index.php)
    $publicDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $publicDir = rtrim($publicDir, '/');
    $root = preg_replace('#/public$#', '', $publicDir);

    // Si l'usuari ha entrat directament per /public/, mantenim aquest prefix als enllaços
    $uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $enterViaPublic = str_starts_with($uriPath, $publicDir . '/') || $uriPath === $publicDir;
    if ($publicDir !== $root && $enterViaPublic) {
        return $publicDir;
    }
    return $root;
}

/** Ruta de la petició actual, relativa a la base ("/" , "/scores"...). */
function requestPath(): string
{
    $uriPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = basePath();
    if ($base !== '' && str_starts_with($uriPath, $base)) {
        $uriPath = substr($uriPath, strlen($base));
    }
    // Si algú obre /index.php directament, ho tractem com a inici
    if ($uriPath === '/index.php') {
        $uriPath = '/';
    }
    $uriPath = '/' . trim($uriPath, '/');
    return $uriPath;
}

/** URL completa d'una pàgina de l'aplicació: url('/scores'). */
function url(string $path = '/'): string
{
    return basePath() . '/' . ltrim($path, '/');
}

/** URL d'un recurs estàtic de public/: asset('css/base.css'). */
function asset(string $path): string
{
    return basePath() . '/' . ltrim($path, '/');
}
