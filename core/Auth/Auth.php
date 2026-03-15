<?php

declare(strict_types=1);

class Auth
{
    // ── Login ─────────────────────────────────────────────────────────────
    public static function attempt(string $email, string $password): bool
    {
        $user = DB::selectOne(
            "SELECT u.*, t.estado AS tenant_estado, t.slug AS tenant_slug
             FROM users u
             JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = ? AND u.estado = 'activo' AND u.deleted_at IS NULL
             LIMIT 1",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if ($user['tenant_estado'] !== 'activo') {
            return false;
        }

        self::loginUser($user);
        return true;
    }

    // ── Guardar sesión ────────────────────────────────────────────────────
    public static function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['tenant_id']  = $user['tenant_id'];
        $_SESSION['tenant_slug']= $user['tenant_slug'] ?? '';
        $_SESSION['user_name']  = $user['nombre'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['rol'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_at']   = time();

        // Cargar datos completos del tenant para plantillas Word
        $tenant = DB::selectOne("SELECT * FROM tenants WHERE id = ?", [$user['tenant_id']]);
        if ($tenant) {
            $_SESSION['tenant_nombre']        = $tenant['nombre']              ?? '';
            $_SESSION['tenant_ruc']           = $tenant['ruc']                 ?? '';
            $_SESSION['tenant_representante'] = $tenant['representante_legal'] ?? '';
            $_SESSION['tenant_ciudad']        = $tenant['ciudad']              ?? 'Quito';
            $_SESSION['tenant_direccion']     = $tenant['direccion']           ?? '';
            $_SESSION['tenant_telefono']      = $tenant['telefono']            ?? '';
            $_SESSION['tenant_email']         = $tenant['email']               ?? '';
            $_SESSION['tenant_tipo_contrib']  = $tenant['tipo_contribuyente']  ?? 'Sociedad';
            $_SESSION['tenant_regimen']       = $tenant['regimen_tributario']  ?? 'RIMPE';
            $_SESSION['tenant_logo']          = $tenant['logo_url']            ?? '';
        }

        // Actualizar último acceso
        DB::update('users', ['ultimo_acceso' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        // Configurar tenant en DB
        DB::setTenant((int) $user['tenant_id']);

        // Auditoría
        DB::audit('LOGIN', 'users', (int)$user['id']);
    }

    // ── Cerrar sesión ─────────────────────────────────────────────────────
    public static function logout(): void
    {
        DB::audit('LOGOUT', 'users', (int)($_SESSION['user_id'] ?? 0));
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── Verificaciones ────────────────────────────────────────────────────
    public static function check(): bool
    {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        return [
            'id'          => $_SESSION['user_id'],
            'tenant_id'   => $_SESSION['tenant_id'],
            'tenant_slug' => $_SESSION['tenant_slug'],
            'nombre'      => $_SESSION['user_name'],
            'email'       => $_SESSION['user_email'],
            'rol'         => $_SESSION['user_role'],
        ];
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function tenantId(): ?int
    {
        return isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    // ── Permisos por rol ──────────────────────────────────────────────────
    public static function can(string $permission): bool
    {
        $role = self::role();
        $perms = [
            'super_admin' => ['*'],
            'admin'       => ['procesos.*', 'instituciones.*', 'documentos.*', 'facturas.*',
                              'pagos.*', 'usuarios.*', 'configuracion.*', 'ia.*', 'reportes.*'],
            'gestor'      => ['procesos.*', 'instituciones.*', 'documentos.*', 'facturas.ver',
                              'ia.*', 'reportes.ver'],
            'contador'    => ['facturas.*', 'pagos.*', 'reportes.*', 'procesos.ver'],
            'visualizador'=> ['procesos.ver', 'instituciones.ver', 'reportes.ver', 'facturas.ver'],
        ];

        $rolPerms = $perms[$role] ?? [];
        if (in_array('*', $rolPerms)) return true;

        [$module, $action] = explode('.', $permission, 2);
        return in_array("{$module}.*", $rolPerms) || in_array($permission, $rolPerms);
    }

    // ── Hash de password ──────────────────────────────────────────────────
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ── Token CSRF ────────────────────────────────────────────────────────
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}