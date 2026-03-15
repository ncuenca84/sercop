<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Extraer PDF / TDR</h4>
    <p class="text-muted small mb-0">Sube el PDF del TDR y el sistema extrae autom&aacute;ticamente los datos t&eacute;cnicos del proceso</p>
  </div>
  <a href="/procesos/crear" class="btn btn-outline-primary btn-sm">
    <i class="bi bi-globe me-1"></i>Importar desde SERCOP
  </a>
</div>

<div class="row g-4">
  <!-- Panel izquierdo: solo PDF -->
  <div class="col-lg-5">

    <div class="card border-danger border-opacity-50">
      <div class="card-header fw-semibold bg-danger bg-opacity-10">
        <i class="bi bi-file-pdf me-2 text-danger"></i>Subir PDF del TDR
      </div>
      <div class="card-body">
        <form method="POST" action="/ia/extraer" enctype="multipart/form-data" id="formPdf">
          <?= csrf_field() ?>
          <div class="border border-2 rounded-3 p-4 text-center mb-3"
               style="border-style:dashed!important;cursor:pointer;border-color:#dc3545!important"
               onclick="document.getElementById('archivoPdf').click()"
               ondragover="event.preventDefault()"
               ondrop="handleDrop(event)">
            <i class="bi bi-file-earmark-pdf fs-1 text-danger d-block mb-2"></i>
            <div class="fw-semibold small">Arrastra el PDF aqu&iacute;</div>
            <small class="text-muted">o haz clic para seleccionar</small>
            <p class="mb-0 small text-success fw-semibold mt-2" id="nombreArchivo"></p>
          </div>
          <input type="file" id="archivoPdf" name="archivo" accept=".pdf,.txt" class="d-none" onchange="mostrarNombre(this)">
          <div class="d-grid mb-3">
            <button type="submit" class="btn btn-danger" id="btnPdf">
              <i class="bi bi-magic me-2"></i>Extraer datos del PDF
            </button>
          </div>
        </form>
        <div class="p-2 bg-light rounded small text-muted">
          <i class="bi bi-info-circle text-primary me-1"></i>
          <strong>Extrae autom&aacute;ticamente:</strong> monto, plazo, especificaciones t&eacute;cnicas,
          metodolog&iacute;a, vigencia de oferta y m&aacute;s campos del TDR
        </div>
      </div>
    </div>

    <div class="card mt-3 border-0 bg-light">
      <div class="card-body py-2 px-3 small text-muted">
        <i class="bi bi-lightbulb text-warning me-1"></i>
        <strong>&iquest;Quieres crear un proceso desde la web del SERCOP?</strong><br>
        Ve a <a href="/procesos/crear" class="text-primary fw-semibold">Nuevo Proceso</a>
        y usa la opci&oacute;n &ldquo;Importar desde SERCOP&rdquo; con la URL del proceso.
      </div>
    </div>

  </div>
  <!-- Panel derecho: resultado -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-2 text-success"></i>Datos extraídos</span>
        <?php if(!empty($metodo)): ?>
        <span class="badge <?= $metodo==='sercop'?'bg-primary':($metodo==='regex'?'bg-success':($metodo==='ia_escaneado'?'bg-warning text-dark':'bg-secondary')) ?>">
          <?= $metodo==='sercop'?'������ SERCOP':($metodo==='regex'?'✅ Sin IA':($metodo==='ia_escaneado'?'������ IA':'������ Manual')) ?>
        </span>
        <?php endif; ?>
      </div>
      <div class="card-body">

        <?php if(!empty($error)): ?>
        <div class="alert alert-danger small"><i class="bi bi-x-circle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>
        <?php if(!empty($aviso)): ?>
        <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($aviso) ?></div>
        <?php endif; ?>

        <?php if(!empty($datos)): ?>

        <!-- FUNCIONARIO / CONTACTO -->
        <?php if(!empty($funcionarioNombre) || !empty($funcionarioEmail)): ?>
        <div class="alert alert-info py-2 mb-3">
          <div class="fw-semibold small mb-1"><i class="bi bi-person-badge me-1"></i>Funcionario Encargado</div>
          <?php if(!empty($funcionarioNombre)): ?>
          <div class="small"><i class="bi bi-person me-1"></i><?= e($funcionarioNombre) ?></div>
          <?php endif; ?>
          <?php if(!empty($funcionarioEmail)): ?>
          <div class="small">
            <i class="bi bi-envelope me-1"></i>
            <a href="mailto:<?= e($funcionarioEmail) ?>" class="text-primary fw-semibold"><?= e($funcionarioEmail) ?></a>
            <span class="badge bg-success ms-1">&#10003; Se usar&aacute; para notificaciones</span>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- DATOS SERCOP: FECHAS / UBICACIÓN / RUC -->
        <?php
          $hayDatosSercop = !empty($datos['ruc_institucion']) || !empty($datos['provincia'])
                         || !empty($datos['canton']) || !empty($datos['fecha_publicacion'])
                         || !empty($fechaLimite) || !empty($datos['direccion']);
        ?>
        <?php if($hayDatosSercop): ?>
        <div class="card mb-3 border-0 bg-light">
          <div class="card-body py-2 px-3">
            <div class="fw-semibold small mb-2 text-primary"><i class="bi bi-globe me-1"></i>Datos extraídos del portal SERCOP</div>
            <div class="row g-1 small">
              <?php if(!empty($datos['ruc_institucion'])): ?>
              <div class="col-sm-6"><span class="text-muted">RUC Institución:</span> <strong><?= e($datos['ruc_institucion']) ?></strong></div>
              <?php endif; ?>
              <?php if(!empty($datos['provincia'])): ?>
              <div class="col-sm-6"><span class="text-muted">Provincia:</span> <strong><?= e($datos['provincia']) ?></strong></div>
              <?php endif; ?>
              <?php if(!empty($datos['canton'])): ?>
              <div class="col-sm-6"><span class="text-muted">Cant&oacute;n:</span> <strong><?= e($datos['canton']) ?></strong></div>
              <?php endif; ?>
              <?php if(!empty($datos['fecha_publicacion'])): ?>
              <div class="col-sm-6"><span class="text-muted">Fecha publicaci&oacute;n:</span> <strong><?= e($datos['fecha_publicacion']) ?></strong></div>
              <?php endif; ?>
              <?php if(!empty($fechaLimite)): ?>
              <div class="col-12 mt-1">
                <span class="text-danger fw-semibold"><i class="bi bi-clock me-1"></i>L&iacute;mite proformas:</span>
                <strong class="text-danger"><?= e($fechaLimite) ?></strong>
                <?php if(!empty($urlProforma)): ?>
                &nbsp;<a href="<?= e($urlProforma) ?>" target="_blank" class="btn btn-danger btn-sm py-0 px-2 ms-1">
                  <i class="bi bi-box-arrow-up-right me-1"></i>Ingresar en SERCOP
                </a>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <?php if(!empty($datos['direccion'])): ?>
              <div class="col-12 mt-1"><span class="text-muted">Direcci&oacute;n entrega:</span> <?= e($datos['direccion']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- DOCUMENTOS ADJUNTOS -->
        <?php if(!empty($documentos)): ?>
        <div class="card mb-3 border-0 bg-light">
          <div class="card-body py-2 px-3">
            <div class="fw-semibold small mb-2"><i class="bi bi-paperclip me-1 text-primary"></i>Documentos del proceso</div>
            <?php foreach($documentos as $doc): ?>
            <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
              <span class="small"><i class="bi bi-file-earmark me-1 text-muted"></i><?= e($doc['nombre']) ?></span>
              <div class="d-flex gap-1">
                <?php if(!empty($doc['url'])): ?>
                <a href="<?= e($doc['url']) ?>" target="_blank" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2">
                  <i class="bi bi-download"></i> Descargar
                </a>
                <?php endif; ?>
                <!-- Siempre ofrecer subida manual al repositorio -->
                <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2"
                        onclick="toggleUpload('upload_<?= md5($doc['nombre']) ?>')">
                  <i class="bi bi-cloud-upload"></i> Subir
                </button>
              </div>
            </div>
            <!-- Input de subida manual oculto -->
            <div id="upload_<?= md5($doc['nombre']) ?>" class="d-none mt-1 mb-2">
              <form method="POST" action="/documentos/subir-adjunto" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="nombre_doc" value="<?= e($doc['nombre']) ?>">
                <input type="hidden" name="url_sercop" value="<?= e($_POST['url_sercop'] ?? '') ?>">
                <input type="file" name="archivo_adjunto" class="form-control form-control-sm" accept=".pdf,.doc,.docx">
                <button type="submit" class="btn btn-sm btn-success text-nowrap">
                  <i class="bi bi-check2"></i> Guardar
                </button>
              </form>
            </div>
            <?php endforeach; ?>

            <!-- Enlace para ir a descargar manualmente desde SERCOP -->
            <?php if(!empty($urlOrigen)): ?>
            <div class="mt-2 small text-muted">
              <i class="bi bi-info-circle me-1"></i>
              Si los botones de descarga no funcionan,
              <a href="<?= e($urlOrigen) ?>" target="_blank">abre el proceso en SERCOP</a>
              y descarga los archivos manualmente, luego usa el botón "Subir" para guardarlos aquí.
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- ITEMS -->
        <?php if(!empty($items)): ?>
        <div class="mb-3">
          <div class="fw-semibold small mb-1"><i class="bi bi-list-ul me-1"></i>Ítems del proceso</div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered small mb-0">
              <thead class="table-primary">
                <tr><th>#</th><th>Descripción</th><th>Unidad</th><th>Cant.</th></tr>
              </thead>
              <tbody>
              <?php foreach($items as $it): ?>
              <tr>
                <td><?= e($it['no'] ?? $it['numero'] ?? '') ?></td>
                <td><?= e($it['descripcion']) ?></td>
                <td><?= e($it['unidad']) ?></td>
                <td><?= e($it['cantidad']) ?></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <!-- FORMULARIO CREAR PROCESO -->
        <form method="POST" action="/procesos">
          <?= csrf_field() ?>
          <!-- Guardar datos extras como campos hidden para usarlos al crear el proceso -->
          <input type="hidden" name="funcionario_nombre" value="<?= e($funcionarioNombre ?? '') ?>">
          <input type="hidden" name="funcionario_email"  value="<?= e($funcionarioEmail  ?? '') ?>">
          <input type="hidden" name="url_sercop"         value="<?= e($_POST['url_sercop'] ?? '') ?>">

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold small">N° Proceso / Orden de Compra</label>
              <input type="text" name="numero_proceso" class="form-control form-control-sm"
                     value="<?= e($datos['numero_proceso'] ?? '') ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Institución Contratante</label>

              <?php
                // Buscar si ya existe en la lista
                $instDetectada  = $datos['institucion_contratante'] ?? '';
                $instEncontrada = null;
                foreach ($instituciones as $i) {
                    if ($instDetectada && stripos($i['nombre'], substr($instDetectada,0,15)) !== false) {
                        $instEncontrada = $i;
                        break;
                    }
                }
              ?>

              <?php if ($instEncontrada): ?>
                <!-- ✅ Ya existe → seleccionada automáticamente -->
                <input type="hidden" name="institucion_id" value="<?= $instEncontrada['id'] ?>">
                <div class="d-flex align-items-center gap-2 p-2 rounded bg-success bg-opacity-10 border border-success">
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <div>
                    <div class="fw-semibold small text-success"><?= e($instEncontrada['nombre']) ?></div>
                    <div class="text-muted" style="font-size:11px">Institución encontrada en tu lista — seleccionada automáticamente</div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                          onclick="document.getElementById('cambiarInstitucion').classList.toggle('d-none')">
                    Cambiar
                  </button>
                </div>
                <!-- Panel para cambiar manualmente (oculto) -->
                <div id="cambiarInstitucion" class="d-none mt-2">
                  <select name="institucion_id_alt" class="form-select form-select-sm"
                          onchange="document.querySelector('[name=institucion_id]').value=this.value">
                    <option value="">Seleccionar otra...</option>
                    <?php foreach($instituciones as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= $i['id']==$instEncontrada['id']?'selected':'' ?>><?= e($i['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

              <?php elseif (!empty($instDetectada)): ?>
                <!-- ⚠ Detectada pero NO está en la lista → ofrecer crear inline -->
                <div class="p-2 rounded bg-warning bg-opacity-10 border border-warning mb-2">
                  <div class="small fw-semibold text-warning mb-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Institución detectada pero no está en tu lista:
                  </div>
                  <div class="fw-bold"><?= e($instDetectada) ?></div>
                </div>

                <!-- Tabs: Crear nueva / Seleccionar existente -->
                <ul class="nav nav-tabs nav-sm mb-2" id="tabInstitucion">
                  <li class="nav-item">
                    <button class="nav-link active small py-1 px-3" data-bs-toggle="tab" data-bs-target="#tabCrear">
                      <i class="bi bi-plus-circle me-1"></i>Crear nueva
                    </button>
                  </li>
                  <li class="nav-item">
                    <button class="nav-link small py-1 px-3" data-bs-toggle="tab" data-bs-target="#tabExistente">
                      <i class="bi bi-search me-1"></i>Seleccionar existente
                    </button>
                  </li>
                </ul>
                <div class="tab-content border rounded p-3 bg-light">
                  <!-- Crear nueva institución inline -->
                  <div class="tab-pane fade show active" id="tabCrear">
                    <input type="hidden" name="institucion_id" value="0">
                    <input type="hidden" name="nueva_institucion" value="1">
                    <div class="row g-2">
                      <div class="col-12">
                        <label class="form-label small mb-1 fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="inst_nombre" class="form-control form-control-sm"
                               value="<?= e($instDetectada) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small mb-1">RUC</label>
                        <input type="text" name="inst_ruc" class="form-control form-control-sm"
                               value="<?= e($datos['ruc_institucion'] ?? '') ?>" placeholder="Ej: 1768107770001" maxlength="13">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small mb-1">Provincia / Ciudad</label>
                        <input type="text" name="inst_ciudad" class="form-control form-control-sm"
                               value="<?= e($datos['canton'] ?? $datos['provincia'] ?? '') ?>" placeholder="Ej: Quito">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small mb-1">Administrador del Contrato</label>
                        <input type="text" name="inst_administrador" class="form-control form-control-sm"
                               value="<?= e($funcionarioNombre ?? '') ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small mb-1">Email Administrador</label>
                        <input type="email" name="inst_email" class="form-control form-control-sm"
                               value="<?= e($funcionarioEmail ?? '') ?>">
                      </div>
                    </div>
                    <div class="mt-2 small text-muted">
                      <i class="bi bi-info-circle me-1"></i>La institución se creará automáticamente al guardar el proceso
                    </div>
                  </div>
                  <!-- Seleccionar existente -->
                  <div class="tab-pane fade" id="tabExistente">
                    <select name="institucion_id" class="form-select form-select-sm">
                      <option value="">Seleccionar institución existente...</option>
                      <?php foreach($instituciones as $i): ?>
                      <option value="<?= $i['id'] ?>"><?= e($i['nombre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

              <?php else: ?>
                <!-- Sin detección → selector normal -->
                <div class="input-group input-group-sm">
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
                <div id="nuevaInstInline" class="d-none mt-2 p-2 border rounded bg-light">
                  <input type="hidden" name="nueva_institucion" value="1">
                  <div class="row g-2">
                    <div class="col-12"><input type="text" name="inst_nombre" class="form-control form-control-sm" placeholder="Nombre institución *"></div>
                    <div class="col-6"><input type="text" name="inst_ruc" class="form-control form-control-sm" placeholder="RUC"></div>
                    <div class="col-6"><input type="text" name="inst_ciudad" class="form-control form-control-sm" placeholder="Provincia/Ciudad"></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Objeto de Contratación</label>
              <input type="text" name="objeto_contratacion" class="form-control form-control-sm"
                     value="<?= e($datos['objeto_contratacion'] ?? '') ?>" required>
            </div>
            <!-- CPC -->
            <div class="col-12">
              <label class="form-label fw-semibold small">
                Código CPC
                <?php if(!empty($datos['cpc'])): ?>
                  <span class="badge bg-success ms-1">&#10003; extraído</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark ms-1">completar</span>
                <?php endif; ?>
              </label>
              <input type="text" name="cpc" class="form-control form-control-sm"
                     value="<?= e($datos['cpc'] ?? '') ?>"
                     placeholder="Ej: 842200011">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Monto (USD)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" name="monto_total" class="form-control"
                       value="<?= $datos['monto_total'] ?? '' ?>" min="0">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Plazo (días)</label>
              <div class="input-group input-group-sm">
                <input type="number" name="plazo_dias" class="form-control"
                       value="<?= $datos['plazo_dias'] ?? 30 ?>" min="1">
                <span class="input-group-text">días</span>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Fecha Inicio</label>
              <input type="date" name="fecha_inicio" class="form-control form-control-sm"
                     value="<?= $datos['fecha_inicio'] ?? date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Tipo de Proceso</label>
              <select name="tipo_proceso" class="form-select form-select-sm">
                <?php foreach([
                  'infima_cuantia'=>'Ínfima Cuantía','catalogo'=>'Catálogo Electrónico',
                  'subasta'=>'Subasta Inversa','licitacion'=>'Licitación',
                  'menor_cuantia'=>'Menor Cuantía','contratacion_directa'=>'Contratación Directa','otro'=>'Otro'
                ] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($datos['tipo_proceso']??'')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- ESPECIFICACIONES TÉCNICAS -->
            <div class="col-12">
              <label class="form-label fw-semibold small">
                Especificaciones Técnicas
                <?php if(!empty($datos['especificaciones_tecnicas'])): ?>
                  <span class="badge bg-success ms-1">&#10003; extraído</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark ms-1">completar si aplica</span>
                <?php endif; ?>
              </label>
              <textarea name="especificaciones_tecnicas" class="form-control form-control-sm" rows="4"
                        placeholder="Se extrae automáticamente del TDR..."><?= e($datos['especificaciones_tecnicas'] ?? '') ?></textarea>
            </div>
            <!-- METODOLOGÍA -->
            <div class="col-12">
              <label class="form-label fw-semibold small">
                Metodología de Trabajo
                <?php if(!empty($datos['metodologia_trabajo'])): ?>
                  <span class="badge bg-success ms-1">&#10003; extraído</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark ms-1">completar si aplica</span>
                <?php endif; ?>
              </label>
              <textarea name="metodologia_trabajo" class="form-control form-control-sm" rows="3"
                        placeholder="Se extrae automáticamente del TDR..."><?= e($datos['metodologia_trabajo'] ?? '') ?></textarea>
            </div>
            <div class="col-12 d-grid mt-1">
              <button type="submit" class="btn btn-success">
                <i class="bi bi-check-circle me-2"></i>Crear Proceso con estos Datos
              </button>
            </div>
          </div>
        </form>

        <?php else: ?>
        <div class="text-center text-muted py-5">
          <i class="bi bi-arrow-left-circle fs-2 d-block mb-3 text-primary"></i>
          <p class="fw-semibold mb-1">Pega una URL del SERCOP o sube un PDF</p>
          <p class="small">Se extraen automáticamente todos los datos del proceso</p>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<script>
function mostrarNombre(i){
  if(i.files[0]){
    var nombre = i.files[0].name;
    var limpio = nombre.replace(/[^\x20-\x7E\u00C0-\u024F\u00A0-\u00FF]/g,'').trim() || nombre;
    document.getElementById('nombreArchivo').textContent='\uD83D\uDCC4 '+limpio;
  }
}
function handleDrop(e){ e.preventDefault(); const f=e.dataTransfer.files[0]; if(f){ document.getElementById('archivoPdf').files=e.dataTransfer.files; mostrarNombre(document.getElementById('archivoPdf')); } }
function toggleUpload(id){ const el=document.getElementById(id); el.classList.toggle('d-none'); }
document.getElementById('formPdf').addEventListener('submit',function(){
  document.getElementById('btnPdf').innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Extrayendo...';
  document.getElementById('btnPdf').disabled=true;
});
</script>