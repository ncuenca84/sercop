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
#panelIa { display: none; }
#panelIa.visible { display: block; }
</style>

<!-- ── Selector de modo ─────────────────────────────────────────────── -->
<div class="d-flex gap-2 mb-3">
  <button type="button" id="btnModoManual" class="btn btn-primary btn-sm"
          onclick="setModo('manual')">
    <i class="bi bi-pencil-square me-1"></i>Manual
  </button>
  <button type="button" id="btnModoIa" class="btn btn-outline-primary btn-sm"
          onclick="setModo('ia')">
    <i class="bi bi-stars me-1"></i>Generar con IA
  </button>
</div>

<!-- ── Panel IA ──────────────────────────────────────────────────────── -->
<div id="panelIa" class="card border-primary shadow-sm mb-3">
  <div class="card-header bg-primary text-white fw-semibold small">
    <i class="bi bi-stars me-1"></i>Generación con IA
  </div>
  <div class="card-body">

    <!-- Contexto que se enviará -->
    <p class="small text-muted mb-2">
      La IA usará los datos técnicos del proceso para redactar el contenido:
      <strong><?= e(mb_substr($proceso['objeto_contratacion'],0,80)) ?></strong>
      — Monto: <strong>$<?= number_format((float)($proceso['monto_total']??0),2) ?></strong>
      — Plazo: <strong><?= (int)($proceso['plazo_dias']??0) ?> días</strong>.
      <br><em class="text-success">No se envían firmas, datos del administrador ni información ya definida en la plantilla.</em>
    </p>

    <label class="form-label fw-semibold small">
      Instrucciones adicionales para la IA <span class="text-muted fw-normal">(opcional)</span>
    </label>
    <textarea id="iaPromptExtra" class="form-control form-control-sm mb-3" rows="2"
              placeholder="Ej: enfócate en el aspecto ambiental, menciona que se cumplieron los 3 entregables, el servicio fue de capacitación presencial..."></textarea>

    <button type="button" id="btnGenerarIa" class="btn btn-primary" onclick="generarConIa()">
      <i class="bi bi-stars me-1"></i>Generar contenido
    </button>
    <button type="button" id="btnGenerarIaLoading" class="btn btn-primary d-none" disabled>
      <span class="spinner-border spinner-border-sm me-1"></span><span id="iaProgreso">Sección 1/3…</span>
    </button>

    <div id="iaError" class="alert alert-danger mt-2 d-none small"></div>
    <div id="iaOk" class="alert alert-success mt-2 d-none small">
      <i class="bi bi-check-circle me-1"></i>
      Contenido generado. Revísalo en los editores y ajusta lo que necesites antes de generar el documento.
    </div>

  </div>
</div>

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
        <p><i class="bi bi-stars text-primary me-1"></i>Usa <strong>Generar con IA</strong> para que la IA redacte el contenido técnico del documento en base a los datos del proceso.</p>
        <p><i class="bi bi-pencil text-secondary me-1"></i>Con <strong>Manual</strong> puedes escribir o editar directamente en los editores.</p>
        <p><i class="bi bi-fonts text-primary me-1"></i>Puedes usar <strong>negrita, cursiva, listas</strong> en los editores.</p>
        <p><i class="bi bi-image text-success me-1"></i>Para insertar una imagen: botón <strong>🖼</strong> en la barra o pega (Ctrl+V).</p>
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
document.getElementById('formDocumento').addEventListener('submit', function() {
  document.getElementById('hEspec').value = ckEspec ? ckEspec.getData() : '';
  document.getElementById('hMetod').value = ckMetod ? ckMetod.getData() : '';
  document.getElementById('hObs').value   = ckObs   ? ckObs.getData()   : '';
});

// ── Selector de modo ─────────────────────────────────────────────────────
function setModo(modo) {
  const panelIa    = document.getElementById('panelIa');
  const btnManual  = document.getElementById('btnModoManual');
  const btnIa      = document.getElementById('btnModoIa');

  if (modo === 'ia') {
    panelIa.classList.add('visible');
    btnIa.classList.replace('btn-outline-primary', 'btn-primary');
    btnManual.classList.replace('btn-primary', 'btn-outline-primary');
  } else {
    panelIa.classList.remove('visible');
    btnManual.classList.replace('btn-outline-primary', 'btn-primary');
    btnIa.classList.replace('btn-primary', 'btn-outline-primary');
  }
}

// ── Llamada AJAX para generar con IA (3 llamadas secuenciales, 1 sección c/u) ──
// Cada llamada genera ~800 tokens y finaliza en < 25s, evitando el límite
// max_execution_time=30 del servidor cPanel.
async function generarConIa() {
  const btn        = document.getElementById('btnGenerarIa');
  const btnLoading = document.getElementById('btnGenerarIaLoading');
  const progreso   = document.getElementById('iaProgreso');
  const errDiv     = document.getElementById('iaError');
  const okDiv      = document.getElementById('iaOk');
  const promptExtra = document.getElementById('iaPromptExtra').value.trim();

  btn.classList.add('d-none');
  btnLoading.classList.remove('d-none');
  errDiv.classList.add('d-none');
  okDiv.classList.add('d-none');

  const csrfToken = document.querySelector('input[name="_csrf"]')?.value ?? '';
  const tipo      = document.querySelector('input[name="tipo"]').value;
  const procesoId = <?= (int)$proceso['id'] ?>;
  const url       = `/procesos/${procesoId}/documento/generar-ia`;

  const secciones = [
    { key: 'especificaciones_tecnicas', ck: () => ckEspec, label: 'Especificaciones (1/3)' },
    { key: 'metodologia_trabajo',       ck: () => ckMetod, label: 'Metodología (2/3)' },
    { key: 'observaciones',             ck: () => ckObs,   label: 'Observaciones (3/3)' },
  ];

  try {
    for (const sec of secciones) {
      progreso.textContent = sec.label + '…';

      const controller = new AbortController();
      const timeoutId  = setTimeout(() => controller.abort(), 28000); // 28s < 30s PHP limit

      let data;
      try {
        const body = new URLSearchParams({
          _csrf:        csrfToken,
          tipo:         tipo,
          seccion:      sec.key,
          prompt_extra: promptExtra,
        });

        const r = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString(),
          signal: controller.signal,
        });
        clearTimeout(timeoutId);

        if (!r.ok) throw new Error('El servidor respondió con código ' + r.status);
        data = await r.json();
      } catch (fetchErr) {
        clearTimeout(timeoutId);
        throw fetchErr.name === 'AbortError'
          ? new Error('La IA tardó demasiado en la sección "' + sec.label + '". Intenta de nuevo.')
          : fetchErr;
      }

      if (!data.ok) throw new Error(data.error ?? 'respuesta inesperada de la IA');

      const editor = sec.ck();
      if (editor && data.html) editor.setData(data.html);
    }

    okDiv.classList.remove('d-none');
    setModo('manual');

  } catch (err) {
    errDiv.textContent = 'Error: ' + err.message;
    errDiv.classList.remove('d-none');
  } finally {
    btn.classList.remove('d-none');
    btnLoading.classList.add('d-none');
  }
}
</script>
