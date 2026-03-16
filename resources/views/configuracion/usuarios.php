<?php
// Mapa de permisos por rol para mostrar en UI
$rolesInfo = [
    'super_admin'  => ['label'=>'Super Admin',  'color'=>'danger',   'icono'=>'shield-lock',   'permisos'=>'Acceso total al sistema'],
    'admin'        => ['label'=>'Administrador','color'=>'primary',  'icono'=>'person-gear',   'permisos'=>'Procesos, documentos, facturas, pagos, usuarios, configuración, IA, reportes'],
    'gestor'       => ['label'=>'Gestor',        'color'=>'success',  'icono'=>'briefcase',     'permisos'=>'Procesos, documentos, IA, reportes (solo ver facturas)'],
    'contador'     => ['label'=>'Contador',      'color'=>'warning',  'icono'=>'calculator',    'permisos'=>'Facturas, pagos, reportes (solo ver procesos)'],
    'visualizador' => ['label'=>'Visualizador',  'color'=>'secondary','icono'=>'eye',           'permisos'=>'Solo lectura: procesos, instituciones, facturas, reportes'],
];
$usuarioActualId = Auth::id();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Usuarios del Sistema</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
    <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
  </button>
</div>

<!-- Tarjetas de referencia de roles -->
<div class="row g-2 mb-4">
  <?php foreach($rolesInfo as $rol => $info): ?>
  <div class="col-md col-6">
    <div class="card border-0 bg-light h-100 p-2">
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge bg-<?= $info['color'] ?>"><i class="bi bi-<?= $info['icono'] ?> me-1"></i><?= $info['label'] ?></span>
      </div>
      <small class="text-muted" style="font-size:.72rem"><?= $info['permisos'] ?></small>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabla de usuarios -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Nombre</th>
          <th>Email</th>
          <th>Rol</th>
          <th>Último acceso</th>
          <th>Estado</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($usuarios as $u): ?>
        <?php $ri = $rolesInfo[$u['rol']] ?? ['label'=>$u['rol'],'color'=>'secondary','icono'=>'person']; ?>
        <tr class="<?= $u['estado'] === 'inactivo' ? 'opacity-50' : '' ?>">
          <td class="fw-semibold">
            <?= e($u['nombre']) ?>
            <?php if((int)$u['id'] === (int)$usuarioActualId): ?>
              <span class="badge bg-info ms-1" style="font-size:.65rem">Tú</span>
            <?php endif; ?>
          </td>
          <td class="small"><?= e($u['email']) ?></td>
          <td>
            <span class="badge bg-<?= $ri['color'] ?>">
              <i class="bi bi-<?= $ri['icono'] ?> me-1"></i><?= $ri['label'] ?>
            </span>
          </td>
          <td class="small text-muted"><?= formatDateTime($u['ultimo_acceso']) ?></td>
          <td>
            <?php if($u['estado'] === 'activo'): ?>
              <span class="badge bg-success">Activo</span>
            <?php elseif($u['estado'] === 'inactivo'): ?>
              <span class="badge bg-secondary">Inactivo</span>
            <?php else: ?>
              <span class="badge bg-danger"><?= e($u['estado']) ?></span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <!-- Editar -->
              <button class="btn btn-outline-primary"
                      title="Editar usuario"
                      onclick="abrirEditar(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <!-- Toggle estado -->
              <?php if((int)$u['id'] !== (int)$usuarioActualId): ?>
              <form method="POST" action="/configuracion/usuarios/<?= (int)$u['id'] ?>/toggle" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-<?= $u['estado']==='activo'?'warning':'success' ?>"
                        title="<?= $u['estado']==='activo'?'Desactivar':'Activar' ?>">
                  <i class="bi bi-<?= $u['estado']==='activo'?'pause-circle':'play-circle' ?>"></i>
                </button>
              </form>
              <!-- Eliminar -->
              <form method="POST" action="/configuracion/usuarios/<?= (int)$u['id'] ?>/eliminar" class="d-inline"
                    onsubmit="return confirm('¿Eliminar usuario <?= e(addslashes($u['nombre'])) ?>? Esta acción no se puede deshacer.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($usuarios)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ─── Modal: Nuevo Usuario ─── -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" action="/configuracion/usuarios">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" required maxlength="150">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Confirmar Contraseña</label>
          <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Rol <span class="text-danger">*</span></label>
          <select name="rol" class="form-select" required onchange="mostrarDescRol(this,'desc-nuevo')">
            <option value="">-- Selecciona un rol --</option>
            <option value="admin">Administrador</option>
            <option value="gestor">Gestor de Contratos</option>
            <option value="contador">Contador / Finanzas</option>
            <option value="visualizador">Visualizador (solo lectura)</option>
          </select>
          <div id="desc-nuevo" class="form-text text-muted mt-1"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear Usuario</button>
      </div>
    </form>
  </div></div>
</div>

<!-- ─── Modal: Editar Usuario ─── -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Usuario</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" id="formEditar" action="">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
          <input type="text" name="nombre" id="edit-nombre" class="form-control" required maxlength="150">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" id="edit-email" class="form-control" required>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Rol <span class="text-danger">*</span></label>
          <select name="rol" id="edit-rol" class="form-select" required onchange="mostrarDescRol(this,'desc-editar')">
            <option value="admin">Administrador</option>
            <option value="gestor">Gestor de Contratos</option>
            <option value="contador">Contador / Finanzas</option>
            <option value="visualizador">Visualizador (solo lectura)</option>
          </select>
          <div id="desc-editar" class="form-text text-muted mt-1"></div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Estado</label>
          <select name="estado" id="edit-estado" class="form-select">
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Nueva Contraseña <small class="text-muted fw-normal">(dejar vacío para no cambiar)</small></label>
          <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password"
                 placeholder="Mínimo 8 caracteres">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
      </div>
    </form>
  </div></div>
</div>

<script>
const rolesDesc = {
  admin:        'Acceso total excepto super administrador del sistema.',
  gestor:       'Gestiona procesos, documentos e IA. Ve facturas pero no las edita.',
  contador:     'Gestiona facturas y pagos. Solo ve procesos.',
  visualizador: 'Solo lectura en procesos, instituciones, facturas y reportes.',
};

function mostrarDescRol(sel, descId) {
  document.getElementById(descId).textContent = rolesDesc[sel.value] || '';
}

function abrirEditar(u) {
  document.getElementById('formEditar').action = '/configuracion/usuarios/' + u.id;
  document.getElementById('edit-nombre').value = u.nombre;
  document.getElementById('edit-email').value  = u.email;
  document.getElementById('edit-rol').value    = u.rol;
  document.getElementById('edit-estado').value = u.estado;
  mostrarDescRol(document.getElementById('edit-rol'), 'desc-editar');
  new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
}
</script>
