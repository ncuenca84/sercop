<?php

declare(strict_types=1);

/**
 * DB — Wrapper PDO con soporte multi-tenant automático
 * Uso: DB::query('SELECT * FROM procesos WHERE id = ?', [$id])
 */
class DB
{
    private static ?PDO $pdo = null;
    private static ?int $currentTenantId = null;

    // ── Conexión ──────────────────────────────────────────────────────────
    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }
        return self::$pdo;
    }

    // ── Tenant ────────────────────────────────────────────────────────────
    public static function setTenant(int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }

    public static function getTenantId(): ?int
    {
        return self::$currentTenantId;
    }

    // ── Consulta directa ──────────────────────────────────────────────────
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // ── Obtener todos ──────────────────────────────────────────────────────
    public static function select(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    // ── Obtener uno ────────────────────────────────────────────────────────
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    // ── Insertar y retornar ID ─────────────────────────────────────────────
    public static function insert(string $table, array $data): int
    {
        $data = self::injectTenant($table, $data);
        $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `{$table}` ({$cols}) VALUES ({$phs})", array_values($data));
        return (int) self::connection()->lastInsertId();
    }

    // ── Actualizar ─────────────────────────────────────────────────────────
    public static function update(string $table, array $data, array $where): int
    {
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $cond   = implode(' AND ', array_map(fn($c) => "`{$c}` = ?", array_keys($where)));
        $params = [...array_values($data), ...array_values($where)];
        $stmt   = self::query("UPDATE `{$table}` SET {$set} WHERE {$cond}", $params);
        return $stmt->rowCount();
    }

    // ── Ejecutar sentencia SQL directa (sin retorno) ───────────────────────
    public static function statement(string $sql, array $params = []): bool
    {
        self::query($sql, $params);
        return true;
    }

    // ── Eliminar lógico ────────────────────────────────────────────────────
    public static function softDelete(string $table, int $id, string $col = 'id'): int
    {
        return self::update($table, ['deleted_at' => date('Y-m-d H:i:s')], [$col => $id]);
    }

    // ── Contar ─────────────────────────────────────────────────────────────
    public static function count(string $sql, array $params = []): int
    {
        $row = self::selectOne($sql, $params);
        return (int) ($row ? array_values($row)[0] : 0);
    }

    // ── Paginación ─────────────────────────────────────────────────────────
    public static function paginate(string $sql, array $params = [], int $page = 1, int $perPage = PER_PAGE): array
    {
        $total = self::count("SELECT COUNT(*) FROM ({$sql}) AS t", $params);
        $offset = ($page - 1) * $perPage;
        $rows  = self::select("{$sql} LIMIT {$perPage} OFFSET {$offset}", $params);
        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
            'from'         => $offset + 1,
            'to'           => min($offset + $perPage, $total),
        ];
    }

    // ── Transacción ────────────────────────────────────────────────────────
    public static function transaction(callable $callback): mixed
    {
        self::connection()->beginTransaction();
        try {
            $result = $callback();
            self::connection()->commit();
            return $result;
        } catch (\Throwable $e) {
            self::connection()->rollBack();
            throw $e;
        }
    }

    // ── Inyectar tenant_id automáticamente ───────────────────────────────
    private static function injectTenant(string $table, array $data): array
    {
        // Tablas que NO llevan tenant_id
        $skip = ['tenants', 'audit_logs', 'sessions', 'migrations'];
        if (!in_array($table, $skip) && self::$currentTenantId && !isset($data['tenant_id'])) {
            $data = array_merge(['tenant_id' => self::$currentTenantId], $data);
        }
        return $data;
    }

    // ── Registro de auditoría ─────────────────────────────────────────────
    public static function audit(string $accion, string $entidad, int $entidadId,
                                  ?array $antes = null, ?array $despues = null): void
    {
        try {
            $stmt = self::connection()->prepare(
                "INSERT INTO audit_logs (tenant_id, user_id, accion, entidad_tipo, entidad_id,
                 valores_antes, valores_despues, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                self::$currentTenantId,
                $_SESSION['user_id'] ?? null,
                $accion,
                $entidad,
                $entidadId,
                $antes   ? json_encode($antes, JSON_UNESCAPED_UNICODE)   : null,
                $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
            ]);
        } catch (\Throwable) {
            // Auditoría no debe romper el flujo principal
        }
    }
}
