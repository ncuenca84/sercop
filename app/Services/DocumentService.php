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
            case 'informe_tecnico':    return self::renderizar(self::tplInformeTecnico(),    $vars, $proceso);
            case 'acta_provisional':   return self::renderizar(self::tplActaEntrega('Provisional'), $vars, $proceso);
            case 'acta_definitiva':    return self::renderizar(self::tplActaEntrega('Definitiva'),  $vars, $proceso);
            case 'aceptacion_oc':      return self::renderizar(self::tplAceptacionOC(),      $vars, $proceso);
            case 'garantia_tecnica':   return self::renderizar(self::tplGarantiaTecnica(),   $vars, $proceso);
            case 'solicitud_pago':     return self::renderizar(self::tplSolicitudPago(),     $vars, $proceso);
            case 'informe_conformidad':return self::renderizar(self::tplInformeConformidad(),$vars, $proceso);
            default:                   return self::renderizar(self::tplInformeTecnico(),    $vars, $proceso);
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
            // URLs
            '{{url_back}}'                => '#',
            '{{url_pdf}}'                 => '#',
            '{{anio}}'                    => date('Y'),
        ];
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

        return '<table style="width:100%;border-collapse:collapse;font-size:11px">
            <thead><tr style="background:#f0f0f0">
                <th style="border:1px solid #ccc;padding:4px">N°</th>
                <th style="border:1px solid #ccc;padding:4px">Entregable</th>
                <th style="border:1px solid #ccc;padding:4px">Descripción</th>
                <th style="border:1px solid #ccc;padding:4px">Fecha</th>
                <th style="border:1px solid #ccc;padding:4px">Monto</th>
                <th style="border:1px solid #ccc;padding:4px">Estado</th>
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
            body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #222; background: #f5f5f5; }
            .page { background: #fff; max-width: 210mm; margin: 0 auto; padding: 20mm 20mm 25mm 20mm; min-height: 297mm; position: relative; }
            .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid {$color}; padding-bottom: 12px; margin-bottom: 20px; }
            .doc-header-left { flex: 1; }
            .doc-header-right { text-align: right; }
            .doc-title { font-size: 15pt; font-weight: bold; color: {$color}; margin: 14px 0 4px; text-transform: uppercase; }
            .doc-numero { font-size: 10pt; color: #555; }
            .doc-meta { background: #f8f9fa; border-left: 4px solid {$color}; padding: 10px 14px; margin: 16px 0; border-radius: 0 4px 4px 0; }
            .doc-meta table { width: 100%; border-collapse: collapse; }
            .doc-meta td { padding: 3px 8px; font-size: 10pt; }
            .doc-meta td:first-child { font-weight: bold; color: #555; width: 35%; }
            .seccion { margin: 18px 0; }
            .seccion-titulo { font-size: 11pt; font-weight: bold; color: {$color}; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
            .seccion-body { font-size: 10.5pt; line-height: 1.6; text-align: justify; }
            .firma-section { margin-top: 40px; display: flex; justify-content: space-around; }
            .firma-box { text-align: center; width: 200px; }
            .firma-linea { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; }
            .firma-nombre { font-weight: bold; font-size: 10pt; }
            .firma-cargo { font-size: 9pt; color: #555; }
            .footer-doc { position: fixed; bottom: 10mm; left: 20mm; right: 20mm; text-align: center; font-size: 8pt; color: #aaa; border-top: 1px solid #eee; padding-top: 4px; }
            .btn-bar { position: fixed; top: 0; left: 0; right: 0; background: #1B4F72; color: #fff; padding: 8px 20px; display: flex; gap: 10px; align-items: center; z-index: 9999; }
            .btn-bar a, .btn-bar button { background: rgba(255,255,255,0.2); color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 12px; text-decoration: none; }
            .btn-bar a:hover, .btn-bar button:hover { background: rgba(255,255,255,0.35); }
            .btn-bar .spacer { flex: 1; }
            @media print {
                body { background: #fff; }
                .btn-bar { display: none !important; }
                .footer-doc { position: fixed; }
            }
        </style>";
    }

    // ── Barra de acciones ─────────────────────────────────────────────────
    private static function btnBar(string $titulo): string
    {
        return '<div class="btn-bar">
            <a href="{{url_back}}">← Volver</a>
            <span style="font-size:13px;font-weight:bold">' . htmlspecialchars($titulo) . '</span>
            <div class="spacer"></div>
            <a href="{{url_pdf}}" title="Descargar PDF">⬇ Descargar PDF</a>
            <button onclick="window.print()">������ Imprimir / PDF</button>
        </div><div style="height:45px"></div>';
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
                <div class="seccion-titulo">1. Antecedentes</div>
                <div class="seccion-body">
                    En cumplimiento del proceso de contratación <strong>{{proceso.numero}}</strong>, cuyo objeto es
                    <strong>{{proceso.objeto}}</strong>, la empresa <strong>{{proveedor.razon_social}}</strong>
                    presenta el presente informe técnico de entrega de los bienes/servicios contratados.
                </div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">2. Especificaciones Técnicas Entregadas</div>
                <div class="seccion-body">{{proceso.especificaciones}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">3. Metodología y Proceso de Entrega</div>
                <div class="seccion-body">{{proceso.metodologia}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">4. Entregables</div>
                {{entregables_tabla}}
            </div>

            <div class="seccion">
                <div class="seccion-titulo">5. Observaciones</div>
                <div class="seccion-body">{{doc.observaciones}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">6. Conclusión</div>
                <div class="seccion-body">
                    Los bienes/servicios han sido entregados en su totalidad conforme a las especificaciones
                    técnicas acordadas, en el plazo establecido de {{proceso.plazo}}, a entera satisfacción
                    de la institución contratante.
                </div>
            </div>

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

    // ── Acta de Entrega (Provisional / Definitiva) ────────────────────────
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

    // ── Aceptación de Orden de Compra ─────────────────────────────────────
    private static function tplAceptacionOC(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Aceptación OC — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Aceptación de Orden de Compra')
        . '<div class="page">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Aceptación de Orden de Compra</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div class="doc-meta"><table>
                <tr><td>Proceso NIC:</td><td>{{proceso.numero}}</td></tr>
                <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                <tr><td>Institución:</td><td>{{institucion.nombre}}</td></tr>
                <tr><td>Monto:</td><td>{{proceso.monto}}</td></tr>
                <tr><td>Plazo:</td><td>{{proceso.plazo}}</td></tr>
            </table></div>

            <div class="seccion">
                <div class="seccion-body">
                    <strong>{{doc.lugar}}, {{doc.fecha}}</strong><br><br>
                    Señor(a)<br>
                    <strong>{{institucion.administrador}}</strong><br>
                    {{institucion.cargo_admin}}<br>
                    <strong>{{institucion.nombre}}</strong><br>
                    Presente.–<br><br>

                    De mi consideración:<br><br>

                    Yo, <strong>{{proveedor.representante}}</strong>, en calidad de Representante Legal de
                    <strong>{{proveedor.razon_social}}</strong>, con RUC <strong>{{proveedor.ruc}}</strong>,
                    me dirijo a usted para hacer constar formalmente la <strong>aceptación de la Orden de Compra</strong>
                    N° <strong>{{doc.numero}}</strong> correspondiente al proceso de contratación
                    <strong>{{proceso.numero}}</strong>, cuyo objeto es <strong>{{proceso.objeto}}</strong>.<br><br>

                    Mi representada se compromete a cumplir con todas las especificaciones técnicas,
                    plazos y condiciones establecidas en el proceso de contratación, en el plazo de
                    <strong>{{proceso.plazo}}</strong> contados a partir de la notificación de la orden de compra.<br><br>

                    La forma de pago acordada es: <strong>{{proceso.forma_pago}}</strong>.<br><br>

                    {{doc.observaciones}}
                </div>
            </div>

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

    // ── Certificado de Garantía Técnica ───────────────────────────────────
    private static function tplGarantiaTecnica(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Garantía Técnica — {{proceso.numero}}</title>'
        . self::css() . '</head><body>'
        . self::btnBar('Certificado de Garantía Técnica')
        . '<div class="page" style="text-align:center">
            <div class="doc-header">
                <div class="doc-header-left">{{logo_html}}</div>
                <div class="doc-header-right">
                    <div class="doc-title">Certificado de Garantía Técnica</div>
                    <div class="doc-numero">N° {{doc.numero}} &nbsp;|&nbsp; {{doc.lugar}}, {{doc.fecha}}</div>
                </div>
            </div>

            <div style="margin: 30px 0; text-align:left">
                <div class="doc-meta"><table>
                    <tr><td>Proceso:</td><td>{{proceso.numero}}</td></tr>
                    <tr><td>Objeto:</td><td>{{proceso.objeto}}</td></tr>
                    <tr><td>Beneficiario:</td><td>{{institucion.nombre}}</td></tr>
                    <tr><td>Proveedor:</td><td>{{proveedor.razon_social}} — RUC: {{proveedor.ruc}}</td></tr>
                    <tr><td>Monto:</td><td>{{proceso.monto}}</td></tr>
                    <tr><td>Fecha de Emisión:</td><td>{{doc.fecha}}</td></tr>
                </table></div>

                <div class="seccion">
                    <div class="seccion-body">
                        La empresa <strong>{{proveedor.razon_social}}</strong>, con RUC <strong>{{proveedor.ruc}}</strong>,
                        representada por <strong>{{proveedor.representante}}</strong>, CERTIFICA que los bienes
                        y/o servicios entregados en virtud del proceso de contratación <strong>{{proceso.numero}}</strong>
                        cuentan con garantía técnica en los siguientes términos:
                    </div>
                </div>

                <div class="seccion">
                    <div class="seccion-titulo">Alcance de la Garantía</div>
                    <div class="seccion-body">{{proceso.especificaciones}}</div>
                </div>

                <div class="seccion">
                    <div class="seccion-titulo">Condiciones</div>
                    <div class="seccion-body">
                        La garantía cubre defectos de fabricación, funcionamiento deficiente y fallas
                        técnicas no atribuibles al uso indebido por parte del beneficiario.
                        Para hacer efectiva la garantía, comunicarse a: <strong>{{proveedor.email}}</strong>
                        o al teléfono <strong>{{proveedor.telefono}}</strong>.
                        <br><br>{{doc.observaciones}}
                    </div>
                </div>
            </div>

            <div class="firma-section">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">{{proveedor.representante}}</div>
                        <div class="firma-cargo">Representante Legal<br>{{proveedor.razon_social}}</div>
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

            <div class="seccion">
                <div class="seccion-body">
                    <strong>{{doc.lugar}}, {{doc.fecha}}</strong><br><br>
                    Señor(a)<br>
                    <strong>{{institucion.administrador}}</strong><br>
                    {{institucion.cargo_admin}}<br>
                    <strong>{{institucion.nombre}}</strong><br>
                    Presente.–<br><br>

                    De mi consideración:<br><br>

                    Yo, <strong>{{proveedor.representante}}</strong>, Representante Legal de
                    <strong>{{proveedor.razon_social}}</strong>, RUC <strong>{{proveedor.ruc}}</strong>,
                    me permito presentar la solicitud de pago correspondiente al proceso de contratación
                    <strong>{{proceso.numero}}</strong>.
                </div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Detalle del Valor a Cobrar</div>
                <table style="width:100%;border-collapse:collapse;font-size:11pt;margin-top:8px">
                    <tr style="background:#f0f0f0">
                        <td style="border:1px solid #ccc;padding:6px 10px;font-weight:bold">Concepto</td>
                        <td style="border:1px solid #ccc;padding:6px 10px;font-weight:bold;text-align:right">Valor</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #ccc;padding:6px 10px">{{proceso.objeto}}</td>
                        <td style="border:1px solid #ccc;padding:6px 10px;text-align:right">{{proceso.monto}}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #ccc;padding:6px 10px">IVA 15%</td>
                        <td style="border:1px solid #ccc;padding:6px 10px;text-align:right">{{proceso.iva}}</td>
                    </tr>
                    <tr style="font-weight:bold">
                        <td style="border:1px solid #ccc;padding:6px 10px">TOTAL</td>
                        <td style="border:1px solid #ccc;padding:6px 10px;text-align:right">{{proceso.total_iva}}</td>
                    </tr>
                </table>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Forma de Pago</div>
                <div class="seccion-body">{{proceso.forma_pago}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-titulo">Observaciones</div>
                <div class="seccion-body">{{doc.observaciones}}</div>
            </div>

            <div class="seccion">
                <div class="seccion-body">
                    En virtud de haber cumplido con todos los requisitos establecidos en el proceso de contratación,
                    solicito se proceda con el pago correspondiente a la brevedad posible.<br><br>
                    Atentamente,
                </div>
            </div>

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