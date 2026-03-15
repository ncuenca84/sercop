<?php

declare(strict_types=1);

class NotificacionService
{
    // ── Crear notificación interna + email opcional ───────────────────────
    public static function crear(int $userId, string $tipo, string $titulo, string $mensaje,
                                  string $entidadTipo = '', int $entidadId = 0,
                                  bool $sendEmail = false, string $email = ''): void
    {
        Notificacion::crear([
            'user_id'      => $userId,
            'tipo'         => $tipo,
            'titulo'       => $titulo,
            'mensaje'      => $mensaje,
            'entidad_tipo' => $entidadTipo,
            'entidad_id'   => $entidadId,
            'canal'        => 'sistema',
        ]);

        if ($sendEmail && $email) {
            $html = Mailer::template($titulo, "<p>{$mensaje}</p>", 'Ver en el sistema', APP_URL . '/dashboard');
            Mailer::send($email, $titulo, $html);
        }
    }

    // ── CRON: Verificar documentos por vencer (llamar diariamente) ────────
    public static function verificarDocumentosHabilitantes(): int
    {
        $count = 0;
        // Actualizar estados de documentos
        $docs = DB::select(
            "SELECT dh.*, t.id AS tenant_id,
                    u.id AS user_id, u.email, u.nombre AS user_nombre
             FROM documentos_habilitantes dh
             JOIN tenants t ON t.id = dh.tenant_id
             JOIN users u ON u.tenant_id = t.id AND u.rol IN ('admin','gestor') AND u.estado = 'activo'
             WHERE dh.fecha_vencimiento IS NOT NULL AND dh.deleted_at IS NULL
             AND dh.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)"
        );

        foreach ($docs as $doc) {
            DB::setTenant((int)$doc['tenant_id']);
            $dias = daysUntil($doc['fecha_vencimiento']);

            // Notificar en días clave: 60, 30, 15, 7, 3, 1
            if (!in_array($dias, [60, 30, 15, 7, 3, 1])) continue;

            $titulo  = "⚠️ Documento por vencer: {$doc['nombre']}";
            $mensaje = "Su documento '{$doc['nombre']}' vence en {$dias} días ({$doc['fecha_vencimiento']}).";

            self::crear((int)$doc['user_id'], 'doc_vencimiento', $titulo, $mensaje,
                'documentos_habilitantes', (int)$doc['id'],
                true, $doc['email']);
            $count++;
        }
        return $count;
    }

    // ── CRON: Verificar entregables próximos ──────────────────────────────
    public static function verificarEntregables(): int
    {
        $count = 0;
        $entregables = DB::select(
            "SELECT e.*, p.numero_proceso, p.tenant_id,
                    u.id AS user_id, u.email, u.nombre AS user_nombre
             FROM entregables e
             JOIN procesos p ON p.id = e.proceso_id
             JOIN users u ON u.tenant_id = p.tenant_id AND u.rol IN ('admin','gestor') AND u.estado = 'activo'
             WHERE e.estado IN ('pendiente','en_progreso')
             AND e.fecha_compromiso BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
             AND e.deleted_at IS NULL AND p.deleted_at IS NULL"
        );

        foreach ($entregables as $e) {
            DB::setTenant((int)$e['tenant_id']);
            $dias = daysUntil($e['fecha_compromiso']);
            if (!in_array($dias, [15, 7, 3, 1])) continue;

            $titulo  = "📋 Entrega próxima: {$e['nombre']}";
            $mensaje = "El entregable '{$e['nombre']}' del proceso {$e['numero_proceso']} vence en {$dias} días.";

            self::crear((int)$e['user_id'], 'entrega', $titulo, $mensaje, 'entregables', (int)$e['id'], true, $e['email']);
            $count++;
        }
        return $count;
    }

    // ── CRON: Facturas sin pago ────────────────────────────────────────────
    public static function verificarPagosPendientes(): int
    {
        $count = 0;
        $facturas = DB::select(
            "SELECT f.*, p.numero_proceso, p.tenant_id,
                    u.id AS user_id, u.email, u.nombre AS user_nombre,
                    DATEDIFF(CURDATE(), f.fecha_emision) AS dias
             FROM facturas f
             JOIN procesos p ON p.id = f.proceso_id
             JOIN users u ON u.tenant_id = p.tenant_id AND u.rol IN ('admin','contador') AND u.estado = 'activo'
             WHERE f.estado NOT IN ('pagada','anulada')
             AND DATEDIFF(CURDATE(), f.fecha_emision) IN (30,45,60,90)
             AND f.deleted_at IS NULL"
        );

        foreach ($facturas as $f) {
            DB::setTenant((int)$f['tenant_id']);
            $titulo  = "💰 Factura pendiente {$f['dias']} días: {$f['numero_sri']}";
            $mensaje = "La factura {$f['numero_sri']} del proceso {$f['numero_proceso']} lleva {$f['dias']} días sin ser cancelada.";
            self::crear((int)$f['user_id'], 'pago', $titulo, $mensaje, 'facturas', (int)$f['id'], true, $f['email']);
            $count++;
        }
        return $count;
    }
}
