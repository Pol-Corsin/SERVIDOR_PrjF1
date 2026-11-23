<?php
/**
 * Autor: Pol Corsin — PT04 osu! Scores
 * Router mínim: associa "MÈTODE + ruta" amb "Controlador@acció".
 * Admet paràmetres numèrics a la ruta: '/scores/{id}' crida l'acció amb l'id com a enter.
 */
class Router
{
    /** @var array<string, array<string, string>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, string $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            // '{id}' -> grup numèric; la resta de la ruta s'escapa
            $regex = '#^' . preg_replace('#\\\{(\w+)\\\}#', '(\d+)', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                [$controllerName, $action] = explode('@', $handler);
                (new $controllerName())->$action(...array_map('intval', $matches));
                return;
            }
        }

        (new Controller())->abort(404);
    }
}
