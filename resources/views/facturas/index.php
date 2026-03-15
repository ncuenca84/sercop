<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-1"><i class="bi bi-receipt me-2 text-success"></i>Facturas y Pagos</h4>
  <p class="text-muted small mb-0">Control de facturación y seguimiento de cobros</p></div>
</div>

<!-- Pendientes de cobro -->
<?php if(!empty($pendientes)): ?>
<div class="card border-warning mb-4">
  <div class="card-header bg-warning-subtle fw-semibold">
    <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Facturas Pendientes de Cobro (<?= count($pendientes) ?>)
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>N° Factura</th><th>Proceso</th><th>Institución</th><th>Monto</th><th>Días transcurridos</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach($pendientes as $f): ?>
        <tr class="<?= $f['dias_transcurridos']>60?'table-danger':($f['dias_transcurridos']>30?'table-warning':'') ?>">
          <td class="fw-semibold small"><?= e($f['numero_sri']) ?></td>
          <td><a href="/procesos/<?= $f['proceso_id'] ?>" class="small text-decoration-none"><?= e($f['numero_proceso']) ?></a></td>
          <td class="small"><?= e(truncate($f['institucion_nombre'],30)) ?></td>
          <td class="fw-bold"><?= money($f['monto_total']) ?></td>
          <td>
            <span class="badge <?= $f['dias_transcurridos']>60?'bg-danger':($f['dias_transcurridos']>30?'bg-warning text-dark':'bg-secondary') ?>">
              <?= $f['dias_transcurridos'] ?> días
            </span>
          </td>
          <td><?= estadoBadge($f['estado']) ?></td>
          <td>
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalPago"
                    data-factura-id="<?= $f['id'] ?>" data-monto="<?= $f['monto_neto'] ?? $f['monto_total'] ?>">
              <i class="bi bi-check-circle me-1"></i>Registrar Cobro
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Historial completo -->
<div class="card">
  <div class="card-header fw-semibold">Historial de Facturas</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>N° SRI</th><th>Proceso</th><th>Institución</th><th>F. Emisión</th>
        <th>Subtotal</th><th>IVA</th><th>Total</th><th>Neto</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach($todas['data'] as $f): ?>
        <tr>
          <td class="small fw-semibold"><?= e($f['numero_sri']) ?></td>
          <td><a href="/procesos/<?= $f['proceso_id'] ?>" class="small text-decoration-none"><?= e($f['numero_proceso']) ?></a></td>
          <td class="small"><?= e(truncate($f['inst'],28)) ?></td>
          <td class="small"><?= formatDate($f['fecha_emision']) ?></td>
          <td class="small"><?= money($f['monto_subtotal']) ?></td>
          <td class="small"><?= money($f['monto_iva']) ?></td>
          <td class="fw-semibold small"><?= money($f['monto_total']) ?></td>
          <td class="small text-success fw-bold"><?= money($f['monto_neto']) ?></td>
          <td><?= estadoBadge($f['estado']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($todas['data'])): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No hay facturas registradas</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($todas['last_page']>1): ?>
  <div class="card-footer"><?= paginationLinks($todas, '/facturas') ?></div>
  <?php endif; ?>
</div>

<!-- Modal Pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-check-circle-fill text-success me-2"></i>Registrar Cobro</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="formPago" method="POST" action="">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Fecha de Cobro *</label>
          <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-6"><label class="form-label">Monto Cobrado *</label>
          <div class="input-group"><span class="input-group-text">$</span>
          <input type="number" step="0.01" name="monto_pagado" id="montoCobroPago" class="form-control" required></div></div>
        <div class="col-6">
          <label class="form-label">Tipo de Pago</label>
          <select name="tipo_pago" class="form-select">
            <option value="transferencia">Transferencia Bancaria / SPI</option>
            <option value="cheque">Cheque</option>
            <option value="caja_fiscal">Caja Fiscal</option>
          </select>
        </div>
        <div class="col-6"><label class="form-label">N° Referencia / SPI</label>
          <input type="text" name="referencia" class="form-control" placeholder="Ej: SPI-20250315-001"></div>
        <div class="col-12"><label class="form-label">Notas</label>
          <textarea name="notas" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Confirmar Cobro</button>
      </div>
    </form>
  </div></div>
</div>
<script>
document.getElementById('modalPago').addEventListener('show.bs.modal',function(e){
  const b=e.relatedTarget;
  document.getElementById('formPago').action='/facturas/'+b.dataset.facturaId+'/pago';
  document.getElementById('montoCobroPago').value=b.dataset.monto||'';
});
</script>
