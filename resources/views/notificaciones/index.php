<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2 text-primary"></i>Notificaciones</h4>
  <form method="POST" action="/notificaciones/todas-leidas"><?= csrf_field() ?>
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check-all me-1"></i>Marcar todas como leídas</button>
  </form>
</div>
<div class="card">
  <div class="list-group list-group-flush">
  <?php foreach($todas as $n): ?>
    <div class="list-group-item list-group-item-action p-3 <?= $n['estado']==='pendiente'?'bg-light':'' ?>">
      <div class="d-flex justify-content-between align-items-start">
        <div class="flex-grow-1">
          <?php
          $icon = match($n['tipo']) {
            'doc_vencimiento'=>'shield-exclamation text-danger',
            'pago'=>'cash-coin text-warning',
            'entrega'=>'calendar-check text-primary',
            'garantia'=>'shield text-info',
            default=>'bell text-secondary'
          };
          ?>
          <i class="bi bi-<?= $icon ?> me-2"></i>
          <strong class="small"><?= e($n['titulo']) ?></strong>
          <?php if($n['estado']==='pendiente'): ?>
          <span class="badge bg-primary ms-2" style="font-size:9px">Nuevo</span>
          <?php endif; ?>
          <p class="mb-1 mt-1 text-muted small"><?= e($n['mensaje']) ?></p>
          <small class="text-muted"><i class="bi bi-clock me-1"></i><?= formatDateTime($n['created_at']) ?></small>
        </div>
        <?php if($n['estado']==='pendiente'): ?>
        <form method="POST" action="/notificaciones/<?= $n['id'] ?>/leida" class="ms-2">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if(empty($todas)): ?>
    <div class="list-group-item text-center text-muted py-5">
      <i class="bi bi-bell-slash fs-2 d-block mb-2"></i>Sin notificaciones
    </div>
  <?php endif; ?>
  </div>
</div>
