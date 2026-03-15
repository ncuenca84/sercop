<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-building me-2"></i>Instituciones</h4>
    <p class="text-muted mb-0 small">Entidades contratantes registradas</p>
  </div>
  <?php if(can('instituciones.*')): ?>
  <a href="/instituciones/crear" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>Nueva Institución
  </a>
  <?php endif; ?>
</div>

<?php if(empty($instituciones)): ?>
<div class="alert alert-info">No hay instituciones registradas aún.</div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Institución</th>
          <th>RUC</th>
          <th class="text-center">Procesos</th>
          <th class="text-end">Monto Total</th>
          <th class="text-end">Saldo Pendiente</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($instituciones as $inst): ?>
        <tr>
          <td>
            <a href="/instituciones/<?= $inst['id'] ?>" class="fw-semibold text-decoration-none">
              <?= e($inst['nombre']) ?>
            </a>
          </td>
          <td class="text-muted small"><?= e($inst['ruc'] ?? '—') ?></td>
          <td class="text-center">
            <span class="badge bg-secondary"><?= (int)($inst['total_procesos'] ?? 0) ?></span>
          </td>
          <td class="text-end small">
            $<?= number_format((float)($inst['total_pagado'] ?? 0), 2) ?>
          </td>
          <td class="text-end small">
            $<?= number_format((float)($inst['saldo_pendiente'] ?? 0), 2) ?>
          </td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
              <?php if(can('instituciones.*')): ?>
              <a href="/instituciones/<?= $inst['id'] ?>/editar"
                 class="btn btn-sm btn-outline-primary" title="Editar">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="/instituciones/<?= $inst['id'] ?>/eliminar"
                    onsubmit="return confirm('¿Eliminar institución «<?= e(addslashes($inst['nombre'])) ?>»? Esta acción no se puede deshacer.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
