<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <a href="/instituciones" class="btn btn-sm btn-outline-secondary mb-2">
      <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
    <h4 class="fw-bold mb-1"><i class="bi bi-building me-2"></i><?= e($inst['nombre']) ?></h4>
    <p class="text-muted mb-0 small">RUC: <?= e($inst['ruc'] ?? '—') ?></p>
  </div>
  <?php if(can('instituciones.*')): ?>
  <div class="d-flex gap-2">
    <a href="/instituciones/<?= $inst['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-pencil me-1"></i>Editar
    </a>
    <form method="POST" action="/instituciones/<?= $inst['id'] ?>/eliminar"
          onsubmit="return confirm('¿Eliminar esta institución? Esta acción no se puede deshacer.')">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash me-1"></i>Eliminar
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<div class="row g-4">
  <!-- Info principal -->
  <div class="col-md-5">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-semibold">Datos de la Institución</div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr><th class="text-muted small" width="40%">Nombre</th><td><?= e($inst['nombre']) ?></td></tr>
          <tr><th class="text-muted small">RUC</th><td><?= e($inst['ruc'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small">Tipo</th><td><?= e($inst['tipo'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small">Ciudad</th><td><?= e($inst['ciudad'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small">Dirección</th><td><?= e($inst['direccion'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small">Administrador</th><td><?= e($inst['administrador_nombre'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small">Email</th><td><?= e($inst['administrador_email'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small">Cargo</th><td><?= e($inst['administrador_cargo'] ?? '—') ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <!-- Procesos asociados -->
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold d-flex justify-content-between">
        Procesos Asociados
        <span class="badge bg-secondary"><?= count($procesos) ?></span>
      </div>
      <?php if(empty($procesos)): ?>
      <div class="card-body text-muted small">Sin procesos registrados.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>N° Proceso</th>
              <th>Objeto</th>
              <th class="text-end">Monto</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($procesos as $p): ?>
            <tr>
              <td><a href="/procesos/<?= $p['id'] ?>" class="small text-decoration-none"><?= e($p['numero_proceso']) ?></a></td>
              <td class="small"><?= e(truncate($p['objeto_contratacion'] ?? '', 50)) ?></td>
              <td class="text-end small">$<?= number_format((float)($p['monto_total'] ?? 0), 2) ?></td>
              <td><?= estadoBadge($p['estado']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SECCIÓN DOMINIOS
     ══════════════════════════════════════════════════════════════ -->
<div class="mt-4" id="dominios">
  <?php
  require_once APP_PATH . '/Services/DominioRdapService.php';
  $dominios = Dominio::porInstitucion((int)$inst['id']);

  // Helpers de semáforo
  $semaforo = function(string $estado): string {
    return match($estado) {
      'vencido'    => '<span class="badge bg-danger">������ Vencido</span>',
      'por_vencer' => '<span class="badge bg-warning text-dark">������ Por vencer</span>',
      'suspendido' => '<span class="badge bg-secondary">⏸ Suspendido</span>',
      'cancelado'  => '<span class="badge bg-dark">✖ Cancelado</span>',
      default      => '<span class="badge bg-success">������ Activo</span>',
    };
  };
  ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold">
        <i class="bi bi-globe2 me-1 text-primary"></i>Dominios Web
        <span class="badge bg-secondary ms-1"><?= count($dominios) ?></span>
      </span>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarDominio">
        <i class="bi bi-plus me-1"></i>Agregar dominio
      </button>
    </div>

    <?php if(empty($dominios)): ?>
    <div class="card-body text-center py-4 text-muted">
      <i class="bi bi-globe2 fs-2 d-block mb-2 opacity-25"></i>
      <p class="mb-1">No hay dominios registrados para esta institución.</p>
      <small>Agrega un dominio para monitorear su fecha de caducidad.</small>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" style="font-size:13px">
        <thead class="table-light">
          <tr>
            <th>Dominio</th>
            <th class="text-center">Estado</th>
            <th>Caducidad</th>
            <th class="text-center">Días</th>
            <th>Titular</th>
            <th>Nameservers</th>
            <th>Registrado</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($dominios as $d): ?>
          <?php
            $dias = DominioRdapService::diasHastaVencimiento($d['fecha_caducidad']);
            $diasTexto = $dias === null ? '—'
                : ($dias < 0 ? '<span class="text-danger fw-bold">Venció hace '.abs($dias).' días</span>'
                : ($dias <= 7 ? '<span class="text-danger fw-bold">'.$dias.' días</span>'
                : ($dias <= 30 ? '<span class="text-warning fw-bold">'.$dias.' días</span>'
                : '<span class="text-success">'.$dias.' días</span>')));
            $ns = $d['nameservers'] ? json_decode($d['nameservers'], true) : [];
          ?>
          <tr id="fila-dominio-<?= $d['id'] ?>">
            <td>
              <a href="https://<?= e($d['dominio_completo']) ?>" target="_blank"
                 class="fw-semibold text-decoration-none">
                <?= e($d['dominio_completo']) ?>
                <i class="bi bi-box-arrow-up-right ms-1 small opacity-50"></i>
              </a>
            </td>
            <td class="text-center"><?= $semaforo($d['estado']) ?></td>
            <td>
              <?php if($d['fecha_caducidad']): ?>
                <span class="<?= $d['estado'] === 'vencido' ? 'text-danger fw-bold' : '' ?>">
                  <?= date('d/m/Y', strtotime($d['fecha_caducidad'])) ?>
                </span>
              <?php else: ?>
                <span class="text-muted small">Sin fecha</span>
              <?php endif; ?>
            </td>
            <td class="text-center"><?= $diasTexto ?></td>
            <td class="text-muted small"><?= e($d['titular'] ?? '—') ?></td>
            <td class="text-muted small" style="font-size:11px">
              <?php if(!empty($ns)): ?>
                <?= implode('<br>', array_map('e', $ns)) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-muted small">
              <?= $d['fecha_registro'] ? date('d/m/Y', strtotime($d['fecha_registro'])) : '—' ?>
            </td>
            <td class="text-end pe-2">
              <div class="d-flex gap-1 justify-content-end">
                <!-- Botón actualizar RDAP -->
                <button class="btn btn-xs btn-outline-info py-0 px-2"
                        title="Actualizar datos RDAP desde NIC.ec"
                        onclick="actualizarRdap(<?= $d['id'] ?>, '<?= e($d['dominio_completo']) ?>', this)">
                  <i class="bi bi-arrow-clockwise"></i>
                </button>
                <!-- Botón editar -->
                <button class="btn btn-xs btn-outline-secondary py-0 px-2"
                        title="Editar"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarDominio"
                        data-id="<?= $d['id'] ?>"
                        data-dominio="<?= e($d['dominio_completo']) ?>"
                        data-caducidad="<?= e($d['fecha_caducidad'] ?? '') ?>"
                        data-registro="<?= e($d['fecha_registro'] ?? '') ?>"
                        data-dias-alerta="<?= (int)$d['dias_alerta'] ?>"
                        data-costo="<?= e($d['costo_renovacion'] ?? '') ?>"
                        data-notas="<?= e($d['notas'] ?? '') ?>"
                        data-renovacion-auto="<?= $d['renovacion_auto'] ? '1' : '0' ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <!-- Botón eliminar -->
                <form method="POST" action="/dominios/<?= $d['id'] ?>/eliminar"
                      onsubmit="return confirm('¿Eliminar el dominio <?= e($d['dominio_completo']) ?>?')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2" title="Eliminar">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer text-muted small py-1">
      <i class="bi bi-info-circle me-1"></i>
      Haz clic en <i class="bi bi-arrow-clockwise"></i> para actualizar los datos desde RDAP/NIC.ec en tiempo real.
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Modal: Agregar dominio ──────────────────────────────────────── -->
<div class="modal fade" id="modalAgregarDominio" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="/dominios" id="formAgregarDominio">
        <?= csrf_field() ?>
        <input type="hidden" name="institucion_id" value="<?= $inst['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-globe2 me-2"></i>Agregar Dominio</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small py-2 mb-2">
            <i class="bi bi-magic me-1"></i>
            Al salir del campo dominio se consulta <strong>RDAP/NIC.ec automáticamente</strong> y se llenan las fechas.
          </div>
          <!-- Resumen RDAP (aparece al obtener datos) -->
          <div id="rdapResumen" class="d-none mb-2"></div>

          <div class="mb-2">
            <label class="form-label fw-semibold">Dominio completo <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="text" name="dominio_completo" class="form-control"
                     placeholder="Ej: miempresa.ec  o  miempresa.com"
                     required pattern="[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}"
                     oninput="this.value=this.value.toLowerCase().replace(/\s/g,'')">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnConsultarRdap"
                      onclick="document.querySelector('input[name=dominio_completo]').dispatchEvent(new Event('blur'))">
                <i class="bi bi-search me-1"></i>Consultar RDAP
              </button>
            </div>
            <small class="text-muted">Sin http:// ni www. — escribe el dominio y espera o haz clic en "Consultar RDAP"</small>
          </div>

          <!-- Campos hidden que llena el JS con datos RDAP -->
          <input type="hidden" name="rdap_titular">
          <input type="hidden" name="rdap_registrador">
          <input type="hidden" name="rdap_estado">
          <input type="hidden" name="rdap_nameservers">
          <input type="hidden" name="rdap_ultimo_cambio">

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Fecha caducidad</label>
              <input type="date" name="fecha_caducidad" class="form-control form-control-sm">
              <small class="text-muted" style="font-size:10px">Se llena automáticamente con RDAP</small>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Fecha de registro</label>
              <input type="date" name="fecha_registro" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Alertar con días de anticipación</label>
              <input type="number" name="dias_alerta" class="form-control form-control-sm"
                     value="30" min="1" max="365">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Costo renovación ($)</label>
              <input type="number" step="0.01" name="costo_renovacion" class="form-control form-control-sm"
                     placeholder="Ej: 25.00">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Notas internas</label>
              <textarea name="notas" class="form-control form-control-sm" rows="2"
                        placeholder="Proveedor, credenciales, observaciones..."></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="renovacion_auto" id="chkRenovAuto" value="1">
                <label class="form-check-label small" for="chkRenovAuto">Renovación automática activada</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarDominio">
            <i class="bi bi-globe2 me-1"></i>Agregar y consultar RDAP
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: Editar dominio ───────────────────────────────────────── -->
<div class="modal fade" id="modalEditarDominio" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="formEditarDominio">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Dominio: <span id="editDominioNombre"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Fecha caducidad</label>
              <input type="date" name="fecha_caducidad" id="editFechaCaducidad" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Fecha de registro</label>
              <input type="date" name="fecha_registro" id="editFechaRegistro" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Alertar con (días)</label>
              <input type="number" name="dias_alerta" id="editDiasAlerta" class="form-control form-control-sm" min="1" max="365">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Costo renovación ($)</label>
              <input type="number" step="0.01" name="costo_renovacion" id="editCosto" class="form-control form-control-sm">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Notas internas</label>
              <textarea name="notas" id="editNotas" class="form-control form-control-sm" rows="2"></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="renovacion_auto" id="editRenovAuto" value="1">
                <label class="form-check-label small" for="editRenovAuto">Renovación automática</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ════════════════════════════════════════════════════════════════════════
// RDAP via proxy PHP propio — usa WHOIS puerto 43 (whois.nic.ec)
// El servidor puede conectar a whois.nic.ec:43 sin restricciones
// ════════════════════════════════════════════════════════════════════════

async function consultarRdapBrowser(dominioCompleto) {
  const resp = await fetch(`/dominios/proxy-rdap?dominio=${encodeURIComponent(dominioCompleto)}`, {
    headers: { 'Accept': 'application/json' }
  });
  if (!resp.ok) throw new Error(`Proxy HTTP ${resp.status}`);
  const data = await resp.json();
  if (!data.ok) throw new Error(data.msg || 'Sin datos WHOIS');
  // Normalizar al formato esperado por parsearRdap
  return {
    events: [
      { eventAction: 'registration', eventDate: data.fecha_registro },
      { eventAction: 'expiration',   eventDate: data.fecha_caducidad },
      { eventAction: 'last changed', eventDate: data.ultimo_cambio },
    ].filter(e => e.eventDate),
    status:      data.estado_rdap ? [data.estado_rdap] : [],
    nameservers: (data.nameservers || []).map(n => ({ ldhName: n })),
    entities:    [
      data.titular     ? { roles: ['registrant'], vcardArray: ['vcard', [['fn',{},'text', data.titular]]] }     : null,
      data.registrador ? { roles: ['registrar'],  vcardArray: ['vcard', [['fn',{},'text', data.registrador]]] } : null,
    ].filter(Boolean),
  };
}

/**
 * Parsea la respuesta RDAP y extrae los campos relevantes
 */
function parsearRdap(data) {
  const result = {
    fecha_registro: null, fecha_caducidad: null,
    fecha_ultimo_cambio: null, estado_rdap: null,
    titular: null, registrador: null, nameservers: []
  };

  // Eventos
  for (const ev of data.events || []) {
    const d = ev.eventDate ? ev.eventDate.substring(0, 10) : null;
    if (ev.eventAction === 'registration')  result.fecha_registro      = d;
    if (ev.eventAction === 'expiration')    result.fecha_caducidad     = d;
    if (ev.eventAction === 'last changed')  result.fecha_ultimo_cambio = d;
  }

  // Estado
  if (data.status) {
    result.estado_rdap = Array.isArray(data.status) ? data.status.join(', ') : data.status;
  }

  // Nameservers
  for (const ns of data.nameservers || []) {
    const n = ns.ldhName || ns.unicodeName;
    if (n) result.nameservers.push(n.toLowerCase());
  }

  // Entidades (titular / registrador)
  for (const ent of data.entities || []) {
    const roles  = ent.roles || [];
    const nombre = extraerNombreVcard(ent.vcardArray);
    if (roles.includes('registrant') && nombre) result.titular     = nombre;
    if (roles.includes('registrar')  && nombre) result.registrador = nombre;
  }

  return result;
}

function extraerNombreVcard(vcard) {
  if (!Array.isArray(vcard) || vcard.length < 2) return null;
  for (const campo of vcard[1]) {
    if (campo[0] === 'fn' && campo[3]) return campo[3];
  }
  return null;
}

// ── Al escribir el dominio en el modal de agregar: prellenar con RDAP ──
document.getElementById('modalAgregarDominio')?.addEventListener('shown.bs.modal', function() {
  const inputDominio = this.querySelector('input[name="dominio_completo"]');
  inputDominio.focus();
});

document.querySelector('input[name="dominio_completo"]')?.addEventListener('blur', async function() {
  const dominio = this.value.trim().toLowerCase();
  if (!dominio || !dominio.includes('.')) return;

  const btnConsultar = document.getElementById('btnConsultarRdap');
  if (btnConsultar) {
    btnConsultar.disabled = true;
    btnConsultar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Consultando RDAP...';
  }

  try {
    const data   = await consultarRdapBrowser(dominio);
    const parsed = parsearRdap(data);

    // Llenar campos hidden con los datos RDAP
    const setVal = (name, val) => {
      const el = document.querySelector(`[name="${name}"]`);
      if (el && val) el.value = val;
    };
    setVal('fecha_caducidad', parsed.fecha_caducidad);
    setVal('fecha_registro',  parsed.fecha_registro);
    setVal('rdap_titular',    parsed.titular);
    setVal('rdap_registrador',parsed.registrador);
    setVal('rdap_estado',     parsed.estado_rdap);
    setVal('rdap_nameservers',JSON.stringify(parsed.nameservers));
    setVal('rdap_ultimo_cambio', parsed.fecha_ultimo_cambio);

    // Mostrar resumen visual
    mostrarResumenRdap(parsed, dominio);

    if (btnConsultar) {
      btnConsultar.disabled = false;
      btnConsultar.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Datos obtenidos';
    }
  } catch(e) {
    console.warn('RDAP no disponible para:', dominio, e);
    if (btnConsultar) {
      btnConsultar.disabled = false;
      btnConsultar.innerHTML = '<i class="bi bi-exclamation-triangle text-warning me-1"></i>Sin datos RDAP — ingresa fechas manualmente';
    }
  }
});

function mostrarResumenRdap(parsed, dominio) {
  const div = document.getElementById('rdapResumen');
  if (!div) return;

  const fmt = d => d ? new Date(d).toLocaleDateString('es-EC', {day:'2-digit',month:'2-digit',year:'numeric'}) : '—';
  const hoy = new Date();
  const vcto = parsed.fecha_caducidad ? new Date(parsed.fecha_caducidad) : null;
  const dias = vcto ? Math.ceil((vcto - hoy) / 86400000) : null;
  const colorDias = dias === null ? '' : (dias < 0 ? 'text-danger fw-bold' : dias <= 30 ? 'text-warning fw-bold' : 'text-success');

  div.innerHTML = `
    <div class="alert alert-success py-2 small mb-0">
      <strong><i class="bi bi-check-circle me-1"></i>Datos RDAP obtenidos para ${dominio}</strong><br>
      <span class="me-3">������ Registrado: ${fmt(parsed.fecha_registro)}</span>
      <span class="me-3">⏰ Caduca: ${fmt(parsed.fecha_caducidad)}</span>
      ${dias !== null ? `<span class="${colorDias}">⚡ ${dias >= 0 ? dias+' días restantes' : 'Vencido hace '+Math.abs(dias)+' días'}</span>` : ''}
      ${parsed.titular ? `<br>������ Titular: ${parsed.titular}` : ''}
      ${parsed.nameservers.length ? `<br>������ NS: ${parsed.nameservers.join(', ')}` : ''}
    </div>`;
  div.classList.remove('d-none');
}

// ── Actualizar RDAP desde browser (botón ↺ en la tabla) ─────────────────
async function actualizarRdap(id, dominio, btnEl) {
  btnEl.disabled = true;
  btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  try {
    const data   = await consultarRdapBrowser(dominio);
    const parsed = parsearRdap(data);

    // Enviar los datos al servidor vía fetch POST
    const csrf = document.querySelector('input[name="_csrf_token"]')?.value || '';
    const body = new URLSearchParams({
      _csrf_token:        csrf,
      fecha_caducidad:    parsed.fecha_caducidad    || '',
      fecha_registro:     parsed.fecha_registro     || '',
      rdap_titular:       parsed.titular            || '',
      rdap_registrador:   parsed.registrador        || '',
      rdap_estado:        parsed.estado_rdap        || '',
      rdap_nameservers:   JSON.stringify(parsed.nameservers),
      rdap_ultimo_cambio: parsed.fecha_ultimo_cambio || '',
    });

    const resp = await fetch('/dominios/' + id + '/rdap-datos', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    });
    const result = await resp.json();

    if (result.ok) {
      location.reload();
    } else {
      throw new Error(result.msg || 'Error al guardar');
    }
  } catch(e) {
    alert('⚠️ No se pudo consultar RDAP para este dominio.\n' + (e.message || ''));
    btnEl.disabled = false;
    btnEl.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
  }
}

// ── Rellenar modal de edición ────────────────────────────────────────────
document.getElementById('modalEditarDominio')?.addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('editDominioNombre').textContent  = btn.dataset.dominio;
  document.getElementById('editFechaCaducidad').value       = btn.dataset.caducidad  || '';
  document.getElementById('editFechaRegistro').value        = btn.dataset.registro   || '';
  document.getElementById('editDiasAlerta').value           = btn.dataset.diasAlerta || 30;
  document.getElementById('editCosto').value                = btn.dataset.costo      || '';
  document.getElementById('editNotas').value                = btn.dataset.notas      || '';
  document.getElementById('editRenovAuto').checked          = btn.dataset.renovacionAuto === '1';
  document.getElementById('formEditarDominio').action       = '/dominios/' + btn.dataset.id;
});

// ── Spinner en submit de agregar ─────────────────────────────────────────
document.getElementById('formAgregarDominio')?.addEventListener('submit', function() {
  const btn = document.getElementById('btnGuardarDominio');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
});
</script>