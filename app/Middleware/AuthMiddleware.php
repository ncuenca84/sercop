<?php

declare(strict_types=1);

class AuthMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            View::flash('error', 'Debe iniciar sesión para continuar.');
            View::redirect('/login');
        }
        // Refrescar tenant en DB
        DB::setTenant(Auth::tenantId());
    }
}

class GuestMiddleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            View::redirect('/dashboard');
        }
    }
}

class ApiAuthMiddleware
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()) {
            jsonError('No autorizado', 401);
        }
        DB::setTenant(Auth::tenantId());
    }
}

class CsrfMiddleware
{
    public function handle(): void
    {
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            verifyCsrf();
        }
    }
}

class CanMiddleware
{
    public function __construct(private string $permission) {}
    public function handle(): void
    {
        if (!Auth::can($this->permission)) {
            flash('error', 'No tiene permisos para esta acción.');
            View::redirect('/dashboard');
        }
    }
}
