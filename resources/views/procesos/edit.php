<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="/procesos" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>
      <?= isset($proceso) ? 'Editar Proceso' : 'Nuevo Proceso' ?>
    </h4>
  </div>
  <a href="/ia" class="btn btn-outline-primary">
    <i class="bi bi-cpu me-1"></i>Usar IA para auto-completar
  </a>
</div>

<form method="POST" action="<?= isset($proceso) ? '/procesos/'.$proceso['id'] : '/procesos' ?>">
  <?= csrf_field() ?>
  <?php if(isset($proceso)): ?><?= method_field('POST') ?><?php endif; ?>

  <div class="row g-4">
    <!-- Columna principal -->
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-folder me-2 text-primary"></i>Datos del Proceso</div>
        <div class="card-body row g-3">
          <div class="col-md-6">
            <label class="form-label">N° Proceso / Orden de Compra <span class="text-danger">*</span></label>
            <input type="text" name="numero_proceso" class="form-control"
                   value="<?= e($proceso['numero_proceso'] ?? '') ?>"
                   placeholder="Ej: OC-MINEDU-2025-0123" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo de Proceso <span class="text-danger">*</span></label>
            <select name="tipo_proceso" class="form-select" required>
              <?php foreach([
                'infima_cuantia'=>'Ínfima Cuantía',
                'catalogo'=>'Catálogo Electrónico',
                'subasta'=>'Subasta Inversa Electrónica',
                'licitacion'=>'Licitación',
                'menor_cuantia'=>'Menor Cuantía',
                'contratacion_directa'=>'Contratación Directa',
                'otro'=>'Otro'
              ] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($proceso['tipo_proceso']??'')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Objeto de Contratación <span class="text-danger">*</span></label>
            <input type="text" name="objeto_contratacion" class="form-control"
                   value="<?= e($proceso['objeto_contratacion'] ?? '') ?>"
                   placeholder="Descripción completa del bien o servicio contratado" required>
          </div>
          <div class="col-12">
            <label class="form-label">Descripción Detallada</label>
            <textarea name="descripcion_detallada" class="form-control" rows="3"
                      placeholder="Detalles adicionales, alcance, especificaciones..."><?= e($proceso['descripcion_detallada'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Institución Contratante <span class="text-danger">*</span></label>
            <select name="institucion_id" class="form-select" required>
              <option value="">Seleccione una institución...</option>
              <?php foreach($instituciones as $i): ?>
              <option value="<?= $i['id'] ?>" <?= ($proceso['institucion_id']??'')==$i['id']?'selected':'' ?>>
                <?= e($i['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><a href="/instituciones/crear" target="_blank"><i class="bi bi-plus me-1"></i>Crear nueva institución</a></div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-cash-coin me-2 text-success"></i>Condiciones Económicas</div>
        <div class="card-body row g-3">
          <div class="col-md-4">
            <label class="form-label">Monto Total (sin IVA) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" name="monto_total" class="form-control"
                     value="<?= $proceso['monto_total'] ?? '' ?>" required min="0.01">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Plazo de Ejecución <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" name="plazo_dias" class="form-control"
                     value="<?= $proceso['plazo_dias'] ?? 30 ?>" required min="1">
              <span class="input-group-text">días</span>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha de Inicio</label>
            <input type="date" name="fecha_inicio" class="form-control"
                   value="<?= $proceso['fecha_inicio'] ?? date('Y-m-d') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Penalidad por día de retraso</label>
            <div class="input-group">
              <span class="input-group-text">1 x</span>
              <input type="number" step="0.0001" name="penalidad_por_dia" class="form-control"
                     value="<?= $proceso['penalidad_por_dia'] ?? '0.001' ?>" placeholder="0.001">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check mt-4">
              <input type="checkbox" name="tiene_anticipo" id="tieneAnticipo" class="form-check-input"
                     <?= ($proceso['tiene_anticipo']??0)?'checked':'' ?> onchange="toggleAnticipo()">
              <label class="form-check-label" for="tieneAnticipo">Tiene anticipo</label>
            </div>
          </div>
          <div class="col-md-4" id="montoAnticipo" style="display:<?= ($proceso['tiene_anticipo']??0)?'block':'none' ?>">
            <label class="form-label">Monto del Anticipo</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" name="monto_anticipo" class="form-control"
                     value="<?= $proceso['monto_anticipo'] ?? '' ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Columna lateral -->
    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-shield-check me-2 text-warning"></i>Garantía Técnica</div>
        <div class="card-body">
          <div class="form-check mb-3">
            <input type="checkbox" name="tiene_garantia" id="tieneGarantia" class="form-check-input"
                   <?= ($proceso['tiene_garantia']??1)?'checked':'' ?>>
            <label class="form-check-label" for="tieneGarantia">Requiere garantía técnica</label>
          </div>
          <label class="form-label">Plazo de Garantía</label>
          <div class="input-group">
            <input type="number" name="plazo_garantia_dias" class="form-control"
                   value="<?= $proceso['plazo_garantia_dias'] ?? 365 ?>">
            <span class="input-group-text">días</span>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-sticky me-2"></i>Notas Internas</div>
        <div class="card-body">
          <textarea name="notas_internas" class="form-control" rows="4"
                    placeholder="Observaciones, recordatorios, información relevante..."><?= e($proceso['notas_internas'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg">
          <i class="bi bi-check-circle me-2"></i>
          <?= isset($proceso) ? 'Guardar Cambios' : 'Crear Proceso' ?>
        </button>
        <a href="/procesos" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>
  </div>
</form>

<script>
function toggleAnticipo(){
  document.getElementById('montoAnticipo').style.display =
    document.getElementById('tieneAnticipo').checked ? 'block' : 'none';
}
</script>
