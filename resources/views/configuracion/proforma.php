<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Configuración de Proformas</h4>
    <small class="text-muted">Personaliza tu proforma HTML — se genera instantáneamente sin instalar nada</small>
  </div>
  <div class="d-flex gap-2">
    <a href="/configuracion" class="btn btn-sm btn-outline-secondary">← Configuración</a>
  </div>
</div>

<?php if(!empty($flash_messages ?? [])): foreach($flash_messages as $f): ?>
<div class="alert alert-<?= $f['type']==='success'?'success':'danger' ?> alert-dismissible">
  <?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; endif; ?>

<div class="row g-4">

  <!-- COLUMNA IZQUIERDA: Config + Logo -->
  <div class="col-lg-5">

    <!-- LOGO -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-image me-2 text-primary"></i>Logo de la Empresa
      </div>
      <div class="card-body">
        <?php
        $logoActual = $tenant['logo_url'] ?? '';
        if ($logoActual && file_exists($logoActual)):
          $ext  = strtolower(pathinfo($logoActual, PATHINFO_EXTENSION));
          $mime = $ext === 'png' ? 'image/png' : ($ext === 'svg' ? 'image/svg+xml' : 'image/jpeg');
          $b64  = base64_encode(file_get_contents($logoActual));
        ?>
          <div class="mb-3 text-center p-3 bg-light rounded">
            <img src="data:<?= $mime ?>;base64,<?= $b64 ?>" style="max-height:80px;max-width:200px" alt="Logo actual">
          </div>
        <?php else: ?>
          <div class="mb-3 text-center p-3 bg-light rounded text-muted small">
            <i class="bi bi-image fs-3"></i><br>Sin logo — se usará el nombre de la empresa
          </div>
        <?php endif; ?>
        <form method="POST" action="/configuracion/proforma/logo" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="file" name="logo" class="form-control form-control-sm mb-2" accept="image/*" required>
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-upload me-1"></i>Subir Logo
          </button>
        </form>
        <div class="form-text">PNG, JPG o SVG. Recomendado: fondo transparente, máx 300x100px</div>
      </div>
    </div>

    <!-- CONFIGURACIÓN -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-sliders me-2 text-primary"></i>Configuración General
      </div>
      <form method="POST" action="/configuracion/proforma">
        <?= csrf_field() ?>
        <div class="card-body row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Color principal</label>
            <div class="input-group">
              <input type="color" name="color_primario" class="form-control form-control-color"
                     value="<?= e($config['color_primario'] ?? '#1B4F72') ?>" style="width:50px">
              <input type="text" class="form-control form-control-sm"
                     value="<?= e($config['color_primario'] ?? '#1B4F72') ?>"
                     oninput="this.previousElementSibling.value=this.value">
            </div>
            <div class="form-text">Color de cabeceras y títulos</div>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">N° Proforma siguiente</label>
            <input type="text" name="proforma_numero" class="form-control form-control-sm"
                   value="<?= e($config['proforma_numero'] ?? date('Y').'-001') ?>"
                   placeholder="<?= date('Y') ?>-001">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Forma de Pago</label>
            <input type="text" name="forma_pago" class="form-control form-control-sm"
                   value="<?= e($config['forma_pago'] ?? 'Contra entrega del servicio/bien') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Vigencia de la Oferta</label>
            <input type="text" name="vigencia_oferta" class="form-control form-control-sm"
                   value="<?= e($config['vigencia_oferta'] ?? '30 días calendario') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Texto adicional (condiciones, notas)</label>
            <textarea name="texto_adicional" class="form-control form-control-sm" rows="3"
                      placeholder="Ej: Se incluye soporte técnico por 12 meses..."><?= e($config['texto_adicional'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="card-footer bg-white">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-check2 me-1"></i>Guardar Configuración
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- COLUMNA DERECHA: Plantilla HTML + Variables -->
  <div class="col-lg-7">

    <!-- PREVIEW RÁPIDO -->
    <div class="card border-0 shadow-sm mb-4 border-success">
      <div class="card-header bg-success text-white fw-semibold">
        <i class="bi bi-eye me-2"></i>Cómo funciona
      </div>
      <div class="card-body p-3">
        <div class="d-flex gap-3 align-items-start">
          <div class="text-center" style="min-width:40px">
            <div class="badge bg-primary rounded-circle p-2 mb-1">1</div>
            <div class="small">Configuras</div>
          </div>
          <div class="text-muted small pt-1">→</div>
          <div class="text-center" style="min-width:40px">
            <div class="badge bg-primary rounded-circle p-2 mb-1">2</div>
            <div class="small">Proceso</div>
          </div>
          <div class="text-muted small pt-1">→</div>
          <div class="text-center" style="min-width:40px">
            <div class="badge bg-success rounded-circle p-2 mb-1">3</div>
            <div class="small">Generar</div>
          </div>
          <div class="text-muted small pt-1">→</div>
          <div class="text-center" style="min-width:40px">
            <div class="badge bg-warning rounded-circle p-2 mb-1">4</div>
            <div class="small">Ctrl+P</div>
          </div>
          <div class="text-muted small pt-1">→</div>
          <div class="text-center" style="min-width:40px">
            <div class="badge bg-danger rounded-circle p-2 mb-1">5</div>
            <div class="small">Firma</div>
          </div>
          <div class="text-muted small pt-1">→</div>
          <div class="text-center" style="min-width:40px">
            <div class="badge bg-dark rounded-circle p-2 mb-1">6</div>
            <div class="small">SERCOP</div>
          </div>
        </div>
        <div class="alert alert-info small mb-0 mt-3">
          <i class="bi bi-lightbulb me-1"></i>
          En cada proceso aparece el botón <strong>📄 Proforma</strong>.
          Al hacer clic se genera el HTML con todos los datos, listo para imprimir o guardar como PDF desde el navegador (<kbd>Ctrl+P</kbd> → Guardar como PDF).
          Sin instalar nada en el servidor.
        </div>
      </div>
    </div>

    <!-- PLANTILLA HTML EDITABLE -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-code-slash me-2 text-primary"></i>Plantilla HTML Personalizada</span>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" onclick="resetPlantilla()">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar por defecto
          </button>
        </div>
      </div>
      <form method="POST" action="/configuracion/proforma/plantilla">
        <?= csrf_field() ?>
        <div class="card-body p-0">
          <div class="bg-light px-3 py-2 border-bottom small text-muted">
            Edita el HTML de tu proforma. Usa los marcadores <code>{{variable}}</code> de abajo.
            Si no editas, se usa la plantilla profesional por defecto.
          </div>
          <textarea name="contenido_html" id="editorHtml"
                    class="form-control border-0 font-monospace"
                    style="height:320px;font-size:11px;resize:vertical;border-radius:0"
                    placeholder="Deja vacío para usar la plantilla por defecto..."><?= htmlspecialchars($htmlActual ?? '') ?></textarea>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i>Guardar Plantilla HTML
          </button>
          <button type="button" class="btn btn-outline-info btn-sm" onclick="previewHtml()">
            <i class="bi bi-eye me-1"></i>Vista previa
          </button>
        </div>
      </form>
    </div>

    <!-- VARIABLES DISPONIBLES -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-braces me-2 text-warning"></i>Variables disponibles
        <small class="fw-normal text-muted ms-1">— Click para copiar</small>
      </div>
      <div class="card-body p-3">
        <div class="row g-2">
          <?php foreach($variables as $grupo => $vars): ?>
          <div class="col-md-6">
            <div class="small fw-semibold text-muted mb-1"><?= e($grupo) ?></div>
            <div class="d-flex flex-wrap gap-1 mb-2">
              <?php foreach($vars as $v): ?>
              <code class="badge bg-light text-dark border small py-1 px-2"
                    style="cursor:pointer;font-size:9.5px"
                    onclick="copiarVar('<?= e($v) ?>', this)"
                    title="Click para copiar"><?= e($v) ?></code>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Botón agregar Proforma en procesos -->
<div class="card border-0 shadow-sm mt-4 border-primary">
  <div class="card-body d-flex justify-content-between align-items-center py-3">
    <div>
      <strong><i class="bi bi-info-circle text-primary me-2"></i>En cada proceso</strong>
      <span class="text-muted small ms-2">aparece el botón "📄 Proforma" que genera la proforma con los datos de ese proceso</span>
    </div>
    <a href="/procesos" class="btn btn-primary btn-sm">Ver Procesos →</a>
  </div>
</div>

<script>
function copiarVar(texto, el) {
  navigator.clipboard.writeText(texto).then(() => {
    const orig = el.textContent;
    el.textContent = '✓';
    el.classList.add('bg-success','text-white');
    setTimeout(() => { el.textContent = orig; el.classList.remove('bg-success','text-white'); }, 1000);
  });
}
function resetPlantilla() {
  if(confirm('¿Restaurar plantilla por defecto? Se perderá la personalización.')) {
    document.getElementById('editorHtml').value = '';
  }
}
function previewHtml() {
  const html = document.getElementById('editorHtml').value;
  if (!html.trim()) { alert('Escribe HTML primero'); return; }
  const w = window.open('', '_blank');
  w.document.write(html.replace(/\{\{[^}]+\}\}/g, '<span style="background:#ff0;padding:0 2px">$&</span>'));
  w.document.close();
}
</script>
