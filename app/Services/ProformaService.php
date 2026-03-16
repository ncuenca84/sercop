<?php
namespace Services;

/**
 * ProformaService
 * Sistema de proformas HTML editables → PDF
 * Sin dependencias externas. Funciona en cualquier cPanel compartido.
 *
 * Flujo:
 * 1. Cliente configura su plantilla HTML desde Configuración
 * 2. Al generar: PHP reemplaza {{variables}} con datos del proceso
 * 3. Se muestra HTML con CSS de impresión perfecto
 * 4. Usuario imprime → "Guardar como PDF" del navegador (o Ctrl+P)
 * 5. Firma electrónica y sube al SERCOP
 */
class ProformaService
{
    // ── Configuración por defecto ─────────────────────────────────────────
    public static function configDefecto(): array
    {
        return [
            'color_primario'   => '#1B4F72',
            'proforma_numero'  => date('Y') . '-001',
            'forma_pago'       => 'Contra entrega del servicio/bien',
            'vigencia_oferta'  => '30 días calendario',
            'texto_adicional'  => '',
            'mostrar_specs'    => true,
        ];
    }

    // ── Obtener config del tenant ─────────────────────────────────────────
    public static function getConfig(int $tenantId): array
    {
        $tenant = \DB::selectOne("SELECT config FROM tenants WHERE id = ?", [$tenantId]);
        $config = [];
        if ($tenant && $tenant['config']) {
            $data   = json_decode($tenant['config'], true) ?? [];
            $config = $data['proforma'] ?? [];
        }
        return array_merge(self::configDefecto(), $config);
    }

    // ── Guardar config del tenant ─────────────────────────────────────────
    public static function saveConfig(int $tenantId, array $nuevaConfig): void
    {
        $tenant = \DB::selectOne("SELECT config FROM tenants WHERE id = ?", [$tenantId]);
        $data   = [];
        if ($tenant && $tenant['config']) {
            $data = json_decode($tenant['config'], true) ?? [];
        }
        $data['proforma'] = $nuevaConfig;
        \DB::query(
            "UPDATE tenants SET config = ? WHERE id = ?",
            [json_encode($data, JSON_UNESCAPED_UNICODE), $tenantId]
        );
    }

    // ── Plantilla HTML base (editable por el cliente) ─────────────────────
    public static function getPlantillaHtml(int $tenantId): string
    {
        // Buscar plantilla personalizada del tenant
        $plantilla = \DB::selectOne(
            "SELECT contenido_html FROM plantillas_documentos
             WHERE tipo = 'proforma_sercop' AND tenant_id = ? AND activa = 1 AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 1",
            [$tenantId]
        );

        if ($plantilla) {
            $html = $plantilla['contenido_html'];
            // Si la plantilla guardada no tiene las variables nuevas, usar la default actualizada
            if (strpos($html, '{{proceso.especificaciones}}') === false
                || strpos($html, '{{proceso.declaracion}}') === false) {
                return self::plantillaDefault();
            }
            return $html;
        }

        // Usar plantilla por defecto del sistema
        return self::plantillaDefault();
    }

    // ── Generar HTML final con datos del proceso ──────────────────────────
    public static function generar(array $proceso, int $tenantId, string $logoUrl = ''): string
    {
        $config   = self::getConfig($tenantId);
        $template = self::getPlantillaHtml($tenantId);
        $vars     = self::buildVars($proceso, $config, $logoUrl);

        $html = $template;
        foreach ($vars as $key => $value) {
            $html = str_replace($key, (string)$value, $html);
        }

        // Procesar items tabla si hay entregables/items del proceso
        $html = str_replace('{{items_tabla}}', self::buildItemsTabla($proceso), $html);

        return $html;
    }

    // ── Variables disponibles ─────────────────────────────────────────────
    public static function buildVars(array $proceso, array $config, string $logoUrl = ''): array
    {
        $monto = (float)($proceso['monto_total'] ?? 0);

        // Logo HTML
        $logoHtml = '';
        if ($logoUrl && file_exists($logoUrl)) {
            $ext      = strtolower(pathinfo($logoUrl, PATHINFO_EXTENSION));
            $mime     = $ext === 'png' ? 'image/png' : 'image/jpeg';
            $b64      = base64_encode(file_get_contents($logoUrl));
            $logoHtml = "<img src=\"data:{$mime};base64,{$b64}\" alt=\"Logo\">";
        } elseif ($logoUrl && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
            $logoHtml = "<img src=\"" . htmlspecialchars($logoUrl) . "\" alt=\"Logo\">";
        } else {
            $logoHtml = "<div class=\"header-logo-text\">" .
                        htmlspecialchars($_SESSION['tenant_nombre'] ?? '') . "</div>";
        }

        $textoAdicional = '';
        if (!empty($config['texto_adicional'])) {
            $textoAdicional = '<div class="seccion"><div class="condiciones">'
                . nl2br(htmlspecialchars($config['texto_adicional']))
                . '</div></div>';
        }

        return [
            // Config
            '{{config.color_primario}}'  => htmlspecialchars($config['color_primario']),
            '{{config.proforma_numero}}' => htmlspecialchars($config['proforma_numero']),
            '{{config.forma_pago}}'      => htmlspecialchars($config['forma_pago']),
            '{{config.vigencia_oferta}}' => htmlspecialchars($config['vigencia_oferta']),
            '{{config.texto_adicional}}' => $textoAdicional,
            // Logo
            '{{logo_html}}'              => $logoHtml,
            // Proceso
            '{{proceso.numero}}'         => htmlspecialchars($proceso['numero_proceso'] ?? ''),
            '{{proceso.objeto}}'         => htmlspecialchars($proceso['objeto_contratacion'] ?? ''),
            '{{proceso.tipo}}'           => htmlspecialchars(tipoProceso($proceso['tipo_proceso'] ?? '')),
            '{{proceso.monto}}'          => '$' . number_format($monto, 2),
            '{{proceso.iva}}'            => '$' . number_format($monto * 0.15, 2),
            '{{proceso.total_iva}}'      => '$' . number_format($monto * 1.15, 2),
            '{{proceso.plazo}}'          => htmlspecialchars(($proceso['plazo_dias'] ?? 0) . ' días calendario'),
            '{{proceso.descripcion}}'    => htmlspecialchars($proceso['descripcion_detallada'] ?? $proceso['objeto_contratacion'] ?? ''),
            // Institución
            '{{institucion.nombre}}'     => htmlspecialchars($proceso['institucion_nombre'] ?? ''),
            '{{institucion.ruc}}'        => htmlspecialchars($proceso['institucion_ruc'] ?? ''),
            '{{institucion.ciudad}}'     => htmlspecialchars($proceso['institucion_ciudad'] ?? ''),
            '{{institucion.administrador}}' => htmlspecialchars($proceso['administrador_nombre'] ?? ''),
            '{{institucion.cargo_admin}}'=> htmlspecialchars($proceso['administrador_cargo'] ?? 'Administrador del Contrato'),
            '{{institucion.email_admin}}'=> htmlspecialchars($proceso['administrador_email'] ?? ''),
            '{{institucion.direccion}}'  => htmlspecialchars($proceso['institucion_direccion'] ?? ''),
            // Proveedor
            '{{proveedor.razon_social}}' => htmlspecialchars($_SESSION['tenant_nombre'] ?? ''),
            '{{proveedor.ruc}}'          => htmlspecialchars($_SESSION['tenant_ruc'] ?? ''),
            '{{proveedor.representante}}'=> htmlspecialchars($_SESSION['tenant_representante'] ?? ''),
            '{{proveedor.ciudad}}'       => htmlspecialchars($_SESSION['tenant_ciudad'] ?? 'Quito'),
            '{{proveedor.direccion}}'    => htmlspecialchars($_SESSION['tenant_direccion'] ?? ''),
            '{{proveedor.telefono}}'     => htmlspecialchars($_SESSION['tenant_telefono'] ?? ''),
            '{{proveedor.email}}'        => htmlspecialchars($_SESSION['tenant_email'] ?? ''),
            '{{proveedor.tipo_contrib}}' => htmlspecialchars($_SESSION['tenant_tipo_contrib'] ?? 'Sociedad'),
            '{{proveedor.regimen}}'      => htmlspecialchars($_SESSION['tenant_regimen'] ?? 'RIMPE'),
            // Fecha
            '{{fecha.actual}}'           => self::fechaLarga(),
            '{{fecha.actual_corta}}'     => date('d/m/Y'),
            '{{anio.actual}}'            => date('Y'),
            '{{ciudad.actual}}'          => htmlspecialchars($_SESSION['tenant_ciudad'] ?? 'Quito'),
            // TDR
            '{{proceso.especificaciones}}' => self::seccionEspecificaciones($proceso),
            '{{proceso.metodologia}}'      => self::seccionMetodologia($proceso),
            '{{proceso.cpc}}'              => self::seccionCpc($proceso),
            '{{proceso.plazo_texto}}'      => htmlspecialchars($proceso['plazo_texto'] ?? ''),
            '{{proceso.forma_pago}}'       => self::seccionFormaPago($proceso),
            '{{proceso.declaracion}}'      => self::seccionDeclaracion($proceso),
            '{{proceso.campos_extra}}'     => self::seccionCamposExtra($proceso),
            // Vigencia: tomar del proceso primero, si no del config
            '{{proceso.vigencia_oferta}}'  => htmlspecialchars(
                !empty($proceso['vigencia_oferta'])
                    ? $proceso['vigencia_oferta']
                    : ($config['vigencia_oferta'] ?? '30 días calendario')
            ),
            // URL back (inyectado por el controller, no reemplazar aquí)
        ];
    }

    // ── Tabla de ítems ────────────────────────────────────────────────────
    private static function buildItemsTabla(array $proceso): string
    {
        try {
            $items = \DB::select(
                "SELECT * FROM proceso_items WHERE proceso_id = ? AND deleted_at IS NULL ORDER BY numero ASC",
                [$proceso['id']]
            );
        } catch (\Throwable $e) {
            $items = [];
        }

        if (!empty($items)) {
            $rows = '';
            $totalGlobal = 0;
            foreach ($items as $item) {
                $total        = (float)($item['precio_total'] ?? ((float)($item['cantidad'] ?? 1) * (float)($item['precio_unitario'] ?? 0)));
                $totalGlobal += $total;
                $rows .= "<tr>
                    <td style='text-align:center'>" . (int)($item['numero'] ?? 1) . "</td>
                    <td>" . htmlspecialchars($item['cpc'] ?? '') . "</td>
                    <td style='white-space:pre-wrap;font-size:9pt'>" . htmlspecialchars($item['descripcion'] ?? '') . "</td>
                    <td style='text-align:center'>" . htmlspecialchars($item['unidad'] ?? '') . "</td>
                    <td style='text-align:center'>" . number_format((float)($item['cantidad'] ?? 1), 2) . "</td>
                    <td style='text-align:right'>$" . number_format((float)($item['precio_unitario'] ?? 0), 2) . "</td>
                    <td style='text-align:right;font-weight:bold'>$" . number_format($total, 2) . "</td>
                </tr>";
            }
            $rows .= "<tr style='background:#f0f0f0;font-weight:bold'>
                <td colspan='6' style='text-align:right;padding-right:8px'>SUBTOTAL:</td>
                <td style='text-align:right'>$" . number_format($totalGlobal, 2) . "</td>
            </tr>
            <tr style='background:#f0f0f0;font-weight:bold'>
                <td colspan='6' style='text-align:right;padding-right:8px'>IVA 15%:</td>
                <td style='text-align:right'>$" . number_format($totalGlobal * 0.15, 2) . "</td>
            </tr>
            <tr style='background:#1B4F72;color:#fff;font-weight:bold'>
                <td colspan='6' style='text-align:right;padding-right:8px'>TOTAL:</td>
                <td style='text-align:right'>$" . number_format($totalGlobal * 1.15, 2) . "</td>
            </tr>";
            return $rows;
        }

        // Fallback: un solo ítem con el objeto y monto total
        $monto = (float)($proceso['monto_total'] ?? 0);
        return "<tr>
            <td style='text-align:center'>1</td>
            <td>" . htmlspecialchars($proceso['cpc'] ?? '') . "</td>
            <td>" . htmlspecialchars($proceso['objeto_contratacion'] ?? '') . "</td>
            <td style='text-align:center'>Global</td>
            <td style='text-align:center'>1</td>
            <td style='text-align:right'>$" . number_format($monto, 2) . "</td>
            <td style='text-align:right;font-weight:bold'>$" . number_format($monto, 2) . "</td>
        </tr>
        <tr style='background:#1B4F72;color:#fff;font-weight:bold'>
            <td colspan='6' style='text-align:right;padding-right:8px'>TOTAL:</td>
            <td style='text-align:right'>$" . number_format($monto * 1.15, 2) . "</td>
        </tr>";
    }

    // ── Sección HTML Especificaciones Técnicas ────────────────────────────
    private static function seccionEspecificaciones(array $proceso): string
    {
        $texto = trim($proceso['especificaciones_tecnicas'] ?? '');
        if (empty($texto)) return '';
        $contenido = self::sanitizarHtmlEditor($texto);

        // Nota fija: activa por defecto, desactivable por toggle
        $notaActiva = ($proceso['nota_espec_activa'] ?? '1') !== '0';
        $notaBloque = '';
        if ($notaActiva) {
            $notaTexto = trim($proceso['nota_espec_texto'] ?? '')
                ?: 'Todos los servicios serán entregados de manera virtual, adicional es importante tomar en consideración que la recuperación de la información de administraciones anteriores, solo es posible si el proveedor anterior facilita el acceso, caso contrario es imposible y no podremos obtener la información antigua.';
            $notaBloque = '<div style="margin-top:10px;padding:8px 10px;background:#fff8e1;border-left:3px solid #f39c12;font-size:8.5pt;line-height:1.5">
                <strong>NOTA:</strong> ' . htmlspecialchars($notaTexto) . '
            </div>';
        }

        return '<div style="margin-bottom:12px">
            <div class="sec-tit">2. Especificaciones T&eacute;cnicas</div>
            <div class="ck-content" style="font-size:9pt;line-height:1.5">'
            . $contenido
            . $notaBloque
            . '</div></div>';
    }

    // ── Sección HTML Metodología de Trabajo ───────────────────────────────
    private static function seccionMetodologia(array $proceso): string
    {
        $texto = trim($proceso['metodologia_trabajo'] ?? '');
        if (empty($texto)) return '';
        $contenido = self::sanitizarHtmlEditor($texto);
        return '<div style="margin-bottom:12px">
            <div class="sec-tit">3. Metodolog&iacute;a de Trabajo</div>
            <div class="ck-content" style="font-size:9pt;line-height:1.5">'
            . $contenido .
            '</div></div>';
    }

    // ── Sección HTML Campos Extra (dinámicos) ─────────────────────────────
    private static function seccionCamposExtra(array $proceso): string
    {
        $campos = $proceso['_campos_extra'] ?? [];
        if (empty($campos)) return '';

        $html = '';
        foreach ($campos as $campo) {
            $nombre    = trim($campo['nombre'] ?? '');
            $contenido = trim($campo['contenido'] ?? '');
            if (empty($nombre) || empty($contenido)) continue;

            $html .= '<div style="margin-bottom:12px">
                <div class="sec-tit">' . htmlspecialchars($nombre) . '</div>
                <div style="font-size:9.5pt;line-height:1.5">'
                . nl2br(htmlspecialchars($contenido))
                . '</div></div>';
        }
        return $html;
    }

    // ── Sección HTML CPC ──────────────────────────────────────────────────
    private static function seccionCpc(array $proceso): string
    {
        $texto = trim($proceso['cpc_descripcion'] ?? '');
        if (empty($texto)) return '';
        return '<div style="margin-bottom:12px">
            <div class="sec-tit">4. CPC</div>
            <div style="font-size:9.5pt;line-height:1.5;white-space:pre-wrap">'
            . htmlspecialchars($texto) .
            '</div></div>';
    }

    // ── Sección HTML Forma y Condiciones de Pago ──────────────────────────
    private static function seccionFormaPago(array $proceso): string
    {
        $texto = trim($proceso['forma_pago'] ?? '');
        if (empty($texto)) return '';
        $contenido = self::sanitizarHtmlEditor($texto);
        return '<div style="margin-bottom:12px">
            <div class="sec-tit">6. Forma y Condiciones de Pago</div>
            <div class="ck-content" style="font-size:9.5pt;line-height:1.5">'
            . $contenido .
            '</div></div>';
    }

    // ── Sección HTML Declaración de Cumplimiento ──────────────────────────
    private static function seccionDeclaracion(array $proceso): string
    {
        // Si el toggle está explícitamente desactivado, no mostrar
        if (($proceso['declaracion_activa'] ?? '1') === '0') return '';

        $texto = trim($proceso['declaracion_cumplimiento'] ?? '');
        if (empty($texto)) {
            $texto = 'Confirmamos que nuestra oferta cumple completamente con todos los términos y condiciones especificados en los términos de referencia (TDR) proporcionados por su institución.';
        }
        return '<div style="margin-bottom:12px">
            <div class="sec-tit">8. Declaraci&oacute;n de Cumplimiento</div>
            <div style="font-size:9.5pt;line-height:1.5">'
            . nl2br(htmlspecialchars($texto)) .
            '</div></div>';
    }

    // ── Sanitizar HTML del CKEditor para PDF ─────────────────────────────
    private static function sanitizarHtmlEditor(string $html): string
    {
        // Si no contiene etiquetas HTML, es texto plano — convertir saltos de línea
        if (strip_tags($html) === $html) {
            return '<p>' . nl2br(htmlspecialchars($html)) . '</p>';
        }
        // Es HTML del CKEditor — permitir directamente (ya viene del propio sistema)
        return $html;
    }

    // ── Fecha larga en español ────────────────────────────────────────────
    private static function fechaLarga(): string
    {
        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return date('j') . ' de ' . $meses[(int)date('n') - 1] . ' de ' . date('Y');
    }

    // ── Variables para mostrar al usuario ────────────────────────────────
    public static function variablesDisponibles(): array
    {
        return [
            'Proceso'      => ['{{proceso.numero}}','{{proceso.objeto}}','{{proceso.tipo}}',
                               '{{proceso.monto}}','{{proceso.iva}}','{{proceso.total_iva}}',
                               '{{proceso.plazo}}','{{proceso.descripcion}}'],
            'TDR / Oferta' => ['{{proceso.especificaciones}}','{{proceso.metodologia}}',
                               '{{proceso.cpc}}','{{proceso.plazo_texto}}',
                               '{{proceso.forma_pago}}','{{proceso.declaracion}}',
                               '{{proceso.vigencia_oferta}}','{{proceso.campos_extra}}'],
            'Institución'  => ['{{institucion.nombre}}','{{institucion.ruc}}','{{institucion.ciudad}}',
                               '{{institucion.administrador}}','{{institucion.cargo_admin}}',
                               '{{institucion.email_admin}}','{{institucion.direccion}}'],
            'Tu Empresa'   => ['{{proveedor.razon_social}}','{{proveedor.ruc}}','{{proveedor.representante}}',
                               '{{proveedor.ciudad}}','{{proveedor.direccion}}','{{proveedor.telefono}}',
                               '{{proveedor.email}}','{{proveedor.tipo_contrib}}','{{proveedor.regimen}}'],
            'Config'       => ['{{config.color_primario}}','{{config.proforma_numero}}',
                               '{{config.forma_pago}}','{{config.vigencia_oferta}}',
                               '{{config.texto_adicional}}','{{logo_html}}'],
            'Fecha'        => ['{{fecha.actual}}','{{fecha.actual_corta}}','{{anio.actual}}','{{ciudad.actual}}'],
            'Tabla ítems'  => ['{{items_tabla}}'],
        ];
    }

    // ── Plantilla HTML por defecto ────────────────────────────────────────
    public static function plantillaDefault(): string
    {
        return self::plantillaInline();
    }

    // ── Plantilla inline de emergencia ────────────────────────────────────
    private static function plantillaInline(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  @page{margin:1.5cm 2cm}*{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;font-size:10pt;color:#1a1a1a;line-height:1.4}
  .header{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid {{config.color_primario}};padding-bottom:10px;margin-bottom:14px}
  .header-logo img{max-height:60px;max-width:180px}
  .header-logo-text{font-size:18pt;font-weight:bold;color:{{config.color_primario}}}
  .header-empresa{text-align:right;font-size:8.5pt;color:#444}
  .header-empresa strong{font-size:10pt;color:#1a1a1a;display:block}
  .doc-titulo{text-align:center;margin:10px 0 14px}
  .doc-titulo h1{font-size:13pt;font-weight:bold;text-transform:uppercase;color:{{config.color_primario}}}
  .doc-fecha{text-align:right;font-size:9pt;color:#555;margin-bottom:10px}
  .sec-tit{background:{{config.color_primario}};color:white;font-weight:bold;font-size:9pt;padding:4px 8px;margin-bottom:6px;text-transform:uppercase}
  .campo{display:flex;gap:6px;margin-bottom:3px;font-size:9.5pt}
  .campo strong{min-width:160px;color:#333}
  table{width:100%;border-collapse:collapse;margin:6px 0;font-size:9pt}
  thead tr{background:{{config.color_primario}};color:white}
  thead th{padding:5px 6px;text-align:left}
  tbody tr:nth-child(even){background:#f5f7fa}
  tbody td{padding:5px 6px;border-bottom:1px solid #e0e0e0;vertical-align:top}
  /* Tablas generadas por CKEditor */
  .ck-content table{width:100%;border-collapse:collapse;margin:8px 0;font-size:9pt}
  .ck-content table td,.ck-content table th{border:1px solid #ccc;padding:4px 6px;vertical-align:top}
  .ck-content table thead td,.ck-content table th{background:#f0f0f0;font-weight:bold}
  .ck-content ul,.ck-content ol{margin:4px 0 4px 18px;padding:0}
  .ck-content li{margin-bottom:2px}
  .ck-content p{margin-bottom:4px}
  .ck-content figure.table{margin:6px 0;width:100%}
  .ck-content figure.table table{width:100%}
  /* Imágenes generadas por CKEditor (base64 o URL) */
  .ck-content img{max-width:100%;height:auto;display:block;margin:6px 0}
  .ck-content figure.image{margin:8px 0;text-align:center}
  .ck-content figure.image img{max-width:100%;height:auto}
  .ck-content figure.image.image-style-side{float:right;max-width:50%;margin:4px 0 4px 12px}
  .ck-content figure.image figcaption{font-size:8pt;color:#666;font-style:italic;margin-top:3px}
  tfoot td{padding:5px 6px;font-weight:bold;text-align:right;border-top:2px solid {{config.color_primario}}}
  .firma-area{margin-top:30px;display:flex;justify-content:space-between}
  .firma-box{text-align:center;width:44%}
  .firma-linea{border-top:1px solid #333;margin-top:45px;padding-top:5px;font-size:9pt}
  .doc-footer{margin-top:20px;padding-top:8px;border-top:1px solid #ddd;font-size:8pt;color:#888;text-align:center}
  @media screen{body{max-width:800px;margin:20px auto;padding:20px;background:#f0f2f5}
    .doc{background:white;padding:30px 35px;box-shadow:0 2px 12px rgba(0,0,0,.12);border-radius:4px}
    .toolbar{background:#1B4F72;color:white;padding:10px 20px;display:flex;gap:10px;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;border-radius:4px 4px 0 0}
    .btn-print{background:#27ae60;color:white;border:none;padding:7px 16px;border-radius:4px;cursor:pointer;font-size:10pt;font-weight:bold}
  .btn-pdf{background:#c0392b;color:white;border:none;padding:7px 16px;border-radius:4px;cursor:pointer;font-size:10pt;font-weight:bold;text-decoration:none;display:inline-block}
    .btn-back{background:transparent;color:#aaa;border:1px solid #555;padding:6px 14px;border-radius:4px;font-size:9pt;text-decoration:none;color:#ccc}}
  @media print{.no-print{display:none!important}}
</style>
</head>
<body>
<div class="toolbar no-print">
  <div><h2 style="font-size:11pt;margin:0">&#128196; Proforma &mdash; {{proceso.numero}}</h2>
  <small style="color:#aaa">Lista para imprimir y firmar electr&oacute;nicamente</small></div>
  <div style="display:flex;gap:8px">
    <a href="{{url_back}}" class="btn-back">&larr; Volver</a>
    <button class="btn-print" onclick="window.print()">&#128424; Imprimir / Guardar PDF</button>
    <a href="{{url_back_pdf}}" class="btn-pdf">&#11015; Descargar PDF</a>
  </div>
</div>
<div class="doc">
  <div class="header">
    <div class="header-logo">{{logo_html}}</div>
    <div class="header-empresa">
      <strong>{{proveedor.razon_social}}</strong>
      RUC: {{proveedor.ruc}}<br>
      {{proveedor.tipo_contrib}} · Régimen: {{proveedor.regimen}}<br>
      {{proveedor.direccion}}<br>
      Tel: {{proveedor.telefono}} · {{proveedor.email}}
    </div>
  </div>
  <div class="doc-titulo"><h1>Proforma / Oferta Económica</h1>
    <div style="font-size:10pt;color:#555">Proforma N° {{config.proforma_numero}}</div>
  </div>
  <div class="doc-fecha">{{ciudad.actual}}, {{fecha.actual}}</div>
  <div style="margin-bottom:12px"><div class="sec-tit">Destinatario</div>
    <div class="campo"><strong>Entidad:</strong> {{institucion.nombre}}</div>
    <div class="campo"><strong>RUC:</strong> {{institucion.ruc}}</div>
    <div class="campo"><strong>Código NIC:</strong> {{proceso.numero}}</div>
    <div class="campo"><strong>Funcionario Encargado:</strong> {{institucion.administrador}}</div>
    <div class="campo"><strong>Cargo:</strong> {{institucion.cargo_admin}}</div>
    <div class="campo"><strong>Correo:</strong> {{institucion.email_admin}}</div>
    <div class="campo"><strong>Objeto de compra:</strong> {{proceso.objeto}}</div>
    <div class="campo"><strong>Dirección:</strong> {{institucion.direccion}}</div>
  </div>

  <!-- 1. PROPUESTA ECONÓMICA -->
  <div style="margin-bottom:12px"><div class="sec-tit">1. Propuesta Económica</div>
    <table><thead><tr>
      <th style="width:4%;text-align:center">N°</th>
      <th style="width:10%">CPC</th>
      <th>Descripción</th>
      <th style="width:10%">Unidad</th>
      <th style="width:7%;text-align:center">Cant.</th>
      <th style="width:11%;text-align:right">P. Unit.</th>
      <th style="width:11%;text-align:right">Total</th>
    </tr></thead>
    <tbody>{{items_tabla}}</tbody>
    </table>
  </div>

  <!-- 2. ESPECIFICACIONES TÉCNICAS -->
  {{proceso.especificaciones}}

  <!-- 3. METODOLOGÍA DE TRABAJO -->
  {{proceso.metodologia}}

  <!-- 4. CPC -->
  {{proceso.cpc}}

  <!-- 5. PLAZO DE ENTREGA -->
  <div style="margin-bottom:12px"><div class="sec-tit">5. Plazo de Entrega</div>
    <div style="font-size:9.5pt">{{proceso.plazo_texto}}</div>
  </div>

  <!-- 6. FORMA Y CONDICIONES DE PAGO -->
  {{proceso.forma_pago}}

  <!-- 7. VIGENCIA DE LA OFERTA -->
  <div style="margin-bottom:12px"><div class="sec-tit">7. Vigencia de la Oferta</div>
    <div style="font-size:9.5pt">{{proceso.vigencia_oferta}}</div>
  </div>

  <!-- 8. DECLARACIÓN DE CUMPLIMIENTO -->
  {{proceso.declaracion}}

  {{proceso.campos_extra}}
  {{config.texto_adicional}}

  <div class="firma-area" style="justify-content:center">
    <div class="firma-box" style="width:55%"><div class="firma-linea">
      <strong>{{proveedor.representante}}</strong><br>
      Representante Legal<br>
      {{proveedor.razon_social}}<br>
      RUC: {{proveedor.ruc}}
    </div></div>
  </div>
  <div class="doc-footer">
    Documento generado por Sistema de Contratación Pública · {{proveedor.razon_social}} · {{fecha.actual}}
  </div>
</div>
</body>
</html>
HTML;
    }
}