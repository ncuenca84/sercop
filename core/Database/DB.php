<?php

/**
 * DB — Wrapper PDO con soporte multi-tenant automático
 * Uso: DB::query('SELECT * FROM procesos WHERE id = ?', [$id])
 */
class DB
{
    private static $pdo = null;
    private static $currentTenantId = null;

    // ── Conexión ──────────────────────────────────────────────────────────
    public static function connection()
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ));
        }
        return self::$pdo;
    }

    // ── Tenant ────────────────────────────────────────────────────────────
    public static function setTenant($tenantId)
    {
        self::$currentTenantId = (int) $tenantId;
    }

    public static function getTenantId()
    {
        return self::$currentTenantId;
    }

    // ── Consulta directa ──────────────────────────────────────────────────
    public static function query($sql, $params = array())
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // ── Obtener todos ──────────────────────────────────────────────────────
    public static function select($sql, $params = array())
    {
        return self::query($sql, $params)->fetchAll();
    }

    // ── Obtener uno ────────────────────────────────────────────────────────
    public static function selectOne($sql, $params = array())
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    // ── Insertar y retornar ID ─────────────────────────────────────────────
    public static function insert($table, $data)
    {
        $data = self::injectTenant($table, $data);
        $cols = implode(', ', array_map(function ($c) { return "`{$c}`"; }, array_keys($data)));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `{$table}` ({$cols}) VALUES ({$phs})", array_values($data));
        return (int) self::connection()->lastInsertId();
    }

    // ── Actualizar ─────────────────────────────────────────────────────────
    public static function update($table, $data, $where)
    {
        $set    = implode(', ', array_map(function ($c) { return "`{$c}` = ?"; }, array_keys($data)));
        $cond   = implode(' AND ', array_map(function ($c) { return "`{$c}` = ?"; }, array_keys($where)));
        $params = array_merge(array_values($data), array_values($where));
        $stmt   = self::query("UPDATE `{$table}` SET {$set} WHERE {$cond}", $params);
        return $stmt->rowCount();
    }

    // ── Ejecutar sentencia SQL directa (sin retorno) ───────────────────────
    public static function statement($sql, $params = array())
    {
        self::query($sql, $params);
        return true;
    }

    // ── Eliminar lógico ────────────────────────────────────────────────────
    public static function softDelete($table, $id, $col = 'id')
    {
        return self::update($table, array('deleted_at' => date('Y-m-d H:i:s')), array($col => $id));
    }

    // ── Contar ─────────────────────────────────────────────────────────────
    public static function count($sql, $params = array())
    {
        $row = self::selectOne($sql, $params);
        return (int) ($row ? array_values($row)[0] : 0);
    }

    // ── Paginación ─────────────────────────────────────────────────────────
    public static function paginate($sql, $params = array(), $page = 1, $perPage = PER_PAGE)
    {
        $total  = self::count("SELECT COUNT(*) FROM ({$sql}) AS t", $params);
        $offset = ($page - 1) * $perPage;
        $rows   = self::select("{$sql} LIMIT {$perPage} OFFSET {$offset}", $params);
        return array(
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
            'from'         => $offset + 1,
            'to'           => min($offset + $perPage, $total),
        );
    }

    // ── Transacción ────────────────────────────────────────────────────────
    public static function transaction($callback)
    {
        self::connection()->beginTransaction();
        try {
            $result = $callback();
            self::connection()->commit();
            return $result;
        } catch (Exception $e) {
            self::connection()->rollBack();
            throw $e;
        }
    }

    // ── Inyectar tenant_id automáticamente ───────────────────────────────
    private static function injectTenant($table, $data)
    {
        // Tablas que NO llevan tenant_id
        $skip = array('tenants', 'audit_logs', 'sessions', 'migrations');
        if (!in_array($table, $skip) && self::$currentTenantId && !isset($data['tenant_id'])) {
            $data = array_merge(array('tenant_id' => self::$currentTenantId), $data);
        }
        return $data;
    }

    // ── Registro de auditoría ─────────────────────────────────────────────
    public static function audit($accion, $entidad, $entidadId, $antes = null, $despues = null)
    {
        try {
            $stmt = self::connection()->prepare(
                "INSERT INTO audit_logs (tenant_id, user_id, accion, entidad_tipo, entidad_id,
                 valores_antes, valores_despues, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute(array(
                self::$currentTenantId,
                isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                $accion,
                $entidad,
                $entidadId,
                $antes   ? json_encode($antes,   JSON_UNESCAPED_UNICODE) : null,
                $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 300),
            ));
        } catch (Exception $e) {
            // Auditoría no debe romper el flujo principal
        }
    }
}
