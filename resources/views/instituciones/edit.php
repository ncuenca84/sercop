<div class="mb-4">
  <a href="/instituciones" class="btn btn-sm btn-outline-secondary mb-2">
    <i class="bi bi-arrow-left me-1"></i>Volver
  </a>
  <h4 class="fw-bold mb-1">
    <?= isset($inst) ? 'Editar Institución' : 'Nueva Institución' ?>
  </h4>
</div>

<div class="card shadow-sm" style="max-width:700px">
  <div class="card-body">
    <form method="POST" action="<?= isset($inst) ? '/instituciones/' . $inst['id'] : '/instituciones' ?>">
      <?= csrf_field() ?>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre de la Institución *</label>
          <input type="text" name="nombre" class="form-control"
                 value="<?= e($inst['nombre'] ?? '') ?>" required maxlength="300"
                 placeholder="Ej: GOBIERNO AUTÓNOMO DESCENTRALIZADO...">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">RUC *</label>
          <input type="text" name="ruc" class="form-control"
                 value="<?= e($inst['ruc'] ?? '') ?>" required
                 maxlength="13" minlength="13" pattern="\d{13}"
                 placeholder="0000000000001">
          <div class="form-text">13 dígitos exactos</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Tipo</label>
          <select name="tipo" class="form-select">
            <?php
            $tipos = ['GAD Municipal','GAD Parroquial','GAD Provincial','Ministerio','Secretaría','Empresa Pública','Otro'];
            $tipoActual = $inst['tipo'] ?? '';
            foreach($tipos as $t):
            ?>
            <option value="<?= $t ?>" <?= $tipoActual === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Ciudad</label>
          <input type="text" name="ciudad" class="form-control"
                 value="<?= e($inst['ciudad'] ?? '') ?>" placeholder="Quito">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Provincia</label>
          <input type="text" name="provincia" class="form-control"
                 value="<?= e($inst['provincia'] ?? '') ?>" placeholder="Pichincha">
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Dirección</label>
          <input type="text" name="direccion" class="form-control"
                 value="<?= e($inst['direccion'] ?? '') ?>"
                 placeholder="Av. Principal 123">
        </div>

        <hr class="my-1">
        <p class="fw-semibold mb-0 small text-muted">ADMINISTRADOR DEL CONTRATO</p>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Nombre del Administrador</label>
          <input type="text" name="administrador_nombre" class="form-control"
                 value="<?= e($inst['administrador_nombre'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="administrador_email" class="form-control"
                 value="<?= e($inst['administrador_email'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Cargo</label>
          <input type="text" name="administrador_cargo" class="form-control"
                 value="<?= e($inst['administrador_cargo'] ?? 'Administrador del Contrato') ?>">
        </div>

        <div class="col-12 d-flex gap-2 justify-content-end mt-2">
          <a href="/instituciones" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i>
            <?= isset($inst) ? 'Guardar Cambios' : 'Crear Institución' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
