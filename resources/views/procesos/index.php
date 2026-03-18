<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-1"><i class="bi bi-folder2-open me-2 text-primary"></i>Procesos y Contratos</h4>
  <p class="text-muted small mb-0">Gestión completa del ciclo de contratación pública</p></div>
  <?php if(can('procesos.*')): ?>
  <div class="d-flex gap-2">
    <a href="/ia" class="btn btn-outline-primary"><i class="bi bi-cpu me-1"></i>Analizar con IA</a>
    <a href="/procesos/crear" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nuevo Proceso</a>
  </div>
  <?php endif; ?>
</div>

<!-- FILTROS -->
<div class="card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="buscar" class="form-control form-control-sm"
               placeholder="🔍 Buscar por número o descripción..." value="<?= e($filtros['buscar'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <select name="estado" class="form-select form-select-sm">
          <option value="">Todos los estados</option>
          <?php foreach(['en_proceso','adjudicado','en_ejecucion','entregado_provisional','entregado_definitivo','facturado','pagado','cerrado','cancelado'] as $est): ?>
          <option value="<?= $est ?>" <?= ($filtros['estado']??'')===$est?'selected':'' ?>><?= ucwords(str_replace('_',' ',$est)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="institucion" class="form-select form-select-sm">
          <option value="">Todas las instituciones</option>
          <?php foreach($instituciones as $i): ?>
          <option value="<?= $i['id'] ?>" <?= ($filtros['institucion']??'')==$i['id']?'selected':'' ?>><?= e($i['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filtrar</button>
        <a href="/procesos" class="btn btn-outline-secondary btn-sm">Limpiar</a>
      </div>
    </form>
  </div>
</div>

<!-- TABLA -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th>N° Proceso</th><th>Objeto / Descripción</th><th>Institución</th>
        <th>Monto</th><th>Plazo</th><th>Estado</th><th>Avance</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach($paginator['data'] as $p): ?>
        <tr>
          <td><a href="/procesos/<?= $p['id'] ?>" class="fw-semibold text-decoration-none text-primary"><?= e($p['numero_proceso']) ?></a>
              <br><small class="text-muted"><?= formatDate($p['fecha_inicio']) ?></small></td>
          <td><small><?= e(truncate($p['objeto_contratacion'], 60)) ?></small></td>
          <td><small><?= e(truncate($p['institucion_nombre'], 35)) ?></small></td>
          <td class="fw-semibold small"><?= money($p['monto_total']) ?></td>
          <td><small><?= $p['plazo_dias'] ?> días</small></td>
          <td><?= estadoBadge($p['estado']) ?></td>
          <td>
            <div class="progress" style="width:70px;height:6px">
              <div class="progress-bar" style="width:<?= $p['porcentaje_avance'] ?>%;background:<?= $p['porcentaje_avance']>=100?'#27ae60':'#2E86C1' ?>"></div>
            </div>
            <small class="text-muted"><?= $p['porcentaje_avance'] ?>%</small>
          </td>
          <td>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
              </button>
              <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="/procesos/<?= $p['id'] ?>"><i class="bi bi-eye me-2"></i>Ver expediente</a></li>
                <?php if(can('procesos.*')): ?>
                <li><a class="dropdown-item" href="/procesos/<?= $p['id'] ?>/editar"><i class="bi bi-pencil me-2"></i>Editar</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#"
                       onclick="if(confirm('¿Eliminar este proceso?')){document.getElementById('del-<?= $p['id'] ?>').submit()}">
                  <i class="bi bi-trash me-2"></i>Eliminar</a></li>
                <form id="del-<?= $p['id'] ?>" method="POST" action="/procesos/<?= $p['id'] ?>/eliminar" style="display:none">
                  <?= csrf_field() ?>
                </form>
                <?php endif; ?>
              </ul>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($paginator['data'])): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">
          <i class="bi bi-inbox fs-2 d-block mb-2"></i>No hay procesos. <a href="/procesos/crear">Crear el primero</a>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($paginator['last_page'] > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">Mostrando <?= $paginator['from'] ?>–<?= $paginator['to'] ?> de <?= $paginator['total'] ?></small>
    <?= paginationLinks($paginator, '/procesos') ?>
  </div>
  <?php endif; ?>
</div>
