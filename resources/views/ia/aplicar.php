<div class="mb-4">
  <h4 class="fw-bold"><i class="bi bi-stars me-2 text-warning"></i>Datos Extraídos por la IA</h4>
  <p class="text-muted">Revise los datos extraídos y confirme para crear el proceso automáticamente.</p>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <form method="POST" action="/ia/<?= $analisis['id'] ?>/aplicar">
      <?= csrf_field() ?>
      <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-building me-2"></i>Datos del Proceso</div>
        <div class="card-body row g-3">
          <div class="col-6">
            <label class="form-label">N° Proceso</label>
            <input type="text" name="numero_proceso" class="form-control" value="<?= generarNumeroProceso() ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Tipo de Proceso</label>
            <select name="tipo_proceso" class="form-select">
              <?php foreach(['infima_cuantia'=>'Ínfima Cuantía','catalogo'=>'Catálogo','subasta'=>'Subasta Inversa','licitacion'=>'Licitación','menor_cuantia'=>'Menor Cuantía','otro'=>'Otro'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($datos['tipo_proceso']??'')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Objeto de Contratación</label>
            <textarea name="objeto_contratacion" class="form-control" rows="2" required><?= e($datos['objeto_contratacion'] ?? '') ?></textarea>
          </div>
          <div class="col-6">
            <label class="form-label">Monto Total (USD)</label>
            <input type="number" step="0.01" name="monto_total" class="form-control" value="<?= $datos['monto_total'] ?? 0 ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Plazo (días)</label>
            <input type="number" name="plazo_dias" class="form-control" value="<?= $datos['plazo_dias'] ?? 30 ?>" required>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-building me-2"></i>Institución Contratante</div>
        <div class="card-body row g-3">
          <div class="col-12">
            <label class="form-label">Seleccionar existente</label>
            <select name="institucion_id" class="form-select">
              <option value="">— Crear nueva institución automáticamente —</option>
              <?php foreach($instituciones as $i): ?>
              <option value="<?= $i['id'] ?>"><?= e($i['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <div class="alert alert-info small mb-0">
              <i class="bi bi-info-circle me-1"></i>
              Si deja "crear nueva", se creará automáticamente: <strong><?= e($datos['institucion_contratante'] ?? 'No detectado') ?></strong>
              <?php if($datos['administrador_contrato']??null): ?>
              — Administrador: <strong><?= e($datos['administrador_contrato']) ?></strong>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-success btn-lg w-100">
        <i class="bi bi-check-circle-fill me-2"></i>Crear Proceso con estos datos
      </button>
      <a href="/ia" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a>
    </form>
  </div>

  <!-- Panel de datos extraídos (read-only) -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header bg-warning-subtle"><i class="bi bi-robot me-2"></i>Datos Completos Extraídos</div>
      <div class="card-body">
        <?php
        $display = [
          'Institución'    => $datos['institucion_contratante'] ?? null,
          'RUC'            => $datos['ruc_institucion'] ?? null,
          'Monto'          => isset($datos['monto_total']) ? '$'.number_format($datos['monto_total'],2) : null,
          'Plazo'          => isset($datos['plazo_dias']) ? $datos['plazo_dias'].' días' : null,
          'Administrador'  => $datos['administrador_contrato'] ?? null,
          'Cargo Admin'    => $datos['cargo_administrador'] ?? null,
          'Email Admin'    => $datos['email_administrador'] ?? null,
          'Forma de Pago'  => $datos['forma_de_pago'] ?? null,
          'Penalidades'    => $datos['penalidades'] ?? null,
        ];
        foreach($display as $label=>$val): if(!$val) continue; ?>
        <div class="mb-2">
          <small class="text-muted d-block"><?= $label ?></small>
          <strong class="small"><?= e($val) ?></strong>
        </div>
        <?php endforeach; ?>

        <?php if(!empty($datos['entregables'])): ?>
        <hr>
        <strong class="small">Entregables (<?= count($datos['entregables']) ?>):</strong>
        <ol class="small mt-1 ps-3">
          <?php foreach($datos['entregables'] as $e): ?>
          <li><?= e($e['descripcion'] ?? '') ?><?= isset($e['plazo_dias'])?' ('.$e['plazo_dias'].' días)':'' ?></li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>

        <?php if(!empty($datos['documentos_requeridos'])): ?>
        <hr>
        <strong class="small">Documentos Requeridos:</strong>
        <ul class="small mt-1 ps-3">
          <?php foreach($datos['documentos_requeridos'] as $d): ?>
          <li><?= e($d) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if($datos['resumen_ejecutivo']??null): ?>
        <hr>
        <strong class="small">Resumen:</strong>
        <p class="small mt-1 text-muted"><?= e($datos['resumen_ejecutivo']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header small"><i class="bi bi-info-circle me-1"></i>Modelo usado</div>
      <div class="card-body small text-muted">
        Modelo: <strong><?= e($analisis['modelo_usado'] ?? '') ?></strong><br>
        Tokens: <strong><?= number_format((int)$analisis['tokens_usados']) ?></strong>
      </div>
    </div>
  </div>
</div>
