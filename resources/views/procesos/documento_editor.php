<div class="mb-3 d-flex align-items-center gap-2">
  <a href="/procesos/<?= $proceso['id'] ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Volver
  </a>
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= e($titulo) ?></h5>
    <small class="text-muted"><?= e($proceso['numero_proceso']) ?> — <?= e(mb_substr($proceso['objeto_contratacion'],0,60)) ?></small>
  </div>
</div>

<!-- CKEditor con soporte de imágenes -->
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script src="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.umd.js"></script>

<style>
.ck-editor__editable { min-height: 120px; }
.ck-editor__editable img { max-width: 100%; height: auto; }
</style>

<div class="row g-3">
  <!-- Columna izquierda: formulario -->
  <div class="col-lg-6">
    <form id="formDocumento" method="POST" action="/procesos/<?= $proceso['id'] ?>/documento/generar" target="_blank">
      <?= csrf_field() ?>
      <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
      <!-- Campos sincronizados desde CKEditor -->
      <input type="hidden" name="especificaciones_tecnicas" id="hEspec">
      <input type="hidden" name="metodologia_trabajo"       id="hMetod">
      <input type="hidden" name="doc_observaciones"         id="hObs">

      <div class="card shadow-sm">
        <div class="card-header fw-semibold small bg-primary text-white">
          <i class="bi bi-pencil-square me-1"></i>Datos del Documento
        </div>
        <div class="card-body row g-3">

          <div class="col-md-6">
            <label class="form-label fw-semibold small">N° Documento</label>
            <input type="text" name="doc_numero" class="form-control form-control-sm"
                   value="<?= date('Y') ?>-<?= str_pad($proceso['id'],3,'0',STR_PAD_LEFT) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Fecha</label>
            <input type="text" name="doc_fecha" class="form-control form-control-sm"
                   value="<?= date('d/m/Y') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">Lugar</label>
            <input type="text" name="doc_lugar" class="form-control form-control-sm"
                   value="<?= e($_SESSION['tenant_ciudad'] ?? 'Quito') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold small">Monto (USD)</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" name="monto_total" class="form-control"
                     value="<?= $proceso['monto_total'] ?>">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Plazo (días)</label>
            <div class="input-group input-group-sm">
              <input type="number" name="plazo_dias" class="form-control"
                     value="<?= $proceso['plazo_dias'] ?>">
              <span class="input-group-text">días</span>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold small">Objeto de Contratación</label>
            <textarea name="objeto_contratacion" class="form-control form-control-sm" rows="2"><?= e($proceso['objeto_contratacion'] ?? '') ?></textarea>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold small">Forma de Pago</label>
            <textarea name="forma_pago" class="form-control form-control-sm" rows="2"><?= e($proceso['forma_pago'] ?? '') ?></textarea>
          </div>

          <hr class="my-0">

          <!-- ESPECIFICACIONES con CKEditor + imágenes -->
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <i class="bi bi-card-text me-1"></i>Especificaciones Técnicas
              <span class="badge bg-info text-dark ms-1" style="font-size:9px">admite imágenes</span>
            </label>
            <div id="editorEspec"><?= $proceso['especificaciones_tecnicas'] ?? '' ?></div>
          </div>

          <!-- METODOLOGÍA con CKEditor + imágenes -->
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <i class="bi bi-card-text me-1"></i>Metodología de Trabajo
              <span class="badge bg-info text-dark ms-1" style="font-size:9px">admite imágenes</span>
            </label>
            <div id="editorMetod"><?= $proceso['metodologia_trabajo'] ?? '' ?></div>
          </div>

          <!-- OBSERVACIONES con CKEditor + imágenes -->
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <i class="bi bi-card-text me-1"></i>Observaciones / Evidencia fotográfica
              <span class="badge bg-info text-dark ms-1" style="font-size:9px">admite imágenes</span>
            </label>
            <div id="editorObs"></div>
          </div>

        </div>
        <div class="card-footer">
          <button type="submit" id="btnGenerar" class="btn btn-primary w-100">
            <i class="bi bi-eye me-1"></i>Generar y Ver Documento
          </button>
        </div>
      </div>

    </form>
  </div>

  <!-- Columna derecha: info del proceso -->
  <div class="col-lg-6">
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
        </table>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-lightbulb me-1 text-warning"></i>Instrucciones
      </div>
      <div class="card-body small text-muted">
        <p><i class="bi bi-fonts text-primary me-1"></i>Puedes usar <strong>negrita, cursiva, listas</strong> en los editores.</p>
        <p><i class="bi bi-image text-success me-1"></i>Para insertar una imagen: haz clic en el botón <strong>������</strong> en la barra del editor, o simplemente <strong>pega</strong> (Ctrl+V) una imagen copiada.</p>
        <p><i class="bi bi-eye me-1"></i>Haz clic en <em>"Generar y Ver Documento"</em> — se abre en pestaña nueva.</p>
        <p><i class="bi bi-file-pdf me-1"></i>Usa <strong>"⬇ Descargar PDF"</strong> o <strong>Ctrl+P → Guardar como PDF</strong>.</p>
        <p class="mb-0"><i class="bi bi-folder me-1"></i>El documento queda guardado en el Expediente Digital.</p>
      </div>
    </div>
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

let ckEspec, ckMetod, ckObs;

ClassicEditor.create(document.getElementById('editorEspec'), ckConfig)
  .then(e => { ckEspec = e; }).catch(console.error);

ClassicEditor.create(document.getElementById('editorMetod'), ckConfig)
  .then(e => { ckMetod = e; }).catch(console.error);

ClassicEditor.create(document.getElementById('editorObs'), ckConfig)
  .then(e => { ckObs = e; }).catch(console.error);

// Antes de enviar: sincronizar CKEditors → hidden inputs
document.getElementById('formDocumento').addEventListener('submit', function(e) {
  document.getElementById('hEspec').value = ckEspec ? ckEspec.getData() : '';
  document.getElementById('hMetod').value = ckMetod ? ckMetod.getData() : '';
  document.getElementById('hObs').value   = ckObs   ? ckObs.getData()   : '';
});
</script>