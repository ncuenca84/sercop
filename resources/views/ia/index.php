<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-cpu me-2 text-primary"></i>Análisis con Inteligencia Artificial</h4>
    <p class="text-muted small mb-0">Sube un TDR, pliego u orden de compra y la IA extrae todos los datos automáticamente</p>
  </div>
</div>

<div class="row g-4">
  <!-- Upload -->
  <div class="col-lg-5">
    <div class="card border-primary">
      <div class="card-header bg-primary text-white"><i class="bi bi-cloud-upload me-2"></i>Analizar Documento</div>
      <div class="card-body">
        <?php if(empty(OPENROUTER_KEY)): ?>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <strong>API Key no configurada.</strong> Configure <code>OPENROUTER_KEY</code> en su archivo <code>.env</code>
        </div>
        <?php endif; ?>

        <form method="POST" action="/ia/analizar" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Tipo de Documento</label>
            <select name="tipo_doc" class="form-select">
              <option value="tdr">TDR (Términos de Referencia)</option>
              <option value="pliego">Pliego de Condiciones</option>
              <option value="orden_compra">Orden de Compra</option>
              <option value="especificaciones">Especificaciones Técnicas</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Documento (PDF, DOCX o TXT)</label>
            <div class="border-2 border-dashed rounded p-4 text-center" style="border-color:#2E86C1;background:#f0f8ff;cursor:pointer"
                 onclick="document.getElementById('fileIA').click()">
              <i class="bi bi-file-earmark-arrow-up fs-2 text-primary d-block mb-2"></i>
              <p class="mb-1 fw-semibold">Haz clic para seleccionar o arrastra el archivo</p>
              <small class="text-muted">PDF (texto), DOCX, TXT — máx. <?= UPLOAD_MAX_MB ?>MB</small>
              <div id="fileName" class="mt-2 text-success fw-semibold"></div>
            </div>
            <input type="file" id="fileIA" name="documento" class="d-none"
                   accept=".pdf,.doc,.docx,.txt" onchange="document.getElementById('fileName').textContent=this.files[0]?.name||''" required>
          </div>
          <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Tip:</strong> Para mejores resultados usa PDF de texto (no escaneado). El análisis puede tomar 15-30 segundos.
          </div>
          <button type="submit" class="btn btn-primary w-100" id="btnAnalizar">
            <i class="bi bi-magic me-2"></i>Analizar con IA
          </button>
        </form>
      </div>
    </div>

    <!-- Qué extrae la IA -->
    <div class="card mt-3">
      <div class="card-header"><i class="bi bi-stars me-2 text-warning"></i>¿Qué extrae la IA?</div>
      <ul class="list-group list-group-flush small">
        <?php foreach([
          'Institución contratante y RUC',
          'Objeto de la contratación',
          'Tipo de proceso (ínfima cuantía, etc.)',
          'Monto total del contrato',
          'Plazo de ejecución en días',
          'Administrador del contrato y contacto',
          'Lista de entregables con plazos',
          'Penalidades por incumplimiento',
          'Documentos habilitantes requeridos',
          'Forma de pago y condiciones',
          'Resumen ejecutivo del proceso',
        ] as $item): ?>
        <li class="list-group-item py-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= $item ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- Historial de análisis -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Análisis Recientes</div>
      <?php if(empty($analisis)): ?>
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-cpu fs-2 d-block mb-2"></i>
        Sube tu primer documento para comenzar el análisis con IA
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Tipo</th><th>Fecha</th><th>Modelo</th><th>Tokens</th><th>Estado</th><th></th></tr></thead>
          <tbody>
          <?php foreach($analisis as $a): ?>
            <tr>
              <td><span class="badge bg-primary"><?= strtoupper($a['tipo_documento']) ?></span></td>
              <td><small><?= formatDateTime($a['created_at']) ?></small></td>
              <td><small class="text-muted"><?= e(str_replace('anthropic/','', $a['modelo_usado'] ?? '')) ?></small></td>
              <td><small><?= number_format((int)$a['tokens_usados']) ?></small></td>
              <td><?= estadoBadge($a['estado']) ?></td>
              <td>
                <?php if($a['estado'] === 'completado'): ?>
                <a href="/ia/<?= $a['id'] ?>/aplicar" class="btn btn-sm btn-success">
                  <i class="bi bi-check2-circle me-1"></i>Aplicar
                </a>
                <?php elseif($a['proceso_id']): ?>
                <a href="/procesos/<?= $a['proceso_id'] ?>" class="btn btn-sm btn-outline-primary">Ver proceso</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.querySelector('form').addEventListener('submit',function(){
  const btn=document.getElementById('btnAnalizar');
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Analizando... (puede tomar 20-30 seg)';
});
</script>
