<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><i class="bi bi-file-earmark-word text-primary me-2"></i>Plantillas Word</h4>
    <small class="text-muted">Sube tus plantillas .docx con marcadores <code>{{variable}}</code> para generación automática</small>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSubir">
    <i class="bi bi-upload me-1"></i>Subir Plantilla
  </button>
</div>


<!-- TABLA DE PLANTILLAS -->
<?php if(empty($plantillas)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5">
    <i class="bi bi-file-earmark-word fs-1 text-muted"></i>
    <p class="mt-3 text-muted">No tienes plantillas Word aún.</p>
    <p class="small text-muted">Sube tu proforma en .docx con marcadores como <code>{{proceso.numero}}</code>, <code>{{institucion.nombre}}</code>, etc.</p>
    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalSubir">
      <i class="bi bi-upload me-1"></i>Subir primera plantilla
    </button>
  </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Nombre</th>
          <th>Tipo</th>
          <th>Archivo original</th>
          <th>Usos</th>
          <th>Subida</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($plantillas as $p): ?>
        <tr>
          <td>
            <strong><?= e($p['nombre']) ?></strong>
            <?php if($p['descripcion']): ?>
              <div class="small text-muted"><?= e($p['descripcion']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-primary-subtle text-primary"><?= e(ucfirst(str_replace('_',' ',$p['tipo']))) ?></span></td>
          <td class="small text-muted"><?= e($p['nombre_original']) ?></td>
          <td><span class="badge bg-secondary"><?= (int)$p['usos'] ?></span></td>
          <td class="small text-muted"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-info me-1"
                    onclick="verMarcadores()"
                    title="Ver marcadores disponibles">
              <i class="bi bi-code-slash"></i>
            </button>
            <form method="POST" action="/plantillas-docx/<?= $p['id'] ?>/eliminar" class="d-inline"
                  onsubmit="return confirm('¿Eliminar esta plantilla?')">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- REFERENCIA DE MARCADORES -->
<div class="card border-0 shadow-sm mt-4" id="cardMarcadores">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-code-slash me-2 text-primary"></i>Marcadores disponibles para tu plantilla</strong>
    <small class="text-muted">Copia y pega estos marcadores exactamente en tu documento Word</small>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php
      $grupos = [
        '?? Proceso'      => ['proceso.numero','proceso.objeto','proceso.tipo','proceso.monto','proceso.monto_num','proceso.iva','proceso.total_iva','proceso.plazo','proceso.fecha_inicio','proceso.descripcion'],
        '??? Institución'  => ['institucion.nombre','institucion.ruc','institucion.ciudad','institucion.administrador','institucion.cargo_admin','institucion.email_admin','institucion.telefono','institucion.direccion'],
        '?? Tu Empresa'   => ['proveedor.razon_social','proveedor.ruc','proveedor.representante','proveedor.ciudad','proveedor.direccion','proveedor.telefono','proveedor.email','proveedor.tipo_contrib','proveedor.regimen'],
        '?? Fechas'       => ['fecha.actual','fecha.actual_corta','anio.actual','ciudad.actual'],
      ];
      $marcadores = \Services\DocxTemplateService::marcadores();
      foreach($grupos as $grupo => $keys):
      ?>
      <div class="col-md-6">
        <h6 class="fw-semibold mb-2"><?= $grupo ?></h6>
        <table class="table table-sm table-bordered mb-0">
          <tbody>
            <?php foreach($keys as $k):
              $marcador = '{{'.$k.'}}';
              $desc = $marcadores[$marcador] ?? $k;
            ?>
            <tr>
              <td style="width:45%">
                <code class="text-primary small user-select-all" onclick="copiar('<?= e($marcador) ?>', this)"
                      style="cursor:pointer" title="Click para copiar">
                  <?= e($marcador) ?>
                </code>
              </td>
              <td class="small text-muted"><?= e($desc) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="alert alert-info mt-3 mb-0">
      <i class="bi bi-lightbulb me-2"></i>
      <strong>Cómo usar:</strong> Abre tu proforma en Word, escribe el marcador exactamente como aparece (incluyendo los <code>{{</code> y <code>}}</code>).
      El sistema buscará y reemplazará ese texto con los datos reales del proceso al generar.
      <br><strong>Tip:</strong> Si el marcador está en negrita en tu Word, el texto reemplazado también saldrá en negrita.
    </div>
  </div>
</div>

<!-- MODAL SUBIR PLANTILLA -->
<div class="modal fade" id="modalSubir" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Subir Plantilla Word</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/plantillas-docx" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Archivo .docx <span class="text-danger">*</span></label>
            <input type="file" name="plantilla" class="form-control" accept=".docx" required>
            <div class="form-text">Solo archivos Word (.docx). Máximo 20MB.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre de la plantilla <span class="text-danger">*</span></label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej: Proforma Exxalink 2026" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="proforma">Proforma / Oferta Económica</option>
              <option value="aceptacion_oc">Aceptación de Orden de Compra</option>
              <option value="informe_tecnico">Informe Técnico</option>
              <option value="acta_entrega">Acta de Entrega</option>
              <option value="solicitud_pago">Solicitud de Pago</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label">Descripción (opcional)</label>
            <input type="text" name="descripcion" class="form-control" placeholder="Ej: Plantilla principal para ínfima cuantía">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-upload me-1"></i>Subir Plantilla
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function copiar(texto, el) {
  navigator.clipboard.writeText(texto).then(() => {
    const orig = el.textContent;
    el.textContent = '? Copiado';
    el.classList.add('text-success');
    setTimeout(() => { el.textContent = orig; el.classList.remove('text-success'); }, 1200);
  });
}
function verMarcadores() {
  document.getElementById('cardMarcadores').scrollIntoView({behavior:'smooth'});
}
</script>