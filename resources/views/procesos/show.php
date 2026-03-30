<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <a href="/procesos" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <h4 class="fw-bold mb-1"><?= e($proceso['numero_proceso']) ?></h4>
    <p class="text-muted mb-0 small"><?= e(truncate($proceso['objeto_contratacion'], 80)) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?= estadoBadge($proceso['estado']) ?>
    <?php if(can('procesos.*')): ?>
    <a href="/procesos/<?= $proceso['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-pencil me-1"></i>Editar
    </a>
    <a href="/procesos/<?= $proceso['id'] ?>/proforma" class="btn btn-sm btn-danger" target="_blank">
      <i class="bi bi-file-earmark-pdf me-1"></i>Proforma
    </a>
    <div class="dropdown">
      <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-file-earmark-plus me-1"></i>Generar Documento
      </button>
      <ul class="dropdown-menu shadow-sm">
        <?php foreach([
          'informe_tecnico'    => ['&#x1F4CA;','Informe Técnico de Entrega'],
          'garantia_tecnica'   => ['&#x1F6E1;','Garantía Técnica'],
          'acta_parcial'       => ['&#x1F4CB;','Acta de Entrega Parcial'],
          'acta_definitiva'    => ['&#x2705;','Acta Entrega Definitiva'],
          'solicitud_pago'     => ['&#x1F4B0;','Solicitud de Pago'],
        ] as $tipo => [$icono,$label]): ?>
        <li>
          <a class="dropdown-item d-flex align-items-center gap-2"
             href="/procesos/<?= $proceso['id'] ?>/documento/editar?tipo=<?= $tipo ?>">
            <span><?= $icono ?></span><span><?= $label ?></span>
            <small class="ms-auto text-muted"><i class="bi bi-pencil-square"></i></small>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- DATOS DEL PROCESO -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-info-circle me-2 text-primary"></i>Datos del Proceso</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th width="45%" class="text-muted">Tipo de Proceso</th><td><?= tipoProceso($proceso['tipo_proceso']) ?></td></tr>
          <tr><th class="text-muted">Institución</th><td><a href="/instituciones/<?= $proceso['institucion_id'] ?>"><?= e($proceso['institucion_nombre']) ?></a></td></tr>
          <tr><th class="text-muted">Administrador</th><td><?= e($proceso['administrador_nombre']) ?><br><small class="text-muted"><?= e($proceso['administrador_cargo']) ?></small></td></tr>
          <tr><th class="text-muted">Monto Total</th><td class="fw-bold text-success"><?= money($proceso['monto_total']) ?></td></tr>
          <tr><th class="text-muted">Plazo</th><td><?= $proceso['plazo_dias'] ?> días calendario</td></tr>
          <tr><th class="text-muted">Fecha Inicio</th><td><?= formatDate($proceso['fecha_inicio']) ?></td></tr>
          <tr><th class="text-muted">Fecha Fin</th><td><?= formatDate($proceso['fecha_fin_calculada']) ?></td></tr>
          <tr><th class="text-muted">Garantía Técnica</th><td><?= $proceso['tiene_garantia'] ? $proceso['plazo_garantia_dias'].' días' : 'No aplica' ?></td></tr>
          <?php if(!empty($proceso['notas_internas'])): ?>
          <tr><th class="text-muted">Notas Internas</th><td><span class="small text-muted fst-italic"><?= nl2br(e($proceso['notas_internas'])) ?></span></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <!-- Cambiar estado + Avance -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Estado del Proceso</div>
      <div class="card-body">
        <div class="mb-3">
          <div class="d-flex justify-content-between mb-1">
            <small class="fw-semibold">Avance General</small>
            <small><?= $proceso['porcentaje_avance'] ?>%</small>
          </div>
          <div class="progress" style="height:10px">
            <div class="progress-bar bg-success" style="width:<?= $proceso['porcentaje_avance'] ?>%"></div>
          </div>
        </div>
        <?php if(can('procesos.*')): ?>
        <form method="POST" action="/procesos/<?= $proceso['id'] ?>/estado" class="d-flex gap-2">
          <?= csrf_field() ?>
          <select name="estado" class="form-select form-select-sm">
            <?php foreach(['en_proceso','adjudicado','en_ejecucion','entregado_provisional','entregado_definitivo','facturado','pagado','cerrado','cancelado'] as $est): ?>
            <option value="<?= $est ?>" <?= $proceso['estado']===$est?'selected':'' ?>><?= ucwords(str_replace('_',' ',$est)) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary">Actualizar</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <!-- Facturas -->
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-receipt me-2 text-success"></i>Facturas</span>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalFactura">+ Factura</button>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach($facturas as $f): ?>
        <div class="list-group-item p-2">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong class="small"><?= e($f['numero_sri']) ?></strong>
              <small class="text-muted ms-2"><?= formatDate($f['fecha_emision']) ?></small>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="fw-bold small"><?= money($f['monto_total']) ?></span>
              <?= estadoBadge($f['estado']) ?>
              <?php if($f['estado'] !== 'pagada'): ?>
              <button class="btn btn-xs btn-success btn-sm" data-bs-toggle="modal"
                      data-bs-target="#modalPago" data-factura-id="<?= $f['id'] ?>">Cobrar</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($facturas)): ?>
        <div class="list-group-item text-muted text-center small py-2">Sin facturas registradas</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     FASE 2 — DATOS TÉCNICOS Y CAMPOS EXTRA
     ═══════════════════════════════════════════════════════════════════════ -->
<?php $fase = (int)($proceso['fase'] ?? 1);
  $pendienteFase2 = $fase < 2 || empty($proceso['monto_total']) || !$proceso['monto_total'];
?>
<div class="card mb-4 <?= $pendienteFase2 ? 'border-warning' : 'border-success' ?>">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>
      <?php if($pendienteFase2): ?>
        <i class="bi bi-2-circle text-warning me-2"></i>
        <strong class="text-warning">Fase 2 pendiente</strong>
        <span class="text-muted small ms-2">— Completa los datos técnicos y económicos</span>
      <?php else: ?>
        <i class="bi bi-check-circle-fill text-success me-2"></i>
        <strong>Datos Técnicos del TDR</strong>
      <?php endif; ?>
    </span>
    <?php if(can('procesos.*')): ?>
    <button class="btn btn-sm btn-outline-secondary" type="button"
            data-bs-toggle="collapse" data-bs-target="#fase2Panel">
      <i class="bi bi-pencil me-1"></i><?= $pendienteFase2 ? 'Completar ahora' : 'Editar' ?>
    </button>
    <?php endif; ?>
  </div>

  <!-- Vista compacta (cuando ya está completa) -->
  <?php if(!$pendienteFase2): ?>
  <div class="card-body py-2">
    <div class="row g-2 small">
      <?php if($proceso['monto_total'] > 0): ?>
      <div class="col-sm-3"><span class="text-muted">Monto:</span> <strong class="text-success"><?= money($proceso['monto_total']) ?></strong></div>
      <?php endif; ?>
      <?php if($proceso['plazo_dias'] > 0): ?>
      <div class="col-sm-3"><span class="text-muted">Plazo:</span> <strong><?= $proceso['plazo_dias'] ?> días</strong></div>
      <?php endif; ?>
      <?php if(!empty($proceso['vigencia_oferta'])): ?>
      <div class="col-sm-3"><span class="text-muted">Vigencia oferta:</span> <strong><?= e($proceso['vigencia_oferta']) ?></strong></div>
      <?php endif; ?>
      <?php if(!empty($camposExtra)): ?>
      <div class="col-sm-3"><span class="text-muted">Campos extra:</span> <strong><?= count($camposExtra) ?></strong></div>
      <?php endif; ?>
    </div>
    <?php if(!empty($proceso['especificaciones_tecnicas'])): ?>
    <div class="mt-2"><span class="badge bg-success me-1">&#10003;</span><small>Especificaciones técnicas cargadas</small></div>
    <?php endif; ?>
    <?php if(!empty($proceso['metodologia_trabajo'])): ?>
    <div class="mt-1"><span class="badge bg-success me-1">&#10003;</span><small>Metodología de trabajo cargada</small></div>
    <?php endif; ?>
    <?php foreach($camposExtra as $campo): ?>
    <div class="mt-1 p-2 bg-light rounded small">
      <strong><?= e($campo['nombre']) ?>:</strong>
      <?= nl2br(e(mb_substr($campo['contenido'], 0, 200))) ?><?= strlen($campo['contenido']) > 200 ? '...' : '' ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Panel editable (colapsable) -->
  <div class="collapse <?= $pendienteFase2 ? 'show' : '' ?>" id="fase2Panel">
    <div class="card-body border-top">

      <?php if($pendienteFase2): ?>
      <!-- ══ EXTRACTOR TDR ══════════════════════════════════════════════ -->
      <div class="card mb-3 border-primary border-opacity-50" id="cardExtractorTdr">
        <div class="card-header bg-primary bg-opacity-10 py-2">
          <div class="d-flex align-items-center justify-content-between">
            <span class="fw-semibold small text-primary">
              <i class="bi bi-file-earmark-arrow-up me-1"></i>
              Extraer secciones del PDF TDR
            </span>
            <span class="badge bg-secondary" id="badgeSecciones" style="display:none!important"></span>
          </div>
        </div>
        <div class="card-body py-3">
          <!-- Drop zone -->
          <div class="border rounded-2 px-3 py-3 bg-white text-center mb-2"
               id="dropZoneTdr"
               style="cursor:pointer;border-style:dashed!important;transition:background .2s"
               onclick="document.getElementById('pdfTdrInput').click()"
               ondragover="event.preventDefault();this.style.background='#e8f4ff'"
               ondragleave="this.style.background=''"
               ondrop="handleDropTdr(event)">
            <i class="bi bi-file-earmark-pdf text-danger fs-3 d-block mb-1"></i>
            <span id="pdfTdrNombre" class="small text-muted">Arrastra el PDF del TDR aquí o haz clic para seleccionar</span>
          </div>
          <input type="file" id="pdfTdrInput" accept=".pdf,.txt" class="d-none"
                 onchange="onPdfTdrSeleccionado(this)">
          <div class="d-flex gap-2 mt-2">
            <button type="button" id="btnAnalizarTdr" class="btn btn-primary btn-sm" disabled
                    onclick="analizarTdr()">
              <i class="bi bi-magic me-1"></i>Analizar TDR y detectar secciones
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="document.getElementById('cardExtractorTdr').style.display='none'">
              <i class="bi bi-x me-1"></i>Cerrar
            </button>
          </div>
          <div id="alertaTdr" class="mt-2 d-none"></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ══ MODAL SELECTOR DE SECCIONES TDR ═══════════════════════════════ -->
      <div class="modal fade" id="modalSeccionesTdr" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
              <h5 class="modal-title small mb-0">
                <i class="bi bi-layout-text-sidebar-reverse me-2"></i>
                Secciones detectadas del TDR — selecciona y edita las que quieres importar
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
              <!-- Barra de acciones -->
              <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-light">
                <button class="btn btn-xs btn-outline-primary py-0 px-2" onclick="seleccionarTodas(true)">
                  <i class="bi bi-check-all me-1"></i>Todas
                </button>
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="seleccionarTodas(false)">
                  <i class="bi bi-square me-1"></i>Ninguna
                </button>
                <span class="vr"></span>
                <small class="text-muted">
                  <i class="bi bi-info-circle me-1"></i>
                  <span class="text-success fw-semibold">Verde</span> = se importa a un campo del formulario •
                  <span class="text-secondary fw-semibold">Gris</span> = se agrega como campo adicional
                </small>
                <span class="ms-auto badge bg-primary" id="resumenSeleccion">0 seleccionadas</span>
              </div>
              <!-- Lista acordeón de secciones -->
              <div id="listaSecciones" class="px-3 py-2">
                <!-- JS rellena aquí -->
              </div>
            </div>
            <div class="modal-footer py-2">
              <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-success btn-sm" onclick="importarSeccionesSeleccionadas()">
                <i class="bi bi-download me-1"></i>Importar secciones seleccionadas al formulario
              </button>
            </div>
          </div>
        </div>
      </div>

      <form method="POST" action="/procesos/<?= $proceso['id'] ?>/fase2" id="formFase2">
        <?= csrf_field() ?>
        <div class="row g-3">

          <!-- N° Proceso y CPC (readonly, vienen de Fase 1) -->
          <div class="col-md-6">
            <label class="form-label fw-semibold small">N° Proceso / NIC</label>
            <input type="text" class="form-control form-control-sm bg-light" readonly
                   value="<?= e($proceso['numero_proceso'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Código CPC</label>
            <input type="text" name="cpc" class="form-control form-control-sm"
                   value="<?= e($proceso['cpc'] ?? '') ?>"
                   placeholder="Ej: 842200011">
          </div>

          <!-- Campos base: Monto y Plazo días -->
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Monto (USD) <span class="text-muted fw-normal">— calculado desde ítems</span></label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" name="monto_total" id="montoTotalInput" class="form-control bg-light"
                     value="<?= $proceso['monto_total'] > 0 ? $proceso['monto_total'] : '' ?>"
                     placeholder="0.00" min="0" readonly>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Plazo (días) <span class="text-muted fw-normal">— número de días</span></label>
            <div class="input-group input-group-sm">
              <input type="number" name="plazo_dias" class="form-control"
                     value="<?= $proceso['plazo_dias'] > 0 ? $proceso['plazo_dias'] : '' ?>"
                     placeholder="0" min="1">
              <span class="input-group-text">días</span>
            </div>
          </div>

          <!-- 0. ÍTEMS DEL PROCESO (extraídos de SERCOP) -->
          <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label fw-semibold small mb-0">
                <i class="bi bi-table me-1 text-primary"></i>Ítems del Proceso
                <?php if(!empty($items)): ?>
                <span class="badge bg-primary ms-1"><?= count($items) ?> ítem<?= count($items) !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
              </label>
              <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px"
                      onclick="document.getElementById('tablaItemsWrap').classList.toggle('d-none')">
                <i class="bi bi-chevron-expand"></i> Mostrar/Ocultar
              </button>
            </div>
            <input type="hidden" name="items_json" id="itemsJsonInput">
            <div id="tablaItemsWrap">
              <div class="table-responsive border rounded">
                <table class="table table-sm table-hover mb-0" id="tablaItems" style="font-size:12px">
                  <thead class="table-dark">
                    <tr>
                      <th class="text-center" style="width:35px">N°</th>
                      <th style="width:90px">CPC</th>
                      <th style="min-width:200px">Descripción del Producto</th>
                      <th style="width:70px">Unidad</th>
                      <th class="text-center" style="width:70px">Cant.</th>
                      <th class="text-end" style="width:90px">P. Unit. $</th>
                      <th class="text-end" style="width:90px">Total $</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($items as $it): ?>
                    <tr data-num="<?= (int)$it['numero'] ?>"
                        data-cpc="<?= e($it['cpc'] ?? '') ?>"
                        data-cpc-desc="<?= e($it['cpc_descripcion'] ?? '') ?>"
                        data-unidad="<?= e($it['unidad'] ?? '') ?>"
                        data-cantidad="<?= (float)($it['cantidad'] ?? 0) ?>">
                      <td class="text-center align-middle"><?= (int)$it['numero'] ?></td>
                      <td class="align-middle small text-muted"><?= e($it['cpc'] ?? '') ?></td>
                      <td class="align-middle" style="white-space:pre-wrap;font-size:11px"><?= e($it['descripcion'] ?? '') ?></td>
                      <td class="align-middle small"><?= e($it['unidad'] ?? '') ?></td>
                      <td class="text-center align-middle"><?= number_format((float)($it['cantidad'] ?? 0), 2) ?></td>
                      <td class="text-end align-middle">
                        <input type="number" step="0.01" min="0"
                               class="form-control form-control-sm text-end p-1 item-precio"
                               style="width:80px;margin-left:auto"
                               value="<?= number_format((float)($it['precio_unitario'] ?? 0), 2, '.', '') ?>"
                               onchange="recalcularItem(this)">
                      </td>
                      <td class="text-end align-middle fw-semibold item-total">
                        $<?= number_format((float)($it['precio_total'] ?? 0), 2) ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot class="table-light">
                    <?php $subtotal = ProcesoItem::totalMonto($proceso['id']); $iva = round($subtotal * 0.15, 2); $total = $subtotal + $iva; ?>
                    <tr>
                      <td colspan="6" class="text-end small pe-2">SUBTOTAL:</td>
                      <td class="text-end small" id="itemsSubtotal">$<?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <tr>
                      <td colspan="6" class="text-end small pe-2">IVA 15%:</td>
                      <td class="text-end small" id="itemsIva">$<?= number_format($iva, 2) ?></td>
                    </tr>
                    <tr>
                      <td colspan="6" class="text-end fw-bold small pe-2">TOTAL:</td>
                      <td class="text-end fw-bold text-success" id="itemsTotalGeneral">$<?= number_format($total, 2) ?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted">
                  <i class="bi bi-info-circle me-1"></i>Ingresa los precios unitarios — el total con IVA 15% actualiza el Monto al guardar.
                </small>
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:12px" onclick="addItemRow()">
                  <i class="bi bi-plus-circle me-1"></i>Agregar ítem
                </button>
              </div>
            </div>
          </div>

          <?php
          // ── Títulos personalizables de secciones de Fase 2 ────────────────
          $titulosSecciones = json_decode($proceso['secciones_titulos'] ?? '{}', true) ?: [];
          function tituloSeccion(array &$ts, string $key, string $default): string {
              $val = htmlspecialchars($ts[$key] ?? $default, ENT_QUOTES);
              return '<span class="d-inline-flex align-items-center gap-1">'
                   . '<span class="titulo-seccion-texto" data-key="' . $key . '">' . $val . '</span>'
                   . '<button type="button" class="btn btn-xs btn-link p-0 ms-1 text-muted titulo-seccion-edit" '
                   .         'data-key="' . $key . '" title="Editar título">'
                   .   '<i class="bi bi-pencil" style="font-size:.7rem"></i>'
                   . '</button>'
                   . '<input type="hidden" name="secciones_titulos[' . $key . ']" '
                   .        'class="titulo-seccion-hidden" data-key="' . $key . '" value="' . $val . '">'
                   . '</span>';
          }
          ?>
          <!-- 1. ESPECIFICACIONES TÉCNICAS / PRODUCTOS O SERVICIOS ESPERADOS -->
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <?= tituloSeccion($titulosSecciones, 'especificaciones_tecnicas', 'Especificaciones Técnicas / Productos o Servicios Esperados') ?>
              <?php if(!empty($proceso['especificaciones_tecnicas'])): ?><span class="badge bg-success ms-1">&#10003;</span><?php endif; ?>
            </label>
            <textarea name="especificaciones_tecnicas" id="ck_especificaciones" class="form-control form-control-sm"
                      placeholder="Se puede extraer del PDF TDR..."><?= $proceso['especificaciones_tecnicas'] ?? '' ?></textarea>

            <!-- BOX: Nota fija al final de especificaciones -->
            <?php
            $notaActivada = (string)($proceso['nota_espec_activa'] ?? '1') !== '0';
            $notaTexto    = $proceso['nota_espec_texto'] ?? 'Todos los servicios serán entregados de manera virtual, adicional es importante tomar en consideración que la recuperación de la información de administraciones anteriores, solo es posible si el proveedor anterior facilita el acceso, caso contrario es imposible y no podremos obtener la información antigua.';
            ?>
            <div class="mt-2 border rounded p-2" style="background:#fffbf0;border-color:#f39c12!important">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="d-flex align-items-center gap-2">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="toggleNota" name="nota_espec_activa" value="1"
                           <?= $notaActivada ? 'checked' : '' ?>
                           onchange="document.getElementById('notaEspecBody').classList.toggle('d-none', !this.checked)">
                    <label class="form-check-label fw-semibold small" for="toggleNota">
                      <i class="bi bi-sticky me-1 text-warning"></i>Nota al final de Especificaciones
                    </label>
                  </div>
                  <span class="badge <?= $notaActivada ? 'bg-warning text-dark' : 'bg-secondary' ?> small" id="badgeNota">
                    <?= $notaActivada ? 'Incluida en proforma' : 'Desactivada' ?>
                  </span>
                </div>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:10px"
                        onclick="document.getElementById('notaEspecEditor').classList.toggle('d-none')">
                  <i class="bi bi-pencil"></i> Editar texto
                </button>
              </div>
              <div id="notaEspecBody" class="<?= !$notaActivada ? 'd-none' : '' ?>">
                <div id="notaEspecPreview" class="small text-muted fst-italic px-2 py-1 rounded"
                     style="background:#fff8e1;font-size:11px;line-height:1.5">
                  <strong>NOTA:</strong> <?= e($notaTexto) ?>
                </div>
                <div id="notaEspecEditor" class="d-none mt-2">
                  <textarea name="nota_espec_texto" class="form-control form-control-sm" rows="3"
                            style="font-size:11px"
                            onkeyup="document.getElementById('notaEspecPreview').innerHTML='<strong>NOTA:</strong> '+this.value"
                            ><?= e($notaTexto) ?></textarea>
                  <small class="text-muted">Este texto aparece al final de las Especificaciones Técnicas en la proforma.</small>
                </div>
              </div>
            </div>
            <!-- hidden para desactivado -->
            <input type="hidden" name="nota_espec_activa_off" value="0">
          </div>

          <!-- 2. METODOLOGÍA DE TRABAJO -->
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <?= tituloSeccion($titulosSecciones, 'metodologia_trabajo', 'Metodología de Trabajo') ?>
              <?php if(!empty($proceso['metodologia_trabajo'])): ?><span class="badge bg-success ms-1">&#10003;</span><?php endif; ?>
            </label>
            <textarea name="metodologia_trabajo" id="ck_metodologia" class="form-control form-control-sm"
                      placeholder="Se puede extraer del PDF TDR..."><?= $proceso['metodologia_trabajo'] ?? '' ?></textarea>
          </div>

          <!-- 3. CPC -->
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <?= tituloSeccion($titulosSecciones, 'cpc_descripcion', 'CPC — Código y Descripción') ?>
              <?php if(!empty($proceso['cpc_descripcion'])): ?><span class="badge bg-success ms-1">&#10003;</span><?php endif; ?>
            </label>
            <textarea name="cpc_descripcion" id="cpc_descripcion" rows="3" class="form-control form-control-sm"
                      placeholder="Ej: 842200011&#10;SERVICIOS DE SUMINISTROS DE UNA CONEXION DIRECTA A INTERNET..."><?= e($proceso['cpc_descripcion'] ?? '') ?></textarea>
          </div>

          <!-- 4. PLAZO DE ENTREGA (texto completo) -->
          <?php $plazoTextoVal = $proceso['plazo_texto'] ?? 'El plazo de la ejecución del servicio es de'; ?>
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <?= tituloSeccion($titulosSecciones, 'plazo_texto', 'Plazo de Entrega') ?>
              <?php if(!empty($proceso['plazo_texto'])): ?><span class="badge bg-success ms-1">&#10003;</span><?php endif; ?>
            </label>
            <textarea name="plazo_texto" id="plazo_texto" rows="2" class="form-control form-control-sm"
                      placeholder="El plazo de la ejecución del servicio es de"><?= e($plazoTextoVal) ?></textarea>
          </div>

          <!-- 5. FORMA Y CONDICIONES DE PAGO -->
          <?php $formaPagoVal = $proceso['forma_pago'] ?? 'Contra Entrega.'; ?>
          <div class="col-12">
            <label class="form-label fw-semibold small">
              <?= tituloSeccion($titulosSecciones, 'forma_pago', 'Forma y Condiciones de Pago') ?>
              <?php if(!empty($proceso['forma_pago'])): ?><span class="badge bg-success ms-1">&#10003;</span><?php endif; ?>
            </label>
            <textarea name="forma_pago" id="ck_forma_pago" class="form-control form-control-sm"
                      placeholder="Contra Entrega."><?= $formaPagoVal ?></textarea>
          </div>

          <!-- 6. VIGENCIA DE LA OFERTA -->
          <div class="col-12">
            <label class="form-label fw-semibold small">Vigencia de la Oferta</label>
            <input type="text" name="vigencia_oferta" class="form-control form-control-sm"
                   value="<?= e($proceso['vigencia_oferta'] ?? '') ?>" placeholder="Ej: La oferta tendrá una vigencia de 60 días calendario.">
          </div>

          <!-- 7. DECLARACIÓN DE CUMPLIMIENTO -->
          <?php
          $declActiva = (string)($proceso['declaracion_activa'] ?? '1') !== '0';
          $declTexto  = !empty($proceso['declaracion_cumplimiento'])
              ? $proceso['declaracion_cumplimiento']
              : 'Confirmamos que nuestra oferta cumple completamente con todos los términos y condiciones especificados en los términos de referencia (TDR) proporcionados por su institución.';
          ?>
          <div class="col-12">
            <div class="border rounded p-2" style="background:#f0fff4;border-color:#27ae60!important">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="d-flex align-items-center gap-2">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="toggleDecl" name="declaracion_activa" value="1"
                           <?= $declActiva ? 'checked' : '' ?>
                           onchange="
                             document.getElementById('declBody').classList.toggle('d-none', !this.checked);
                             document.getElementById('badgeDecl').textContent = this.checked ? 'Incluida en proforma' : 'Desactivada';
                             document.getElementById('badgeDecl').className = 'badge small ' + (this.checked ? 'bg-success' : 'bg-secondary');
                           ">
                    <label class="form-check-label fw-semibold small" for="toggleDecl">
                      <i class="bi bi-patch-check me-1 text-success"></i>Declaración de Cumplimiento TDR
                    </label>
                  </div>
                  <span class="badge <?= $declActiva ? 'bg-success' : 'bg-secondary' ?> small" id="badgeDecl">
                    <?= $declActiva ? 'Incluida en proforma' : 'Desactivada' ?>
                  </span>
                </div>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:10px"
                        onclick="document.getElementById('declEditor').classList.toggle('d-none')">
                  <i class="bi bi-pencil"></i> Editar texto
                </button>
              </div>
              <div id="declBody" class="<?= !$declActiva ? 'd-none' : '' ?>">
                <div id="declPreview" class="small text-muted px-2 py-1 rounded fst-italic"
                     style="background:#eafaf1;font-size:11px;line-height:1.5">
                  <?= e($declTexto) ?>
                </div>
                <div id="declEditor" class="d-none mt-2">
                  <textarea name="declaracion_cumplimiento" id="declaracion_cumplimiento"
                            class="form-control form-control-sm" rows="3"
                            style="font-size:11px"
                            onkeyup="document.getElementById('declPreview').textContent=this.value"
                            ><?= e($declTexto) ?></textarea>
                  <small class="text-muted">Este texto aparece como sección "8. Declaración de Cumplimiento" en la proforma.</small>
                </div>
              </div>
              <!-- Cuando está desactivado enviar campo vacío para limpiar -->
              <input type="hidden" name="declaracion_activa_off" value="0">
            </div>
          </div>

          <!-- NUESTRO PLUS toggle -->
          <?php
          $plusActivo = (string)($proceso['plus_activo'] ?? '1') !== '0';
          $plusTextoDefault = 'Servicio de Antispam Dedicado - Protección Avanzada Contra el Correo No Deseado
Ofrecemos un sistema antispam dedicado, completamente gratuito durante el periodo de contratación, que garantiza un 99% de efectividad en la detección de spam. Este servicio avanzado incluye cuarentenas configurables, gestión de listas blancas y negras, y un potente sistema AntiSpam/Antivirus que permite establecer políticas personalizadas para filtrar correos por contenido, asunto, remitente y más.

Entre sus principales características se incluyen:
• Sistema de Cuarentena configurable
• Lista blanca y negra general y por usuarios.
• Mail Traking Center para seguimiento, registro y análisis de envío y recepción de correos
• IP dedicada de salida con servicio de IP Whitelist
• Compatibilidad con protocolos IMAP, POP3 y SMTP.
• Conexiones cifradas mediante SSL en Postfix.
• Consultas RBL para detección de IPs en listas negras.
• Configuración de registros DNS esenciales (PTR, DKIM, SPF, DMARC).
• Implementación de políticas de seguridad en clases de servicio (fallos de inicio de sesión, contraseñas seguras, entre otros).
• Escaneo y detección de ataques como spoofing y phishing.
• Control de envíos por tiempo, incluyendo restricciones de acceso, con alerta de correo al administrador, con el fin de evitar el envío masivo de spam en caso de cuentas comprometidas.
Este servicio proporciona una solución integral para la protección y gestión del correo electrónico, asegurando una comunicación segura y libre de amenazas.';
          $plusTexto = !empty($proceso['plus_texto']) ? $proceso['plus_texto'] : $plusTextoDefault;
          ?>
          <div class="col-12">
            <div class="border rounded p-2" style="background:#fffbf0;border-color:#f39c12!important">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="d-flex align-items-center gap-2">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="togglePlus" name="plus_activo" value="1"
                           <?= $plusActivo ? 'checked' : '' ?>
                           onchange="
                             document.getElementById('plusBody').classList.toggle('d-none', !this.checked);
                             document.getElementById('badgePlus').textContent = this.checked ? 'Incluida en proforma' : 'Desactivada';
                             document.getElementById('badgePlus').className = 'badge small ' + (this.checked ? 'bg-warning text-dark' : 'bg-secondary');
                           ">
                    <label class="form-check-label fw-semibold small" for="togglePlus">
                      <i class="bi bi-star-fill me-1 text-warning"></i>NUESTRO PLUS
                    </label>
                  </div>
                  <span class="badge <?= $plusActivo ? 'bg-warning text-dark' : 'bg-secondary' ?> small" id="badgePlus">
                    <?= $plusActivo ? 'Incluida en proforma' : 'Desactivada' ?>
                  </span>
                </div>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:10px"
                        onclick="document.getElementById('plusEditor').classList.toggle('d-none')">
                  <i class="bi bi-pencil"></i> Editar texto
                </button>
              </div>
              <div id="plusBody" class="<?= !$plusActivo ? 'd-none' : '' ?>">
                <div id="plusPreview" class="small text-muted px-2 py-1 rounded fst-italic"
                     style="background:#fef9e7;font-size:11px;line-height:1.5;white-space:pre-line">
                  <?= e($plusTexto) ?>
                </div>
                <div id="plusEditor" class="d-none mt-2">
                  <textarea name="plus_texto" id="plus_texto"
                            class="form-control form-control-sm" rows="8"
                            style="font-size:11px"
                            onkeyup="document.getElementById('plusPreview').textContent=this.value"
                            ><?= e($plusTexto) ?></textarea>
                  <small class="text-muted">Este texto aparece como sección "NUESTRO PLUS" en la proforma.</small>
                </div>
              </div>
              <input type="hidden" name="plus_activo_off" value="0">
            </div>
          </div>

          <!-- ── CAMPOS DINÁMICOS ─────────────────────────────────────── -->
          <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label fw-semibold small mb-0">
                <i class="bi bi-plus-circle text-primary me-1"></i>Campos Adicionales del TDR
              </label>
              <div class="d-flex gap-2">
                <?php if(!empty($plantillasCampos)): ?>
                <select class="form-select form-select-sm" style="width:auto" id="selPlantilla"
                        onchange="cargarPlantilla(this)">
                  <option value="">Cargar plantilla...</option>
                  <?php foreach($plantillasCampos as $pt): ?>
                  <option value="<?= htmlspecialchars(json_encode(json_decode($pt['campos'],true))) ?>">
                    <?= e($pt['nombre']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarCampo()">
                  <i class="bi bi-plus me-1"></i>Agregar campo
                </button>
              </div>
            </div>

            <!-- Sugerencias rápidas -->
            <div class="mb-2" id="sugerencias">
              <small class="text-muted me-1">Sugerencias:</small>
              <?php foreach(['Experiencia del proveedor','Garantía técnica','Personal mínimo requerido','Certificaciones','Forma de pago','Penalidades','Requisitos habilitantes','Propiedad intelectual'] as $sug): ?>
              <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2 me-1 mb-1"
                      onclick="agregarCampoNombre('<?= $sug ?>')">
                + <?= $sug ?>
              </button>
              <?php endforeach; ?>
            </div>

            <!-- Contenedor de campos extra -->
            <div id="camposExtra">
              <?php foreach($camposExtra as $i => $campo): ?>
              <div class="campo-extra-item card mb-2 border-0 bg-light">
                <div class="card-body p-2">
                  <div class="d-flex gap-2 mb-1">
                    <input type="text" name="campo_nombre[]" class="form-control form-control-sm fw-semibold"
                           value="<?= e($campo['nombre']) ?>" placeholder="Nombre del campo *" style="max-width:280px">
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="this.closest('.campo-extra-item').remove()">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                  <textarea name="campo_contenido[]" rows="3" class="form-control form-control-sm"
                            placeholder="Contenido del campo..."><?= e($campo['contenido']) ?></textarea>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Guardar como plantilla -->
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="guardar_plantilla" id="chkPlantilla"
                     onchange="document.getElementById('bloquePlantilla').classList.toggle('d-none',!this.checked)">
              <label class="form-check-label small" for="chkPlantilla">
                Guardar estos campos como plantilla reutilizable
              </label>
            </div>
            <div id="bloquePlantilla" class="d-none mt-2 p-2 border rounded bg-light">
              <div class="row g-2">
                <div class="col-md-6">
                  <input type="text" name="nombre_plantilla" class="form-control form-control-sm"
                         placeholder="Nombre de la plantilla (ej: Hosting cPanel) *">
                </div>
                <div class="col-md-6">
                  <input type="text" name="desc_plantilla" class="form-control form-control-sm"
                         placeholder="Descripción opcional">
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 d-grid">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check-circle me-2"></i>Guardar Datos Técnicos
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ENTREGABLES -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-list-check me-2 text-primary"></i>Entregables del Proceso</span>
    <?php if(can('procesos.*')): ?>
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEntregable">
      <i class="bi bi-plus me-1"></i>Agregar
    </button>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>#</th><th>Entregable</th><th>Fecha Compromiso</th><th>Entregado</th><th>Monto</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach($entregables as $e): ?>
        <tr>
          <td><?= $e['numero_orden'] ?></td>
          <td>
            <strong class="small"><?= e($e['nombre']) ?></strong>
            <?php if($e['descripcion']): ?><br><small class="text-muted"><?= e(truncate($e['descripcion'],60)) ?></small><?php endif; ?>
          </td>
          <td><small><?= formatDate($e['fecha_compromiso']) ?></small>
            <?php if($e['dias_retraso']>0): ?><span class="badge bg-danger ms-1"><?= $e['dias_retraso'] ?> días tarde</span><?php endif; ?></td>
          <td><small><?= formatDate($e['fecha_entrega']) ?></small></td>
          <td><small><?= $e['monto_entregable'] ? money($e['monto_entregable']) : '—' ?></small></td>
          <td><?= estadoBadge($e['estado']) ?></td>
          <td>
            <form method="POST" action="/entregables/<?= $e['id'] ?>/estado" class="d-flex gap-1">
              <?= csrf_field() ?>
              <select name="estado" class="form-select form-select-sm" style="width:120px">
                <?php foreach(['pendiente','en_progreso','entregado','aprobado','observado','cancelado'] as $est): ?>
                <option value="<?= $est ?>" <?= $e['estado']===$est?'selected':'' ?>><?= ucfirst($est) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-primary">✓</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($entregables)): ?>
        <tr><td colspan="7" class="text-center text-muted py-3 small">Sin entregables. Agréguelos manualmente o use el análisis IA.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- EXPEDIENTE DIGITAL -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-folder2 me-2 text-warning"></i>Expediente Digital</div>
  <div class="card-body">
    <form method="POST" action="/procesos/<?= $proceso['id'] ?>/documentos"
          enctype="multipart/form-data" class="row g-2 mb-3 p-3 bg-light rounded">
      <?= csrf_field() ?>
      <div class="col-md-3">
        <select name="categoria" class="form-select form-select-sm">
          <?php foreach(['informe_necesidad'=>'00 Informe de Necesidad','tdr'=>'01 TDR / Especificaciones Técnicas','orden_compra'=>'02 Orden de Compra','proforma'=>'03 Proformas','doc_proveedor'=>'04 Docs. Proveedor','garantia'=>'05 Garantía Técnica','informe_tecnico'=>'06 Informes Técnicos','acta_entrega'=>'07 Actas de Entrega','factura'=>'08 Facturación','solicitud_pago'=>'09 Solicitud Pago','comunicacion'=>'10 Comunicaciones','imagenes_f2'=>'11 Imágenes del Proceso','otro'=>'12 Otros'] as $k=>$v): ?>
          <option value="<?= $k ?>"><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6"><input type="file" name="archivo" class="form-control form-control-sm" required accept=".pdf,.doc,.docx,.jpg,.png,.xlsx"></div>
      <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Subir</button></div>
    </form>

    <div class="row g-2">
      <?php
      $categorias = [
        'informe_necesidad'=> ['00','Informe de Necesidad',  'file-earmark-check',   'primary'],
        'tdr'           => ['01','TDR / Especif. Técnicas', 'file-earmark-ruled',   'primary'],
        'orden_compra'  => ['02','Orden de Compra',         'file-earmark-pdf',     'danger'],
        'proforma'      => ['03','Proformas',               'file-earmark-text',    'info'],
        'doc_proveedor' => ['04','Docs. Proveedor',         'person-badge',         'secondary'],
        'garantia'      => ['05','Garantía Técnica',        'shield-check',         'warning'],
        'informe_tecnico'=> ['06','Informes Técnicos',      'file-earmark-richtext','success'],
        'acta_entrega'  => ['07','Actas de Entrega',        'pen',                  'success'],
        'factura'       => ['08','Facturación',             'receipt',              'success'],
        'solicitud_pago'=> ['09','Solicitud Pago',          'cash-coin',            'success'],
        'comunicacion'  => ['10','Comunicaciones',          'envelope',             'secondary'],
        'imagenes_f2'   => ['11','Imágenes del Proceso',    'images',               'info'],
        'otro'          => ['12','Otros Documentos',        'folder2',              'secondary'],
      ];
      foreach($categorias as $cat=>[$num,$label,$icon,$color]):
        $docs = $documentos[$cat] ?? [];
      ?>
      <div class="col-6 col-md-3">
        <div class="card border h-100 <?= !empty($docs) ? 'border-success' : '' ?>">
          <div class="card-body p-2 text-center">
            <i class="bi bi-<?= $icon ?> fs-4 <?= empty($docs) ? 'text-muted' : "text-{$color}" ?>"></i>
            <div class="small fw-semibold mt-1"><?= $num ?> <?= $label ?></div>
            <?php if(empty($docs)): ?>
              <div class="badge bg-secondary mt-1">0 archivos</div>
            <?php else: ?>
              <div class="badge bg-success mt-1"><?= count($docs) ?> archivo<?= count($docs)!==1?'s':'' ?></div>
              <?php foreach($docs as $d): ?>
              <div class="mt-1 text-start">
                <a href="/documentos/<?= $d['id'] ?>/descargar"
                   class="small text-decoration-none d-flex align-items-center gap-1"
                   title="<?= e($d['nombre_original']) ?>">
                  <i class="bi bi-download text-primary flex-shrink-0"></i>
                  <span class="text-truncate" style="max-width:100px"><?= e($d['nombre_original']) ?></span>
                </a>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- MODALES -->
<!-- Modal Entregable -->
<div class="modal fade" id="modalEntregable" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Agregar Entregable</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="/procesos/<?= $proceso['id'] ?>/entregables">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-2"><label class="form-label">N°</label><input type="number" name="numero_orden" class="form-control" value="<?= count($entregables)+1 ?>"></div>
        <div class="col-10"><label class="form-label">Nombre del Entregable *</label><input type="text" name="nombre" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
        <div class="col-6"><label class="form-label">Fecha Compromiso</label><input type="date" name="fecha_compromiso" class="form-control"></div>
        <div class="col-6"><label class="form-label">Monto (USD)</label><input type="number" step="0.01" name="monto_entregable" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
    </form>
  </div></div>
</div>

<!-- Modal Factura -->
<div class="modal fade" id="modalFactura" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Registrar Factura</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="/facturas">
      <?= csrf_field() ?>
      <input type="hidden" name="proceso_id" value="<?= $proceso['id'] ?>">
      <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">N° Factura SRI *</label><input type="text" name="numero_sri" class="form-control" placeholder="001-001-000000001" required></div>
        <div class="col-6"><label class="form-label">Fecha Emisión *</label><input type="date" name="fecha_emision" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-4"><label class="form-label">Subtotal</label><input type="number" step="0.01" name="monto_subtotal" class="form-control" id="fSubtotal" oninput="calcFactura()"></div>
        <div class="col-4"><label class="form-label">IVA (15%)</label><input type="number" step="0.01" name="monto_iva" class="form-control" id="fIva"></div>
        <div class="col-4"><label class="form-label">Total *</label><input type="number" step="0.01" name="monto_total" class="form-control" id="fTotal" required></div>
        <div class="col-6"><label class="form-label">Ret. Fuente</label><input type="number" step="0.01" name="retencion_fuente" class="form-control" value="0"></div>
        <div class="col-6"><label class="form-label">Ret. IVA</label><input type="number" step="0.01" name="retencion_iva" class="form-control" value="0"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Registrar Factura</button></div>
    </form>
  </div></div>
</div>

<!-- Modal Pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Registrar Pago</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" id="formPago" action="/facturas/__ID__/pago">
      <?= csrf_field() ?>
      <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Fecha de Pago *</label><input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-6"><label class="form-label">Monto Pagado *</label><input type="number" step="0.01" name="monto_pagado" class="form-control" required></div>
        <div class="col-6">
          <label class="form-label">Tipo de Pago</label>
          <select name="tipo_pago" class="form-select">
            <option value="transferencia">Transferencia Bancaria</option>
            <option value="cheque">Cheque</option>
            <option value="caja_fiscal">Caja Fiscal</option>
          </select>
        </div>
        <div class="col-6"><label class="form-label">Referencia / N° SPI</label><input type="text" name="referencia" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Confirmar Pago</button></div>
    </form>
  </div></div>
</div>

<script>
function calcFactura(){
  const s=parseFloat(document.getElementById('fSubtotal').value)||0;
  const iva=Math.round(s*0.15*100)/100;
  document.getElementById('fIva').value=iva;
  document.getElementById('fTotal').value=Math.round((s+iva)*100)/100;
}
// Set factura ID in pago modal
document.getElementById('modalPago').addEventListener('show.bs.modal',function(e){
  const btn=e.relatedTarget;
  const fId=btn.getAttribute('data-factura-id');
  document.getElementById('formPago').action='/facturas/'+fId+'/pago';
});

function agregarCampo(nombre, contenido) {
  const div = document.createElement('div');
  div.className = 'campo-extra-item card mb-2 border-0 bg-light';
  div.innerHTML = `
    <div class="card-body p-2">
      <div class="d-flex gap-2 mb-1">
        <input type="text" name="campo_nombre[]" class="form-control form-control-sm fw-semibold"
               value="${nombre||''}" placeholder="Nombre del campo *" style="max-width:280px">
        <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                onclick="this.closest('.campo-extra-item').remove()">
          <i class="bi bi-trash"></i>
        </button>
      </div>
      <textarea name="campo_contenido[]" rows="3" class="form-control form-control-sm"
                placeholder="Contenido del campo...">${contenido||''}</textarea>
    </div>`;
  document.getElementById('camposExtra').appendChild(div);
  div.querySelector('input').focus();
}

function agregarCampoNombre(nombre) {
  agregarCampo(nombre, '');
  // Ocultar la sugerencia usada
  document.querySelectorAll('#sugerencias button').forEach(b => {
    if(b.textContent.includes(nombre)) b.classList.add('d-none');
  });
}

function cargarPlantilla(sel) {
  if(!sel.value) return;
  try {
    const campos = JSON.parse(sel.value);
    campos.forEach(c => agregarCampo(c.nombre, ''));
    sel.value = '';
  } catch(e) {}
}
</script>

<!-- CKEditor 5 — editor WYSIWYG con soporte completo de tablas e imágenes -->
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script src="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.umd.js"></script>
<style>
/* Editores CKEditor en formulario Fase 2 */
.ck-editor__editable { min-height: 220px; max-height: 500px; overflow-y: auto; font-size: 13px; }
.ck-editor__editable img { max-width: 100%; height: auto; }
/* Sección card en modal */
.seccion-card { border-left: 4px solid #dee2e6; transition: border-color .2s; }
.seccion-card.seleccionada { border-left-color: #0d6efd; }
.seccion-card.tiene-campo  { border-left-color: #198754; }
.seccion-card.tiene-campo.seleccionada { border-left-color: #198754; }
/* Acordeón del modal */
#listaSecciones .seccion-editor-wrap {
  border-top: 1px solid #dee2e6;
  padding: 12px;
  background: #fafafa;
}
</style>
<script>
// ════════════════════════════════════════════════════════════════════════
// CKEditor 5 — instancias para los 3 editores principales de Fase 2
// ════════════════════════════════════════════════════════════════════════
const { ClassicEditor, Essentials, Bold, Italic, Underline,
        List, Paragraph, Heading, Table, TableToolbar,
        Image, ImageUpload, ImageInsert, ImageResize, ImageToolbar, ImageCaption, ImageStyle,
        Base64UploadAdapter, Link, BlockQuote, Indent } = CKEDITOR;

const ckFase2Config = {
  plugins: [
    Essentials, Bold, Italic, Underline,
    List, Paragraph, Heading, Table, TableToolbar,
    Image, ImageUpload, ImageInsert, ImageResize, ImageToolbar, ImageCaption, ImageStyle,
    Base64UploadAdapter, Link, BlockQuote, Indent
  ],
  toolbar: {
    items: [
      'heading', '|',
      'bold', 'italic', 'underline', '|',
      'bulletedList', 'numberedList', 'blockQuote', '|',
      'insertImage', 'insertTable', 'link', '|',
      'outdent', 'indent', '|',
      'undo', 'redo'
    ]
  },
  image: {
    toolbar: ['imageStyle:inline','imageStyle:block','|','toggleImageCaption','imageTextAlternative','|','resizeImage'],
    resizeOptions: [
      { name: 'resizeImage:original', value: null, label: 'Original' },
      { name: 'resizeImage:50',       value: '50',  label: '50%' },
      { name: 'resizeImage:75',       value: '75',  label: '75%' },
    ],
    upload: { types: ['jpeg','jpg','png','gif','webp'] }
  },
  table: { contentToolbar: ['tableColumn','tableRow','mergeTableCells'] }
};

let qEspec, qMetod, qPago;

document.addEventListener('DOMContentLoaded', () => {
  const campos = [
    { id: 'ck_especificaciones', assign: e => qEspec = e },
    { id: 'ck_metodologia',      assign: e => qMetod = e },
    { id: 'ck_forma_pago',       assign: e => qPago  = e },
  ];

  campos.forEach(({ id, assign }) => {
    const ta = document.getElementById(id);
    if (!ta) return;
    const edDiv = document.createElement('div');
    edDiv.id = 'ckdiv_' + id;
    ta.parentNode.insertBefore(edDiv, ta);
    ta.style.display = 'none';

    ClassicEditor.create(edDiv, ckFase2Config)
      .then(editor => {
        if (ta.value.trim()) editor.setData(ta.value);
        assign(editor);
      })
      .catch(console.error);
  });
});

// Sync CKEditor → textarea antes de submit
document.getElementById('formFase2').addEventListener('submit', function() {
  syncQuillFormato();
});

function syncQuillFormato() {
  if (qEspec) document.getElementById('ck_especificaciones').value = qEspec.getData();
  if (qMetod) document.getElementById('ck_metodologia').value      = qMetod.getData();
  if (qPago)  document.getElementById('ck_forma_pago').value       = qPago.getData();
}

function fillCkFields(d) {
  if (qEspec && d.especificaciones_tecnicas) qEspec.setData(d.especificaciones_tecnicas);
  if (qMetod && d.metodologia_trabajo)       qMetod.setData(d.metodologia_trabajo);
  if (qPago  && d.forma_pago)                qPago.setData(d.forma_pago);
}

// ════════════════════════════════════════════════════════════════════════
// EXTRACTOR TDR — sistema de secciones
// ════════════════════════════════════════════════════════════════════════
let tdrSecciones = [];
let tdrFile      = null;
let quillsModal  = {}; // idx → Quill instance

function onPdfTdrSeleccionado(input) {
  tdrFile = input.files[0] || null;
  const nombre = tdrFile ? tdrFile.name : 'Arrastra el PDF del TDR aquí o haz clic para seleccionar';
  document.getElementById('pdfTdrNombre').textContent = nombre;
  document.getElementById('btnAnalizarTdr').disabled  = !tdrFile;
  document.getElementById('dropZoneTdr').style.background = tdrFile ? '#e8ffe8' : '';
}

function handleDropTdr(event) {
  event.preventDefault();
  document.getElementById('dropZoneTdr').style.background = '';
  const file = event.dataTransfer.files[0];
  if (!file) return;
  const inp = document.getElementById('pdfTdrInput');
  const dt  = new DataTransfer();
  dt.items.add(file);
  inp.files = dt.files;
  onPdfTdrSeleccionado(inp);
}

async function analizarTdr() {
  if (!tdrFile) return;
  const btn    = document.getElementById('btnAnalizarTdr');
  const alerta = document.getElementById('alertaTdr');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Analizando TDR...';
  alerta.className = 'd-none';

  const fd = new FormData();
  fd.append('pdf', tdrFile);
  fd.append('_csrf_token', document.querySelector('input[name="_csrf_token"]')?.value || '');

  try {
    const resp = await fetch('/ia/extraer-secciones', { method: 'POST', body: fd });
    const data = await resp.json();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-magic me-1"></i>Analizar TDR y detectar secciones';

    if (!data.ok) {
      alerta.className = 'mt-2 alert alert-danger small py-2';
      alerta.textContent = 'Error: ' + (data.error || 'No se pudo analizar el PDF');
      return;
    }

    if (!data.secciones || data.secciones.length === 0) {
      alerta.className = 'mt-2 alert alert-warning small py-2';
      alerta.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>No se detectaron secciones. El PDF puede ser escaneado (imagen) o tener formato no estándar.';
      return;
    }

    tdrSecciones = data.secciones;
    alerta.className = 'mt-2 alert alert-success small py-2';
    alerta.innerHTML = `<i class="bi bi-check-circle me-1"></i><strong>${data.total} secciones detectadas.</strong> Revisa y selecciona cuáles importar.`;

    renderModalSecciones();
    new bootstrap.Modal(document.getElementById('modalSeccionesTdr')).show();

  } catch(err) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-magic me-1"></i>Analizar TDR y detectar secciones';
    alerta.className = 'mt-2 alert alert-danger small py-2';
    alerta.textContent = 'Error de red: ' + err.message;
  }
}

// ── Renderizar acordeón de secciones ────────────────────────────────────
function renderModalSecciones() {
  quillsModal = {};
  const lista = document.getElementById('listaSecciones');
  lista.innerHTML = '';

  tdrSecciones.forEach((sec, idx) => {
    const tieneCampo  = !!sec.campo_destino;
    const selDefault  = tieneCampo; // Pre-seleccionar las que tienen campo mapeado
    const badgeColor  = tieneCampo ? 'success' : 'secondary';
    const badgeLabel  = tieneCampo ? '→ ' + nombreCampoLabel(sec.campo_destino) : 'Campo adicional';
    const cardClass   = tieneCampo ? 'tiene-campo' : '';
    const checked     = selDefault ? 'checked' : '';

    const card = document.createElement('div');
    card.className = `seccion-card ${cardClass} ${selDefault ? 'seleccionada' : ''} mb-2 rounded`;
    card.id = 'secCard' + idx;
    card.innerHTML = `
      <div class="d-flex align-items-center gap-2 px-3 py-2" style="cursor:pointer" onclick="toggleSeccion(${idx})">
        <!-- Checkbox -->
        <div class="form-check mb-0" onclick="event.stopPropagation()">
          <input class="form-check-input seccion-chk" type="checkbox" id="chk${idx}" data-idx="${idx}" ${checked}
                 onchange="onChkChange(${idx}, this.checked)">
        </div>
        <!-- Info -->
        <div class="flex-grow-1">
          <div class="fw-semibold small">
            ${escHtml(sec.titulo)}
            <span class="badge bg-${badgeColor} ms-1" style="font-size:10px">${badgeLabel}</span>
          </div>
          <div class="text-muted" style="font-size:10px">${sec.longitud.toLocaleString()} chars extraídos del PDF</div>
        </div>
        <!-- Flecha acordeón -->
        <i class="bi bi-chevron-${selDefault ? 'up' : 'down'} text-muted toggle-icon" id="icon${idx}"></i>
      </div>
      <!-- Editor Quill (expandido/colapsado) -->
      <div class="seccion-editor-wrap ${selDefault ? '' : 'd-none'}" id="editorWrap${idx}">
        <div class="d-flex gap-2 mb-2 align-items-center">
          <small class="text-muted">
            <i class="bi bi-pencil me-1"></i>Edita el contenido antes de importar:
          </small>
          ${tieneCampo
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:10px">
                 Se importará en: <strong>${nombreCampoLabel(sec.campo_destino)}</strong>
               </span>`
            : `<span class="badge bg-secondary-subtle text-secondary border" style="font-size:10px">
                 Se agregará como campo adicional
               </span>`
          }
        </div>
        <div id="quillSec${idx}" style="background:#fff;min-height:150px"></div>
      </div>`;
    lista.appendChild(card);
  });

  actualizarResumen();

  // Inicializar Quill en las secciones pre-seleccionadas
  tdrSecciones.forEach((sec, idx) => {
    const chk = document.getElementById('chk' + idx);
    if (chk && chk.checked) initQuillSeccion(idx);
  });
}

function initQuillSeccion(idx) {
  if (quillsModal[idx]) return;
  const sec = tdrSecciones[idx];
  const q = new Quill('#quillSec' + idx, {
    theme: 'snow',
    modules: {
      toolbar: [
        ['bold','italic','underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['clean'],
      ]
    }
  });
  // Cargar HTML extraído del PDF
  if (sec.html) {
    q.clipboard.dangerouslyPasteHTML(sec.html);
  } else if (sec.contenido) {
    q.setText(sec.contenido);
  }
  // Guardar cambios en tiempo real
  q.on('text-change', () => { tdrSecciones[idx].html = q.root.innerHTML; });
  quillsModal[idx] = q;
}

function toggleSeccion(idx) {
  const wrap = document.getElementById('editorWrap' + idx);
  const icon = document.getElementById('icon' + idx);
  const card = document.getElementById('secCard' + idx);
  const oculto = wrap.classList.contains('d-none');
  wrap.classList.toggle('d-none', !oculto);
  icon.className = `bi bi-chevron-${oculto ? 'up' : 'down'} text-muted toggle-icon`;
  if (oculto) initQuillSeccion(idx);
}

function onChkChange(idx, checked) {
  const card = document.getElementById('secCard' + idx);
  const wrap = document.getElementById('editorWrap' + idx);
  const icon = document.getElementById('icon' + idx);
  card.classList.toggle('seleccionada', checked);
  wrap.classList.toggle('d-none', !checked);
  icon.className = `bi bi-chevron-${checked ? 'up' : 'down'} text-muted toggle-icon`;
  if (checked) initQuillSeccion(idx);
  actualizarResumen();
}

function seleccionarTodas(valor) {
  document.querySelectorAll('.seccion-chk').forEach(chk => {
    const idx = parseInt(chk.dataset.idx);
    if (chk.checked !== valor) {
      chk.checked = valor;
      onChkChange(idx, valor);
    }
  });
}

function actualizarResumen() {
  const n = document.querySelectorAll('.seccion-chk:checked').length;
  document.getElementById('resumenSeleccion').textContent =
    `${n} sección${n !== 1 ? 'es' : ''} seleccionada${n !== 1 ? 's' : ''}`;
}

// ── Importar secciones → campos del formulario ─────────────────────────
function importarSeccionesSeleccionadas() {
  // Sync todos los editores del modal
  Object.entries(quillsModal).forEach(([idx, q]) => {
    tdrSecciones[idx].html = q.root.innerHTML;
  });

  const importados  = [];
  let   extraCount  = 0;

  tdrSecciones.forEach((sec, idx) => {
    const chk = document.getElementById('chk' + idx);
    if (!chk || !chk.checked) return;

    const html = sec.html || ('<p>' + escHtml(sec.contenido) + '</p>');

    if (sec.campo_destino) {
      switch (sec.campo_destino) {
        case 'especificaciones_tecnicas':
          if (qEspec) qEspec.setData(html);
          else        document.getElementById('ck_especificaciones').value = html;
          importados.push('Especificaciones Técnicas');
          break;
        case 'metodologia_trabajo':
          if (qMetod) qMetod.setData(html);
          else        document.getElementById('ck_metodologia').value = html;
          importados.push('Metodología');
          break;
        case 'forma_pago':
          if (qPago) qPago.setData(html);
          else       document.getElementById('ck_forma_pago').value = html;
          importados.push('Forma de Pago');
          break;
        case 'plazo_texto': {
          const el = document.querySelector('[name="plazo_texto"]');
          if (el) { el.value = sec.contenido.substring(0, 500); el.classList.add('border-success'); }
          importados.push('Plazo');
          break;
        }
        case 'vigencia_oferta': {
          const el = document.querySelector('[name="vigencia_oferta"]');
          if (el) { el.value = sec.contenido.substring(0, 200); el.classList.add('border-success'); }
          importados.push('Vigencia');
          break;
        }
        case 'declaracion_cumplimiento': {
          const el = document.querySelector('[name="declaracion_cumplimiento"]');
          if (el) { el.value = sec.contenido; el.classList.add('border-success'); }
          importados.push('Declaración');
          break;
        }
      }
    } else {
      agregarCampo(sec.titulo, html);
      extraCount++;
    }
  });

  // Cerrar modal y limpiar
  bootstrap.Modal.getInstance(document.getElementById('modalSeccionesTdr'))?.hide();
  quillsModal = {};

  // Abrir panel Fase 2
  const panel = document.getElementById('fase2Panel');
  if (panel && !panel.classList.contains('show')) {
    new bootstrap.Collapse(panel, { toggle: false }).show();
  }

  // Alerta de éxito
  const alerta = document.getElementById('alertaTdr');
  alerta.className = 'mt-2 alert alert-success small py-2';
  alerta.innerHTML = `<i class="bi bi-check-circle me-1"></i>
    <strong>Importado correctamente:</strong> ${importados.join(', ')}
    ${extraCount > 0 ? ` + ${extraCount} campo${extraCount > 1 ? 's' : ''} adicional${extraCount > 1 ? 'es' : ''}` : ''}.
    Revisa y ajusta el contenido antes de guardar.`;

  panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Helpers ─────────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Títulos personalizables de secciones Fase 2 ──────────────────────────
document.querySelectorAll('.titulo-seccion-edit').forEach(function(btn) {
  btn.addEventListener('click', function() {
    const key    = this.dataset.key;
    const span   = document.querySelector('.titulo-seccion-texto[data-key="' + key + '"]');
    const hidden = document.querySelector('.titulo-seccion-hidden[data-key="' + key + '"]');
    const current = span.textContent;
    const input = document.createElement('input');
    input.type  = 'text';
    input.value = current;
    input.className = 'form-control form-control-sm d-inline-block fw-semibold';
    input.style.maxWidth = '380px';
    input.style.fontSize = '.8rem';
    span.replaceWith(input);
    this.innerHTML = '<i class="bi bi-check-lg" style="font-size:.7rem"></i>';
    this.title = 'Guardar título';
    input.focus();
    const guardar = () => {
      const val = input.value.trim() || current;
      const newSpan = document.createElement('span');
      newSpan.className = 'titulo-seccion-texto';
      newSpan.dataset.key = key;
      newSpan.textContent = val;
      input.replaceWith(newSpan);
      hidden.value = val;
      btn.innerHTML = '<i class="bi bi-pencil" style="font-size:.7rem"></i>';
      btn.title = 'Editar título';
    };
    this.addEventListener('click', guardar, { once: true });
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); guardar(); }
    });
  });
});

function nombreCampoLabel(campo) {
  return {
    especificaciones_tecnicas: 'Especif. Técnicas',
    metodologia_trabajo:       'Metodología',
    forma_pago:                'Forma de Pago',
    plazo_texto:               'Plazo',
    vigencia_oferta:           'Vigencia',
    declaracion_cumplimiento:  'Declaración',
  }[campo] || campo;
}
</script>

<script>
// ── ITEMS: recalcular fila al cambiar precio unitario ────────────────────
function recalcularItem(input) {
  const tr        = input.closest('tr');
  const cantInput = tr.querySelector('.item-cant-input');
  const cantidad  = cantInput ? (parseFloat(cantInput.value) || 0) : (parseFloat(tr.dataset.cantidad) || 0);
  const precio    = parseFloat(input.value) || 0;
  const total     = cantidad * precio;
  tr.querySelector('.item-total').textContent = '$' + total.toFixed(2);
  recalcularTotalGeneral();
}

function addItemRow() {
  const tbody = document.querySelector('#tablaItems tbody');
  const n = tbody.querySelectorAll('tr').length + 1;
  const tr = document.createElement('tr');
  tr.dataset.num = n; tr.dataset.cpc = ''; tr.dataset.cpcDesc = ''; tr.dataset.unidad = ''; tr.dataset.cantidad = '1'; tr.dataset.manual = '1';
  tr.innerHTML = `
    <td class="text-center align-middle">${n}</td>
    <td class="align-middle small text-muted"></td>
    <td class="align-middle"><input type="text" class="form-control form-control-sm item-desc-input" placeholder="Descripción del producto/servicio"></td>
    <td class="align-middle"><input type="text" class="form-control form-control-sm item-unidad-input" value="Global" style="width:70px"></td>
    <td class="text-center align-middle"><input type="number" class="form-control form-control-sm text-center item-cant-input" value="1" min="0.01" step="0.01" style="width:70px" onchange="recalcularItem(this.closest('tr').querySelector('.item-precio'))"></td>
    <td class="text-end align-middle">
      <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end p-1 item-precio" style="width:80px;margin-left:auto" value="0.00" onchange="recalcularItem(this)">
    </td>
    <td class="text-end align-middle fw-semibold item-total">$0.00</td>
  `;
  tbody.appendChild(tr);
}

function recalcularTotalGeneral() {
  let subtotal = 0;
  document.querySelectorAll('#tablaItems tbody tr').forEach(tr => {
    const txt = tr.querySelector('.item-total')?.textContent.replace(/[$,]/g,'') || '0';
    subtotal += parseFloat(txt) || 0;
  });
  const iva   = Math.round(subtotal * 0.15 * 100) / 100;
  const total = Math.round((subtotal + iva) * 100) / 100;

  const elSub = document.getElementById('itemsSubtotal');
  const elIva = document.getElementById('itemsIva');
  const elTot = document.getElementById('itemsTotalGeneral');
  const elMonto = document.getElementById('montoTotalInput');

  if (elSub) elSub.textContent = '$' + subtotal.toFixed(2);
  if (elIva) elIva.textContent = '$' + iva.toFixed(2);
  if (elTot) elTot.textContent = '$' + total.toFixed(2);
  // Actualizar el campo Monto (readonly) con el total con IVA
  if (elMonto) elMonto.value = total.toFixed(2);
}

// Antes de enviar el form, serializar ítems + sincronizar TinyMCE
document.getElementById('formFase2').addEventListener('submit', function() {
  syncQuillFormato(); // Sincroniza editores Quill → textarea

  // Serializar ítems a JSON
  const inputJson = document.getElementById('itemsJsonInput');
  if (inputJson) {
    const rows = document.querySelectorAll('#tablaItems tbody tr');
    const data = Array.from(rows).map(tr => {
      const precio      = parseFloat(tr.querySelector('.item-precio')?.value) || 0;
      const cantInput   = tr.querySelector('.item-cant-input');
      const cantidad    = cantInput ? (parseFloat(cantInput.value) || 0) : (parseFloat(tr.dataset.cantidad) || 0);
      const descInput   = tr.querySelector('.item-desc-input');
      const descripcion = descInput ? descInput.value.trim() : tr.querySelector('td:nth-child(3)').textContent.trim();
      const unidadInput = tr.querySelector('.item-unidad-input');
      const unidad      = unidadInput ? unidadInput.value.trim() : (tr.dataset.unidad || '');
      return {
        numero:          parseInt(tr.dataset.num) || 0,
        cpc:             tr.dataset.cpc          || '',
        cpc_descripcion: tr.dataset.cpcDesc      || '',
        descripcion:     descripcion,
        unidad:          unidad,
        cantidad:        cantidad,
        precio_unitario: precio,
        precio_total:    +(cantidad * precio).toFixed(2),
      };
    });
    inputJson.value = JSON.stringify(data);

    // Recalcular monto con IVA y escribirlo si hay ítems (si no, se usa el valor manual)
    if (data.length > 0) {
      const subtotal = data.reduce((s, i) => s + i.precio_total, 0);
      const totalConIva = Math.round((subtotal * 1.15) * 100) / 100;
      const montoInput = document.getElementById('montoTotalInput');
      if (montoInput && totalConIva > 0) montoInput.value = totalConIva.toFixed(2);
    }
  }
});
</script>