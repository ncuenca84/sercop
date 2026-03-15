<?php

declare(strict_types=1);

class View
{
    private static array $shared = [];

    // ── Compartir datos globales con todas las vistas ─────────────────────
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    // ── Renderizar vista ─────────────────────────────────────────────────
    public static function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        $data = array_merge(self::$shared, $data);
        $file = RESOURCES_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }
        extract($data, EXTR_SKIP);
        require $file;
    }

    // ── Renderizar con layout ─────────────────────────────────────────────
    public static function make(string $layout, string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        $data = array_merge(self::$shared, $data);

        $viewFile = RESOURCES_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }

        // Capturar el contenido de la vista
        ob_start();
        extract($data, EXTR_SKIP);
        require $viewFile;
        $content = ob_get_clean();

        // Renderizar el layout con el contenido
        $layoutFile = RESOURCES_PATH . '/views/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout no encontrado: {$layout}");
        }
        extract($data, EXTR_SKIP);
        require $layoutFile;
    }

    // ── JSON response ─────────────────────────────────────────────────────
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── Redirect ──────────────────────────────────────────────────────────
    public static function redirect(string $url, int $status = 302): void
    {
        if (!str_starts_with($url, 'http')) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? 'localhost';
            $url    = $scheme . '://' . $host . '/' . ltrim($url, '/');
        }
        header("Location: {$url}", true, $status);
        exit;
    }

    // ── Escape HTML ───────────────────────────────────────────────────────
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    // ── Flash messages ────────────────────────────────────────────────────
    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}
