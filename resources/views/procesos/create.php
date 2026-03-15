<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="/procesos" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Nuevo Proceso</h4>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     OPCIÓN A — IMPORTAR DESDE SERCOP
     ═══════════════════════════════════════════════════════════════════ -->
<div class="card mb-4 border-primary">
  <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-globe me-2"></i>Opción A — Importar desde SERCOP</span>
    <span class="badge bg-warning text-dark">Recomendado</span>
  </div>
  <div class="card-body">

    <?php if(!empty($errorImport)): ?>
    <div class="alert alert-danger small py-2">
      <i class="bi bi-x-circle me-1"></i><?= e($errorImport) ?>
    </div>
    <?php endif; ?>

    <?php if(!empty($sercopBloqueado)): ?>
    <!-- Modo alternativo: pegar HTML copiado del navegador -->
    <div class="alert alert-warning small py-2 mb-3">
      <i class="bi bi-exclamation-triangle me-1"></i>
      <strong>Modo alternativo:</strong> Abre el proceso en tu navegador, presiona
      <kbd>Ctrl+U</kbd> (ver código fuente), selecciona todo <kbd>Ctrl+A</kbd>,
      copia <kbd>Ctrl+C</kbd> y pega aquí:
    </div>
    <form method="POST" action="/procesos/importar-sercop-html" id="formHtmlSercop">
      <?= csrf_field() ?>
      <input type="hidden" name="url_sercop" value="<?= e($url_sercop ?? $url ?? '') ?>">
      <textarea name="html_sercop" class="form-control form-control-sm mb-2" rows="4"
                placeholder="Pega aquí el código fuente HTML de la página del proceso SERCOP..."
                style="font-family:monospace;font-size:11px"></textarea>
      <button type="submit" class="btn btn-warning btn-sm" id="btnHtml">
        <i class="bi bi-code-slash me-1"></i>Procesar HTML pegado
      </button>
    </form>
    <hr class="my-3">
    <?php endif; ?>

    <p class="small text-muted mb-3">
      Pega la URL del proceso desde <strong>compraspublicas.gob.ec</strong> y el sistema importa automáticamente:
      institución, NIC, objeto, tipo, fechas, funcionario y todos los ítems.
    </p>
    <form method="POST" action="/procesos/importar-sercop" id="formUrlSercop">
      <?= csrf_field() ?>
      <div class="row g-2 align-items-end">
        <div class="flex-grow-1 col">
          <label class="form-label small fw-semibold mb-1">URL del proceso SERCOP</label>
          <input type="url" name="url_sercop" class="form-control"
                 placeholder="https://www.compraspublicas.gob.ec/ProcesoContratacion/compras/NCO/..."
                 value="<?= e($url_sercop ?? '') ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary" id="btnImportar">
            <i class="bi bi-cloud-download me-2"></i>Importar
          </button>
        </div>
      </div>
    </form>

    <?php if(!empty($datos)): ?>
    <!-- Resultado de la importación -->
    <div class="mt-3 p-3 bg-success bg-opacity-10 border border-success rounded">
      <div class="fw-semibold small text-success mb-2">
        <i class="bi bi-check-circle-fill me-1"></i>
        Datos importados — revisa y ajusta si es necesario, luego haz clic en "Crear Proceso"
      </div>
      <?php if(!empty($aviso)): ?>
      <div class="alert alert-warning small py-1 mb-2"><?= e($aviso) ?></div>
      <?php endif; ?>
      <div class="row g-1 small">
        <?php if(!empty($datos['numero_proceso'])): ?>
        <div class="col-sm-6"><span class="text-muted">NIC:</span> <strong><?= e($datos['numero_proceso']) ?></strong></div>
        <?php endif; ?>
        <?php if(!empty($datos['institucion_contratante'])): ?>
        <div class="col-sm-6"><span class="text-muted">Institución:</span> <strong><?= e($datos['institucion_contratante']) ?></strong></div>
        <?php endif; ?>
        <?php if(!empty($datos['objeto_contratacion'])): ?>
        <div class="col-12"><span class="text-muted">Objeto:</span> <?= e(mb_substr($datos['objeto_contratacion'],0,120)) ?></div>
        <?php endif; ?>
        <?php if(!empty($fechaLimite)): ?>
        <div class="col-12 mt-1">
          <span class="text-danger fw-semibold"><i class="bi bi-clock me-1"></i>Límite proformas:</span>
          <strong class="text-danger"><?= e($fechaLimite) ?></strong>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Separador -->
<div class="d-flex align-items-center gap-3 mb-4">
  <hr class="flex-grow-1">
  <span class="text-muted small fw-semibold px-2">O CREA MANUALMENTE</span>
  <hr class="flex-grow-1">
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     OPCIÓN B — FORMULARIO MANUAL
     ═══════════════════════════════════════════════════════════════════ -->

<form method="POST" action="<?= isset($proceso) ? '/procesos/'.$proceso['id'] : '/procesos' ?>">
<form method="POST" action="<?= isset($proceso) ? '/procesos/'.$proceso['id'] : '/procesos' ?>">
  <?= csrf_field() ?>
  <?php if(isset($proceso)): ?><?= method_field('POST') ?><?php endif; ?>

  <?php if(!empty($datos['fecha_limite_proforma'])): ?>
  <input type="hidden" name="fecha_limite_proforma" value="<?= e($datos['fecha_limite_proforma']) ?>">
  <?php endif; ?>
  <?php if(!empty($url_sercop)): ?>
  <input type="hidden" name="url_sercop" value="<?= e($url_sercop) ?>">
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header fw-semibold">
      <i class="bi bi-1-circle-fill text-primary me-2"></i>Fase 1 &mdash; Datos principales del proceso
    </div>
    <div class="card-body row g-3">

      <!-- NIC / N° Proceso -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">N&deg; Proceso / NIC <span class="text-danger">*</span></label>
        <input type="text" name="numero_proceso" class="form-control"
               value="<?= e($proceso['numero_proceso'] ?? $datos['numero_proceso'] ?? '') ?>"
               placeholder="Ej: NIC-0460000480001-2026-00004" required>
      </div>

      <!-- Tipo de Proceso -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Tipo de Proceso <span class="text-danger">*</span></label>
        <select name="tipo_proceso" class="form-select" required>
          <?php
            $tipoActual = $proceso['tipo_proceso'] ?? $datos['tipo_proceso'] ?? '';
            foreach(['infima_cuantia'=>'&Iacute;nfima Cuant&iacute;a','catalogo'=>'Cat&aacute;logo Electr&oacute;nico',
              'subasta'=>'Subasta Inversa','licitacion'=>'Licitaci&oacute;n',
              'menor_cuantia'=>'Menor Cuant&iacute;a','contratacion_directa'=>'Contrataci&oacute;n Directa','otro'=>'Otro'
            ] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $tipoActual===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Objeto de Contratación -->
      <div class="col-12">
        <label class="form-label fw-semibold">Objeto de Contrataci&oacute;n <span class="text-danger">*</span></label>
        <input type="text" name="objeto_contratacion" class="form-control"
               value="<?= e($proceso['objeto_contratacion'] ?? $datos['objeto_contratacion'] ?? '') ?>"
               placeholder="Descripci&oacute;n del bien o servicio" required>
      </div>

      <!-- CPC -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          C&oacute;digo CPC
          <?php if(!empty($datos['cpc'])): ?><span class="badge bg-success ms-1">&#10003; extra&iacute;do</span><?php endif; ?>
        </label>
        <input type="text" name="cpc" class="form-control"
               value="<?= e($proceso['cpc'] ?? $datos['cpc'] ?? '') ?>"
               placeholder="Ej: 842200011">
      </div>

      <!-- Fecha Inicio -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Fecha de Inicio</label>
        <input type="date" name="fecha_inicio" class="form-control"
               value="<?= e($proceso['fecha_inicio'] ?? $datos['fecha_inicio'] ?? date('Y-m-d')) ?>">
      </div>

      <?php if(!empty($datos['fecha_limite_proforma'])): ?>
      <div class="col-12">
        <div class="p-2 rounded border border-danger bg-danger bg-opacity-10 small">
          <i class="bi bi-clock-fill text-danger me-1"></i>
          <strong class="text-danger">L&iacute;mite proformas:</strong>
          <?= e($datos['fecha_limite_proforma']) ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Institución Contratante -->
      <div class="col-12">
        <label class="form-label fw-semibold">Instituci&oacute;n Contratante <span class="text-danger">*</span></label>
        <?php
          $instDetectada  = $datos['institucion_contratante'] ?? '';
          $instEncontrada = null;
          if ($instDetectada) {
              foreach ($instituciones as $i) {
                  if (stripos($i['nombre'], substr($instDetectada,0,15)) !== false) {
                      $instEncontrada = $i; break;
                  }
              }
          }
          $instRuc       = $datos['ruc_institucion'] ?? '';
          $instCiudad    = $datos['canton']          ?? $datos['provincia'] ?? '';
          $instAdmin     = $datos['funcionario']     ?? $funcionarioNombre  ?? '';
          $instEmail     = $datos['correo_contacto'] ?? $funcionarioEmail   ?? '';
          $instDireccion = $datos['direccion']       ?? '';
        ?>

        <?php if ($instEncontrada): ?>
          <!-- ✅ Encontrada → mostrar las dos opciones lado a lado -->
          <div class="row g-2 mb-2">
            <!-- Opción A: Usar la encontrada -->
            <div class="col-md-6">
              <div id="cardExistente" class="h-100 p-3 rounded border border-success bg-success bg-opacity-10 cursor-pointer"
                   onclick="elegirExistente()" style="cursor:pointer">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <span class="fw-semibold small text-success">Usar institución existente</span>
                </div>
                <div class="fw-bold"><?= e($instEncontrada['nombre']) ?></div>
                <div class="small text-muted mt-1">Ya está registrada en tu lista</div>
              </div>
            </div>
            <!-- Opción B: Crear nueva -->
            <div class="col-md-6">
              <div id="cardNueva" class="h-100 p-3 rounded border border-secondary bg-light"
                   onclick="elegirNueva()" style="cursor:pointer">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-building-add text-secondary"></i>
                  <span class="fw-semibold small text-secondary">Crear nueva institución</span>
                </div>
                <div class="fw-bold text-muted"><?= e($instDetectada) ?></div>
                <div class="small text-muted mt-1">Registrar como nueva entrada</div>
              </div>
            </div>
          </div>

          <!-- Inputs ocultos controlados por JS -->
          <input type="hidden" name="institucion_id"    id="instId"   value="<?= $instEncontrada['id'] ?>">
          <input type="hidden" name="nueva_institucion" id="esNueva"  value="0">

          <!-- Panel: cambiar institución existente (oculto por defecto) -->
          <div id="panelExistente" class="">
            <select class="form-select form-select-sm"
                    onchange="document.getElementById('instId').value=this.value">
              <option value="">Seleccionar institución...</option>
              <?php foreach($instituciones as $i): ?>
              <option value="<?= $i['id'] ?>" <?= $i['id']==$instEncontrada['id']?'selected':'' ?>><?= e($i['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Panel: formulario crear nueva (oculto por defecto) -->
          <div id="panelNueva" class="d-none border border-warning rounded p-3 bg-warning bg-opacity-10 mt-1">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small mb-1 fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="inst_nombre" class="form-control form-control-sm fw-semibold"
                       value="<?= e($instDetectada) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">RUC</label>
                <input type="text" name="inst_ruc" class="form-control form-control-sm"
                       value="<?= e($instRuc) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">Cantón / Ciudad</label>
                <input type="text" name="inst_ciudad" class="form-control form-control-sm"
                       value="<?= e($instCiudad) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">Administrador</label>
                <input type="text" name="inst_administrador" class="form-control form-control-sm"
                       value="<?= e($instAdmin) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1 text-muted">Email</label>
                <input type="email" name="inst_email" class="form-control form-control-sm"
                       value="<?= e($instEmail) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1 text-muted">Direcci&oacute;n</label>
                <input type="text" name="inst_direccion" class="form-control form-control-sm"
                       value="<?= e($instDireccion) ?>">
              </div>
            </div>
          </div>

        <?php elseif (!empty($instDetectada)): ?>
          <!-- ⚠️ Detectada del SERCOP pero no existe → mostrar form pre-llenado directamente -->
          <input type="hidden" name="institucion_id" value="0">
          <input type="hidden" name="nueva_institucion" value="1">
          <div class="border border-warning rounded p-3 bg-warning bg-opacity-10">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="fw-semibold text-warning">
                <i class="bi bi-building-add me-1"></i>
                Nueva institución &mdash; datos importados del SERCOP
                <span class="ms-1 text-muted fw-normal small">(puedes editar antes de guardar)</span>
              </div>
              <button type="button"
                      class="btn btn-sm btn-outline-primary"
                      onclick="document.getElementById('usarExistente').classList.toggle('d-none')">
                <i class="bi bi-search me-1"></i>Seleccionar Empresa
              </button>
            </div>
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small mb-1 fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="inst_nombre" class="form-control form-control-sm fw-semibold"
                       value="<?= e($instDetectada) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">RUC</label>
                <input type="text" name="inst_ruc" class="form-control form-control-sm"
                       value="<?= e($instRuc) ?>" placeholder="Ej: 1768107770001">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">Cantón / Ciudad</label>
                <input type="text" name="inst_ciudad" class="form-control form-control-sm"
                       value="<?= e($instCiudad) ?>" placeholder="Ej: Quito">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">Administrador del Contrato</label>
                <input type="text" name="inst_administrador" class="form-control form-control-sm"
                       value="<?= e($instAdmin) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1 text-muted">Email</label>
                <input type="email" name="inst_email" class="form-control form-control-sm"
                       value="<?= e($instEmail) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1 text-muted">Direcci&oacute;n</label>
                <input type="text" name="inst_direccion" class="form-control form-control-sm"
                       value="<?= e($instDireccion) ?>" placeholder="Calle y número, Cantón">
              </div>
            </div>
            <div class="mt-2 small text-muted">
              <i class="bi bi-info-circle me-1"></i>La institución se creará junto con el proceso al guardar.
            </div>
            <div id="usarExistente" class="d-none mt-2">
              <select class="form-select form-select-sm"
                      onchange="
                        if(this.value){
                          document.querySelector('[name=institucion_id]').value=this.value;
                          document.querySelector('[name=nueva_institucion]').value='0';
                          this.closest('.border-warning').classList.add('opacity-50');
                        }">
                <option value="">Seleccionar institución existente...</option>
                <?php foreach($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>"><?= e($i['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

        <?php else: ?>
          <!-- Sin datos importados → selector normal + botón crear inline -->
          <div class="input-group mb-1">
            <select name="institucion_id" class="form-select" required>
              <option value="">Seleccionar institución...</option>
              <?php foreach($instituciones as $i): ?>
              <option value="<?= $i['id'] ?>"><?= e($i['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-primary"
                    onclick="document.getElementById('nuevaInstInline').classList.toggle('d-none')"
                    title="Crear nueva institución">
              <i class="bi bi-plus"></i>
            </button>
          </div>
          <div id="nuevaInstInline" class="d-none p-3 border rounded bg-light">
            <input type="hidden" name="nueva_institucion" value="1">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small mb-1 fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="inst_nombre" class="form-control form-control-sm" placeholder="Nombre completo de la institución">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">RUC</label>
                <input type="text" name="inst_ruc" class="form-control form-control-sm" placeholder="RUC">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">Cantón / Ciudad</label>
                <input type="text" name="inst_ciudad" class="form-control form-control-sm" placeholder="Cantón/Ciudad">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1 text-muted">Administrador</label>
                <input type="text" name="inst_administrador" class="form-control form-control-sm" placeholder="Nombre administrador">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1 text-muted">Email</label>
                <input type="email" name="inst_email" class="form-control form-control-sm" placeholder="email@institucion.gob.ec">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1 text-muted">Direcci&oacute;n</label>
                <input type="text" name="inst_direccion" class="form-control form-control-sm" placeholder="Dirección">
              </div>
            </div>
          </div>

        <?php endif; ?>
      </div>

      <!-- Notas internas opcionales -->
      <!-- Notas internas opcionales -->
      <!-- Notas internas opcionales -->
      <!-- Notas internas opcionales -->
      <div class="col-12">
        <label class="form-label fw-semibold text-muted">Notas Internas <small class="fw-normal">(opcional)</small></label>
        <textarea name="notas_internas" class="form-control form-control-sm" rows="2"
                  placeholder="Observaciones, recordatorios..."><?= e($proceso['notas_internas'] ?? '') ?></textarea>
      </div>

    </div>
  </div>

  <?php if(!empty($items)): ?>
  <div class="card border-primary shadow-sm mb-3">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
      <span class="fw-semibold small">
        <i class="bi bi-table me-1"></i>Ítems extraídos del SERCOP
        <span class="badge bg-white text-primary ms-2"><?= count($items) ?> ítems</span>
      </span>
      <button type="button" class="btn btn-sm btn-outline-light py-0"
              onclick="document.getElementById('tablaItemsWrap').classList.toggle('d-none')">
        Mostrar/Ocultar
      </button>
    </div>
    <div id="tablaItemsWrap" class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" id="tablaItemsCreate" style="font-size:11.5px">
          <thead class="table-secondary">
            <tr>
              <th class="text-center ps-2" style="width:35px">N°</th>
              <th style="width:90px">CPC</th>
              <th>Descripción del Producto</th>
              <th style="width:70px">Unidad</th>
              <th class="text-center" style="width:65px">Cant.</th>
              <th class="text-end pe-2" style="width:95px">P. Unitario $</th>
              <th class="text-end pe-2" style="width:95px">Total $</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($items as $it): ?>
            <tr data-num="<?= (int)$it['numero'] ?>"
                data-cpc="<?= e($it['cpc'] ?? '') ?>"
                data-cpc-desc="<?= e($it['cpc_descripcion'] ?? '') ?>"
                data-unidad="<?= e($it['unidad'] ?? '') ?>"
                data-cantidad="<?= (float)($it['cantidad'] ?? 0) ?>">
              <td class="text-center align-middle ps-2"><?= (int)$it['numero'] ?></td>
              <td class="align-middle text-muted small"><?= e($it['cpc'] ?? '') ?></td>
              <td class="align-middle" style="white-space:pre-wrap;font-size:11px;max-width:350px"><?= e($it['descripcion'] ?? '') ?></td>
              <td class="align-middle small"><?= e($it['unidad'] ?? '') ?></td>
              <td class="text-center align-middle"><?= number_format((float)($it['cantidad'] ?? 0), 2) ?></td>
              <td class="text-end align-middle pe-1">
                <input type="number" step="0.01" min="0"
                       class="form-control form-control-sm text-end p-1 item-precio-create"
                       style="width:84px;margin-left:auto"
                       value="0.00"
                       onchange="recalcItemCreate(this)">
              </td>
              <td class="text-end align-middle fw-semibold pe-2 item-total-create">$0.00</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="6" class="text-end fw-bold small pe-2">TOTAL:</td>
              <td class="text-end fw-bold text-success pe-2" id="itemsTotalCreate">$0.00</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="px-3 pb-2 pt-1">
        <small class="text-muted">
          <i class="bi bi-info-circle me-1"></i>Puedes ingresar precios ahora o en Fase 2. El total actualiza el campo Monto automáticamente.
        </small>
      </div>
    </div>
  </div>
  <input type="hidden" name="items_json" id="itemsJsonCreate"
         value="<?= e(json_encode($items, JSON_UNESCAPED_UNICODE)) ?>">
  <?php endif; ?>

  <div class="alert alert-info small py-2 mb-3">
    <i class="bi bi-2-circle me-1"></i>
    <strong>Fase 2</strong> &mdash; despu&eacute;s de crear el proceso podr&aacute;s completar:
    monto, plazo, especificaciones t&eacute;cnicas, metodolog&iacute;a y campos extra del TDR.
  </div>

  <div class="d-grid gap-2">
    <button type="submit" class="btn btn-success btn-lg">
      <i class="bi bi-check-circle me-2"></i>Crear Proceso
    </button>
    <a href="/procesos" class="btn btn-outline-secondary">Cancelar</a>
  </div>

</form>

<script>
document.getElementById('formUrlSercop')?.addEventListener('submit', function() {
  const btn = document.getElementById('btnImportar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importando...';
});
document.getElementById('formHtmlSercop')?.addEventListener('submit', function() {
  const btn = document.getElementById('btnHtml');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';
});


// ── Selección institución (cuando hay match) ──────────────────────────────
function elegirExistente() {
  document.getElementById('cardExistente')?.classList.add('border-success','bg-success');
  document.getElementById('cardExistente')?.classList.remove('border-secondary','bg-light');
  document.getElementById('cardNueva')?.classList.remove('border-warning');
  document.getElementById('cardNueva')?.classList.add('border-secondary','bg-light');
  document.getElementById('panelExistente')?.classList.remove('d-none');
  document.getElementById('panelNueva')?.classList.add('d-none');
  document.getElementById('esNueva').value  = '0';
}

function elegirNueva() {
  document.getElementById('cardNueva')?.classList.add('border-warning','bg-warning');
  document.getElementById('cardNueva')?.classList.remove('border-secondary','bg-light');
  document.getElementById('cardExistente')?.classList.remove('border-success','bg-success');
  document.getElementById('cardExistente')?.classList.add('border-secondary','bg-light');
  document.getElementById('panelNueva')?.classList.remove('d-none');
  document.getElementById('panelExistente')?.classList.add('d-none');
  document.getElementById('instId').value   = '0';
  document.getElementById('esNueva').value  = '1';
}

// ── Ítems SERCOP: recalcular fila ─────────────────────────────────────────
function recalcItemCreate(input) {
  const tr       = input.closest('tr');
  const cantidad = parseFloat(tr.dataset.cantidad) || 0;
  const precio   = parseFloat(input.value) || 0;
  const total    = cantidad * precio;
  tr.querySelector('.item-total-create').textContent = '$' + total.toFixed(2);

  // Recalcular total general
  let suma = 0;
  document.querySelectorAll('#tablaItemsCreate tbody tr').forEach(r => {
    const txt = r.querySelector('.item-total-create').textContent.replace('$','');
    suma += parseFloat(txt) || 0;
  });
  const elTotal = document.getElementById('itemsTotalCreate');
  if (elTotal) elTotal.textContent = '$' + suma.toFixed(2);

  // Actualizar JSON hidden
  actualizarJsonCreate();

  // Si el campo monto está vacío o en 0, llenarlo
  const montoInput = document.querySelector('input[name="monto_total"]');
  if (montoInput && (!montoInput.value || parseFloat(montoInput.value) === 0)) {
    montoInput.value = suma.toFixed(2);
  }
}

function actualizarJsonCreate() {
  const input = document.getElementById('itemsJsonCreate');
  if (!input) return;
  const rows = document.querySelectorAll('#tablaItemsCreate tbody tr');
  const data = Array.from(rows).map(tr => ({
    numero:          parseInt(tr.dataset.num)      || 0,
    cpc:             tr.dataset.cpc                || '',
    cpc_descripcion: tr.dataset.cpcDesc            || '',
    descripcion:     tr.querySelector('td:nth-child(3)').textContent.trim(),
    unidad:          tr.dataset.unidad             || '',
    cantidad:        parseFloat(tr.dataset.cantidad) || 0,
    precio_unitario: parseFloat(tr.querySelector('.item-precio-create').value) || 0,
    precio_total:    +(parseFloat(tr.dataset.cantidad || 0) *
                       parseFloat(tr.querySelector('.item-precio-create').value || 0)).toFixed(2),
  }));
  input.value = JSON.stringify(data);
}
</script>

<!-- DEBUG TEMPORAL — pegar HTML del SERCOP y ver qué extrae el parser -->
<details class="mt-4">
  <summary class="text-muted small" style="cursor:pointer">������ Diagnóstico del parser (temporal)</summary>
  <form method="POST" action="/debug/sercop-html" target="_blank" class="mt-2">
    <?= csrf_field() ?>
    <textarea name="html_sercop" class="form-control form-control-sm mb-2" rows="4"
              placeholder="Pega el HTML fuente aquí (Ctrl+U en la página SERCOP)"
              style="font-family:monospace;font-size:11px"></textarea>
    <button type="submit" class="btn btn-sm btn-outline-secondary">Ver diagnóstico (nueva pestaña)</button>
  </form