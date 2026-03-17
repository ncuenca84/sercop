<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-code me-2 text-primary"></i>Plantillas de Documentos</h4>
    <p class="text-muted small mb-0">Personalice los documentos generados automáticamente por el sistema</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPlantilla">
    <i class="bi bi-plus me-1"></i>Nueva Plantilla
  </button>
</div>

<div class="row g-3 mb-4">
  <?php
  $tiposStd = [
    'informe_tecnico'    => ['📄','Informe Técnico de Entrega','bg-info'],
    'garantia_tecnica'   => ['🛡️','Certificado de Garantía Técnica','bg-warning'],
    'acta_parcial'       => ['📋','Acta de Entrega Parcial','bg-secondary'],
    'acta_definitiva'    => ['✅','Acta Entrega Definitiva','bg-success'],
    'solicitud_pago'     => ['💰','Solicitud de Pago','bg-success'],
  ];
  $tiposConPlantilla = array_column($plantillas,'tipo');
  foreach($tiposStd as $tipo=>[$emoji,$label,$color]): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100 <?= in_array($tipo,$tiposConPlantilla)?'border-success':'' ?>">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <span class="fs-4"><?= $emoji ?></span>
          <?php if(in_array($tipo,$tiposConPlantilla)): ?>
            <span class="badge bg-success">✓ Personalizada</span>
          <?php else: ?>
            <span class="badge bg-secondary">Por defecto</span>
          <?php endif; ?>
        </div>
        <h6 class="fw-bold"><?= $label ?></h6>
        <small class="text-muted">Variables: <code>{{proceso.numero}}</code>, <code>{{institucion.nombre}}</code>, etc.</small>
      </div>
      <div class="card-footer p-2 d-flex gap-1">
        <a href="/procesos" class="btn btn-sm btn-outline-secondary flex-fill" title="Previsualizar">
          <i class="bi bi-eye me-1"></i>Preview
        </a>
        <button class="btn btn-sm btn-outline-primary flex-fill"
                data-bs-toggle="modal" data-bs-target="#modalPlantilla"
                data-tipo="<?= $tipo ?>" data-label="<?= $label ?>">
          <i class="bi bi-pencil me-1"></i>Editar
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Variables disponibles -->
<div class="card">
  <div class="card-header fw-semibold"><i class="bi bi-braces me-2"></i>Variables Disponibles en Plantillas</div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach([
        'Proceso' => ['{{proceso.numero}}','{{proceso.objeto}}','{{proceso.tipo}}','{{proceso.monto}}','{{proceso.plazo}}','{{proceso.fecha_inicio}}','{{proceso.fecha_fin}}','{{proceso.garantia_dias}}'],
        'Institución' => ['{{institucion.nombre}}','{{institucion.ruc}}','{{institucion.ciudad}}','{{institucion.administrador}}','{{institucion.cargo_admin}}','{{institucion.email_admin}}'],
        'Proveedor' => ['{{proveedor.razon_social}}','{{proveedor.ruc}}','{{proveedor.representante}}','{{proveedor.ciudad}}'],
        'Fecha / Sistema' => ['{{fecha.actual}}','{{fecha.actual_corta}}','{{anio.actual}}','{{ciudad.actual}}','{{usuario.nombre}}'],
      ] as $grupo => $vars): ?>
      <div class="col-md-3">
        <h6 class="fw-semibold text-muted small text-uppercase"><?= $grupo ?></h6>
        <?php foreach($vars as $v): ?>
          <code class="d-block small mb-1" style="font-size:11px"><?= $v ?></code>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Modal crear/editar plantilla -->
<div class="modal fade" id="modalPlantilla" tabindex="-1">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-file-earmark-code me-2"></i>Editor de Plantilla</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" action="/configuracion/plantillas">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre de la Plantilla</label>
          <input type="text" name="nombre" id="plantillaNombre" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tipo de Documento</label>
          <select name="tipo" id="plantillaTipo" class="form-select" required>
            <?php foreach($tiposStd as $k=>[$e,$l,$c]): ?>
            <option value="<?= $k ?>"><?= $e ?> <?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Contenido HTML <small class="text-muted">(use las variables de arriba)</small></label>
          <textarea name="contenido_html" class="form-control font-monospace"
                    rows="16" required
                    placeholder="<h1>MI DOCUMENTO</h1>&#10;<p>Estimados señores de {{institucion.nombre}}...</p>"></textarea>
          <div class="form-text">El sistema añade automáticamente el encabezado y pie de página oficial.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar Plantilla</button>
      </div>
    </form>
  </div></div>
</div>

<script>
document.getElementById('modalPlantilla').addEventListener('show.bs.modal', function(e) {
  const b = e.relatedTarget;
  if (b && b.dataset.tipo) {
    document.getElementById('plantillaTipo').value  = b.dataset.tipo;
    document.getElementById('plantillaNombre').value = b.dataset.label || '';
  }
});
</script>
