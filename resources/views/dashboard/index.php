<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-1 fw-bold">Dashboard</h4>
    <p class="text-muted small mb-0">Bienvenido, <?= e(auth()['nombre']) ?> — <?= date('l, d \d\e F \d\e Y') ?></p>
  </div>
  <a href="/procesos/crear" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i>Nuevo Proceso
  </a>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#1B4F72,#2E86C1)">
      <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
      <div class="stat-value"><?= (int)($stats['total_activos'] ?? 0) ?></div>
      <div class="stat-label">Procesos Activos</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#117A65,#1abc9c)">
      <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
      <div class="stat-value"><?= money($stats['cobrado_anio'] ?? 0) ?></div>
      <div class="stat-label">Cobrado <?= date('Y') ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#8E44AD,#9b59b6)">
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-value"><?= money($stats['monto_pendiente'] ?? 0) ?></div>
      <div class="stat-label">Por Cobrar</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,<?= ($stats['por_vencer_docs']??0)>0?'#c0392b,#e74c3c':'#117A65,#1abc9c' ?>)">
      <div class="stat-icon"><i class="bi bi-shield-exclamation"></i></div>
      <div class="stat-value"><?= (int)($stats['por_vencer_docs'] ?? 0) ?></div>
      <div class="stat-label">Docs por Vencer</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Procesos recientes -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-folder2 me-2 text-primary"></i>Procesos Activos</span>
        <a href="/procesos" class="btn btn-sm btn-outline-primary">Ver todos</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Proceso</th><th>Institución</th><th>Monto</th><th>Estado</th><th>%</th></tr></thead>
          <tbody>
          <?php foreach ($procesos as $p): ?>
            <tr onclick="location.href='/procesos/<?= $p['id'] ?>'" style="cursor:pointer">
              <td>
                <div class="fw-semibold small"><?= e($p['numero_proceso']) ?></div>
                <div class="text-muted" style="font-size:11px"><?= e(truncate($p['objeto_contratacion'], 40)) ?></div>
              </td>
              <td class="small"><?= e(truncate($p['inst'] ?? '', 25)) ?></td>
              <td class="small fw-semibold"><?= money($p['monto_total']) ?></td>
              <td><?= estadoBadge($p['estado']) ?></td>
              <td>
                <div class="progress" style="width:60px">
                  <div class="progress-bar bg-success" style="width:<?= $p['porcentaje_avance'] ?>%"></div>
                </div>
                <small class="text-muted"><?= $p['porcentaje_avance'] ?>%</small>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($procesos)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">
              <i class="bi bi-inbox fs-3 d-block mb-2"></i>
              No hay procesos activos. <a href="/procesos/crear">Crear el primero</a>
            </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Alertas -->
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Alertas de Documentos</div>
      <div class="list-group list-group-flush">
        <?php foreach ($alertas as $a): ?>
          <div class="list-group-item alert-vencimiento p-3">
            <div class="d-flex justify-content-between">
              <strong class="small"><?= e($a['nombre']) ?></strong>
              <?= estadoBadge($a['estado']) ?>
            </div>
            <small class="text-muted">Vence: <?= formatDate($a['fecha_vencimiento']) ?>
              (<?= daysUntil($a['fecha_vencimiento']) ?> días)</small>
          </div>
        <?php endforeach; ?>
        <?php if (empty($alertas)): ?>
          <div class="list-group-item text-center text-success py-3">
            <i class="bi bi-shield-check me-1"></i>Todos los documentos vigentes
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer text-end">
        <a href="/documentos-habilitantes" class="btn btn-sm btn-outline-secondary">Gestionar docs</a>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-hourglass me-2 text-danger"></i>Cobros Pendientes</div>
      <div class="list-group list-group-flush">
        <?php foreach (array_slice($pendientes, 0, 4) as $f): ?>
          <div class="list-group-item p-3">
            <div class="d-flex justify-content-between">
              <strong class="small"><?= e($f['numero_sri']) ?></strong>
              <span class="badge bg-warning text-dark"><?= $f['dias_transcurridos'] ?> días</span>
            </div>
            <small class="text-muted"><?= e(truncate($f['institucion_nombre'], 30)) ?> — <?= money($f['monto_total']) ?></small>
          </div>
        <?php endforeach; ?>
        <?php if (empty($pendientes)): ?>
          <div class="list-group-item text-center text-success py-3">
            <i class="bi bi-check-all me-1"></i>Sin cobros pendientes
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer text-end">
        <a href="/facturas" class="btn btn-sm btn-outline-secondary">Ver facturas</a>
      </div>
    </div>
  </div>
</div>
