<?php

declare(strict_types=1);

class Router
{
    private static ?Router $instance = null;
    private array $routes = [];
    private array $middleware = [];

    public static function getInstance(): self
    {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    // ── Registro de rutas ─────────────────────────────────────────────────
    public function get(string $path, string|array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
    }

    public function post(string $path, string|array $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    public function put(string $path, string|array $action, array $middleware = []): void
    {
        $this->add('PUT', $path, $action, $middleware);
    }

    public function patch(string $path, string|array $action, array $middleware = []): void
    {
        $this->add('PATCH', $path, $action, $middleware);
    }

    public function delete(string $path, string|array $action, array $middleware = []): void
    {
        $this->add('DELETE', $path, $action, $middleware);
    }

    private function add(string $method, string $path, string|array $action, array $mw): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'action'     => $action,
            'middleware' => $mw,
            'pattern'    => $this->buildPattern($path),
        ];
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    // ── Despachar ─────────────────────────────────────────────────────────
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        // Soporte para _method en forms HTML
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Quitar subfolder si aplica (para cPanel en subdirectorio)
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Ejecutar middleware
                foreach ($route['middleware'] as $mw) {
                    $middlewareObj = new $mw();
                    $middlewareObj->handle();
                }

                // Ejecutar controlador
                [$controller, $action] = is_array($route['action'])
                    ? $route['action']
                    : explode('@', $route['action']);

                $ctrl = new $controller();
                $ctrl->$action(...array_values($params));
                return;
            }
        }

        // 404
        http_response_code(404);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Ruta no encontrada']);
        } else {
            require_once RESOURCES_PATH . '/views/errors/404.php';
        }
    }
}
