<div class="mb-3">
  <a href="/procesos/<?= $proceso['id'] ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Volver al proceso
  </a>
</div>


<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-file-earmark-word text-primary me-2"></i>Generar Documento Word</h5>
        <small class="text-muted">Proceso: <strong><?= e($proceso['numero_proceso']) ?></strong></small>
      </div>
      <div class="card-body">

        <?php if(empty($plantillas)): ?>
          <div class="text-center py-4">
            <i class="bi bi-exclamation-circle fs-2 text-warning"></i>
            <p class="mt-2">No tienes plantillas Word configuradas.</p>
            <a href="/plantillas-docx" class="btn btn-primary btn-sm">
              <i class="bi bi-upload me-1"></i>Subir plantilla .docx
            </a>
          </div>
        <?php else: ?>
          <form method="POST" action="/procesos/<?= $proceso['id'] ?>/generar-docx">
            <?= csrf_field() ?>

            <p class="text-muted small mb-3">
              Selecciona una plantilla. El sistema reemplazará los marcadores <code>{{variable}}</code>
              con los datos reales del proceso y descargará el documento listo para firmar.
            </p>

            <div class="mb-4">
              <label class="form-label fw-semibold">Seleccionar plantilla <span class="text-danger">*</span></label>
              <?php foreach($plantillas as $p): ?>
              <div class="form-check mb-2 p-3 border rounded <?= $p['tipo']==='proforma' ? 'border-primary bg-primary-subtle' : '' ?>">
                <input class="form-check-input" type="radio" name="plantilla_id"
                       id="plt_<?= $p['id'] ?>" value="<?= $p['id'] ?>"
                       <?= $p['tipo']==='proforma' ? 'checked' : '' ?> required>
                <label class="form-check-label w-100" for="plt_<?= $p['id'] ?>">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <strong><?= e($p['nombre']) ?></strong>
                      <span class="badge bg-primary-subtle text-primary ms-2 small"><?= e(ucfirst(str_replace('_',' ',$p['tipo']))) ?></span>
                      <?php if($p['descripcion']): ?>
                        <div class="small text-muted mt-1"><?= e($p['descripcion']) ?></div>
                      <?php endif; ?>
                    </div>
                    <small class="text-muted"><?= e($p['nombre_original']) ?></small>
                  </div>
                </label>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Preview de datos que se insertarán -->
            <div class="card bg-light border-0 mb-4">
              <div class="card-body p-3">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-1 text-info"></i>Datos que se insertarán</h6>
                <div class="row g-2 small">
                  <div class="col-6"><strong>N° Proceso:</strong><br><?= e($proceso['numero_proceso']) ?></div>
                  <div class="col-6"><strong>Institución:</strong><br><?= e($proceso['institucion_nombre'] ?? '—') ?></div>
                  <div class="col-6"><strong>Objeto:</strong><br><?= e(truncate($proceso['objeto_contratacion'],60)) ?></div>
                  <div class="col-6"><strong>Monto:</strong><br><?= money($proceso['monto_total'] ?? 0) ?></div>
                  <div class="col-6"><strong>Administrador:</strong><br><?= e($proceso['administrador_nombre'] ?? '—') ?></div>
                  <div class="col-6"><strong>Plazo:</strong><br><?= ($proceso['plazo_dias'] ?? 0) ?> días calendario</div>
                  <div class="col-6"><strong>Tu empresa:</strong><br><?= e($_SESSION['tenant_nombre'] ?? '') ?></div>
                  <div class="col-6"><strong>Representante:</strong><br><?= e($_SESSION['tenant_representante'] ?? '—') ?></div>
                </div>
              </div>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-download me-2"></i>Generar y Descargar .docx
              </button>
            </div>
            <p class="text-muted small text-center mt-2">
              El documento se descargará automáticamente y quedará guardado en el expediente digital del proceso.
            </p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>