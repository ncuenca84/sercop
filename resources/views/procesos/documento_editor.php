<?php
// ─── Secciones editables por tipo de documento ─────────────────────────────
$seccionesPorTipo = [
    'informe_tecnico' => [
        ['key' => 'it_antecedentes',    'label' => '1. Antecedentes',    'icono' => 'bi-journal-text',
         'ayuda' => 'Contexto del proceso, institución contratante, monto y plazo.'],
        ['key' => 'it_objetivo',        'label' => '2. Objetivo',        'icono' => 'bi-bullseye',
         'ayuda' => 'Propósito del informe y del contrato ejecutado.'],
        ['key' => 'it_desarrollo',      'label' => '3. Desarrollo',      'icono' => 'bi-card-checklist',
         'ayuda' => 'Descripción de los trabajos/bienes entregados, especificaciones y metodología.'],
        ['key' => 'it_conclusiones',    'label' => '4. Conclusiones',    'icono' => 'bi-check2-square',
         'ayuda' => 'Resultado de la ejecución contractual y cumplimiento de obligaciones.'],
        ['key' => 'it_recomendaciones', 'label' => '5. Recomendaciones', 'icono' => 'bi-lightbulb',
         'ayuda' => 'Acciones sugeridas: acta de entrega, pago, archivo del expediente, etc.'],
    ],
];
$secciones = $seccionesPorTipo[$tipo] ?? [
    ['key' => 'especificaciones_tecnicas', 'label' => 'Especificaciones Técnicas', 'icono' => 'bi-card-text', 'ayuda' => ''],
    ['key' => 'metodologia_trabajo',       'label' => 'Metodología de Trabajo',   'icono' => 'bi-card-text', 'ayuda' => ''],
    ['key' => 'doc_observaciones',         'label' => 'Observaciones',            'icono' => 'bi-card-text', 'ayuda' => ''],
];

// ─── Sugerencias pre-llenadas desde datos del proceso (Fase 1) ─────────────
$sugerencias = [];
if ($tipo === 'informe_tecnico') {
    $monto   = number_format((float)($proceso['monto_total'] ?? 0), 2);
    $plazo   = (int)($proceso['plazo_dias'] ?? 0);
    $numero  = htmlspecialchars($proceso['numero_proceso'] ?? '');
    $objeto  = htmlspecialchars($proceso['objeto_contratacion'] ?? '');
    $inst    = htmlspecialchars($proceso['institucion_nombre'] ?? '');
    $fi      = htmlspecialchars($proceso['fecha_inicio'] ?? '—');
    $ff      = htmlspecialchars($proceso['fecha_fin']    ?? '—');
    $prov    = htmlspecialchars($_SESSION['tenant_nombre'] ?? '');

    $sugerencias['it_antecedentes'] =
        "<p>En cumplimiento del proceso de contratación <strong>{$numero}</strong>, cuyo objeto es "
        . "<strong>{$objeto}</strong>, celebrado con la institución <strong>{$inst}</strong>; por un monto de "
        . "<strong>\${$monto}</strong> más IVA y un plazo de <strong>{$plazo} días calendario</strong> "
        . "(del {$fi} al {$ff}), la empresa <strong>{$prov}</strong> presenta el presente Informe Técnico de Entrega.</p>";

    $sugerencias['it_objetivo'] =
        "<p>Verificar y documentar el cumplimiento de las obligaciones contractuales derivadas del proceso "
        . "<strong>{$numero}</strong>, correspondiente a: <strong>{$objeto}</strong>, conforme a las "
        . "especificaciones técnicas acordadas entre las partes.</p>";

    $sugerencias['it_desarrollo'] = $proceso['especificaciones_tecnicas']
        ?: "<p>Describir aquí los trabajos, bienes o servicios entregados, así como la metodología aplicada durante la ejecución.</p>";

    $sugerencias['it_conclusiones'] =
        "<p>Los bienes y/o servicios objeto del proceso <strong>{$numero}</strong> han sido entregados en su totalidad, "
        . "dentro del plazo establecido y conforme a las especificaciones técnicas requeridas, a entera satisfacción "
        . "de la institución contratante <strong>{$inst}</strong>.</p>"
        . "<p>La ejecución del contrato cumple con lo establecido en la LOSNCP y demás normativa vigente.</p>";

    $sugerencias['it_recomendaciones'] = $proceso['metodologia_trabajo']
        ?: "<p>Se recomienda proceder con la suscripción del Acta de Entrega-Recepción Provisional y tramitar "
        . "el pago correspondiente conforme a la forma de pago establecida en el contrato.</p>"
        . "<p>Archivar el presente informe en el expediente digital del proceso <strong>{$numero}</strong>.</p>";
} else {
    $sugerencias['especificaciones_tecnicas'] = $proceso['especificaciones_tecnicas'] ?? '';
    $sugerencias['metodologia_trabajo']       = $proceso['metodologia_trabajo']       ?? '';
    $sugerencias['doc_observaciones']         = '';
}
?>

<div class="mb-3 d-flex align-items-center gap-2">
  <a href="/procesos/<?= $proceso['id'] ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Volver
  </a>
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= e($titulo) ?></h5>
    <small class="text-muted"><?= e($proceso['numero_proceso']) ?> — <?= e(mb_substr($proceso['objeto_contratacion'],0,60)) ?></small>
  </div>
</div>

<!-- CKEditor -->
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script src="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.umd.js"></script>

<style>
.ck-editor__editable { min-height: 140px; }
.ck-editor__editable img { max-width: 100%; height: auto; }
.seccion-editor { border-left: 3px solid #0d6efd; padding-left: 10px; margin-bottom: 4px; }
.seccion-ayuda { font-size: 11px; color: #6c757d; margin-bottom: 6px; }
</style>

<div class="row g-3">
  <!-- Columna izquierda: formulario de secciones -->
  <div class="col-lg-7">
    <form id="formDocumento" method="POST" action="/procesos/<?= $proceso['id'] ?>/documento/generar" target="_blank">
      <?= csrf_field() ?>
      <input type="hidden" name="tipo" value="<?= e($tipo) ?>">

      <!-- Hidden inputs sincronizados desde CKEditor -->
<?php foreach ($secciones as $sec): ?>
      <input type="hidden" name="<?= e($sec['key']) ?>" id="h_<?= e($sec['key']) ?>">
<?php endforeach; ?>

      <!-- ── Datos generales del documento ─────────────────────────────── -->
      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold small bg-primary text-white">
          <i class="bi bi-sliders me-1"></i>Datos del Documento
        </div>
        <div class="card-body row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold small mb-1">N° Documento</label>
            <input type="text" name="doc_numero" class="form-control form-control-sm"
                   value="<?= date('Y') ?>-<?= str_pad($proceso['id'],3,'0',STR_PAD_LEFT) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small mb-1">Fecha</label>
            <input type="text" name="doc_fecha" class="form-control form-control-sm" value="<?= date('d/m/Y') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small mb-1">Lugar</label>
            <input type="text" name="doc_lugar" class="form-control form-control-sm"
                   value="<?= e($_SESSION['tenant_ciudad'] ?? 'Quito') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small mb-1">Monto (USD)</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" name="monto_total" class="form-control"
                     value="<?= $proceso['monto_total'] ?>">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small mb-1">Plazo (días)</label>
            <div class="input-group input-group-sm">
              <input type="number" name="plazo_dias" class="form-control" value="<?= $proceso['plazo_dias'] ?>">
              <span class="input-group-text">días</span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small mb-1">Objeto de Contratación</label>
            <textarea name="objeto_contratacion" class="form-control form-control-sm" rows="2"><?= e($proceso['objeto_contratacion'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small mb-1">Forma de Pago</label>
            <textarea name="forma_pago" class="form-control form-control-sm" rows="2"><?= e($proceso['forma_pago'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- ── Secciones del documento ─────────────────────────────────────── -->
      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold small bg-primary text-white">
          <i class="bi bi-pencil-square me-1"></i>Contenido del Documento
          <span class="fw-normal opacity-75 ms-2 small">Los campos se pre-llenan con datos del proceso</span>
        </div>
        <div class="card-body row g-4">
<?php foreach ($secciones as $sec): ?>
          <div class="col-12">
            <div class="seccion-editor">
              <label class="form-label fw-semibold small mb-1">
                <i class="bi <?= e($sec['icono']) ?> me-1 text-primary"></i><?= e($sec['label']) ?>
              </label>
              <?php if ($sec['ayuda']): ?>
              <div class="seccion-ayuda"><?= e($sec['ayuda']) ?></div>
              <?php endif; ?>
              <div id="editor_<?= e($sec['key']) ?>"><?= $sugerencias[$sec['key']] ?? '' ?></div>
            </div>
          </div>
<?php endforeach; ?>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-eye me-1"></i>Generar y Ver Documento
          </button>
        </div>
      </div>

    </form>
  </div>

  <!-- Columna derecha: info del proceso -->
  <div class="col-lg-5">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold small">
        <i class="bi bi-info-circle me-1 text-primary"></i>Datos del Proceso
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <tr><th class="text-muted ps-3" width="38%">Institución</th><td><?= e($proceso['institucion_nombre'] ?? '') ?></td></tr>
          <tr><th class="text-muted ps-3">Administrador</th><td><?= e($proceso['administrador_nombre'] ?? '') ?></td></tr>
          <tr><th class="text-muted ps-3">Cargo</th><td><?= e($proceso['administrador_cargo'] ?? '') ?></td></tr>
          <tr><th class="text-muted ps-3">CPC</th><td><?= e($proceso['cpc'] ?? '—') ?></td></tr>
          <tr><th class="text-muted ps-3">Fecha inicio</th><td><?= e($proceso['fecha_inicio'] ?? '—') ?></td></tr>
          <tr><th class="text-muted ps-3">Fecha fin</th><td><?= e($proceso['fecha_fin'] ?? '—') ?></td></tr>
          <tr><th class="text-muted ps-3">Monto</th><td><strong>$<?= number_format((float)($proceso['monto_total']??0),2) ?></strong></td></tr>
          <tr><th class="text-muted ps-3">Plazo</th><td><?= (int)($proceso['plazo_dias']??0) ?> días</td></tr>
        </table>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold small">
        <i class="bi bi-lightbulb me-1 text-warning"></i>Instrucciones
      </div>
      <div class="card-body small text-muted">
        <p><i class="bi bi-magic text-primary me-1"></i>Cada sección viene <strong>pre-llenada</strong> con los datos registrados en la Fase 1. Edita el texto según lo que corresponda.</p>
        <p><i class="bi bi-fonts text-secondary me-1"></i>Usa <strong>negrita, cursiva, listas y tablas</strong> desde la barra de cada editor.</p>
        <p><i class="bi bi-image text-success me-1"></i>Para insertar imágenes: botón <strong>🖼</strong> en la barra o pega directamente (Ctrl+V).</p>
        <p class="mb-0"><i class="bi bi-folder me-1"></i>Al generar, el documento queda guardado en el <strong>Expediente Digital</strong> del proceso.</p>
      </div>
    </div>

    <?php if (!empty($proceso['especificaciones_tecnicas'])): ?>
    <div class="card shadow-sm border-info">
      <div class="card-header fw-semibold small text-info border-info">
        <i class="bi bi-clipboard-data me-1"></i>Especificaciones de Fase 1 (referencia)
      </div>
      <div class="card-body small" style="max-height:200px;overflow-y:auto">
        <?= $proceso['especificaciones_tecnicas'] ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const { ClassicEditor, Essentials, Bold, Italic, Underline, Strikethrough,
        List, Paragraph, Heading, Table, TableToolbar,
        Image, ImageUpload, ImageInsert, ImageResize, ImageToolbar, ImageCaption, ImageStyle,
        Base64UploadAdapter, Link, BlockQuote, Indent } = CKEDITOR;

const ckConfig = {
  plugins: [
    Essentials, Bold, Italic, Underline, Strikethrough,
    List, Paragraph, Heading, Table, TableToolbar,
    Image, ImageUpload, ImageInsert, ImageResize, ImageToolbar, ImageCaption, ImageStyle,
    Base64UploadAdapter, Link, BlockQuote, Indent
  ],
  toolbar: {
    items: [
      'heading', '|',
      'bold', 'italic', 'underline', '|',
      'bulletedList', 'numberedList', 'blockQuote', '|',
      'insertImage', 'insertTable', 'link', '|',
      'outdent', 'indent', '|',
      'undo', 'redo'
    ]
  },
  image: {
    toolbar: ['imageStyle:inline','imageStyle:block','|','toggleImageCaption','imageTextAlternative','|','resizeImage'],
    resizeOptions: [
      { name: 'resizeImage:original', value: null, label: 'Original' },
      { name: 'resizeImage:50',       value: '50',  label: '50%' },
      { name: 'resizeImage:75',       value: '75',  label: '75%' },
    ],
    upload: { types: ['jpeg','jpg','png','gif','webp'] }
  },
  table: { contentToolbar: ['tableColumn','tableRow','mergeTableCells'] }
};

// Inicializar un CKEditor por sección
const editores = {};
<?php foreach ($secciones as $sec): ?>
ClassicEditor.create(document.getElementById('editor_<?= $sec['key'] ?>'), ckConfig)
  .then(e => { editores['<?= $sec['key'] ?>'] = e; }).catch(console.error);
<?php endforeach; ?>

// Sincronizar CKEditors → hidden inputs antes de enviar
document.getElementById('formDocumento').addEventListener('submit', function() {
  <?php foreach ($secciones as $sec): ?>
  document.getElementById('h_<?= $sec['key'] ?>').value = editores['<?= $sec['key'] ?>']
    ? editores['<?= $sec['key'] ?>'].getData() : '';
  <?php endforeach; ?>
});
</script>
