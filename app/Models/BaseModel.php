<?php

declare(strict_types=1);

abstract class BaseModel
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool   $useTenant = true;
    protected static bool   $softDelete = true;

    // ── Buscar por ID ─────────────────────────────────────────────────────
    public static function find(int $id): ?array
    {
        $where = static::$useTenant ? 'AND tenant_id = ?' : '';
        $params = static::$useTenant ? [$id, DB::getTenantId()] : [$id];
        return DB::selectOne(
            "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ? {$where}"
            . (static::$softDelete ? " AND deleted_at IS NULL" : ""),
            $params
        );
    }

    public static function findOrFail(int $id): array
    {
        $row = static::find($id);
        if (!$row) throw new \RuntimeException("Registro #{$id} no encontrado en " . static::$table);
        return $row;
    }

    // ── Todos ─────────────────────────────────────────────────────────────
    public static function all(string $orderBy = 'created_at DESC'): array
    {
        $where = static::$useTenant ? "WHERE tenant_id = " . (int)DB::getTenantId() : "WHERE 1=1";
        $del   = static::$softDelete ? " AND deleted_at IS NULL" : "";
        return DB::select("SELECT * FROM " . static::$table . " {$where}{$del} ORDER BY {$orderBy}");
    }

    // ── Crear ─────────────────────────────────────────────────────────────
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        if (static::$useTenant && !isset($data['tenant_id'])) {
            $data['tenant_id'] = DB::getTenantId();
        }
        return DB::insert(static::$table, $data);
    }

    // ── Actualizar ────────────────────────────────────────────────────────
    public static function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $where = [static::$primaryKey => $id];
        if (static::$useTenant) $where['tenant_id'] = DB::getTenantId();
        return DB::update(static::$table, $data, $where);
    }

    // ── Eliminar lógico ────────────────────────────────────────────────────
    public static function delete(int $id): int
    {
        if (static::$softDelete) {
            return static::update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        return DB::query("DELETE FROM " . static::$table . " WHERE id = ?", [$id])->rowCount();
    }

    // ── Where ─────────────────────────────────────────────────────────────
    public static function where(array $conditions, string $orderBy = 'created_at DESC'): array
    {
        if (static::$useTenant) $conditions['tenant_id'] = DB::getTenantId();
        $sql    = "SELECT * FROM " . static::$table . " WHERE ";
        $wheres = array_map(fn($c) => "{$c} = ?", array_keys($conditions));
        $sql   .= implode(' AND ', $wheres);
        if (static::$softDelete) $sql .= " AND deleted_at IS NULL";
        $sql   .= " ORDER BY {$orderBy}";
        return DB::select($sql, array_values($conditions));
    }

    public static function whereOne(array $conditions): ?array
    {
        $rows = static::where($conditions);
        return $rows[0] ?? null;
    }

    // ── Contar ────────────────────────────────────────────────────────────
    public static function count(array $conditions = []): int
    {
        if (static::$useTenant) $conditions['tenant_id'] = DB::getTenantId();
        $where = empty($conditions) ? '1=1' : implode(' AND ', array_map(fn($c) => "{$c} = ?", array_keys($conditions)));
        $del   = static::$softDelete ? " AND deleted_at IS NULL" : "";
        return DB::count("SELECT COUNT(*) FROM " . static::$table . " WHERE {$where}{$del}", array_values($conditions));
    }
}
