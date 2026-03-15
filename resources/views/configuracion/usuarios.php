<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Usuarios del Sistema</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
    <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
  </button>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Último acceso</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach($usuarios as $u): ?>
        <tr>
          <td class="fw-semibold"><?= e($u['nombre']) ?></td>
          <td class="small"><?= e($u['email']) ?></td>
          <td><?= estadoBadge($u['rol']) ?></td>
          <td class="small text-muted"><?= formatDateTime($u['ultimo_acceso']) ?></td>
          <td><?= estadoBadge($u['estado']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nuevo Usuario</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="/configuracion/usuarios">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">Nombre Completo *</label>
          <input type="text" name="nombre" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Contraseña *</label>
          <input type="password" name="password" class="form-control" required minlength="8"></div>
        <div class="col-md-6"><label class="form-label">Confirmar Contraseña</label>
          <input type="password" name="password_confirmation" class="form-control"></div>
        <div class="col-12"><label class="form-label">Rol *</label>
          <select name="rol" class="form-select" required>
            <option value="gestor">Gestor de Contratos</option>
            <option value="contador">Contador / Finanzas</option>
            <option value="visualizador">Visualizador (solo lectura)</option>
            <option value="admin">Administrador</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear Usuario</button>
      </div>
    </form>
  </div></div>
</div>
