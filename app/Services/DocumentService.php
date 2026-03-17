<?php
namespace Services;

/**
 * DocumentoService
 * Genera documentos HTML para Fase 3: informes, actas, solicitudes, etc.
 * Mismo flujo que ProformaService: HTML → Ctrl+P / mPDF
 */
class DocumentoService
{
    // ── Entrada principal ─────────────────────────────────────────────────
    public static function generar(string $tipo, array $proceso, int $tenantId, string $logoUrl = ''): string
    {
        $vars = self::buildVars($proceso, $tenantId, $logoUrl);

        switch ($tipo) {
            case 'informe_tecnico':    return self::renderizar(self::tplInformeTecnico(),  $vars, $proceso);
            case 'garantia_tecnica':   return self::renderizar(self::tplGarantiaTecnica(), $vars, $proceso);
            case 'acta_parcial':       return self::renderizar(self::tplActaParcial(),     $vars, $proceso);
            case 'acta_definitiva':    return self::renderizar(self::tplActaDefinitiva(),  $vars, $proceso);
            case 'solicitud_pago':     return self::renderizar(self::tplSolicitudPago(),   $vars, $proceso);
            default:                   return self::renderizar(self::tplInformeTecnico(),  $vars, $proceso);
        }
    }

    private static function renderizar(string $template, array $vars, array $proceso): string
    {
        $html = $template;
        foreach ($vars as $k => $v) {
            $html = str_replace($k, (string)$v, $html);
        }
        return $html;
    }

    // ── Variables comunes ─────────────────────────────────────────────────
    private static function buildVars(array $proceso, int $tenantId, string $logoUrl): array
    {
        $monto    = (float)($proceso['monto_total'] ?? 0);
        $docFecha = $proceso['_doc_fecha']         ?? date('d/m/Y');
        $lugar    = $proceso['_doc_lugar']          ?? ($_SESSION['tenant_ciudad'] ?? 'Quito');
        $docNum   = $proceso['_doc_numero']         ?? (date('Y') . '-001');

        // Logo
        $logoHtml = '';
        if ($logoUrl && file_exists($logoUrl)) {
            $ext      = strtolower(pathinfo($logoUrl, PATHINFO_EXTENSION));
            $mime     = $ext === 'png' ? 'image/png' : 'image/jpeg';
            $b64      = base64_encode(file_get_contents($logoUrl));
            $logoHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"max-height:60px\" alt=\"Logo\">";
        } elseif ($logoUrl) {
            $logoHtml = "<img src=\"" . htmlspecialchars($logoUrl) . "\" style=\"max-height:60px\" alt=\"Logo\">";
        } else {
            $logoHtml = "<strong style='font-size:14px'>" . htmlspecialchars($_SESSION['tenant_nombre'] ?? '') . "</strong>";
        }

        // Entregables
        $entregablesHtml = self::buildEntregablesTabla($proceso);

        return [
            '{{logo_html}}'               => $logoHtml,
            '{{doc.numero}}'              => htmlspecialchars($docNum),
            '{{doc.fecha}}'               => htmlspecialchars($docFecha),
            '{{doc.lugar}}'               => htmlspecialchars($lugar),
            '{{doc.observaciones}}'       => self::htmlSeguro($proceso['_doc_observaciones'] ?? '') ?: '<em>Ninguna</em>',
            // Proceso
            '{{proceso.numero}}'          => htmlspecialchars($proceso['numero_proceso'] ?? ''),
            '{{proceso.objeto}}'          => htmlspecialchars($proceso['objeto_contratacion'] ?? ''),
            '{{proceso.tipo}}'            => htmlspecialchars($proceso['tipo_proceso'] ?? ''),
            '{{proceso.monto}}'           => '$' . number_format($monto, 2),
            '{{proceso.iva}}'             => '$' . number_format($monto * 0.15, 2),
            '{{proceso.total_iva}}'       => '$' . number_format($monto * 1.15, 2),
            '{{proceso.plazo}}'           => htmlspecialchars(($proceso['plazo_dias'] ?? 0) . ' días calendario'),
            '{{proceso.plazo_texto}}'     => htmlspecialchars($proceso['plazo_texto'] ?? ''),
            '{{proceso.fecha_inicio}}'    => htmlspecialchars($proceso['fecha_inicio'] ?? '—'),
            '{{proceso.fecha_fin}}'       => htmlspecialchars($proceso['fecha_fin'] ?? '—'),
            '{{proceso.cpc}}'             => htmlspecialchars($proceso['cpc'] ?? ''),
            // Estos campos vienen de CKEditor → HTML crudo con posibles imágenes base64
            '{{proceso.especificaciones}}'=> self::htmlSeguro($proceso['especificaciones_tecnicas'] ?? ''),
            '{{proceso.metodologia}}'     => self::htmlSeguro($proceso['metodologia_trabajo'] ?? ''),
            '{{proceso.forma_pago}}'      => self::htmlSeguro($proceso['forma_pago'] ?? ''),
            '{{proceso.declaracion}}'     => self::htmlSeguro($proceso['declaracion_cumplimiento'] ?? ''),
            // Institución
            '{{institucion.nombre}}'      => htmlspecialchars($proceso['institucion_nombre'] ?? ''),
            '{{institucion.ruc}}'         => htmlspecialchars($proceso['institucion_ruc'] ?? ''),
            '{{institucion.ciudad}}'      => htmlspecialchars($proceso['institucion_ciudad'] ?? ''),
            '{{institucion.administrador}}'=> htmlspecialchars($proceso['administrador_nombre'] ?? ''),
            '{{institucion.cargo_admin}}' => htmlspecialchars($proceso['administrador_cargo'] ?? 'Administrador del Contrato'),
            '{{institucion.email_admin}}' => htmlspecialchars($proceso['administrador_email'] ?? ''),
            '{{institucion.direccion}}'   => htmlspecialchars($proceso['institucion_direccion'] ?? ''),
            // Proveedor
            '{{proveedor.razon_social}}'  => htmlspecialchars($_SESSION['tenant_nombre'] ?? ''),
            '{{proveedor.ruc}}'           => htmlspecialchars($_SESSION['tenant_ruc'] ?? ''),
            '{{proveedor.representante}}' => htmlspecialchars($_SESSION['tenant_representante'] ?? ''),
            '{{proveedor.ciudad}}'        => htmlspecialchars($_SESSION['tenant_ciudad'] ?? 'Quito'),
            '{{proveedor.telefono}}'      => htmlspecialchars($_SESSION['tenant_telefono'] ?? ''),
            '{{proveedor.email}}'         => htmlspecialchars($_SESSION['tenant_email'] ?? ''),
            // Entregables
            '{{entregables_tabla}}'       => $entregablesHtml,
            // Secciones Informe Técnico (editadas manualmente en el editor)
            '{{it.antecedentes}}'        => self::htmlSeguro($proceso['it_antecedentes']    ?? ''),
            '{{it.objetivo}}'            => self::htmlSeguro($proceso['it_objetivo']        ?? ''),
            '{{it.desarrollo}}'          => self::htmlSeguro($proceso['it_desarrollo']      ?? ''),
            '{{it.conclusiones}}'        => self::htmlSeguro($proceso['it_conclusiones']    ?? ''),
            '{{it.recomendaciones}}'     => self::htmlSeguro($proceso['it_recomendaciones'] ?? ''),
            '{{it.secciones_extra}}'     => self::buildSeccionesExtra($proceso['it_secciones_extra'] ?? ''),
            // Secciones Garantía Técnica
            '{{gt.objeto}}'              => self::htmlSeguro($proceso['gt_objeto']    ?? ''),
            '{{gt.vigencia}}'            => self::htmlSeguro($proceso['gt_vigencia']  ?? ''),
            '{{gt.cobertura}}'           => self::htmlSeguro($proceso['gt_cobertura'] ?? ''),
            // Secciones Acta de Entrega Parcial
            '{{ap.antecedentes}}'        => self::htmlSeguro($proceso['ap_antecedentes'] ?? ''),
            '{{ap.detalle}}'             => self::htmlSeguro($proceso['ap_detalle']      ?? ''),
            '{{ap.pendientes}}'          => self::htmlSeguro($proceso['ap_pendientes']   ?? ''),
            '{{ap.conformidad}}'         => self::htmlSeguro($proceso['ap_conformidad']  ?? ''),
            // Secciones Acta de Entrega Definitiva
            '{{ad.antecedentes}}'        => self::htmlSeguro($proceso['ad_antecedentes'] ?? ''),
            '{{ad.verificacion}}'        => self::htmlSeguro($proceso['ad_verificacion'] ?? ''),
            '{{ad.liquidacion}}'         => self::htmlSeguro($proceso['ad_liquidacion']  ?? ''),
            '{{ad.conformidad}}'         => self::htmlSeguro($proceso['ad_conformidad']  ?? ''),
            // Secciones Solicitud de Pago
            '{{sp.antecedentes}}'        => self::htmlSeguro($proceso['sp_antecedentes'] ?? ''),
            '{{sp.justificacion}}'       => self::htmlSeguro($proceso['sp_justificacion']?? ''),
            '{{sp.documentos}}'          => self::htmlSeguro($proceso['sp_documentos']   ?? ''),
            '{{sp.solicitud}}'           => self::htmlSeguro($proceso['sp_solicitud']    ?? ''),
            // Títulos personalizables de secciones (Informe Técnico)
            '{{titulo.it_antecedentes}}'   => htmlspecialchars($proceso['titulo_it_antecedentes']   ?? '1. Antecedentes'),
            '{{titulo.it_objetivo}}'       => htmlspecialchars($proceso['titulo_it_objetivo']       ?? '2. Objetivo'),
            '{{titulo.it_desarrollo}}'     => htmlspecialchars($proceso['titulo_it_desarrollo']     ?? '3. Desarrollo'),
            '{{titulo.it_conclusiones}}'   => htmlspecialchars($proceso['titulo_it_conclusiones']   ?? '4. Conclusiones'),
            '{{titulo.it_recomendaciones}}'=> htmlspecialchars($proceso['titulo_it_recomendaciones']?? '5. Recomendaciones'),
            // Títulos personalizables (Garantía Técnica)
            '{{titulo.gt_objeto}}'         => htmlspecialchars($proceso['titulo_gt_objeto']         ?? '1. Objeto de la Garantía'),
            '{{titulo.gt_vigencia}}'       => htmlspecialchars($proceso['titulo_gt_vigencia']       ?? '2. Vigencia'),
            '{{titulo.gt_cobertura}}'      => htmlspecialchars($proceso['titulo_gt_cobertura']      ?? '3. Cobertura del Soporte'),
            // Títulos personalizables (Acta Parcial)
            '{{titulo.ap_antecedentes}}'   => htmlspecialchars($proceso['titulo_ap_antecedentes']   ?? '1. Antecedentes'),
            '{{titulo.ap_detalle}}'        => htmlspecialchars($proceso['titulo_ap_detalle']        ?? '2. Detalle de lo Entregado'),
            '{{titulo.ap_pendientes}}'     => htmlspecialchars($proceso['titulo_ap_pendientes']     ?? '3. Pendientes'),
            '{{titulo.ap_conformidad}}'    => htmlspecialchars($proceso['titulo_ap_conformidad']    ?? '4. Conformidad Parcial'),
            // Títulos personalizables (Acta Definitiva)
            '{{titulo.ad_antecedentes}}'   => htmlspecialchars($proceso['titulo_ad_antecedentes']   ?? '1. Antecedentes'),
            '{{titulo.ad_verificacion}}'   => htmlspecialchars($proceso['titulo_ad_verificacion']   ?? '2. Verificación de Entregables'),
            '{{titulo.ad_liquidacion}}'    => htmlspecialchars($proceso['titulo_ad_liquidacion']    ?? '3. Liquidación de Pendientes'),
            '{{titulo.ad_conformidad}}'    => htmlspecialchars($proceso['titulo_ad_conformidad']    ?? '4. Conformidad Definitiva'),
            // Títulos personalizables (Solicitud de Pago)
            '{{titulo.sp_antecedentes}}'   => htmlspecialchars($proceso['titulo_sp_antecedentes']   ?? '1. Antecedentes'),
            '{{titulo.sp_justificacion}}'  => htmlspecialchars($proceso['titulo_sp_justificacion']  ?? '2. Justificación del Pago'),
            '{{titulo.sp_documentos}}'     => htmlspecialchars($proceso['titulo_sp_documentos']     ?? '3. Documentos Adjuntos'),
            '{{titulo.sp_solicitud}}'      => htmlspecialchars($proceso['titulo_sp_solicitud']      ?? '4. Petición Formal'),
            // URLs (inyectadas por el controller)
            '{{anio}}'                    => date('Y'),
        ];
    }

    /**
     * Decodifica el JSON de secciones personalizadas del Informe Técnico y
     * devuelve el HTML de las secciones extra listo para insertar en la plantilla.
     */
    private static function buildSeccionesExtra(string $json): string
    {
        if ($json === '') return '';
        $items = json_decode($json, true);
        if (!is_array($items) || empty($items)) return '';

        $html = '';
        foreach ($items as $item) {
            $titulo    = htmlspecialchars($item['titulo']   ?? '');
            $contenido = self::htmlSeguro($item['contenido'] ?? '');
            if ($titulo === '' && $contenido === '') continue;
            $tituloHtml = $titulo !== '' ? "<div class=\"seccion-titulo\">{$titulo}</div>" : '';
            $html .= "<div class=\"seccion\">{$tituloHtml}<div class=\"seccion-body\">{$contenido}</div></div>\n";
        }
        return $html;
    }

    /**
     * Si el texto ya contiene etiquetas HTML (viene de CKEditor), lo devuelve tal cual.
     * Si es texto plano, lo escapa y convierte saltos de línea a <br>.
     */
    private static function htmlSeguro(string $texto): string
    {
        if ($texto === '') return '';
        // Si contiene etiquetas HTML es output de CKEditor → devolver directo
        if ($texto !== strip_tags($texto)) {
            return $texto;
        }
        // Texto plano → escapar y convertir saltos de línea
        return nl2br(htmlspecialchars($texto));
    }

    private static function buildEntregablesTabla(array $proceso): string
    {
        try {
            $items = \DB::select(
                "SELECT * FROM entregables WHERE proceso_id = ? AND deleted_at IS NULL ORDER BY numero_orden ASC",
                [$proceso['id']]
            );
        } catch (\Throwable $e) { $items = []; }

        if (empty($items)) return '<p><em>Sin entregables registrados.</em></p>';

        $rows = '';
        foreach ($items as $it) {
            $rows .= '<tr>
                <td style="text-align:center">' . (int)($it['numero_orden'] ?? 1) . '</td>
                <td>' . htmlspecialchars($it['nombre'] ?? '') . '</td>
                <td>' . htmlspecialchars($it['descripcion'] ?? '') . '</td>
                <td style="text-align:center">' . htmlspecialchars($it['fecha_entrega'] ?? '—') . '</td>
                <td style="text-align:right">$' . number_format((float)($it['monto_entregable'] ?? 0), 2) . '</td>
                <td style="text-align:center">' . htmlspecialchars(ucfirst($it['estado'] ?? '')) . '</td>
            </tr>';
        }

        return '<table>
            <thead><tr>
                <th style="width:5%;text-align:center">N°</th>
                <th>Entregable</th>
                <th>Descripción</th>
                <th style="width:12%;text-align:center">Fecha</th>
                <th style="width:12%;text-align:right">Monto</th>
                <th style="width:10%;text-align:center">Estado</th>
            </tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>';
    }

    // ── CSS base compartido ───────────────────────────────────────────────
    private static function css(string $color = '#1B4F72'): string
    {
        return "
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #1a1a1a; background: #f0f2f5; }
            .page { background: #fff; max-width: 210mm; margin: 0 auto; padding: 20mm 20mm 25mm 20mm; min-height: 297mm; position: relative; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
            .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid {$color}; padding-bottom: 12px; margin-bottom: 20px; }
            .doc-header-left { flex: 1; }
            .doc-header-right { text-align: right; }
            .doc-title { font-size: 13pt; font-weight: bold; color: {$color}; margin: 10px 0 4px; text-transform: uppercase; }
            .doc-numero { font-size: 9pt; color: #555; }
            .doc-meta { background: #f8f9fa; border-left: 4px solid {$color}; padding: 10px 14px; margin: 16px 0; border-radius: 0 4px 4px 0; }
            .doc-meta table { width: 100%; border-collapse: collapse; }
            .doc-meta td { padding: 3px 8px; font-size: 9.5pt; }
            .doc-meta td:first-child { font-weight: bold; color: #555; width: 35%; }
            .seccion { margin: 14px 0; }
            .seccion-titulo { background: {$color}; color: #fff; font-weight: bold; font-size: 9pt; padding: 4px 8px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
            .seccion-body { font-size: 9.5pt; line-height: 1.6; text-align: justify; }
            /* Tablas de datos (entregables, items) */
            table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 9pt; }
            thead tr { background: {$color}; color: #fff; }
            thead th { padding: 5px 6px; text-align: left; }
            tbody tr:nth-child(even) { background: #f5f7fa; }
            tbody td { padding: 5px 6px; border-bottom: 1px solid #e0e0e0; vertical-align: top; }
            /* Tablas generadas por CKEditor (pegadas desde Excel/Word) */
            .seccion-body table, .ck-content table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9pt; }
            .seccion-body table td, .seccion-body table th,
            .ck-content table td, .ck-content table th { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; }
            .seccion-body table thead td, .seccion-body table th,
            .ck-content table thead td, .ck-content table th { background: {$color}; color: #fff; font-weight: bold; }
            .seccion-body ul, .seccion-body ol, .ck-content ul, .ck-content ol { margin: 4px 0 4px 18px; padding: 0; }
            .seccion-body li, .ck-content li { margin-bottom: 2px; }
            .seccion-body p, .ck-content p { margin-bottom: 4px; }
            .ck-content figure.table { margin: 6px 0; width: 100%; }
            .ck-content figure.table table { width: 100%; }
            /* Imágenes generadas por CKEditor */
            .seccion-body img, .ck-content img { max-width: 100%; height: auto; display: block; margin: 6px 0; }
            .seccion-body figure.image, .ck-content figure.image { margin: 8px 0; text-align: center; }
            .seccion-body figure.image img, .ck-content figure.image img { max-width: 100%; height: auto; }
            .ck-content figure.image.image-style-side { float: right; max-width: 50%; margin: 4px 0 4px 12px; }
            .ck-content figure.image figcaption { font-size: 8pt; color: #666; font-style: italic; margin-top: 3px; }
            /* Firma */
            .firma-section { margin-top: 40px; display: flex; justify-content: space-around; }
            .firma-box { text-align: center; width: 200px; }
            .firma-linea { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; }
            .firma-nombre { font-weight: bold; font-size: 10pt; }
            .firma-cargo { font-size: 9pt; color: #555; }
            .footer-doc { margin-top: 20px; padding-top: 6px; border-top: 1px solid #ddd; text-align: center; font-size: 8pt; color: #aaa; }
            /* Toolbar de pantalla */
            .btn-bar { position: fixed; top: 0; left: 0; right: 0; background: {$color}; color: #fff; padding: 10px 20px; display: flex; gap: 10px; align-items: center; z-index: 9999; }
            .btn-bar-back { background: transparent; color: #ccc; border: 1px solid #555; padding: 6px 14px; border-radius: 4px; font-size: 9pt; text-decoration: none; }
            .btn-bar-titulo { font-size: 11pt; font-weight: bold; }
            .btn-bar-sub { font-size: 8pt; color: #aaa; }
            .btn-bar-spacer { flex: 1; }
            .btn-bar-print { background: #27ae60; color: #fff; border: none; padding: 7px 16px; border-radius: 4px; cursor: pointer; font-size: 10pt; font-weight: bold; }
            .btn-bar-pdf { background: #c0392b; color: #fff; border: none; padding: 7px 16px; border-radius: 4px; cursor: pointer; font-size: 10pt; font-weight: bold; text-decoration: none; display: inline-block; }
            @media screen { body { padding-top: 55px; } }
            @media print {
                body { background: #fff; padding-top: 0; }
                .page { box-shadow: none; }
                .btn-bar { display: none !important; }
            }
        </style>";
    }

    // ── Barra de acciones ─────────────────────────────────────────────────
    private static function btnBar(string $titulo): string
    {
        return '<div class="btn-bar">
            <a href="{{url_back}}" class="btn-bar-back">&larr; Volver</a>
            <div>
                <div class="btn-bar-titulo">' . htmlspecialchars($titulo) . '</div>
                <div class="btn-bar-sub">Lista para imprimir y firmar electr&oacute;nicamente</div>
            </div>
            <div class="btn-bar-spacer"></div>
            <button class="btn-bar-print" onclick="window.print()">&#128424; Imprimir / Guardar PDF</button>
            <a href="{{url_pdf}}" class="btn-bar-pdf">&#11015; Descargar PDF</a>
        </div>';
    }

    // ══════════════════════════════════════════════════════════════════════
    // PLANTILLAS
    // ══════════════════════════════════════════════════════════════════════

    // ── Informe Técnico de Entrega ────────────────────────────────────────
    private static function tplInformeTecnico(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Informe Técnico — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Informe Técnico de Entrega')
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Informe Técnico de Entrega</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Institución:</td><td>{{institucion.nombre}}</td></tr>
                <tr><td>Administrador:</td><td>{{institucion.administrador}} — {{institucion.cargo_admin}}</td></tr>
                <tr><td>Monto:</td><td>{{proceso.monto}}</td></tr>
                <tr><td>Plazo:</td><td>{{proceso.plazo}}</td></tr>
                <tr><td>Proveedor:</td><td>{{proveedor.razon_social}} &nbsp;|&nbsp; RUC: {{proveedor.ruc}}</td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.it_antecedentes}}</div>
                <div class="seccion-body">{{it.antecedentes}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.it_objetivo}}</div>
                <div class="seccion-body">{{it.objetivo}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.it_desarrollo}}</div>
                <div class="seccion-body">{{it.desarrollo}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Entregables</div>
                {{entregables_tabla}}
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.it_conclusiones}}</div>
                <div class="seccion-body">{{it.conclusiones}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.it_recomendaciones}}</div>
                <div class="seccion-body">{{it.recomendaciones}}</div>
            </div>

            {{it.secciones_extra}}

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{proveedor.representante}}</div>
                        <div class="firma-cargo">Representante Legal<br>{{proveedor.razon_social}}</div>
                    </div>
                </div>
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{institucion.administrador}}</div>
                        <div class="firma-cargo">{{institucion.cargo_admin}}<br>{{institucion.nombre}}</div>
                    </div>
                </div>
            </div>

            <div class="footer-doc">Documento generado por sistema Brixs Contratación — {{doc.fecha}}</div>
        </div></body></html>';
    }

    // ── Acta de Entrega Parcial ───────────────────────────────────────────
    private static function tplActaParcial(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Acta de Entrega Parcial — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Acta de Entrega Parcial')
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Acta de Entrega Parcial</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Contratante:</td><td>{{institucion.nombre}} — RUC: {{institucion.ruc}}</td></tr>
                <tr><td>Administrador:</td><td>{{institucion.administrador}}, {{institucion.cargo_admin}}</td></tr>
                <tr><td>Proveedor:</td><td>{{proveedor.razon_social}} — RUC: {{proveedor.ruc}}</td></tr>
                <tr><td>Representante:</td><td>{{proveedor.representante}}</td></tr>
                <tr><td>Monto Total:</td><td>{{proceso.monto}}</td></tr>
                <tr><td>Plazo Contractual:</td><td>{{proceso.plazo}}</td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ap_antecedentes}}</div>
                <div class="seccion-body">{{ap.antecedentes}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ap_detalle}}</div>
                <div class="seccion-body">{{ap.detalle}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Entregables del Contrato</div>
                {{entregables_tabla}}
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ap_pendientes}}</div>
                <div class="seccion-body">{{ap.pendientes}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ap_conformidad}}</div>
                <div class="seccion-body">{{ap.conformidad}}</div>
            </div>

            {{it.secciones_extra}}

            <div class="seccion">
                <div class="seccion-body">
                    En fe de lo cual, las partes suscriben la presente Acta de Entrega Parcial
                    en la ciudad de {{doc.lugar}}, el {{doc.fecha}}.
                </div>
            </div>

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{proveedor.representante}}</div>
                        <div class="firma-cargo">Representante Legal<br>{{proveedor.razon_social}}<br>RUC: {{proveedor.ruc}}</div>
                    </div>
                </div>
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{institucion.administrador}}</div>
                        <div class="firma-cargo">{{institucion.cargo_admin}}<br>{{institucion.nombre}}</div>
                    </div>
                </div>
            </div>

            <div class="footer-doc">Documento generado por sistema Brixs Contratación — {{doc.fecha}}</div>
        </div></body></html>';
    }

    // ── Acta de Entrega Definitiva ────────────────────────────────────────
    private static function tplActaDefinitiva(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Acta de Entrega Definitiva — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Acta de Entrega-Recepción Definitiva')
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Acta de Entrega-Recepción Definitiva</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Contratante:</td><td>{{institucion.nombre}} — RUC: {{institucion.ruc}}</td></tr>
                <tr><td>Administrador:</td><td>{{institucion.administrador}}, {{institucion.cargo_admin}}</td></tr>
                <tr><td>Proveedor:</td><td>{{proveedor.razon_social}} — RUC: {{proveedor.ruc}}</td></tr>
                <tr><td>Representante:</td><td>{{proveedor.representante}}</td></tr>
                <tr><td>Monto Total:</td><td>{{proceso.monto}}</td></tr>
                <tr><td>Plazo Contractual:</td><td>{{proceso.plazo}}</td></tr>
                <tr><td>Fecha Inicio:</td><td>{{proceso.fecha_inicio}}</td></tr>
                <tr><td>Fecha Fin:</td><td>{{proceso.fecha_fin}}</td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ad_antecedentes}}</div>
                <div class="seccion-body">{{ad.antecedentes}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ad_verificacion}}</div>
                <div class="seccion-body">{{ad.verificacion}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Entregables del Contrato</div>
                {{entregables_tabla}}
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ad_liquidacion}}</div>
                <div class="seccion-body">{{ad.liquidacion}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.ad_conformidad}}</div>
                <div class="seccion-body">{{ad.conformidad}}</div>
            </div>

            {{it.secciones_extra}}

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{proveedor.representante}}</div>
                        <div class="firma-cargo">Representante Legal<br>{{proveedor.razon_social}}<br>RUC: {{proveedor.ruc}}</div>
                    </div>
                </div>
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{institucion.administrador}}</div>
                        <div class="firma-cargo">{{institucion.cargo_admin}}<br>{{institucion.nombre}}</div>
                    </div>
                </div>
            </div>

            <div class="footer-doc">Documento generado por sistema Brixs Contratación — {{doc.fecha}}</div>
        </div></body></html>';
    }

    // ── Acta de Entrega (legacy, mantenido por compatibilidad) ────────────
    private static function tplActaEntrega(string $tipo): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Acta Entrega ' . $tipo . ' — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Acta de Entrega ' . $tipo)
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Acta de Entrega-Recepción ' . $tipo . '</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Contratante:</td><td>{{institucion.nombre}} — RUC: {{institucion.ruc}}</td></tr>
                <tr><td>Administrador:</td><td>{{institucion.administrador}}, {{institucion.cargo_admin}}</td></tr>
                <tr><td>Proveedor:</td><td>{{proveedor.razon_social}} — RUC: {{proveedor.ruc}}</td></tr>
                <tr><td>Representante:</td><td>{{proveedor.representante}}</td></tr>
                <tr><td>Monto Total:</td><td>{{proceso.monto}}</td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-body">
                    En la ciudad de {{doc.lugar}}, a los {{doc.fecha}}, comparecen por una parte
                    <strong>{{institucion.administrador}}</strong>, en calidad de <strong>{{institucion.cargo_admin}}</strong>
                    de <strong>{{institucion.nombre}}</strong>; y por otra parte,
                    <strong>{{proveedor.representante}}</strong>, en calidad de Representante Legal de
                    <strong>{{proveedor.razon_social}}</strong>, con RUC <strong>{{proveedor.ruc}}</strong>;
                    quienes convienen en suscribir la presente Acta de Entrega-Recepción ' . $tipo . '.
                </div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Bienes / Servicios Entregados</div>
                {{entregables_tabla}}
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Observaciones</div>
                <div class="seccion-body">{{doc.observaciones}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-body">
                    Las partes declaran que la entrega se realizó a entera satisfacción, que los bienes/servicios
                    cumplen con las especificaciones técnicas requeridas y que no existe ningún pendiente por saldar.
                    En fe de lo cual, las partes suscriben la presente acta en dos ejemplares de igual valor y efecto legal.
                </div>
            </div>

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{proveedor.representante}}</div>
                        <div class="firma-cargo">Representante Legal<br>{{proveedor.razon_social}}<br>RUC: {{proveedor.ruc}}</div>
                    </div>
                </div>
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{institucion.administrador}}</div>
                        <div class="firma-cargo">{{institucion.cargo_admin}}<br>{{institucion.nombre}}</div>
                    </div>
                </div>
            </div>

            <div class="footer-doc">Documento generado por sistema Brixs Contratación — {{doc.fecha}}</div>
        </div></body></html>';
    }

    // ── Certificado de Garantía Técnica ───────────────────────────────────
    private static function tplGarantiaTecnica(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Garantía Técnica — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Certificado de Garantía Técnica')
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Certificado de Garantía Técnica</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Beneficiario:</td><td>{{institucion.nombre}}</td></tr>
                <tr><td>Administrador:</td><td>{{institucion.administrador}} — {{institucion.cargo_admin}}</td></tr>
                <tr><td>Proveedor:</td><td>{{proveedor.razon_social}} &nbsp;|&nbsp; RUC: {{proveedor.ruc}}</td></tr>
                <tr><td>Representante:</td><td>{{proveedor.representante}}</td></tr>
                <tr><td>Monto:</td><td>{{proceso.monto}}</td></tr>
                <tr><td>Fecha de Emisión:</td><td>{{doc.fecha}}</td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.gt_objeto}}</div>
                <div class="seccion-body">{{gt.objeto}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.gt_vigencia}}</div>
                <div class="seccion-body">{{gt.vigencia}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.gt_cobertura}}</div>
                <div class="seccion-body">{{gt.cobertura}}</div>
            </div>

            {{it.secciones_extra}}

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{proveedor.representante}}</div>
                        <div class="firma-cargo">Representante Legal<br>{{proveedor.razon_social}}<br>RUC: {{proveedor.ruc}}</div>
                    </div>
                </div>
            </div>

            <div class="footer-doc">Documento generado por sistema Brixs Contratación — {{doc.fecha}}</div>
        </div></body></html>';
    }

    // ── Solicitud de Pago ─────────────────────────────────────────────────
    private static function tplSolicitudPago(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Solicitud de Pago — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Solicitud de Pago')
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Solicitud de Pago</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Contratante:</td><td>{{institucion.nombre}}</td></tr>
                <tr><td>Administrador:</td><td>{{institucion.administrador}} — {{institucion.cargo_admin}}</td></tr>
                <tr><td>Proveedor:</td><td>{{proveedor.razon_social}} — RUC: {{proveedor.ruc}}</td></tr>
                <tr><td>Representante:</td><td>{{proveedor.representante}}</td></tr>
                <tr><td>Monto:</td><td>{{proceso.monto}} + IVA 15% = <strong>{{proceso.total_iva}}</strong></td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.sp_antecedentes}}</div>
                <div class="seccion-body">{{sp.antecedentes}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.sp_justificacion}}</div>
                <div class="seccion-body">{{sp.justificacion}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Detalle del Valor a Cobrar</div>
                <table style="width:100%;border-collapse:collapse;font-size:10pt;margin-top:8px">
                    <thead><tr>
                        <th style="border:1px solid #ccc;padding:6px 10px;text-align:left">Concepto</th>
                        <th style="border:1px solid #ccc;padding:6px 10px;text-align:right;width:22%">Valor</th>
                    </tr></thead>
                    <tbody>
                    <tr><td style="border:1px solid #ccc;padding:6px 10px">{{proceso.objeto}}</td>
                        <td style="border:1px solid #ccc;padding:6px 10px;text-align:right">{{proceso.monto}}</td></tr>
                    <tr><td style="border:1px solid #ccc;padding:6px 10px">IVA 15%</td>
                        <td style="border:1px solid #ccc;padding:6px 10px;text-align:right">{{proceso.iva}}</td></tr>
                    <tr style="font-weight:bold">
                        <td style="border:1px solid #ccc;padding:6px 10px">TOTAL A COBRAR</td>
                        <td style="border:1px solid #ccc;padding:6px 10px;text-align:right">{{proceso.total_iva}}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Forma de Pago</div>
                <div class="seccion-body">{{proceso.forma_pago}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.sp_documentos}}</div>
                <div class="seccion-body">{{sp.documentos}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">{{titulo.sp_solicitud}}</div>
                <div class="seccion-body">{{sp.solicitud}}</div>
            </div>

            {{it.secciones_extra}}

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{proveedor.representante}}</div>
                        <div class="firma-cargo">Representante Legal<br>{{proveedor.razon_social}}<br>RUC: {{proveedor.ruc}}</div>
                    </div>
                </div>
            </div>

            <div class="footer-doc">Documento generado por sistema Brixs Contratación — {{doc.fecha}}</div>
        </div></body></html>';
    }

    // ── Informe de Conformidad ────────────────────────────────────────────
    private static function tplInformeConformidad(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Informe de Conformidad — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Informe de Conformidad')
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Informe de Conformidad</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Institución:</td><td>{{institucion.nombre}}</td></tr>
                <tr><td>Administrador:</td><td>{{institucion.administrador}}</td></tr>
                <tr><td>Proveedor:</td><td>{{proveedor.razon_social}}</td></tr>
                <tr><td>Monto:</td><td>{{proceso.monto}}</td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-titulo">1. Objeto del Informe</div>
                <div class="seccion-body">
                    El presente informe tiene por objeto dejar constancia de la conformidad con los bienes
                    y/o servicios recibidos en el marco del proceso de contratación <strong>{{proceso.numero}}</strong>.
                </div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">2. Verificación Técnica</div>
                <div class="seccion-body">{{proceso.especificaciones}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">3. Entregables Revisados</div>
                {{entregables_tabla}}
            </div>

            <div class="seccion">
                <div class="seccion-titulo">4. Conclusión</div>
                <div class="seccion-body">
                    Luego de la revisión técnica correspondiente, se determina que los bienes y/o servicios
                    entregados por <strong>{{proveedor.razon_social}}</strong> cumplen con todas las
                    especificaciones técnicas requeridas, por lo que se emite el presente
                    <strong>Informe de Conformidad</strong> favorable.
                    <br><br>{{doc.observaciones}}
                </div>
            </div>

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{institucion.administrador}}</div>
                        <div class="firma-cargo">{{institucion.cargo_admin}}<br>{{institucion.nombre}}</div>
                    </div>
                </div>
            </div>

            <div class="footer-doc">Documento generado por sistema Brixs Contratación — {{doc.fecha}}</div>
        </div></body></html>';
    }
}