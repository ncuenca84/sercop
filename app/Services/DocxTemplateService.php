<?php
namespace Services;

/**
 * DocxTemplateService
 * Reemplaza marcadores {{variable}} en plantillas .docx
 * usando python-docx a través de un script Python.
 */
class DocxTemplateService
{
    // ── Marcadores disponibles ────────────────────────────────────────────
    public static function marcadores(): array
    {
        return [
            // Proceso
            '{{proceso.numero}}'         => 'N° del proceso (NIC-...)',
            '{{proceso.objeto}}'         => 'Objeto de contratación',
            '{{proceso.tipo}}'           => 'Tipo de proceso',
            '{{proceso.monto}}'          => 'Monto total con símbolo $',
            '{{proceso.monto_num}}'      => 'Monto numérico sin símbolo',
            '{{proceso.iva}}'            => 'IVA 15%',
            '{{proceso.total_iva}}'      => 'Total con IVA',
            '{{proceso.plazo}}'          => 'Plazo (ej: 10 días calendario)',
            '{{proceso.fecha_inicio}}'   => 'Fecha de inicio',
            '{{proceso.estado}}'         => 'Estado del proceso',
            '{{proceso.descripcion}}'    => 'Descripción detallada',
            // Institución
            '{{institucion.nombre}}'     => 'Nombre de la entidad contratante',
            '{{institucion.ruc}}'        => 'RUC de la institución',
            '{{institucion.ciudad}}'     => 'Ciudad de la institución',
            '{{institucion.administrador}}' => 'Nombre del administrador del contrato',
            '{{institucion.cargo_admin}}'=> 'Cargo del administrador',
            '{{institucion.email_admin}}'=> 'Email del administrador',
            '{{institucion.telefono}}'   => 'Teléfono de la institución',
            '{{institucion.direccion}}'  => 'Dirección de la institución',
            // Proveedor (datos del tenant)
            '{{proveedor.razon_social}}' => 'Razón social de tu empresa',
            '{{proveedor.ruc}}'          => 'RUC de tu empresa',
            '{{proveedor.representante}}'=> 'Representante legal',
            '{{proveedor.ciudad}}'       => 'Ciudad de tu empresa',
            '{{proveedor.direccion}}'    => 'Dirección de tu empresa',
            '{{proveedor.telefono}}'     => 'Teléfono de tu empresa',
            '{{proveedor.email}}'        => 'Email de tu empresa',
            '{{proveedor.tipo_contrib}}' => 'Tipo de contribuyente',
            '{{proveedor.regimen}}'      => 'Régimen tributario',
            // Fechas
            '{{fecha.actual}}'           => 'Fecha de hoy (dd de mes de yyyy)',
            '{{fecha.actual_corta}}'     => 'Fecha corta (dd/mm/yyyy)',
            '{{anio.actual}}'            => 'Año actual',
            '{{ciudad.actual}}'          => 'Ciudad del proveedor',
        ];
    }

    // ── Construir variables desde proceso ────────────────────────────────
    public static function buildVars(array $proceso): array
    {
        $monto = (float)($proceso['monto_total'] ?? 0);
        return [
            // Proceso
            '{{proceso.numero}}'         => $proceso['numero_proceso'] ?? '',
            '{{proceso.objeto}}'         => $proceso['objeto_contratacion'] ?? '',
            '{{proceso.tipo}}'           => tipoProceso($proceso['tipo_proceso'] ?? ''),
            '{{proceso.monto}}'          => money($monto),
            '{{proceso.monto_num}}'      => number_format($monto, 2),
            '{{proceso.iva}}'            => '$' . number_format($monto * 0.15, 2),
            '{{proceso.total_iva}}'      => '$' . number_format($monto * 1.15, 2),
            '{{proceso.plazo}}'          => ($proceso['plazo_dias'] ?? 0) . ' días calendario',
            '{{proceso.fecha_inicio}}'   => formatDate($proceso['fecha_inicio'] ?? null),
            '{{proceso.estado}}'         => $proceso['estado'] ?? '',
            '{{proceso.descripcion}}'    => $proceso['descripcion_detallada'] ?? $proceso['objeto_contratacion'] ?? '',
            // Institución
            '{{institucion.nombre}}'     => $proceso['institucion_nombre'] ?? '',
            '{{institucion.ruc}}'        => $proceso['institucion_ruc'] ?? '',
            '{{institucion.ciudad}}'     => $proceso['institucion_ciudad'] ?? '',
            '{{institucion.administrador}}' => $proceso['administrador_nombre'] ?? '',
            '{{institucion.cargo_admin}}'=> $proceso['administrador_cargo'] ?? 'Administrador del Contrato',
            '{{institucion.email_admin}}'=> $proceso['administrador_email'] ?? '',
            '{{institucion.telefono}}'   => $proceso['institucion_telefono'] ?? '',
            '{{institucion.direccion}}'  => $proceso['institucion_direccion'] ?? '',
            // Proveedor
            '{{proveedor.razon_social}}' => $_SESSION['tenant_nombre'] ?? APP_NAME,
            '{{proveedor.ruc}}'          => $_SESSION['tenant_ruc'] ?? '',
            '{{proveedor.representante}}'=> $_SESSION['tenant_representante'] ?? '',
            '{{proveedor.ciudad}}'       => $_SESSION['tenant_ciudad'] ?? 'Quito',
            '{{proveedor.direccion}}'    => $_SESSION['tenant_direccion'] ?? '',
            '{{proveedor.telefono}}'     => $_SESSION['tenant_telefono'] ?? '',
            '{{proveedor.email}}'        => $_SESSION['tenant_email'] ?? '',
            '{{proveedor.tipo_contrib}}' => $_SESSION['tenant_tipo_contrib'] ?? 'Sociedad',
            '{{proveedor.regimen}}'      => $_SESSION['tenant_regimen'] ?? 'RIMPE',
            // Fechas
            '{{fecha.actual}}'           => self::fechaLarga(),
            '{{fecha.actual_corta}}'     => date('d/m/Y'),
            '{{anio.actual}}'            => date('Y'),
            '{{ciudad.actual}}'          => $_SESSION['tenant_ciudad'] ?? 'Quito',
        ];
    }

    // ── Procesar plantilla DOCX con python-docx ───────────────────────────
    public static function procesarDocx(string $plantillaPath, array $vars): string
    {
        if (!file_exists($plantillaPath)) {
            throw new \RuntimeException("Plantilla no encontrada: {$plantillaPath}");
        }

        // Archivo de salida temporal
        $outputPath = sys_get_temp_dir() . '/proforma_' . uniqid() . '.docx';

        // Generar JSON con variables (escapar correctamente)
        $varsJson  = tempnam(sys_get_temp_dir(), 'vars_') . '.json';
        file_put_contents($varsJson, json_encode($vars, JSON_UNESCAPED_UNICODE));

        // Script Python inline
        $script = self::getPythonScript();
        $scriptFile = tempnam(sys_get_temp_dir(), 'docx_script_') . '.py';
        file_put_contents($scriptFile, $script);

        $cmd = sprintf(
            'python3 %s %s %s %s 2>&1',
            escapeshellarg($scriptFile),
            escapeshellarg($plantillaPath),
            escapeshellarg($outputPath),
            escapeshellarg($varsJson)
        );

        $output = shell_exec($cmd);

        // Limpiar temporales
        @unlink($varsJson);
        @unlink($scriptFile);

        if (!file_exists($outputPath)) {
            throw new \RuntimeException("Error al procesar plantilla DOCX: " . ($output ?? 'Sin detalles'));
        }

        return $outputPath;
    }

    // ── Script Python para reemplazar marcadores en DOCX ─────────────────
    private static function getPythonScript(): string
    {
        return <<<'PYTHON'
import sys
import json
import re
from docx import Document
from docx.oxml.ns import qn
import copy

def replace_in_runs(paragraph, replacements):
    """Reemplaza marcadores incluso cuando están divididos en múltiples runs."""
    # Primero combinar todo el texto del párrafo para detectar marcadores
    full_text = ''.join(run.text for run in paragraph.runs)
    
    if '{{' not in full_text:
        return
    
    # Aplicar reemplazos
    new_text = full_text
    for key, value in replacements.items():
        new_text = new_text.replace(key, str(value))
    
    if new_text == full_text:
        return
    
    # Preservar formato del primer run y poner todo el texto ahí
    if paragraph.runs:
        first_run = paragraph.runs[0]
        first_run.text = new_text
        # Limpiar el resto de runs
        for run in paragraph.runs[1:]:
            run.text = ''

def replace_in_table(table, replacements):
    for row in table.rows:
        for cell in row.cells:
            for para in cell.paragraphs:
                replace_in_runs(para, replacements)
            # Tablas anidadas
            for nested_table in cell.tables:
                replace_in_table(nested_table, replacements)

def process_document(input_path, output_path, vars_path):
    with open(vars_path, 'r', encoding='utf-8') as f:
        replacements = json.load(f)
    
    doc = Document(input_path)
    
    # Reemplazar en párrafos del cuerpo
    for para in doc.paragraphs:
        replace_in_runs(para, replacements)
    
    # Reemplazar en tablas
    for table in doc.tables:
        replace_in_table(table, replacements)
    
    # Reemplazar en headers y footers
    for section in doc.sections:
        for header in [section.header, section.first_page_header, section.even_page_header]:
            if header:
                for para in header.paragraphs:
                    replace_in_runs(para, replacements)
                for table in header.tables:
                    replace_in_table(table, replacements)
        for footer in [section.footer, section.first_page_footer, section.even_page_footer]:
            if footer:
                for para in footer.paragraphs:
                    replace_in_runs(para, replacements)
                for table in footer.tables:
                    replace_in_table(table, replacements)
    
    doc.save(output_path)
    print("OK")

if __name__ == '__main__':
    process_document(sys.argv[1], sys.argv[2], sys.argv[3])
PYTHON;
    }

    // ── Fecha larga en español ────────────────────────────────────────────
    private static function fechaLarga(): string
    {
        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return date('d') . ' de ' . $meses[(int)date('n') - 1] . ' de ' . date('Y');
    }

    // ── Obtener plantillas del tenant ─────────────────────────────────────
    public static function listar(int $tenantId): array
    {
        return \DB::select(
            "SELECT * FROM plantillas_docx WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY nombre ASC",
            [$tenantId]
        );
    }

    public static function encontrar(int $id, int $tenantId): ?array
    {
        return \DB::selectOne(
            "SELECT * FROM plantillas_docx WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$id, $tenantId]
        );
    }
}