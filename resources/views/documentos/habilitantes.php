<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-1"><i class="bi bi-shield-check me-2 text-primary"></i>Documentos Habilitantes</h4>
  <p class="text-muted small mb-0">Control de vigencia de documentos del proveedor para contratar con el Estado</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDoc">
    <i class="bi bi-plus-circle me-1"></i>Agregar Documento
  </button>
</div>

<!-- Resumen de estado -->
<?php
$vigentes    = array_filter($docs, fn($d) => $d['estado']==='vigente');
$porVencer   = array_filter($docs, fn($d) => $d['estado']==='por_vencer');
$vencidos    = array_filter($docs, fn($d) => $d['estado']==='vencido');
?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card text-center border-success">
    <div class="card-body py-3"><div class="fs-3 text-success fw-bold"><?= count($vigentes) ?></div>
    <div class="text-muted small">Vigentes ✅</div></div></div></div>
  <div class="col-md-4"><div class="card text-center border-warning">
    <div class="card-body py-3"><div class="fs-3 text-warning fw-bold"><?= count($porVencer) ?></div>
    <div class="text-muted small">Por Vencer ⚠️</div></div></div></div>
  <div class="col-md-4"><div class="card text-center border-danger">
    <div class="card-body py-3"><div class="fs-3 text-danger fw-bold"><?= count($vencidos) ?></div>
    <div class="text-muted small">Vencidos 🔴</div></div></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Documento</th><th>Tipo</th><th>N° / Referencia</th>
        <th>F. Emisión</th><th>F. Vencimiento</th><th>Días restantes</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach($docs as $d): ?>
        <?php $dias = daysUntil($d['fecha_vencimiento']); ?>
        <tr class="<?= $d['estado']==='vencido'?'table-danger':($d['estado']==='por_vencer'?'table-warning':'') ?>">
          <td class="fw-semibold small"><?= e($d['nombre']) ?></td>
          <td><span class="badge bg-secondary"><?= strtoupper($d['tipo']) ?></span></td>
          <td class="small"><?= e($d['numero'] ?? '—') ?></td>
          <td class="small"><?= formatDate($d['fecha_emision']) ?></td>
          <td class="small"><?= $d['fecha_vencimiento'] ? formatDate($d['fecha_vencimiento']) : 'Sin vencimiento' ?></td>
          <td>
            <?php if($d['fecha_vencimiento']): ?>
              <span class="badge <?= $dias<0?'bg-danger':($dias<=30?'bg-warning text-dark':'bg-success') ?>">
                <?= $dias < 0 ? abs($dias).' días vencido' : $dias.' días' ?>
              </span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td><?= estadoBadge($d['estado']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <?php if($d['archivo_url']): ?>
              <a href="/storage/<?= e($d['archivo_url']) ?>" class="btn btn-xs btn-sm btn-outline-primary" title="Descargar">
                <i class="bi bi-download"></i>
              </a>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-secondary btn-xs" data-bs-toggle="modal"
                      data-bs-target="#modalEditDoc"
                      data-id="<?= $d['id'] ?>"
                      data-nombre="<?= e($d['nombre']) ?>"
                      data-tipo="<?= e($d['tipo']) ?>"
                      data-numero="<?= e($d['numero']??'') ?>"
                      data-vencimiento="<?= e($d['fecha_vencimiento']??'') ?>"
                      data-alerta="<?= $d['dias_alerta'] ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" action="/documentos-habilitantes/<?= $d['id'] ?>/eliminar" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger btn-xs"
                        onclick="return confirm('¿Eliminar este documento?')"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($docs)): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">
          <i class="bi bi-shield fs-2 d-block mb-2"></i>
          No hay documentos registrados. Agregue sus documentos habilitantes.
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Tipos recomendados -->
<div class="card mt-4">
  <div class="card-header"><i class="bi bi-info-circle me-2 text-primary"></i>Documentos Recomendados para Contratación Pública</div>
  <div class="card-body">
    <div class="row g-2">
      <?php foreach([
        ['ruc','RUC Activo','Sin vencimiento físico, verificar estado mensual'],
        ['rup','Registro RUP (SERCOP)','Renovación anual'],
        ['iess','Certificado IESS','Mensual — renovar antes del día 15'],
        ['sri','Certificado SRI (No Deudor)','Variable — verificar mensualmente'],
        ['poliza_anticipo','Póliza Buen Uso del Anticipo','Duración del contrato + 60 días'],
        ['poliza_cumplimiento','Póliza Fiel Cumplimiento','Duración del contrato'],
        ['representacion_legal','Nombramiento Representante Legal','Según vigencia del nombramiento'],
        ['garantia_tecnica','Certificados de Garantía Técnica','Por proceso — según contrato'],
      ] as [$tipo,$nombre,$desc]): ?>
      <div class="col-md-6 col-lg-3">
        <div class="border rounded p-2 h-100 <?= in_array($tipo, array_column($docs,'tipo'))?'border-success bg-success bg-opacity-5':'border-dashed' ?>">
          <div class="small fw-semibold"><?= $nombre ?></div>
          <div class="text-muted" style="font-size:11px"><?= $desc ?></div>
          <?php if(in_array($tipo, array_column($docs,'tipo'))): ?>
          <span class="badge bg-success mt-1" style="font-size:10px">✓ Registrado</span>
          <?php else: ?>
          <span class="badge bg-secondary mt-1" style="font-size:10px">Pendiente</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Modal Agregar -->
<div class="modal fade" id="modalDoc" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Agregar Documento Habilitante</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="/documentos-habilitantes" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-6">
          <label class="form-label">Tipo <span class="text-danger">*</span></label>
          <select name="tipo" class="form-select" required>
            <?php foreach(['ruc'=>'RUC','rup'=>'RUP','iess'=>'IESS','sri'=>'SRI','poliza_anticipo'=>'Póliza Anticipo','poliza_cumplimiento'=>'Póliza Cumplimiento','representacion_legal'=>'Representación Legal','garantia_tecnica'=>'Garantía Técnica','otro'=>'Otro'] as $k=>$v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Nombre <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="col-6">
          <label class="form-label">N° / Referencia</label>
          <input type="text" name="numero" class="form-control">
        </div>
        <div class="col-6">
          <label class="form-label">Fecha de Vencimiento</label>
          <input type="date" name="fecha_vencimiento" class="form-control">
        </div>
        <div class="col-6">
          <label class="form-label">Fecha de Emisión</label>
          <input type="date" name="fecha_emision" class="form-control">
        </div>
        <div class="col-6">
          <label class="form-label">Alertar (días antes)</label>
          <input type="number" name="dias_alerta" class="form-control" value="30">
        </div>
        <div class="col-12">
          <label class="form-label">Archivo (PDF)</label>
          <input type="file" name="archivo" class="form-control" accept=".pdf,.jpg,.png">
        </div>
        <div class="col-12">
          <label class="form-label">Notas</label>
          <textarea name="notas" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar Documento</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditDoc" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Editar Documento</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" id="formEditDoc" action="">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">Nombre</label>
          <input type="text" name="nombre" id="editNombre" class="form-control"></div>
        <div class="col-6"><label class="form-label">F. Vencimiento</label>
          <input type="date" name="fecha_vencimiento" id="editVencimiento" class="form-control"></div>
        <div class="col-6"><label class="form-label">Días de alerta</label>
          <input type="number" name="dias_alerta" id="editAlerta" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Actualizar</button>
      </div>
    </form>
  </div></div>
</div>

<script>
document.getElementById('modalEditDoc').addEventListener('show.bs.modal', function(e) {
  const b = e.relatedTarget;
  document.getElementById('formEditDoc').action = '/documentos-habilitantes/'+b.dataset.id;
  document.getElementById('editNombre').value     = b.dataset.nombre;
  document.getElementById('editVencimiento').value= b.dataset.vencimiento;
  document.getElementById('editAlerta').value     = b.dataset.alerta;
});
</script>
