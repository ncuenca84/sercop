<h4 class="fw-bold mb-4"><i class="bi bi-gear me-2 text-primary"></i>Configuración del Sistema</h4>

<div class="row g-4">
  <!-- DATOS DE LA EMPRESA -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-building me-2 text-primary"></i>Datos de Mi Empresa
        <small class="text-muted fw-normal ms-2">— Usados automáticamente en proformas y documentos Word</small>
      </div>
      <form method="POST" action="/configuracion">
        <?= csrf_field() ?>
        <div class="card-body row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
            <input type="text" name="nombre" class="form-control" value="<?= e($tenant['nombre']??'') ?>" required>
            <div class="form-text">Nombre legal tal como aparece en el RUC</div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">RUC</label>
            <input type="text" name="ruc" class="form-control" value="<?= e($tenant['ruc']??'') ?>" maxlength="13" placeholder="1793190705001">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Representante Legal</label>
            <input type="text" name="representante_legal" class="form-control" value="<?= e($tenant['representante_legal']??'') ?>" placeholder="Nixon Miguel Cuenca Jima">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Tipo de Contribuyente</label>
            <select name="tipo_contribuyente" class="form-select">
              <?php foreach(['Persona Natural','Sociedad','Persona Natural Obligada a Llevar Contabilidad'] as $t): ?>
              <option value="<?= $t ?>" <?= ($tenant['tipo_contribuyente']??'Sociedad')===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Régimen</label>
            <select name="regimen_tributario" class="form-select">
              <?php foreach(['RIMPE','General','Especial'] as $r): ?>
              <option value="<?= $r ?>" <?= ($tenant['regimen_tributario']??'RIMPE')===$r?'selected':'' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Ciudad</label>
            <input type="text" name="ciudad" class="form-control" value="<?= e($tenant['ciudad']??'Quito') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Teléfono</label>
            <input type="text" name="telefono" class="form-control" value="<?= e($tenant['telefono']??'') ?>" placeholder="+593 98 485 3489">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($tenant['email']??'') ?>" placeholder="info@tuempresa.ec">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Dirección</label>
            <input type="text" name="direccion" class="form-control" value="<?= e($tenant['direccion']??'') ?>" placeholder="Av. Colón y 9 de Octubre">
          </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
          <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Marcador en plantillas: <code>{{proveedor.ruc}}</code>, <code>{{proveedor.representante}}</code>, etc.</small>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar Datos</button>
        </div>
      </form>
    </div>

    <!-- PLANTILLAS WORD -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-file-earmark-word me-2 text-success"></i>Mis Plantillas Word (Proformas y Documentos)
      </div>
      <div class="card-body">
        <?php
        try {
          $plantillasDocx = \DB::select(
            "SELECT * FROM plantillas_docx WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY tipo ASC, nombre ASC",
            [\tenantId()]
          );
        } catch(\Throwable $e) { $plantillasDocx = []; }
        ?>
        <?php if(empty($plantillasDocx)): ?>
          <p class="text-muted small mb-3">
            No tienes plantillas Word aún. Abre tu proforma en Word, coloca marcadores como
            <code>{{proceso.numero}}</code>, <code>{{institucion.nombre}}</code>,
            <code>{{proveedor.ruc}}</code> donde quieres los datos, guarda como .docx y súbela aquí.
          </p>
        <?php else: ?>
          <div class="table-responsive mb-3">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light"><tr><th>Nombre</th><th>Tipo</th><th>Generadas</th><th></th></tr></thead>
              <tbody>
              <?php foreach($plantillasDocx as $p): ?>
              <tr>
                <td><strong><?= e($p['nombre']) ?></strong><br><small class="text-muted"><?= e($p['nombre_original']) ?></small></td>
                <td><span class="badge bg-success-subtle text-success"><?= ucfirst(str_replace('_',' ',$p['tipo'])) ?></span></td>
                <td><span class="badge bg-secondary"><?= (int)$p['usos'] ?></span></td>
                <td>
                  <form method="POST" action="/plantillas-docx/<?= $p['id'] ?>/eliminar" class="d-inline" onsubmit="return confirm('¿Eliminar?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalSubirPlantilla">
          <i class="bi bi-upload me-1"></i>Subir Plantilla .docx
        </button>
        <a href="/plantillas-docx" class="btn btn-outline-secondary btn-sm ms-1">
          <i class="bi bi-code-slash me-1"></i>Ver todos los marcadores
        </a>
      </div>
    </div>
  </div>

  <!-- SIDEBAR -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">Información del Plan</div>
      <div class="card-body">
        <p class="mb-1"><strong>Plan:</strong> <span class="badge bg-primary"><?= strtoupper($tenant['plan']??'basico') ?></span></p>
        <p><strong>Estado:</strong> <?= estadoBadge($tenant['estado']??'activo') ?></p>
        <hr>
        <p class="small text-muted mb-1"><strong>Cron Job (alertas automáticas)</strong></p>
        <code class="small d-block bg-light p-2 rounded" style="word-break:break-all">
          0 8 * * * curl -s "<?= APP_URL ?>/cron/run?token=<?= md5(APP_KEY.date('Y-m-d')) ?>"
        </code>
        <p class="small text-muted mt-1">Configurar en cPanel → Cron Jobs (8:00 AM diariamente)</p>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Accesos Rápidos</div>
      <div class="list-group list-group-flush">
        <a href="/configuracion/usuarios" class="list-group-item list-group-item-action">
          <i class="bi bi-people me-2 text-primary"></i>Gestionar Usuarios
        </a>
        <a href="/configuracion/proforma" class="list-group-item list-group-item-action">
          <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Configurar Proformas
        </a>
        <a href="/configuracion/plantillas" class="list-group-item list-group-item-action">
          <i class="bi bi-file-earmark-code me-2 text-secondary"></i>Plantillas HTML
        </a>
      </div>
    </div>
  </div>
</div>

<!-- MODAL SUBIR PLANTILLA -->
<div class="modal fade" id="modalSubirPlantilla" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-file-earmark-word me-2 text-success"></i>Subir Plantilla Word</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" action="/plantillas-docx" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="alert alert-info small mb-3">
          <i class="bi bi-lightbulb me-1"></i>
          Escribe marcadores en tu plantilla Word: <code>{{proceso.numero}}</code>, <code>{{institucion.nombre}}</code>,
          <code>{{proveedor.representante}}</code>, <code>{{fecha.actual}}</code>, etc.
          El sistema los reemplazará con los datos reales al generar.
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Archivo .docx <span class="text-danger">*</span></label>
          <input type="file" name="plantilla" class="form-control" accept=".docx" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" placeholder="Proforma Exxalink 2026" required>
        </div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label fw-semibold">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="proforma">Proforma</option>
              <option value="aceptacion_oc">Aceptación OC</option>
              <option value="informe_tecnico">Informe Técnico</option>
              <option value="acta_entrega">Acta de Entrega</option>
              <option value="solicitud_pago">Solicitud de Pago</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Descripción</label>
            <input type="text" name="descripcion" class="form-control" placeholder="Opcional">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-upload me-1"></i>Subir</button>
      </div>
    </form>
  </div></div>
</div>