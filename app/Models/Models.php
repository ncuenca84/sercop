<?php

declare(strict_types=1);

require_once APP_PATH . '/Models/BaseModel.php';

// ══════════════════════════════════════════════════════════════════════════
// TENANT
// ══════════════════════════════════════════════════════════════════════════
class Tenant extends BaseModel
{
    protected static string $table = 'tenants';
    protected static bool $useTenant = false;
    protected static bool $softDelete = false;

    public static function findBySlug(string $slug): ?array
    {
        return DB::selectOne("SELECT * FROM tenants WHERE slug = ? AND estado = 'activo' LIMIT 1", [$slug]);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// USER
// ══════════════════════════════════════════════════════════════════════════
class User extends BaseModel
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return DB::selectOne(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1", [$email]
        );
    }

    public static function createUser(array $data): int
    {
        $data['password_hash'] = Auth::hashPassword($data['password']);
        unset($data['password'], $data['password_confirmation']);
        return static::create($data);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// INSTITUCIÓN
// ══════════════════════════════════════════════════════════════════════════
class Institucion extends BaseModel
{
    protected static string $table = 'instituciones';

    public static function search(string $q): array
    {
        return DB::select(
            "SELECT * FROM instituciones WHERE tenant_id = ? AND deleted_at IS NULL
             AND (nombre LIKE ? OR ruc LIKE ?) ORDER BY nombre LIMIT 20",
            [DB::getTenantId(), "%{$q}%", "%{$q}%"]
        );
    }

    public static function conEstadisticas(): array
    {
        return DB::select(
            "SELECT i.*,
                COUNT(p.id) AS total_procesos,
                SUM(CASE WHEN p.estado = 'pagado' THEN p.monto_total ELSE 0 END) AS total_pagado,
                SUM(CASE WHEN p.estado NOT IN ('pagado','cerrado','cancelado') THEN p.monto_total ELSE 0 END) AS saldo_pendiente,
                AVG(DATEDIFF(pg.fecha_pago, f.fecha_emision)) AS dias_pago_promedio
             FROM instituciones i
             LEFT JOIN procesos p ON p.institucion_id = i.id AND p.deleted_at IS NULL
             LEFT JOIN facturas f ON f.proceso_id = p.id
             LEFT JOIN pagos pg ON pg.factura_id = f.id
             WHERE i.tenant_id = ? AND i.deleted_at IS NULL
             GROUP BY i.id ORDER BY total_procesos DESC",
            [DB::getTenantId()]
        );
    }
}

// ══════════════════════════════════════════════════════════════════════════
// PROCESO
// ══════════════════════════════════════════════════════════════════════════
class Proceso extends BaseModel
{
    protected static string $table = 'procesos';

    public static function conInstitucion(int $id): ?array
    {
        return DB::selectOne(
            "SELECT p.*, i.nombre AS institucion_nombre, i.ruc AS institucion_ruc,
                    i.administrador_nombre, i.administrador_email, i.administrador_cargo,
                    i.ciudad AS institucion_ciudad, i.direccion AS institucion_direccion
             FROM procesos p
             JOIN instituciones i ON i.id = p.institucion_id
             WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL",
            [$id, DB::getTenantId()]
        );
    }

    public static function listar(array $filtros = [], int $page = 1): array
    {
        $where  = ['p.tenant_id = ?'];
        $params = [DB::getTenantId()];

        if (!empty($filtros['estado']))       { $where[] = 'p.estado = ?';        $params[] = $filtros['estado']; }
        if (!empty($filtros['tipo']))         { $where[] = 'p.tipo_proceso = ?';   $params[] = $filtros['tipo']; }
        if (!empty($filtros['institucion']))  { $where[] = 'p.institucion_id = ?'; $params[] = $filtros['institucion']; }
        if (!empty($filtros['buscar']))       {
            $where[] = '(p.numero_proceso LIKE ? OR p.objeto_contratacion LIKE ?)';
            $params[] = "%{$filtros['buscar']}%";
            $params[] = "%{$filtros['buscar']}%";
        }

        $whereStr = implode(' AND ', $where);
        $sql = "SELECT p.*, i.nombre AS institucion_nombre
                FROM procesos p JOIN instituciones i ON i.id = p.institucion_id
                WHERE {$whereStr} AND p.deleted_at IS NULL
                ORDER BY p.created_at DESC";

        return DB::paginate($sql, $params, $page);
    }

    public static function dashboard(): array
    {
        $tid = DB::getTenantId();
        return [
            'total_activos'   => DB::count("SELECT COUNT(*) FROM procesos WHERE tenant_id = ? AND estado NOT IN ('cerrado','cancelado') AND deleted_at IS NULL", [$tid]),
            'monto_pendiente' => DB::selectOne("SELECT COALESCE(SUM(p.monto_total),0) AS t FROM procesos p LEFT JOIN facturas f ON f.proceso_id=p.id WHERE p.tenant_id=? AND p.estado IN ('entregado_definitivo','facturado') AND f.estado != 'pagada'", [$tid])['t'] ?? 0,
            'cobrado_anio'    => DB::selectOne("SELECT COALESCE(SUM(pg.monto_pagado),0) AS t FROM pagos pg JOIN facturas f ON f.id=pg.factura_id JOIN procesos p ON p.id=f.proceso_id WHERE p.tenant_id=? AND YEAR(pg.fecha_pago)=YEAR(CURDATE())", [$tid])['t'] ?? 0,
            'por_vencer_docs' => DB::count("SELECT COUNT(*) FROM documentos_habilitantes WHERE tenant_id=? AND estado IN ('por_vencer','vencido') AND deleted_at IS NULL", [$tid]),
        ];
    }

    public static function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $antes = static::find($id);
        static::update($id, ['estado' => $nuevoEstado]);
        DB::audit('UPDATE', 'procesos', $id, ['estado' => $antes['estado']], ['estado' => $nuevoEstado]);
        return true;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ENTREGABLE
// ══════════════════════════════════════════════════════════════════════════
class Entregable extends BaseModel
{
    protected static string $table = 'entregables';

    public static function porProceso(int $procesoId): array
    {
        return DB::select(
            "SELECT * FROM entregables WHERE proceso_id = ? AND tenant_id = ? AND deleted_at IS NULL ORDER BY numero_orden",
            [$procesoId, DB::getTenantId()]
        );
    }

    public static function calcularAvance(int $procesoId): float
    {
        $total     = DB::count("SELECT COUNT(*) FROM entregables WHERE proceso_id = ? AND deleted_at IS NULL", [$procesoId]);
        $aprobados = DB::count("SELECT COUNT(*) FROM entregables WHERE proceso_id = ? AND estado = 'aprobado' AND deleted_at IS NULL", [$procesoId]);
        return $total > 0 ? round(($aprobados / $total) * 100, 2) : 0;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// PROCESO ITEMS — ítems extraídos del SERCOP (tabla de detalle)
// ══════════════════════════════════════════════════════════════════════════
class ProcesoItem extends BaseModel
{
    protected static string $table = 'proceso_items';

    public static function porProceso(int $procesoId): array
    {
        return DB::select(
            "SELECT * FROM proceso_items WHERE proceso_id = ? AND tenant_id = ? AND deleted_at IS NULL ORDER BY numero ASC",
            [$procesoId, DB::getTenantId()]
        );
    }

    public static function totalMonto(int $procesoId): float
    {
        $rows = DB::select(
            "SELECT SUM(precio_total) AS total FROM proceso_items WHERE proceso_id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$procesoId, DB::getTenantId()]
        );
        return (float)($rows[0]['total'] ?? 0);
    }

    public static function sincronizar(int $procesoId, array $items): void
    {
        $tid = DB::getTenantId();
        // Soft-delete anteriores
        DB::query("UPDATE proceso_items SET deleted_at = NOW() WHERE proceso_id = ? AND tenant_id = ?", [$procesoId, $tid]);
        foreach ($items as $it) {
            DB::insert('proceso_items', [
                'proceso_id'      => $procesoId,
                'tenant_id'       => $tid,
                'numero'          => (int)($it['numero'] ?? 0),
                'cpc'             => ($it['cpc'] ?? '') ?: null,
                'cpc_descripcion' => ($it['cpc_descripcion'] ?? '') ?: null,
                'descripcion'     => ($it['descripcion'] ?? '') ?: null,
                'unidad'          => ($it['unidad'] ?? '') ?: null,
                'cantidad'        => (float)($it['cantidad'] ?? 0),
                'precio_unitario' => (float)($it['precio_unitario'] ?? 0),
                'precio_total'    => (float)($it['precio_total'] ?? 0),
            ]);
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// DOCUMENTO DEL PROCESO
// ══════════════════════════════════════════════════════════════════════════
class DocumentoProceso extends BaseModel
{
    protected static string $table = 'documentos_proceso';

    public static function porProceso(int $procesoId): array
    {
        return DB::select(
            "SELECT * FROM documentos_proceso WHERE proceso_id = ? AND tenant_id = ? AND deleted_at IS NULL ORDER BY categoria, created_at",
            [$procesoId, DB::getTenantId()]
        );
    }

    public static function porCategoria(int $procesoId): array
    {
        $docs = static::porProceso($procesoId);
        $grouped = [];
        foreach ($docs as $d) {
            $grouped[$d['categoria']][] = $d;
        }
        return $grouped;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// DOCUMENTO HABILITANTE
// ══════════════════════════════════════════════════════════════════════════
class DocumentoHabilitante extends BaseModel
{
    protected static string $table = 'documentos_habilitantes';

    public static function actualizarEstados(): void
    {
        $tid = DB::getTenantId();
        DB::query(
            "UPDATE documentos_habilitantes
             SET estado = CASE
               WHEN fecha_vencimiento < CURDATE() THEN 'vencido'
               WHEN fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL dias_alerta DAY) THEN 'por_vencer'
               ELSE 'vigente'
             END
             WHERE tenant_id = ? AND fecha_vencimiento IS NOT NULL AND deleted_at IS NULL",
            [$tid]
        );
    }

    public static function porVencer(): array
    {
        return DB::select(
            "SELECT dh.*, u.email, u.nombre AS user_nombre
             FROM documentos_habilitantes dh
             JOIN tenants t ON t.id = dh.tenant_id
             JOIN users u ON u.tenant_id = dh.tenant_id AND u.rol IN ('admin','gestor') AND u.estado = 'activo'
             WHERE dh.estado IN ('por_vencer','vencido') AND dh.deleted_at IS NULL
             GROUP BY dh.id, u.id"
        );
    }
}

// ══════════════════════════════════════════════════════════════════════════
// FACTURA
// ══════════════════════════════════════════════════════════════════════════
class Factura extends BaseModel
{
    protected static string $table = 'facturas';

    public static function conProceso(int $id): ?array
    {
        return DB::selectOne(
            "SELECT f.*, p.numero_proceso, p.objeto_contratacion, i.nombre AS institucion_nombre
             FROM facturas f
             JOIN procesos p ON p.id = f.proceso_id
             JOIN instituciones i ON i.id = p.institucion_id
             WHERE f.id = ? AND f.tenant_id = ? AND f.deleted_at IS NULL",
            [$id, DB::getTenantId()]
        );
    }

    public static function pendientesCobro(): array
    {
        return DB::select(
            "SELECT f.*, p.numero_proceso, p.objeto_contratacion, i.nombre AS institucion_nombre,
                    DATEDIFF(CURDATE(), f.fecha_emision) AS dias_transcurridos
             FROM facturas f
             JOIN procesos p ON p.id = f.proceso_id
             JOIN instituciones i ON i.id = p.institucion_id
             WHERE f.tenant_id = ? AND f.estado NOT IN ('pagada','anulada') AND f.deleted_at IS NULL
             ORDER BY f.fecha_emision ASC",
            [DB::getTenantId()]
        );
    }
}

// ══════════════════════════════════════════════════════════════════════════
// PAGO
// ══════════════════════════════════════════════════════════════════════════
class Pago extends BaseModel
{
    protected static string $table = 'pagos';

    public static function registrar(int $facturaId, array $data): int
    {
        $id = static::create(array_merge($data, ['factura_id' => $facturaId, 'registrado_por' => authId()]));
        // Marcar factura como pagada
        Factura::update($facturaId, ['estado' => 'pagada']);
        // Marcar proceso como pagado
        $factura = Factura::find($facturaId);
        if ($factura) Proceso::update($factura['proceso_id'], ['estado' => 'pagado']);
        return $id;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// NOTIFICACIÓN
// ══════════════════════════════════════════════════════════════════════════
class Notificacion extends BaseModel
{
    protected static string $table = 'notificaciones';

    public static function crear(array $data): int
    {
        return static::create(array_merge($data, ['estado' => 'pendiente']));
    }

    public static function sinLeer(int $userId): array
    {
        return DB::select(
            "SELECT * FROM notificaciones WHERE user_id = ? AND tenant_id = ? AND estado = 'pendiente' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10",
            [$userId, DB::getTenantId()]
        );
    }

    public static function marcarLeida(int $id): void
    {
        static::update($id, ['estado' => 'leido', 'fecha_leido' => date('Y-m-d H:i:s')]);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ANÁLISIS IA
// ══════════════════════════════════════════════════════════════════════════
class AnalisisIA extends BaseModel
{
    protected static string $table = 'analisis_ia';
}

// ══════════════════════════════════════════════════════════════════════════
// PLANTILLA
// ══════════════════════════════════════════════════════════════════════════
class PlantillaDocumento extends BaseModel
{
    protected static string $table = 'plantillas_documentos';

    public static function porTipo(string $tipo): ?array
    {
        // Buscar primero plantilla del tenant, luego global
        return DB::selectOne(
            "SELECT * FROM plantillas_documentos
             WHERE tipo = ? AND (tenant_id = ? OR es_global = 1) AND activa = 1 AND deleted_at IS NULL
             ORDER BY (tenant_id = ?) DESC LIMIT 1",
            [$tipo, DB::getTenantId(), DB::getTenantId()]
        );
    }
}

// ══════════════════════════════════════════════════════════════════════════
// CAMPO EXTRA DE PROCESO
// ══════════════════════════════════════════════════════════════════════════
class ProcesoCampoExtra extends BaseModel
{
    protected static string $table = 'proceso_campos_extra';

    public static function porProceso(int $procesoId): array
    {
        return DB::select(
            'SELECT * FROM proceso_campos_extra
             WHERE proceso_id = ? AND tenant_id = ?
             ORDER BY orden ASC, id ASC',
            [$procesoId, DB::getTenantId()]
        );
    }

    public static function reemplazar(int $procesoId, array $campos): void
    {
        $tid = DB::getTenantId();
        DB::query('DELETE FROM proceso_campos_extra WHERE proceso_id = ? AND tenant_id = ?', [$procesoId, $tid]);
        foreach ($campos as $orden => $c) {
            $nombre = trim($c['nombre'] ?? '');
            if ($nombre === '') continue;
            DB::insert('proceso_campos_extra', [
                'tenant_id'  => $tid,
                'proceso_id' => $procesoId,
                'nombre'     => $nombre,
                'contenido'  => trim($c['contenido'] ?? ''),
                'orden'      => (int)$orden,
            ]);
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// PLANTILLA DE CAMPOS
// ══════════════════════════════════════════════════════════════════════════
class PlantillaCampos extends BaseModel
{
    protected static string $table = 'plantillas_campos';

    public static function listar(): array
    {
        return DB::select(
            'SELECT * FROM plantillas_campos WHERE tenant_id = ? ORDER BY nombre ASC',
            [DB::getTenantId()]
        );
    }
}

// ══════════════════════════════════════════════════════════════════════════
// DOMINIO
// ══════════════════════════════════════════════════════════════════════════
class Dominio extends BaseModel
{
    protected static string $table = 'dominios';

    /**
     * Todos los dominios del tenant, con nombre de institución
     */
    public static function conInstitucion(): array
    {
        return DB::select(
            "SELECT d.*, i.nombre AS institucion_nombre
             FROM dominios d
             JOIN instituciones i ON i.id = d.institucion_id
             WHERE d.tenant_id = ? AND d.deleted_at IS NULL
             ORDER BY d.estado ASC, d.fecha_caducidad ASC",
            [DB::getTenantId()]
        );
    }

    /**
     * Dominios de una institución específica
     */
    public static function porInstitucion(int $instId): array
    {
        return DB::select(
            "SELECT * FROM dominios
             WHERE tenant_id = ? AND institucion_id = ? AND deleted_at IS NULL
             ORDER BY fecha_caducidad ASC",
            [DB::getTenantId(), $instId]
        );
    }

    /**
     * Dominios por vencer en los próximos $dias días (para dashboard)
     */
    public static function proximosVencer(int $dias = 60): array
    {
        return DB::select(
            "SELECT d.*, i.nombre AS institucion_nombre
             FROM dominios d
             JOIN instituciones i ON i.id = d.institucion_id
             WHERE d.tenant_id = ?
               AND d.deleted_at IS NULL
               AND d.fecha_caducidad IS NOT NULL
               AND d.fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND d.estado != 'cancelado'
             ORDER BY d.fecha_caducidad ASC",
            [DB::getTenantId(), $dias]
        );
    }

    /**
     * Actualiza estados (vencido / por_vencer / activo) según fecha actual
     */
    public static function actualizarEstados(): void
    {
        $tid = DB::getTenantId();
        // Vencidos
        DB::query(
            "UPDATE dominios SET estado = 'vencido'
             WHERE tenant_id = ? AND deleted_at IS NULL
               AND fecha_caducidad < CURDATE()
               AND estado NOT IN ('cancelado','suspendido')",
            [$tid]
        );
        // Por vencer (dentro de 30 días)
        DB::query(
            "UPDATE dominios SET estado = 'por_vencer'
             WHERE tenant_id = ? AND deleted_at IS NULL
               AND fecha_caducidad >= CURDATE()
               AND fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL dias_alerta DAY)
               AND estado NOT IN ('cancelado','suspendido','vencido')",
            [$tid]
        );
        // Activos
        DB::query(
            "UPDATE dominios SET estado = 'activo'
             WHERE tenant_id = ? AND deleted_at IS NULL
               AND fecha_caducidad > DATE_ADD(CURDATE(), INTERVAL dias_alerta DAY)
               AND estado NOT IN ('cancelado','suspendido')",
            [$tid]
        );
    }
}