<?php

declare(strict_types=1);

abstract class BaseController
{
    protected function view(string $view, array $data = [], int $status = 200): void
    {
        // Datos globales disponibles en todas las vistas
        $data['auth']          = Auth::user();
        $data['flash_messages']= View::getFlash();
        $data['notificaciones']= Auth::check()
            ? Notificacion::sinLeer((int)Auth::id())
            : [];
        View::make('app', $view, $data, $status);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        View::json($data, $status);
    }

    protected function redirect(string $path): void
    {
        View::redirect($path);
    }

    protected function back(): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        View::redirect($ref);
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function allInput(): array
    {
        return array_merge($_GET, $_POST);
    }

    protected function validate(array $rules): array
    {
        $v = Validator::make($this->allInput(), $rules);
        if ($v->fails()) {
            if ($this->isAjax()) {
                jsonError('Datos inválidos', 422, $v->errors());
            }
            View::flash('error', $v->firstError());
            $this->back();
            exit;
        }
        return $this->allInput();
    }

    protected function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function requirePermission(string $perm): void
    {
        if (!Auth::can($perm)) {
            View::flash('error', 'No tiene permisos para esta acción.');
            $this->redirect('/dashboard');
            exit;
        }
    }
}
