<?php

declare(strict_types=1);

require_once APP_PATH . '/Controllers/Web/BaseController.php';
require_once APP_PATH . '/Models/Models.php';
require_once APP_PATH . '/Services/IaService.php';
require_once APP_PATH . '/Services/DocumentService.php';
require_once APP_PATH . '/Services/NotificacionService.php';

require_once APP_PATH . '/Services/PdfExtractorService.php';

// ══════════════════════════════════════════════════════════════════════════
// EXTRACTOR CONTROLLER — extrae datos de PDF sin IA
// ══════════════════════════════════════════════════════════════════════════
class ExtractorController extends BaseController
{
    private function getInstituciones(): array
    {
        return DB::select(
            "SELECT id, nombre FROM instituciones WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY nombre",
            [DB::getTenantId()]
        );
    }

    public function index(): void
    {
        $instituciones = $this->getInstituciones();
        $this->view('ia/extractor', compact('instituciones'));
    }

    // ── EXTRAER DESDE URL SERCOP ─────────────────────────────────────────
    public function extraerUrl(): void
    {
        $instituciones = $this->getInstituciones();
        $url = trim($_POST['url_sercop'] ?? '');

        if (empty($url)) {
            $error = 'Por favor ingresa una URL del portal SERCOP.';
            $this->view('ia/extractor', compact('instituciones', 'error'));
            return;
        }

        try {
            $resultado  = PdfExtractorService::extraerDeUrl($url);
            $datos      = $resultado['datos'];
            $metodo     = $resultado['metodo'];
            $aviso      = $resultado['aviso'] ?? null;
            $urlOrigen  = $url;

            // Mapear campos del nuevo parser a variables de la vista
            $items             = $datos['items']                 ?? [];
            $fechaLimite       = $datos['fecha_limite_proforma'] ?? '';
            $funcionarioNombre = $datos['funcionario']           ?? '';
            $funcionarioEmail  = $datos['correo_contacto']       ?? '';
            $urlProforma       = ''; // se puede construir si se tiene el NIC
            $documentos        = [];

            $this->view('ia/extractor', compact(
                'datos','metodo','aviso','documentos','items',
                'fechaLimite','urlProforma','urlOrigen',
                'funcionarioNombre','funcionarioEmail','instituciones'
            ));
        } catch (\Throwable $e) {
            $error = 'Error al consultar SERCOP: ' . $e->getMessage();
            $this->view('ia/extractor', compact('instituciones', 'error'));
        }
    }

    // ── SUBIR ADJUNTO MANUAL AL REPOSITORIO ─────────────────────────────
    public function subirAdjunto(): void
    {
        if (empty($_FILES['archivo_adjunto']['tmp_name'])) {
            $this->setFlash('error', 'No se seleccionó ningún archivo.');
            $this->redirect('/extractor');
            return;
        }

        $archivo     = $_FILES['archivo_adjunto'];
        $nombreDoc   = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $_POST['nombre_doc'] ?? 'adjunto');
        $tenantId    = \DB::getTenantId();
        $ext         = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $permitidos  = ['pdf', 'doc', 'docx', 'xlsx', 'xls'];

        if (!in_array($ext, $permitidos)) {
            $this->setFlash('error', 'Tipo de archivo no permitido.');
            $this->redirect('/extractor');
            return;
        }

        $dir = ROOT_PATH . '/storage/adjuntos_sercop/' . $tenantId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombreArchivo = date('Ymd_His') . '_' . strtolower(str_replace(' ', '_', $nombreDoc)) . '.' . $ext;
        $destino = $dir . $nombreArchivo;

        if (move_uploaded_file($archivo['tmp_name'], $destino)) {
            $this->setFlash('success', "✅ Archivo '{$nombreDoc}' guardado en el repositorio.");
        } else {
            $this->setFlash('error', 'Error al guardar el archivo.');
        }

        // Redirigir de vuelta al extractor con la URL del proceso
        $urlSercop = $_POST['url_sercop'] ?? '';
        if ($urlSercop) {
            header('Location: /extractor?redirect=1');
        } else {
            $this->redirect('/extractor');
        }
        exit;
    }
    public function extraer(): void
    {
        $instituciones = $this->getInstituciones();

        $uploadError = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
            $error = 'El archivo es demasiado grande. Máximo permitido: ' . ini_get('upload_max_filesize');
            $this->view('ia/extractor', compact('instituciones', 'error'));
            return;
        }
        if (empty($_FILES['archivo']['tmp_name']) || $uploadError !== UPLOAD_ERR_OK) {
            $error = 'Por favor selecciona un archivo PDF.';
            $this->view('ia/extractor', compact('instituciones', 'error'));
            return;
        }
        $archivo = $_FILES['archivo'];

        // Validar por extensión Y por MIME — nombres con emoji/unicode pueden romper pathinfo
        $nombreLimpio = mb_convert_encoding($archivo['name'], 'UTF-8', 'UTF-8');
        // Buscar extensión con regex para evitar problemas con pathinfo + multibyte
        preg_match('/\.([a-zA-Z0-9]+)$/', $nombreLimpio, $mExt);
        $ext = strtolower($mExt[1] ?? '');
        // Si falla la extensión, intentar por MIME del archivo subido
        if (!in_array($ext, ['pdf', 'txt'])) {
            $mime = mime_content_type($archivo['tmp_name']);
            if (strpos($mime, 'pdf') !== false)       $ext = 'pdf';
            elseif (strpos($mime, 'text') !== false)  $ext = 'txt';
        }
        if (!in_array($ext, ['pdf', 'txt'])) {
            $error = 'Solo se aceptan archivos PDF o TXT. (Extensión detectada: ' . htmlspecialchars($ext ?: 'ninguna') . ')';
            $this->view('ia/extractor', compact('instituciones', 'error'));
            return;
        }
        try {
            $datos      = PdfExtractorService::extraerDatos($archivo['tmp_name']);
            $metodo     = 'regex';
            $aviso      = null;
            $textoCrudo = null;
            $this->view('ia/extractor', compact('datos','metodo','aviso','textoCrudo','instituciones'));
        } catch (\Throwable $e) {
            $error = 'Error al procesar: ' . $e->getMessage();
            $this->view('ia/extractor', compact('instituciones', 'error'));
        }
    }

    // ── EXTRAER AJAX — devuelve JSON para rellenar formulario Fase 2 ──────
    public function extraerAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $uploadError = $_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK || empty($_FILES['pdf']['tmp_name'])) {
            echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo.']);
            return;
        }

        $archivo = $_FILES['pdf'];
        $mime = mime_content_type($archivo['tmp_name']);
        $esPdf = strpos($mime, 'pdf') !== false;
        $esTxt = strpos($mime, 'text') !== false;
        if (!$esPdf && !$esTxt) {
            preg_match('/\.([a-zA-Z0-9]+)$/', $archivo['name'], $mExt);
            $ext = strtolower($mExt[1] ?? '');
            if (!in_array($ext, ['pdf','txt'])) {
                echo json_encode(['ok' => false, 'error' => 'Solo PDF o TXT.']);
                return;
            }
        }

        try {
            $resultado = PdfExtractorService::extraerDatos($archivo['tmp_name']);
            $d = $resultado;
            echo json_encode([
                'ok'     => true,
                'metodo' => 'regex',
                'aviso'  => null,
                'datos'  => [
                    'numero_proceso'           => $d['numero_proceso']           ?? '',
                    'ruc_institucion'          => $d['ruc_institucion']          ?? '',
                    'cpc'                      => $d['cpc']                      ?? '',
                    'monto_total'              => $d['monto_total']              ?? 0,
                    'plazo_dias'               => $d['plazo_dias']               ?? 0,
                    'vigencia_oferta'          => $d['vigencia_oferta']          ?? '',
                    'especificaciones_tecnicas'=> $d['especificaciones_tecnicas']?? '',
                    'metodologia_trabajo'      => $d['metodologia_trabajo']      ?? '',
                    'cpc_descripcion'          => $d['cpc_descripcion']          ?? '',
                    'plazo_texto'              => $d['plazo_texto']              ?? '',
                    'forma_pago'               => $d['forma_pago']               ?? '',
                    'declaracion_cumplimiento' => $d['declaracion_cumplimiento'] ?? '',
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Extrae TODAS las secciones del TDR para selector visual ────────────
    public function extraerSeccionesTdr(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $uploadError = $_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK || empty($_FILES['pdf']['tmp_name'])) {
            echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo.']);
            return;
        }

        $mime = mime_content_type($_FILES['pdf']['tmp_name']);
        if (strpos($mime, 'pdf') === false && strpos($mime, 'text') === false) {
            preg_match('/\.([a-zA-Z0-9]+)$/', $_FILES['pdf']['name'], $mExt);
            if (!in_array(strtolower($mExt[1] ?? ''), ['pdf','txt'])) {
                echo json_encode(['ok' => false, 'error' => 'Solo PDF o TXT.']);
                return;
            }
        }

        try {
            $result = PdfExtractorService::extraerSecciones($_FILES['pdf']['tmp_name']);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
} // fin ExtractorController

// ══════════════════════════════════════════════════════════════════════════
// HOME CONTROLLER — redirige / al login o dashboard
// ══════════════════════════════════════════════════════════════════════════
class HomeController extends BaseController
{
    public function index(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        } else {
            $this->redirect('/login');
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// AUTH CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class AuthController extends BaseController
{
    public function showLogin(): void
    {
        View::make('guest', 'auth.login', ['title' => 'Iniciar Sesión']);
    }

    public function login(): void
    {
        verifyCsrf();
        $email    = trim($this->input('email', ''));
        $password = $this->input('password', '');

        if (!$email || !$password) {
            View::flash('error', 'Email y contraseña son requeridos.');
            $this->redirect('/login');
            return;
        }

        if (!Auth::attempt($email, $password)) {
            View::flash('error', 'Credenciales incorrectas o cuenta inactiva.');
            $this->redirect('/login');
            return;
        }

        // Cargar datos del tenant en sesión
        $tenant = Tenant::find((int)Auth::tenantId());
        if ($tenant) {
            $_SESSION['tenant_nombre']       = $tenant['nombre'];
            $_SESSION['tenant_ruc']          = $tenant['ruc'] ?? '';
            $_SESSION['tenant_representante']= $tenant['representante_legal'] ?? '';
            $_SESSION['tenant_ciudad']       = $tenant['ciudad'] ?? 'Quito';
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}

// ══════════════════════════════════════════════════════════════════════════
// DASHBOARD CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class DashboardController extends BaseController
{
    public function index(): void
    {
        $stats     = Proceso::dashboard();
        $procesos  = DB::select(
            "SELECT p.*, i.nombre AS inst FROM procesos p JOIN instituciones i ON i.id=p.institucion_id
             WHERE p.tenant_id=? AND p.estado NOT IN ('cerrado','cancelado') AND p.deleted_at IS NULL
             ORDER BY p.created_at DESC LIMIT 8",
            [DB::getTenantId()]
        );
        $alertas   = DB::select(
            "SELECT * FROM documentos_habilitantes WHERE tenant_id=? AND estado IN ('por_vencer','vencido') AND deleted_at IS NULL ORDER BY fecha_vencimiento ASC LIMIT 5",
            [DB::getTenantId()]
        );
        $pendientes = Factura::pendientesCobro();
        DocumentoHabilitante::actualizarEstados();

        // Dominios por vencer en los próximos 60 días
        Dominio::actualizarEstados();
        $dominiosAlerta = Dominio::proximosVencer(60);

        $this->view('dashboard.index', compact('stats', 'procesos', 'alertas', 'pendientes', 'dominiosAlerta') + ['title' => 'Dashboard']);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// PROCESOS CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class ProcesosController extends BaseController
{
    public function index(): void
    {
        $filtros    = $_GET;
        $page       = (int)($filtros['page'] ?? 1);
        $paginator  = Proceso::listar($filtros, $page);
        $instituciones = Institucion::all('nombre ASC');
        $this->view('procesos.index', compact('paginator', 'instituciones', 'filtros') + ['title' => 'Procesos']);
    }

    public function create(): void
    {
        $this->requirePermission('procesos.*');
        $instituciones = Institucion::all('nombre ASC');
        $this->view('procesos.create', compact('instituciones') + ['title' => 'Nuevo Proceso']);
    }

    // ── IMPORTAR DESDE SERCOP (permanece en /procesos/crear) ─────────────
    public function importarSercop(): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();
        $instituciones = Institucion::all('nombre ASC');
        $url = trim($_POST['url_sercop'] ?? '');

        if (empty($url)) {
            View::flash('error', 'Por favor ingresa una URL del portal SERCOP.');
            $this->view('procesos.create', compact('instituciones') + ['title' => 'Nuevo Proceso']);
            return;
        }

        try {
            $resultado         = PdfExtractorService::extraerDeUrl($url);
            $datos             = $resultado['datos'];
            $metodo            = $resultado['metodo'];
            $aviso             = $resultado['aviso'] ?? null;
            $items             = $datos['items']                 ?? [];
            $fechaLimite       = $datos['fecha_limite_proforma'] ?? '';
            $funcionarioNombre = $datos['funcionario']           ?? '';
            $funcionarioEmail  = $datos['correo_contacto']       ?? '';

            $camposExtraidos = array_filter([
                'NIC'        => $datos['numero_proceso']          ?? '',
                'Institución'=> $datos['institucion_contratante'] ?? '',
                'Objeto'     => mb_substr($datos['objeto_contratacion'] ?? '', 0, 60),
                'CPC'        => $datos['cpc']                     ?? '',
                'Provincia'  => $datos['provincia']               ?? '',
                'Cantón'     => $datos['canton']                  ?? '',
                'Límite'     => $fechaLimite,
            ]);
            $aviso = empty($datos['objeto_contratacion'])
                ? 'Atención: objeto vacío. Extraídos: ' . implode(' | ', array_map(function($k,$v){ return "$k: $v"; }, array_keys($camposExtraidos), $camposExtraidos))
                : ($resultado['aviso'] ?? null);

            $this->view('procesos.create', compact(
                'instituciones','datos','metodo','aviso',
                'items','fechaLimite','funcionarioNombre','funcionarioEmail'
            ) + ['title' => 'Nuevo Proceso', 'url_sercop' => $url]);

        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Si el servidor no puede acceder al SERCOP → mostrar opción pegar HTML
            $sercopBloqueado = strpos($msg, 'SERCOP_BLOCKED') !== false;
            $errorImport = $sercopBloqueado
                ? 'El servidor no pudo conectarse al portal SERCOP. Usa el modo alternativo abajo.'
                : 'Error al consultar SERCOP: ' . $msg;
            $this->view('procesos.create', compact('instituciones','errorImport','sercopBloqueado','url') + ['title' => 'Nuevo Proceso', 'url_sercop' => $url]);
        }
    }

    // ── IMPORTAR PEGANDO HTML COPIADO DEL NAVEGADOR ───────────────────────
    public function importarSercopHtml(): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();
        $instituciones = Institucion::all('nombre ASC');
        $html = trim($_POST['html_sercop'] ?? '');
        $url  = trim($_POST['url_sercop']  ?? '');

        if (strlen($html) < 200) {
            $errorImport = 'El HTML pegado está vacío. Copia el contenido completo de la página.';
            $sercopBloqueado = true;
            $this->view('procesos.create', compact('instituciones','errorImport','sercopBloqueado','url') + ['title' => 'Nuevo Proceso', 'url_sercop' => $url]);
            return;
        }

        try {
            $resultado         = PdfExtractorService::extraerDeHtml($html, $url);
            $datos             = $resultado['datos'];
            $metodo            = 'sercop_html';
            $items             = $datos['items']                 ?? [];
            $fechaLimite       = $datos['fecha_limite_proforma'] ?? '';
            $funcionarioNombre = $datos['funcionario']           ?? '';
            $funcionarioEmail  = $datos['correo_contacto']       ?? '';

            $camposExtraidos = array_filter([
                'NIC'        => $datos['numero_proceso']          ?? '',
                'Institución'=> $datos['institucion_contratante'] ?? '',
                'Objeto'     => mb_substr($datos['objeto_contratacion'] ?? '', 0, 60),
                'CPC'        => $datos['cpc']                     ?? '',
                'Tipo'       => $datos['tipo_proceso']            ?? '',
                'Provincia'  => $datos['provincia']               ?? '',
                'Cantón'     => $datos['canton']                  ?? '',
                'Límite'     => $fechaLimite,
                'Funcionario'=> $funcionarioNombre,
            ]);
            $aviso = 'Extraídos: ' . implode(' | ', array_map(
                function($k,$v){ return "$k: $v"; },
                array_keys($camposExtraidos), $camposExtraidos
            ));

            $this->view('procesos.create', compact(
                'instituciones','datos','metodo','aviso',
                'items','fechaLimite','funcionarioNombre','funcionarioEmail'
            ) + ['title' => 'Nuevo Proceso', 'url_sercop' => $url]);

        } catch (\Throwable $e) {
            $errorImport = 'Error al procesar el HTML: ' . $e->getMessage();
            $sercopBloqueado = true;
            $this->view('procesos.create', compact('instituciones','errorImport','sercopBloqueado','url') + ['title' => 'Nuevo Proceso', 'url_sercop' => $url]);
        }
    }

    public function store(): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();

        // ── Validación básica ─────────────────────────────────────────────
        $numeroProceso     = trim($_POST['numero_proceso']     ?? '');
        $objetoContrat     = trim($_POST['objeto_contratacion']?? '');
        $tipoProceso       = trim($_POST['tipo_proceso']       ?? '');

        if (empty($numeroProceso) || empty($objetoContrat) || empty($tipoProceso)) {
            View::flash('error', 'Los campos Nº Proceso, Objeto y Tipo son obligatorios.');
            $this->redirect('/procesos/crear');
            return;
        }

        // ── Crear institución inline si viene del extractor ──────────────
        $institucionId = (int)($_POST['institucion_id'] ?? 0);

        if (!empty($_POST['nueva_institucion']) && $institucionId === 0) {
            $instNombre = trim($_POST['inst_nombre'] ?? '');
            if (empty($instNombre)) {
                View::flash('error', 'El nombre de la institución es obligatorio.');
                $this->redirect('/procesos/crear');
                return;
            }
            $institucionId = (int) Institucion::create([
                'nombre'               => strtoupper($instNombre),
                'ruc'                  => trim($_POST['inst_ruc']           ?? '0000000000001'),
                'ciudad'               => trim($_POST['inst_ciudad']        ?? ''),
                'direccion'            => trim($_POST['inst_direccion']     ?? ''),
                'administrador_nombre' => trim($_POST['inst_administrador'] ?? ''),
                'administrador_email'  => trim($_POST['inst_email']         ?? ''),
                'administrador_cargo'  => 'Administrador del Contrato',
                'activo'               => 1,
            ]);
            DB::audit('CREATE', 'instituciones', $institucionId, null, ['nombre' => $instNombre]);
        }

        if (!$institucionId) {
            View::flash('error', 'Debes seleccionar o crear una institución.');
            $this->redirect('/procesos/crear');
            return;
        }

        $id = Proceso::create([
            'numero_proceso'           => $numeroProceso,
            'tipo_proceso'             => $tipoProceso,
            'objeto_contratacion'      => $objetoContrat,
            'cpc'                      => ($_POST['cpc']                    ?? '') ?: null,
            'cpc_descripcion'          => ($_POST['cpc_descripcion']        ?? '') ?: null,
            'descripcion_detallada'    => ($_POST['descripcion_detallada']  ?? '') ?: null,
            'especificaciones_tecnicas'=> ($_POST['especificaciones_tecnicas'] ?? '') ?: null,
            'metodologia_trabajo'      => ($_POST['metodologia_trabajo']    ?? '') ?: null,
            'forma_pago'               => ($_POST['forma_pago']             ?? '') ?: null,
            'vigencia_oferta'          => ($_POST['vigencia_oferta']        ?? '') ?: null,
            'alcance'                  => ($_POST['alcance']                ?? '') ?: null,
            'institucion_id'           => $institucionId,
            'monto_total'              => 0,
            'plazo_dias'               => 0,
            'fecha_inicio'             => ($_POST['fecha_inicio']          ?? '') ?: null,
            'fecha_limite_proforma'    => ($_POST['fecha_limite_proforma'] ?? '') ?: null,
            'url_sercop'               => ($_POST['url_sercop']            ?? '') ?: null,
            'notas_internas'           => ($_POST['notas_internas']        ?? '') ?: null,
            'tiene_anticipo'           => 0,
            'tiene_garantia'           => 1,
            'plazo_garantia_dias'      => 365,
            'fase'                     => 1,
            'estado'                   => 'en_proceso',
            'created_by'               => authId(),
        ]);

        DB::audit('CREATE', 'procesos', $id, null, ['numero' => $numeroProceso]);

        // ── Guardar ítems si vienen del extractor SERCOP ──────────────────
        $itemsJson = $_POST['items_json'] ?? '[]';
        $itemsData = json_decode($itemsJson, true);
        if (is_array($itemsData) && !empty($itemsData)) {
            foreach ($itemsData as $it) {
                DB::insert('proceso_items', [
                    'proceso_id'      => $id,
                    'tenant_id'       => tenantId(),
                    'numero'          => (int)($it['numero'] ?? 0),
                    'cpc'             => ($it['cpc'] ?? '') ?: null,
                    'cpc_descripcion' => ($it['cpc_descripcion'] ?? '') ?: null,
                    'descripcion'     => ($it['descripcion'] ?? '') ?: null,
                    'unidad'          => ($it['unidad'] ?? '') ?: null,
                    'cantidad'        => (float)($it['cantidad'] ?? 0),
                    'precio_unitario' => (float)($it['precio_unitario'] ?? 0),
                    'precio_total'    => (float)($it['precio_total'] ?? 0),
                ]);
            }
        }

        $msg = 'Proceso creado exitosamente.';
        if (!empty($_POST['nueva_institucion'])) $msg .= ' Institución "' . strtoupper(trim($_POST['inst_nombre'] ?? '')) . '" creada automáticamente.';
        View::flash('success', $msg);
        $this->redirect("/procesos/{$id}");
    }

    public function show(string $id): void
    {
        $proceso     = Proceso::conInstitucion((int)$id);
        if (!$proceso) { View::flash('error', 'Proceso no encontrado.'); $this->redirect('/procesos'); return; }
        $entregables  = Entregable::porProceso((int)$id);
        $items        = ProcesoItem::porProceso((int)$id);
        $documentos   = DocumentoProceso::porCategoria((int)$id);
        $facturas     = Factura::where(['proceso_id' => (int)$id]);
        $camposExtra  = ProcesoCampoExtra::porProceso((int)$id);
        $plantillasCampos = PlantillaCampos::listar();
        DB::audit('VIEW', 'procesos', (int)$id);
        $this->view('procesos.show', compact('proceso','entregables','items','documentos','facturas','camposExtra','plantillasCampos') + ['title' => 'Proceso ' . $proceso['numero_proceso']]);
    }

    // ── FASE 2: guardar datos técnicos + campos extra ──────────────────────
    public function storeFase2(string $id): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();
        $proceso = Proceso::find((int)$id);
        if (!$proceso) { View::flash('error', 'Proceso no encontrado.'); $this->redirect('/procesos'); return; }

        // Actualizar campos base de Fase 2
        $update = [];

        // Campos de texto — pueden quedar null
        foreach ([
            'cpc',
            'especificaciones_tecnicas',
            'metodologia_trabajo',
            'cpc_descripcion',
            'plazo_texto',
            'forma_pago',
            'vigencia_oferta',
            'declaracion_cumplimiento',
            'nota_espec_texto',
        ] as $f) {
            if (isset($_POST[$f])) $update[$f] = $_POST[$f] === '' ? null : $_POST[$f];
        }

        // Toggles on/off: el checkbox envía "1" si activo, sino viene el hidden "0"
        $update['nota_espec_activa']  = ($_POST['nota_espec_activa']  ?? $_POST['nota_espec_activa_off']  ?? '1') === '1' ? '1' : '0';
        $update['declaracion_activa'] = ($_POST['declaracion_activa'] ?? $_POST['declaracion_activa_off'] ?? '1') === '1' ? '1' : '0';
        // Si la declaración está desactivada, limpiar el texto guardado
        if ($update['declaracion_activa'] === '0') {
            $update['declaracion_cumplimiento'] = null;
        }
        // Campos numéricos — solo actualizar si vienen con valor
        if (!empty($_POST['monto_total'])) $update['monto_total'] = (float)$_POST['monto_total'];
        if (!empty($_POST['plazo_dias']))  $update['plazo_dias']  = (int)$_POST['plazo_dias'];

        $update['fase'] = 2;

        Proceso::update((int)$id, $update);
        DB::audit('UPDATE', 'procesos', (int)$id, $proceso, $update);

        // Guardar campos extra dinámicos
        $nombres    = $_POST['campo_nombre']    ?? [];
        $contenidos = $_POST['campo_contenido'] ?? [];
        $campos = [];
        foreach ($nombres as $i => $nombre) {
            if (trim($nombre) === '') continue;
            $campos[] = ['nombre' => $nombre, 'contenido' => $contenidos[$i] ?? ''];
        }
        ProcesoCampoExtra::reemplazar((int)$id, $campos);

        // Guardar ítems si vienen del formulario (precios editados en Fase 2)
        if (!empty($_POST['items_json'])) {
            $itemsData = json_decode($_POST['items_json'], true);
            if (is_array($itemsData) && !empty($itemsData)) {
                ProcesoItem::sincronizar((int)$id, $itemsData);
                // Recalcular monto total si no fue editado manualmente
                if (empty($_POST['monto_total'])) {
                    $total = array_sum(array_column($itemsData, 'precio_total'));
                    if ($total > 0) Proceso::update((int)$id, ['monto_total' => $total]);
                }
            }
        }

        // Guardar como plantilla si se solicitó
        if (!empty($_POST['guardar_plantilla']) && !empty($_POST['nombre_plantilla'])) {
            $camposJson = array_map(function($c){ return ['nombre' => $c['nombre'], 'placeholder' => '']; }, $campos);
            PlantillaCampos::create([
                'tenant_id'   => DB::getTenantId(),
                'nombre'      => trim($_POST['nombre_plantilla']),
                'descripcion' => trim($_POST['desc_plantilla'] ?? ''),
                'campos'      => json_encode($camposJson, JSON_UNESCAPED_UNICODE),
            ]);
        }

        View::flash('success', 'Datos técnicos guardados correctamente.');
        $this->redirect("/procesos/{$id}");
    }

    public function edit(string $id): void
    {
        $this->requirePermission('procesos.*');
        $proceso       = Proceso::conInstitucion((int)$id);
        $instituciones = Institucion::all('nombre ASC');
        $this->view('procesos.edit', compact('proceso', 'instituciones') + ['title' => 'Editar Proceso']);
    }

    public function update(string $id): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();
        $antes = Proceso::find((int)$id);
        $data  = $this->allInput();
        unset($data['_csrf'], $data['_method']);
        Proceso::update((int)$id, $data);
        DB::audit('UPDATE', 'procesos', (int)$id, $antes, $data);
        View::flash('success', 'Proceso actualizado.');
        $this->redirect("/procesos/{$id}");
    }

    public function cambiarEstado(string $id): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();
        $nuevoEstado = (string)$this->input('estado', '');
        if ($nuevoEstado === '') {
            View::flash('error', 'Estado no especificado.');
            $this->redirect("/procesos/{$id}");
            return;
        }
        Proceso::cambiarEstado((int)$id, $nuevoEstado);
        View::flash('success', 'Estado actualizado a: ' . $nuevoEstado);
        $this->redirect("/procesos/{$id}");
    }

    public function destroy(string $id): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();
        Proceso::delete((int)$id);
        View::flash('success', 'Proceso eliminado.');
        $this->redirect('/procesos');
    }

    // ── Editor previo al documento ────────────────────────────────────────
    public function editarDocumento(string $id): void
    {
        $proceso = Proceso::conInstitucion((int)$id);
        if (!$proceso) { $this->redirect('/procesos'); return; }

        $tipo    = $this->input('tipo', 'informe_tecnico');
        $titulos = [
            'informe_tecnico'    => 'Informe Técnico de Entrega',
            'garantia_tecnica'   => 'Certificado de Garantía Técnica',
            'acta_provisional'   => 'Acta de Entrega Provisional',
            'acta_definitiva'    => 'Acta de Entrega Definitiva',
            'solicitud_pago'     => 'Solicitud de Pago',
            'informe_conformidad'=> 'Informe de Conformidad',
        ];
        $titulo = $titulos[$tipo] ?? ucfirst(str_replace('_',' ',$tipo));

        $camposExtra = ProcesoCampoExtra::porProceso((int)$id);
        $proceso['_campos_extra'] = $camposExtra;

        $this->view('procesos.documento_editor', compact('proceso','tipo','titulo') + ['title' => $titulo]);
    }

    // ── Generar y mostrar documento HTML (igual que proforma) ────────────
    public function generarDocumento(string $id): void
    {
        $proceso = Proceso::conInstitucion((int)$id);
        if (!$proceso) { $this->redirect('/procesos'); return; }

        $tipo = $_POST['tipo'] ?? $this->input('tipo', 'informe_tecnico');

        // Sobrescribir campos del proceso con los editados en el formulario
        $override = [
            'numero_proceso','objeto_contratacion','monto_total','plazo_dias',
            'fecha_inicio','fecha_fin','vigencia_oferta','forma_pago',
            'especificaciones_tecnicas','metodologia_trabajo','declaracion_cumplimiento',
            'cpc','cpc_descripcion','plazo_texto',
        ];
        foreach ($override as $campo) {
            if (isset($_POST[$campo]) && $_POST[$campo] !== '') {
                $proceso[$campo] = $_POST[$campo];
            }
        }
        // Campos adicionales del formulario editor
        $proceso['_doc_fecha']         = ($_POST['doc_fecha']         ?? '') ?: date('d/m/Y');
        $proceso['_doc_lugar']         = ($_POST['doc_lugar']         ?? '') ?: ($proceso['canton'] ?? 'Quito');
        $proceso['_doc_observaciones'] = $_POST['doc_observaciones']  ?? '';
        $proceso['_doc_numero']        = $_POST['doc_numero']         ?? (date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT));

        $camposExtra = ProcesoCampoExtra::porProceso((int)$id);
        $proceso['_campos_extra'] = $camposExtra;

        $tenant  = Tenant::find(tenantId());
        $logoUrl = $tenant['logo_url'] ?? '';

        $html = \Services\DocumentoService::generar($tipo, $proceso, tenantId(), $logoUrl);

        // URL de retorno y PDF
        $html = str_replace('{{url_back}}',     "/procesos/{$id}",                    $html);
        $html = str_replace('{{url_pdf}}',      "/procesos/{$id}/documento/pdf?tipo={$tipo}", $html);

        // Guardar en expediente (solo última versión generada)
        try {
            $cat      = $this->categoriaPorTipo($tipo);
            $dir      = tenantStoragePath("procesos/{$id}/{$cat}");
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            // Eliminar versiones anteriores auto-generadas del mismo tipo
            $anteriores = \DB::select(
                "SELECT id, ruta_storage FROM documentos_proceso
                 WHERE proceso_id = ? AND categoria = ? AND es_generado = 1 AND deleted_at IS NULL AND tenant_id = ?",
                [(int)$id, $cat, tenantId()]
            );
            foreach ($anteriores as $ant) {
                if (!empty($ant['ruta_storage']) && file_exists($ant['ruta_storage'])) {
                    @unlink($ant['ruta_storage']);
                }
                \DB::query("UPDATE documentos_proceso SET deleted_at = NOW() WHERE id = ?", [$ant['id']]);
            }

            $filename = $tipo . '_' . date('Ymd_His') . '.html';
            file_put_contents($dir . '/' . $filename, $html);
            DocumentoProceso::create([
                'proceso_id'     => (int)$id,
                'categoria'      => $cat,
                'nombre_archivo' => $filename,
                'nombre_original'=> $tipo . '_' . $proceso['numero_proceso'] . '.html',
                'ruta_storage'   => $dir . '/' . $filename,
                'tipo_mime'      => 'text/html',
                'es_generado'    => 1,
                'subido_por'     => authId(),
            ]);
        } catch (\Throwable $e) { /* no bloquear */ }

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    // ── Descargar documento como PDF (mPDF) ──────────────────────────────
    public function descargarDocumentoPdf(string $id): void
    {
        $proceso = Proceso::conInstitucion((int)$id);
        if (!$proceso) { $this->redirect('/procesos'); return; }

        $tipo        = $this->input('tipo', 'informe_tecnico');
        $camposExtra = ProcesoCampoExtra::porProceso((int)$id);
        $proceso['_campos_extra'] = $camposExtra;

        $tenant  = Tenant::find(tenantId());
        $logoUrl = $tenant['logo_url'] ?? '';
        $html    = \Services\DocumentoService::generar($tipo, $proceso, tenantId(), $logoUrl);
        $html    = str_replace(['{{url_back}}','{{url_pdf}}'], ["/procesos/{$id}", '#'], $html);

        $titulos = [
            'informe_tecnico'    => 'InformeTecnico',
            'garantia_tecnica'   => 'Garantia',
            'acta_provisional'   => 'ActaProvisional',
            'acta_definitiva'    => 'ActaDefinitiva',
            'solicitud_pago'     => 'SolicitudPago',
            'informe_conformidad'=> 'InformeConformidad',
        ];
        $nombreArchivo = ($titulos[$tipo] ?? $tipo) . '_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $proceso['numero_proceso']) . '.pdf';

        $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
        if (file_exists($vendorAutoload)) require_once $vendorAutoload;

        if (class_exists('\Mpdf\Mpdf')) {
            try {
                $mpdf = new \Mpdf\Mpdf([
                    'mode'          => 'utf-8',
                    'format'        => 'A4',
                    'margin_top'    => 15,
                    'margin_bottom' => 15,
                    'margin_left'   => 20,
                    'margin_right'  => 20,
                    'tempDir'       => sys_get_temp_dir(),
                ]);
                $mpdf->SetTitle($nombreArchivo);
                $mpdf->WriteHTML($html);
                $mpdf->Output($nombreArchivo, 'D');
                exit;
            } catch (\Throwable $e) { /* fallback */ }
        }

        $aviso = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px;font-family:Arial;font-size:12px">
            <strong>⚠ mPDF no instalado.</strong> Usa <strong>Ctrl+P → Guardar como PDF</strong>.
        </div>';
        header('Content-Type: text/html; charset=UTF-8');
        echo $aviso . $html;
        exit;
    }

    private function categoriaPorTipo(string $tipo): string
    {
        if (strpos($tipo, 'proforma')   !== false) return 'proforma';
        if (strpos($tipo, 'informe')    !== false) return 'informe_tecnico';
        if (strpos($tipo, 'acta')       !== false) return 'acta_entrega';
        if (strpos($tipo, 'garantia')   !== false) return 'garantia';
        if (strpos($tipo, 'pago')       !== false) return 'solicitud_pago';
        if (strpos($tipo, 'aceptacion') !== false) return 'orden_compra';
        return 'otro';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// INSTITUCIONES CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class InstitucionesController extends BaseController
{
    public function index(): void
    {
        $instituciones = Institucion::conEstadisticas();
        $this->view('instituciones.index', compact('instituciones') + ['title' => 'Instituciones']);
    }

    public function create(): void
    {
        $this->requirePermission('instituciones.*');
        $this->view('instituciones.create', ['title' => 'Nueva Institución']);
    }

    public function store(): void
    {
        $this->requirePermission('instituciones.*');
        verifyCsrf();
        $data = $this->validate([
            'nombre' => 'required|max:300',
            'ruc'    => 'required|min:13|max:13',
            'tipo'   => 'required',
        ]);
        $id = Institucion::create($data);
        View::flash('success', 'Institución creada.');
        $this->redirect("/instituciones/{$id}");
    }

    public function show(string $id): void
    {
        $inst     = Institucion::findOrFail((int)$id);
        $procesos = Proceso::where(['institucion_id' => (int)$id]);
        // Dominios se cargan directamente en la vista via Dominio::porInstitucion()
        $this->view('instituciones.show', compact('inst', 'procesos') + ['title' => $inst['nombre']]);
    }

    public function edit(string $id): void
    {
        $inst = Institucion::findOrFail((int)$id);
        $this->view('instituciones.edit', compact('inst') + ['title' => 'Editar Institución']);
    }

    public function update(string $id): void
    {
        verifyCsrf();
        $data = $this->allInput();
        unset($data['_csrf'], $data['_method']);
        Institucion::update((int)$id, $data);
        View::flash('success', 'Institución actualizada.');
        $this->redirect("/instituciones/{$id}");
    }

    public function destroy(string $id): void
    {
        $this->requirePermission('instituciones.*');
        verifyCsrf();

        $inst = Institucion::find((int)$id);
        if (!$inst) {
            View::flash('error', 'Institución no encontrada.');
            $this->redirect('/instituciones');
            return;
        }

        // Verificar que no tenga procesos activos
        $totalProcesos = DB::count(
            "SELECT COUNT(*) FROM procesos WHERE institucion_id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [(int)$id, DB::getTenantId()]
        );

        if ($totalProcesos > 0) {
            View::flash('error', "No se puede eliminar: la institución tiene {$totalProcesos} proceso(s) asociado(s). Elimina primero los procesos.");
            $this->redirect('/instituciones');
            return;
        }

        // Soft delete manual en caso de que BaseModel::delete no haga soft delete
        DB::query(
            "UPDATE instituciones SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?",
            [(int)$id, DB::getTenantId()]
        );

        View::flash('success', 'Institución eliminada correctamente.');
        $this->redirect('/instituciones');
    }
}

// ══════════════════════════════════════════════════════════════════════════
// DOCUMENTOS HABILITANTES CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class DocumentosHabilitantesController extends BaseController
{
    public function index(): void
    {
        DocumentoHabilitante::actualizarEstados();
        $docs = DocumentoHabilitante::all('fecha_vencimiento ASC');
        $this->view('documentos.habilitantes', compact('docs') + ['title' => 'Documentos Habilitantes']);
    }

    public function store(): void
    {
        verifyCsrf();
        $data = $this->validate([
            'tipo'   => 'required',
            'nombre' => 'required|max:200',
        ]);

        // Subir archivo si viene
        if (!empty($_FILES['archivo']['name'])) {
            try {
                $uploaded = uploadFile($_FILES['archivo'], tenantStoragePath('habilitantes'));
                $data['archivo_url'] = 'habilitantes/' . $uploaded['filename'];
            } catch (\Throwable $e) {
                View::flash('error', $e->getMessage());
                $this->redirect('/documentos-habilitantes');
                return;
            }
        }

        DocumentoHabilitante::create($data);
        View::flash('success', 'Documento registrado.');
        $this->redirect('/documentos-habilitantes');
    }

    public function update(string $id): void
    {
        verifyCsrf();
        $data = $this->allInput();
        unset($data['_csrf'], $data['_method']);
        DocumentoHabilitante::update((int)$id, $data);
        View::flash('success', 'Documento actualizado.');
        $this->redirect('/documentos-habilitantes');
    }

    public function destroy(string $id): void
    {
        verifyCsrf();
        DocumentoHabilitante::delete((int)$id);
        View::flash('success', 'Documento eliminado.');
        $this->redirect('/documentos-habilitantes');
    }
}

// ══════════════════════════════════════════════════════════════════════════
// DOCUMENTOS DEL PROCESO CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class DocumentosProcesoController extends BaseController
{
    public function upload(string $id): void
    {
        verifyCsrf();
        $id = $id;
        if (empty($_FILES['archivo']['name'])) {
            View::flash('error', 'Seleccione un archivo.');
            $this->redirect("/procesos/{$id}");
            return;
        }
        try {
            $destDir  = tenantStoragePath("procesos/{$id}/" . $this->input('categoria', 'otro'));
            $uploaded = uploadFile($_FILES['archivo'], $destDir);
            DocumentoProceso::create([
                'proceso_id'     => (int)$id,
                'categoria'      => $this->input('categoria', 'otro'),
                'nombre_archivo' => $uploaded['filename'],
                'nombre_original'=> $uploaded['original'],
                'ruta_storage'   => $uploaded['path'],
                'tipo_mime'      => $uploaded['mime'],
                'tamano_bytes'   => $uploaded['size'],
                'hash_sha256'    => $uploaded['hash'],
                'subido_por'     => authId(),
            ]);
            View::flash('success', 'Documento subido exitosamente.');
        } catch (\Throwable $e) {
            View::flash('error', $e->getMessage());
        }
        $this->redirect("/procesos/{$id}");
    }

    public function download(string $id): void
    {
        $doc = DocumentoProceso::find((int)$id);
        if (!$doc || !file_exists($doc['ruta_storage'])) {
            View::flash('error', 'Archivo no encontrado.');
            $this->redirect('/procesos');
            return;
        }
        DB::audit('EXPORT', 'documentos_proceso', (int)$id);
        header('Content-Type: ' . ($doc['tipo_mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $doc['nombre_original'] . '"');
        header('Content-Length: ' . filesize($doc['ruta_storage']));
        readfile($doc['ruta_storage']);
        exit;
    }

    public function destroy(string $id): void
    {
        verifyCsrf();
        $doc = DocumentoProceso::find((int)$id);
        if (!$doc) {
            View::flash('error', 'Documento no encontrado.');
            $this->redirect('/procesos');
            return;
        }
        $procesoId = $doc['proceso_id'];
        // Eliminar archivo físico
        if (!empty($doc['ruta_storage']) && file_exists($doc['ruta_storage'])) {
            @unlink($doc['ruta_storage']);
        }
        DB::query(
            "UPDATE documentos_proceso SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?",
            [(int)$id, DB::getTenantId()]
        );
        View::flash('success', 'Archivo eliminado.');
        $this->redirect("/procesos/{$procesoId}");
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ENTREGABLES CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class EntregablesController extends BaseController
{
    public function store(string $id): void
    {
        verifyCsrf();
        $data = $this->validate(['nombre' => 'required|max:300']);
        Entregable::create(array_merge($data, [
            'proceso_id'   => (int)$id,
            'numero_orden' => (int)$this->input('numero_orden', 1),
            'descripcion'  => $this->input('descripcion'),
            'fecha_compromiso' => $this->input('fecha_compromiso'),
            'monto_entregable' => $this->input('monto_entregable') ? (float)$this->input('monto_entregable') : null,
        ]));
        $avance = Entregable::calcularAvance((int)$id);
        Proceso::update((int)$id, ['porcentaje_avance' => $avance]);
        View::flash('success', 'Entregable agregado.');
        $this->redirect("/procesos/{$id}");
    }

    public function cambiarEstado(string $id): void
    {
        verifyCsrf();
        $entregable = Entregable::find((int)$id);
        Entregable::update((int)$id, [
            'estado'        => $this->input('estado'),
            'fecha_entrega' => $this->input('estado') === 'entregado' ? date('Y-m-d') : null,
            'observaciones' => $this->input('observaciones'),
        ]);
        $avance = Entregable::calcularAvance((int)$entregable['proceso_id']);
        Proceso::update((int)$entregable['proceso_id'], ['porcentaje_avance' => $avance]);
        View::flash('success', 'Estado del entregable actualizado.');
        $this->redirect("/procesos/{$entregable['proceso_id']}");
    }
}

// ══════════════════════════════════════════════════════════════════════════
// FACTURAS CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class FacturasController extends BaseController
{
    public function index(): void
    {
        $pendientes = Factura::pendientesCobro();
        $todas      = DB::paginate(
            "SELECT f.*, p.numero_proceso, i.nombre AS inst FROM facturas f
             JOIN procesos p ON p.id=f.proceso_id JOIN instituciones i ON i.id=p.institucion_id
             WHERE f.tenant_id=? AND f.deleted_at IS NULL ORDER BY f.fecha_emision DESC",
            [DB::getTenantId()], (int)($_GET['page'] ?? 1)
        );
        $this->view('facturas.index', compact('pendientes', 'todas') + ['title' => 'Facturas']);
    }

    public function store(): void
    {
        $this->requirePermission('facturas.*');
        verifyCsrf();
        $data = $this->validate([
            'proceso_id'   => 'required|integer',
            'numero_sri'   => 'required|max:50',
            'fecha_emision'=> 'required|date',
            'monto_total'  => 'required|numeric|min_val:0.01',
        ]);
        $data['monto_subtotal']  = (float)$this->input('monto_subtotal', $data['monto_total']);
        $data['monto_iva']       = (float)$this->input('monto_iva', 0);
        $data['retencion_fuente']= (float)$this->input('retencion_fuente', 0);
        $data['retencion_iva']   = (float)$this->input('retencion_iva', 0);
        $data['monto_neto']      = $data['monto_total'] - $data['retencion_fuente'] - $data['retencion_iva'];
        $id = Factura::create($data);
        Proceso::cambiarEstado((int)$data['proceso_id'], 'facturado');
        View::flash('success', 'Factura registrada.');
        $this->redirect('/facturas');
    }

    public function registrarPago(string $id): void
    {
        $this->requirePermission('pagos.*');
        verifyCsrf();
        $data = $this->validate([
            'fecha_pago'  => 'required|date',
            'monto_pagado'=> 'required|numeric|min_val:0.01',
        ]);
        Pago::registrar((int)$id, array_merge($data, [
            'tipo_pago' => $this->input('tipo_pago', 'transferencia'),
            'referencia'=> $this->input('referencia'),
        ]));
        View::flash('success', '✅ Pago registrado exitosamente.');
        $this->redirect('/facturas');
    }
}

// ══════════════════════════════════════════════════════════════════════════
// IA CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class IaController extends BaseController
{
    public function index(): void
    {
        $analisis = DB::select(
            "SELECT * FROM analisis_ia WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 20",
            [DB::getTenantId()]
        );
        $this->view('ia.index', compact('analisis') + ['title' => 'Análisis IA']);
    }

    public function analizar(): void
    {
        verifyCsrf();
        if (empty($_FILES['documento']['name'])) {
            View::flash('error', 'Seleccione un documento para analizar.');
            $this->redirect('/ia');
            return;
        }

        try {
            $uploaded = uploadFile($_FILES['documento'], tenantStoragePath('ia_uploads'),
                ['application/pdf', 'text/plain', 'application/msword',
                 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

            $texto = IaService::extractText($uploaded['path']);
            if (empty(trim($texto))) {
                View::flash('error', 'No se pudo extraer texto del documento. Pruebe con un PDF de texto (no escaneado) o suba un archivo .txt');
                $this->redirect('/ia');
                return;
            }

            $resultado = IaService::analizarDocumento($texto, $this->input('tipo_doc', 'tdr'));

            $analisisId = AnalisisIA::create([
                'tipo_documento' => $this->input('tipo_doc', 'tdr'),
                'modelo_usado'   => $resultado['modelo'],
                'tokens_usados'  => $resultado['tokens_usados'],
                'texto_original' => substr($texto, 0, 5000),
                'datos_extraidos'=> json_encode($resultado['datos'], JSON_UNESCAPED_UNICODE),
                'estado'         => 'completado',
            ]);

            View::flash('success', '✅ Documento analizado correctamente. Revise los datos extraídos.');
            $this->redirect("/ia/{$analisisId}/aplicar");

        } catch (\Throwable $e) {
            logError('IA Error: ' . $e->getMessage());
            View::flash('error', 'Error al analizar: ' . $e->getMessage());
            $this->redirect('/ia');
        }
    }

    public function mostrarAplicar(string $id): void
    {
        $analisis      = AnalisisIA::findOrFail((int)$id);
        $datos         = json_decode($analisis['datos_extraidos'], true);
        $instituciones = Institucion::all('nombre ASC');
        $this->view('ia.aplicar', compact('analisis', 'datos', 'instituciones') + ['title' => 'Aplicar Análisis IA']);
    }

    public function aplicar(string $id): void
    {
        verifyCsrf();
        $analisis = AnalisisIA::findOrFail((int)$id);
        $datos    = json_decode($analisis['datos_extraidos'], true);

        // Buscar o crear institución
        $instId = (int)$this->input('institucion_id', 0);
        if (!$instId && !empty($datos['institucion_contratante'])) {
            $existente = Institucion::whereOne(['nombre' => $datos['institucion_contratante']]);
            if ($existente) {
                $instId = $existente['id'];
            } else {
                $instId = Institucion::create([
                    'nombre'               => $datos['institucion_contratante'],
                    'ruc'                  => $datos['ruc_institucion'] ?? '9999999999999',
                    'tipo'                 => 'otro',
                    'administrador_nombre' => $datos['administrador_contrato'] ?? '',
                    'administrador_cargo'  => $datos['cargo_administrador'] ?? '',
                    'administrador_email'  => $datos['email_administrador'] ?? '',
                ]);
            }
        }

        // Crear proceso con datos de la IA
        $id = Proceso::create([
            'numero_proceso'       => $this->input('numero_proceso', generarNumeroProceso()),
            'tipo_proceso'         => $datos['tipo_proceso'] ?? 'otro',
            'objeto_contratacion'  => $datos['objeto_contratacion'] ?? '',
            'descripcion_detallada'=> $datos['resumen_ejecutivo'] ?? '',
            'institucion_id'       => $instId,
            'monto_total'          => (float)($datos['monto_total'] ?? 0),
            'plazo_dias'           => (int)($datos['plazo_dias'] ?? 30),
            'fecha_inicio'         => $datos['fecha_inicio'] ?? date('Y-m-d'),
            'tiene_garantia'       => 1,
            'plazo_garantia_dias'  => 365,
            'estado'               => 'en_proceso',
            'datos_ia'             => $analisis['datos_extraidos'],
            'created_by'           => authId(),
        ]);

        // Crear entregables desde IA
        if (!empty($datos['entregables']) && is_array($datos['entregables'])) {
            foreach ($datos['entregables'] as $i => $e) {
                Entregable::create([
                    'proceso_id'  => $id,
                    'numero_orden'=> $i + 1,
                    'nombre'      => $e['descripcion'] ?? "Entregable " . ($i + 1),
                    'plazo_dias'  => $e['plazo_dias'] ?? null,
                ]);
            }
        }

        AnalisisIA::update((int)$id, ['proceso_id' => $id, 'estado' => 'aplicado']);
        View::flash('success', '✅ Proceso creado desde análisis IA. Verifique y complete los datos.');
        $this->redirect("/procesos/{$id}");
    }
}

// ══════════════════════════════════════════════════════════════════════════
// NOTIFICACIONES CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class NotificacionesController extends BaseController
{
    public function index(): void
    {
        $todas = Notificacion::where(['user_id' => authId()], 'created_at DESC');
        $this->view('notificaciones.index', compact('todas') + ['title' => 'Notificaciones']);
    }

    public function marcarLeida(string $id): void
    {
        Notificacion::marcarLeida((int)$id);
        if ($this->isAjax()) { $this->json(['success' => true]); return; }
        $this->redirect('/notificaciones');
    }

    public function marcarTodasLeidas(): void
    {
        DB::query(
            "UPDATE notificaciones SET estado='leido', fecha_leido=NOW() WHERE user_id=? AND tenant_id=? AND estado='pendiente'",
            [authId(), DB::getTenantId()]
        );
        View::flash('success', 'Todas las notificaciones marcadas como leídas.');
        $this->redirect('/notificaciones');
    }
}

// ══════════════════════════════════════════════════════════════════════════
// REPORTES CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class ReportesController extends BaseController
{
    public function dashboard(): void
    {
        $tid = DB::getTenantId();
        $resumen = [
            'por_estado' => DB::select(
                "SELECT estado, COUNT(*) AS total, SUM(monto_total) AS monto FROM procesos WHERE tenant_id=? AND deleted_at IS NULL GROUP BY estado",
                [$tid]
            ),
            'por_institucion' => DB::select(
                "SELECT i.nombre, COUNT(p.id) AS total, SUM(p.monto_total) AS monto FROM procesos p JOIN instituciones i ON i.id=p.institucion_id WHERE p.tenant_id=? AND p.deleted_at IS NULL GROUP BY i.id ORDER BY monto DESC LIMIT 10",
                [$tid]
            ),
            'ingresos_mensual' => DB::select(
                "SELECT DATE_FORMAT(pg.fecha_pago,'%Y-%m') AS mes, SUM(pg.monto_pagado) AS total FROM pagos pg JOIN facturas f ON f.id=pg.factura_id JOIN procesos p ON p.id=f.proceso_id WHERE p.tenant_id=? AND YEAR(pg.fecha_pago)=YEAR(CURDATE()) GROUP BY mes ORDER BY mes",
                [$tid]
            ),
            'tiempo_pago_prom' => DB::selectOne(
                "SELECT AVG(DATEDIFF(pg.fecha_pago,f.fecha_emision)) AS promedio FROM pagos pg JOIN facturas f ON f.id=pg.factura_id JOIN procesos p ON p.id=f.proceso_id WHERE p.tenant_id=?",
                [$tid]
            )['promedio'] ?? 0,
        ];
        $this->view('reportes.dashboard', compact('resumen') + ['title' => 'Reportes y BI']);
    }

    // ── DEBUG TEMPORAL: muestra qué extrae el parser ──────────────────────
    public function debugSercopHtml(): void
    {
        $this->requirePermission('procesos.*');
        verifyCsrf();
        $html = trim($_POST['html_sercop'] ?? '');
        header('Content-Type: text/plain; charset=utf-8');

        if (strlen($html) < 100) { echo "HTML vacío"; exit; }

        echo "=== HTML LENGTH: " . strlen($html) . " chars ===\n\n";

        // Mostrar fragmento alrededor de palabras clave
        foreach (['Objeto','Fecha','Direcci','Provincia','Canton','Proforma','compra'] as $key) {
            $pos = stripos($html, $key);
            if ($pos !== false) {
                $snippet = substr($html, max(0,$pos-80), 400);
                echo "--- '$key' pos=$pos ---\n" . $snippet . "\n\n";
            }
        }

        // Resultado del parser
        echo "\n=== PARSER ===\n";
        try {
            $r = PdfExtractorService::extraerDeHtml($html, '');
            foreach ($r['datos'] as $k => $v) {
                if (!empty($v) && !is_array($v)) echo "$k = $v\n";
            }
            echo "items = " . count($r['datos']['items'] ?? []) . "\n";
        } catch (\Throwable $e) { echo "ERROR: " . $e->getMessage(); }
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// CONFIGURACIÓN CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class ConfiguracionController extends BaseController
{
    public function index(): void
    {
        $tenant = Tenant::find(tenantId());
        $this->view('configuracion.index', compact('tenant') + ['title' => 'Configuración']);
    }

    public function update(): void
    {
        $this->requirePermission('configuracion.*');
        verifyCsrf();
        $data = $this->allInput();
        unset($data['_csrf'], $data['_method']);

        // Solo campos permitidos de la tabla tenants
        $allowed = ['nombre','ruc','representante_legal','ciudad','direccion',
                    'telefono','email','tipo_contribuyente','regimen_tributario'];
        $data = array_intersect_key($data, array_flip($allowed));

        Tenant::update(tenantId(), $data);

        // Sincronizar sesión completa
        $_SESSION['tenant_nombre']        = $data['nombre']               ?? $_SESSION['tenant_nombre']        ?? '';
        $_SESSION['tenant_ruc']           = $data['ruc']                  ?? $_SESSION['tenant_ruc']           ?? '';
        $_SESSION['tenant_representante'] = $data['representante_legal']  ?? $_SESSION['tenant_representante'] ?? '';
        $_SESSION['tenant_ciudad']        = $data['ciudad']               ?? $_SESSION['tenant_ciudad']        ?? '';
        $_SESSION['tenant_direccion']     = $data['direccion']            ?? $_SESSION['tenant_direccion']     ?? '';
        $_SESSION['tenant_telefono']      = $data['telefono']             ?? $_SESSION['tenant_telefono']      ?? '';
        $_SESSION['tenant_email']         = $data['email']                ?? $_SESSION['tenant_email']         ?? '';
        $_SESSION['tenant_tipo_contrib']  = $data['tipo_contribuyente']   ?? $_SESSION['tenant_tipo_contrib']  ?? '';
        $_SESSION['tenant_regimen']       = $data['regimen_tributario']   ?? $_SESSION['tenant_regimen']       ?? '';

        View::flash('success', 'Datos de empresa guardados correctamente.');
        $this->redirect('/configuracion');
    }

    public function usuarios(): void
    {
        $this->requirePermission('usuarios.*');
        $usuarios = User::all('nombre ASC');
        $this->view('configuracion.usuarios', compact('usuarios') + ['title' => 'Usuarios']);
    }

    public function storeUsuario(): void
    {
        $this->requirePermission('usuarios.*');
        verifyCsrf();
        $data = $this->validate([
            'nombre'   => 'required|max:150',
            'email'    => 'required|email',
            'password' => 'required|min:8',
            'rol'      => 'required|in:admin,gestor,visualizador',
        ]);
        unset($data['_csrf'], $data['_method']);
        User::createUser($data);
        View::flash('success', 'Usuario creado correctamente.');
        $this->redirect('/configuracion/usuarios');
    }

    public function updateUsuario(int $id): void
    {
        $this->requirePermission('usuarios.*');
        verifyCsrf();
        $data = $this->validate([
            'nombre' => 'required|max:150',
            'email'  => 'required|email',
            'rol'    => 'required|in:admin,gestor,visualizador',
            'estado' => 'required|in:activo,inactivo',
        ]);
        unset($data['_csrf'], $data['_method']);

        // Cambio de contraseña opcional
        $pw = trim($_POST['password'] ?? '');
        if ($pw !== '') {
            if (mb_strlen($pw) < 8) {
                View::flash('error', 'La contraseña debe tener al menos 8 caracteres.');
                $this->redirect('/configuracion/usuarios');
                return;
            }
            $data['password_hash'] = Auth::hashPassword($pw);
        }

        User::update($id, $data);
        View::flash('success', 'Usuario actualizado.');
        $this->redirect('/configuracion/usuarios');
    }

    public function toggleUsuario(int $id): void
    {
        $this->requirePermission('usuarios.*');
        verifyCsrf();
        $usuario = User::find($id);
        if (!$usuario) {
            View::flash('error', 'Usuario no encontrado.');
            $this->redirect('/configuracion/usuarios');
            return;
        }
        $nuevoEstado = $usuario['estado'] === 'activo' ? 'inactivo' : 'activo';
        User::update($id, ['estado' => $nuevoEstado]);
        $msg = $nuevoEstado === 'activo' ? 'Usuario activado.' : 'Usuario desactivado.';
        View::flash('success', $msg);
        $this->redirect('/configuracion/usuarios');
    }

    public function destroyUsuario(int $id): void
    {
        $this->requirePermission('usuarios.*');
        verifyCsrf();
        // No permitir que un admin se elimine a sí mismo
        if ($id === (int) Auth::id()) {
            View::flash('error', 'No puedes eliminar tu propia cuenta.');
            $this->redirect('/configuracion/usuarios');
            return;
        }
        User::delete($id);
        View::flash('success', 'Usuario eliminado.');
        $this->redirect('/configuracion/usuarios');
    }

    public function plantillas(): void
    {
        $plantillas = PlantillaDocumento::all('tipo ASC');
        $this->view('configuracion.plantillas', compact('plantillas') + ['title' => 'Plantillas']);
    }

    public function storePlantilla(): void
    {
        verifyCsrf();
        $data = $this->validate(['tipo' => 'required', 'nombre' => 'required', 'contenido_html' => 'required']);
        $data['created_by'] = authId();
        PlantillaDocumento::create($data);
        View::flash('success', 'Plantilla guardada.');
        $this->redirect('/configuracion/plantillas');
    }
}

// ══════════════════════════════════════════════════════════════════════════
// CRON CONTROLLER (llamar vía URL protegida o cPanel Cron Jobs)
// ══════════════════════════════════════════════════════════════════════════
class CronController extends BaseController
{
    public function run(): void
    {
        // Proteger acceso al cron
        $token = $_GET['token'] ?? '';
        if ($token !== md5(APP_KEY . date('Y-m-d'))) {
            http_response_code(403);
            exit('Forbidden');
        }

        $log = [];
        $log[] = 'Cron iniciado: ' . date('Y-m-d H:i:s');

        // Procesar todos los tenants activos
        $tenants = DB::select("SELECT id FROM tenants WHERE estado = 'activo'");
        foreach ($tenants as $tenant) {
            DB::setTenant((int)$tenant['id']);
            DocumentoHabilitante::actualizarEstados();
        }

        $n1 = NotificacionService::verificarDocumentosHabilitantes();
        $n2 = NotificacionService::verificarEntregables();
        $n3 = NotificacionService::verificarPagosPendientes();

        $log[] = "Notificaciones enviadas: docs={$n1}, entregables={$n2}, pagos={$n3}";
        $log[] = 'Cron completado: ' . date('Y-m-d H:i:s');

        logInfo('Cron ejecutado', $log);
        header('Content-Type: text/plain');
        echo implode("\n", $log);
        exit;
    }
}
// ══════════════════════════════════════════════════════════════════════════
// PROFORMA CONTROLLER — HTML editable → PDF (sin dependencias)
// ══════════════════════════════════════════════════════════════════════════
class ProformaController extends BaseController
{
    // ── Ver/imprimir proforma de un proceso ───────────────────────────────
    public function ver(string $id): void
    {
        $proceso     = Proceso::conInstitucion((int)$id);
        if (!$proceso) { $this->redirect('/procesos'); return; }

        $camposExtra = ProcesoCampoExtra::porProceso((int)$id);
        $proceso['_campos_extra'] = $camposExtra;

        $tenant   = Tenant::find(tenantId());
        $logoUrl  = $tenant['logo_url'] ?? '';
        $html     = \Services\ProformaService::generar($proceso, tenantId(), $logoUrl);

        // Inyectar URL de volver
        $html = str_replace('{{url_back}}',     "/procesos/{$id}",          $html);
        $html = str_replace('{{url_back_pdf}}', "/procesos/{$id}/proforma/pdf", $html);

        // Guardar copia en expediente digital (solo última versión generada)
        try {
            $dir      = tenantStoragePath("procesos/{$id}/proforma");
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            // Eliminar versiones anteriores auto-generadas de la proforma
            $anteriores = \DB::select(
                "SELECT id, ruta_storage FROM documentos_proceso
                 WHERE proceso_id = ? AND categoria = 'proforma' AND es_generado = 1 AND deleted_at IS NULL AND tenant_id = ?",
                [(int)$id, tenantId()]
            );
            foreach ($anteriores as $ant) {
                if (!empty($ant['ruta_storage']) && file_exists($ant['ruta_storage'])) {
                    @unlink($ant['ruta_storage']);
                }
                \DB::query("UPDATE documentos_proceso SET deleted_at = NOW() WHERE id = ?", [$ant['id']]);
            }

            $filename = 'proforma_' . date('Ymd_His') . '.html';
            file_put_contents($dir . '/' . $filename, $html);
            DocumentoProceso::create([
                'proceso_id'     => (int)$id,
                'categoria'      => 'proforma',
                'nombre_archivo' => $filename,
                'nombre_original'=> 'Proforma_' . $proceso['numero_proceso'] . '.html',
                'ruta_storage'   => $dir . '/' . $filename,
                'tipo_mime'      => 'text/html',
                'es_generado'    => 1,
                'subido_por'     => authId(),
            ]);
        } catch (\Throwable $e) { /* no bloquear si falla */ }

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    // ── Descargar proforma como PDF (mPDF) ────────────────────────────────
    public function descargarPdf(string $id): void
    {
        $proceso     = Proceso::conInstitucion((int)$id);
        if (!$proceso) { $this->redirect('/procesos'); return; }

        $camposExtra = ProcesoCampoExtra::porProceso((int)$id);
        $proceso['_campos_extra'] = $camposExtra;

        $tenant  = Tenant::find(tenantId());
        $logoUrl = $tenant['logo_url'] ?? '';
        $html    = \Services\ProformaService::generar($proceso, tenantId(), $logoUrl);
        $html    = str_replace('{{url_back}}', "/procesos/{$id}", $html);

        $nombreArchivo = 'Proforma_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $proceso['numero_proceso']) . '.pdf';

        // ── Intentar mPDF ──────────────────────────────────────────────────
        $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
        }

        if (class_exists('\Mpdf\Mpdf')) {
            try {
                $mpdf = new \Mpdf\Mpdf([
                    'mode'          => 'utf-8',
                    'format'        => 'A4',
                    'margin_top'    => 15,
                    'margin_bottom' => 15,
                    'margin_left'   => 20,
                    'margin_right'  => 20,
                    'tempDir'       => sys_get_temp_dir(),
                ]);
                $mpdf->SetTitle('Proforma - ' . $proceso['numero_proceso']);
                $mpdf->WriteHTML($html);
                $mpdf->Output($nombreArchivo, 'D'); // D = descarga directa
                exit;
            } catch (\Throwable $e) {
                // Fallback a HTML si mPDF falla
            }
        }

        // ── Fallback: mostrar HTML con mensaje ────────────────────────────
        $aviso = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px;font-family:Arial;font-size:12px">
            <strong>⚠ mPDF no instalado.</strong> Ejecuta en el servidor: <code>composer require mpdf/mpdf</code><br>
            Por ahora usa <strong>Ctrl+P → Guardar como PDF</strong> desde esta vista.
        </div>';
        header('Content-Type: text/html; charset=UTF-8');
        echo $aviso . $html;
        exit;
    }

    // ── Página de configuración de proforma ──────────────────────────────
    public function configurar(): void
    {
        $config    = \Services\ProformaService::getConfig(tenantId());
        $tenant    = Tenant::find(tenantId());
        $plantilla = \DB::selectOne(
            "SELECT contenido_html FROM plantillas_documentos
             WHERE tipo = 'proforma_sercop' AND tenant_id = ? AND activa = 1 AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 1",
            [tenantId()]
        );
        $htmlActual = $plantilla['contenido_html'] ?? null;
        $variables  = \Services\ProformaService::variablesDisponibles();
        $this->view('configuracion.proforma', compact('config','tenant','htmlActual','variables')
            + ['title' => 'Configurar Proforma']);
    }

    // ── Guardar configuración (colores, textos) ───────────────────────────
    public function guardarConfig(): void
    {
        $this->requirePermission('configuracion.*');
        verifyCsrf();
        $config = [
            'color_primario'  => $this->input('color_primario', '#1B4F72'),
            'proforma_numero' => $this->input('proforma_numero', date('Y') . '-001'),
            'forma_pago'      => $this->input('forma_pago', 'Contra entrega del servicio/bien'),
            'vigencia_oferta' => $this->input('vigencia_oferta', '30 días calendario'),
            'texto_adicional' => $this->input('texto_adicional', ''),
        ];
        \Services\ProformaService::saveConfig(tenantId(), $config);
        View::flash('success', 'Configuración de proforma guardada.');
        $this->redirect('/configuracion/proforma');
    }

    // ── Guardar plantilla HTML personalizada ──────────────────────────────
    public function guardarPlantilla(): void
    {
        $this->requirePermission('configuracion.*');
        verifyCsrf();
        $html = $_POST['contenido_html'] ?? '';
        if (empty(trim($html))) {
            View::flash('error', 'El contenido HTML no puede estar vacío.');
            $this->redirect('/configuracion/proforma');
            return;
        }
        // Desactivar plantillas anteriores
        DB::query(
            "UPDATE plantillas_documentos SET activa = 0
             WHERE tipo = 'proforma_sercop' AND tenant_id = ?",
            [tenantId()]
        );
        // Insertar nueva
        DB::insert('plantillas_documentos', [
            'tenant_id'      => tenantId(),
            'nombre'         => 'Proforma personalizada',
            'tipo'           => 'proforma_sercop',
            'contenido_html' => $html,
            'activa'         => 1,
            'es_global'      => 0,
            'created_by'     => authId(),
        ]);
        View::flash('success', 'Plantilla HTML guardada correctamente.');
        $this->redirect('/configuracion/proforma');
    }

    // ── Subir logo ────────────────────────────────────────────────────────
    public function subirLogo(): void
    {
        $this->requirePermission('configuracion.*');
        verifyCsrf();
        if (empty($_FILES['logo']['name'])) {
            View::flash('error', 'Selecciona una imagen.');
            $this->redirect('/configuracion/proforma');
            return;
        }
        $file = $_FILES['logo'];
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/png','image/jpeg','image/webp','image/svg+xml'])) {
            View::flash('error', 'Solo PNG, JPG, WebP o SVG.');
            $this->redirect('/configuracion/proforma');
            return;
        }
        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dir  = tenantStoragePath('logos');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $dest = $dir . '/logo.' . $ext;
        move_uploaded_file($file['tmp_name'], $dest);
        Tenant::update(tenantId(), ['logo_url' => $dest]);
        $_SESSION['tenant_logo'] = $dest;
        View::flash('success', 'Logo actualizado.');
        $this->redirect('/configuracion/proforma');
    }
}

// ══════════════════════════════════════════════════════════════════════════
// DOMINIOS CONTROLLER
// ══════════════════════════════════════════════════════════════════════════
class DominiosController extends BaseController
{
    // ── Lista global de dominios ──────────────────────────────────────────
    public function index(): void
    {
        Dominio::actualizarEstados();
        $dominios = Dominio::conInstitucion();
        $this->view('dominios.index', compact('dominios') + ['title' => 'Gestión de Dominios']);
    }

    // ── Guardar nuevo dominio (POST desde ficha de institución) ───────────
    public function store(): void
    {
        verifyCsrf();
        require_once APP_PATH . '/Services/DominioRdapService.php';

        $raw      = strtolower(trim($_POST['dominio_completo'] ?? ''));
        $instId   = (int)($_POST['institucion_id'] ?? 0);

        if (empty($raw) || !$instId) {
            View::flash('error', 'Dominio e institución son requeridos.');
            $this->redirect("/instituciones/{$instId}");
            return;
        }

        // Separar nombre y TLD
        $partes = explode('.', $raw, 2);
        $nombre = $partes[0];
        $tld    = $partes[1] ?? 'ec';

        // Intentar consulta RDAP automática
        $rdap   = DominioRdapService::consultar($raw);
        $estado = 'activo';

        $data = [
            'tenant_id'        => DB::getTenantId(),
            'institucion_id'   => $instId,
            'dominio'          => $nombre,
            'tld'              => $tld,
            'dominio_completo' => $raw,
            'dias_alerta'      => (int)($_POST['dias_alerta'] ?? 30),
            'costo_renovacion' => ($_POST['costo_renovacion'] ?? '') ?: null,
            'notas'            => ($_POST['notas'] ?? '') ?: null,
            'renovacion_auto'  => isset($_POST['renovacion_auto']) ? 1 : 0,
        ];

        if ($rdap && empty($rdap['error'])) {
            $data['fecha_registro']       = $rdap['fecha_registro'];
            $data['fecha_caducidad']      = $rdap['fecha_caducidad'];
            $data['fecha_ultimo_cambio']  = $rdap['fecha_ultimo_cambio'];
            $data['titular']              = $rdap['titular'];
            $data['registrador']          = $rdap['registrador'];
            $data['estado_rdap']          = $rdap['estado_rdap'];
            $data['nameservers']          = !empty($rdap['nameservers'])
                                            ? json_encode($rdap['nameservers'])
                                            : null;
            $data['rdap_raw']             = $rdap['rdap_raw'];
            $data['ultima_consulta_rdap'] = date('Y-m-d H:i:s');
            $estado = DominioRdapService::calcularEstado(
                $rdap['fecha_caducidad'],
                $data['dias_alerta']
            );
        } else {
            // Datos enviados por el browser via JS (campos hidden del modal)
            $fechaCad = ($_POST['fecha_caducidad'] ?? '') ?: null;
            $data['fecha_caducidad']     = $fechaCad;
            $data['fecha_registro']      = ($_POST['fecha_registro']     ?? '') ?: null;
            $data['fecha_ultimo_cambio'] = ($_POST['rdap_ultimo_cambio'] ?? '') ?: null;
            $data['titular']             = ($_POST['rdap_titular']       ?? '') ?: null;
            $data['registrador']         = ($_POST['rdap_registrador']   ?? '') ?: null;
            $data['estado_rdap']         = ($_POST['rdap_estado']        ?? '') ?: null;
            $nsRaw = ($_POST['rdap_nameservers'] ?? '') ?: null;
            $data['nameservers']         = $nsRaw;
            if ($nsRaw) $data['ultima_consulta_rdap'] = date('Y-m-d H:i:s');
            $estado = DominioRdapService::calcularEstado($fechaCad, $data['dias_alerta']);
        }

        $data['estado'] = $estado;
        Dominio::create($data);

        $msg = $rdap ? '✅ Dominio agregado con datos RDAP.' : '⚠️ Dominio agregado (sin datos RDAP — verifica manualmente).';
        View::flash('success', $msg);
        $this->redirect("/instituciones/{$instId}#dominios");
    }

    // ── Actualizar dominio (POST) ──────────────────────────────────────────
    public function update(string $id): void
    {
        verifyCsrf();
        $dom = Dominio::findOrFail((int)$id);

        $data = [
            'fecha_caducidad' => ($_POST['fecha_caducidad'] ?? '') ?: null,
            'fecha_registro'  => ($_POST['fecha_registro']  ?? '') ?: null,
            'notas'           => ($_POST['notas'] ?? '') ?: null,
            'dias_alerta'     => (int)($_POST['dias_alerta'] ?? 30),
            'costo_renovacion'=> ($_POST['costo_renovacion'] ?? '') ?: null,
            'renovacion_auto' => isset($_POST['renovacion_auto']) ? 1 : 0,
            'estado'          => DominioRdapService::calcularEstado(
                ($_POST['fecha_caducidad'] ?? '') ?: null,
                (int)($_POST['dias_alerta'] ?? 30)
            ),
        ];

        Dominio::update((int)$id, $data);
        View::flash('success', 'Dominio actualizado.');
        $this->redirect("/instituciones/{$dom['institucion_id']}#dominios");
    }

    // ── Eliminar dominio ───────────────────────────────────────────────────
    public function destroy(string $id): void
    {
        verifyCsrf();
        $dom = Dominio::findOrFail((int)$id);
        DB::query(
            "UPDATE dominios SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?",
            [(int)$id, DB::getTenantId()]
        );
        View::flash('success', 'Dominio eliminado.');
        $this->redirect("/instituciones/{$dom['institucion_id']}#dominios");
    }

    // ── Consultar RDAP (AJAX) ──────────────────────────────────────────────
    public function consultarRdap(string $id): void
    {
        require_once APP_PATH . '/Services/DominioRdapService.php';
        $dom = Dominio::findOrFail((int)$id);

        header('Content-Type: application/json');
        $rdap = DominioRdapService::consultar($dom['dominio_completo']);

        if (!$rdap || !empty($rdap['error'])) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo consultar RDAP para este dominio.']);
            exit;
        }

        // Actualizar en BD
        $estado = DominioRdapService::calcularEstado($rdap['fecha_caducidad'], (int)$dom['dias_alerta']);
        Dominio::update((int)$id, [
            'fecha_registro'       => $rdap['fecha_registro'],
            'fecha_caducidad'      => $rdap['fecha_caducidad'],
            'fecha_ultimo_cambio'  => $rdap['fecha_ultimo_cambio'],
            'titular'              => $rdap['titular'],
            'registrador'          => $rdap['registrador'],
            'estado_rdap'          => $rdap['estado_rdap'],
            'nameservers'          => !empty($rdap['nameservers']) ? json_encode($rdap['nameservers']) : null,
            'rdap_raw'             => $rdap['rdap_raw'],
            'ultima_consulta_rdap' => date('Y-m-d H:i:s'),
            'estado'               => $estado,
        ]);

        $dias = DominioRdapService::diasHastaVencimiento($rdap['fecha_caducidad']);
        echo json_encode([
            'ok'              => true,
            'fecha_caducidad' => $rdap['fecha_caducidad'],
            'fecha_registro'  => $rdap['fecha_registro'],
            'titular'         => $rdap['titular'],
            'registrador'     => $rdap['registrador'],
            'estado_rdap'     => $rdap['estado_rdap'],
            'nameservers'     => $rdap['nameservers'],
            'estado'          => $estado,
            'dias'            => $dias,
        ]);
        exit;
    }

    // ── Proxy RDAP: el JS llama a este endpoint, que consulta rdap.nic.ec ──
    public function proxyRdap(): void
    {
        require_once APP_PATH . '/Services/DominioRdapService.php';
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $dominio = strtolower(trim($_GET['dominio'] ?? ''));
        if (empty($dominio) || !preg_match('/^[a-z0-9\-\.]+\.[a-z]{2,}$/', $dominio)) {
            echo json_encode(['ok' => false, 'msg' => 'Dominio inválido']);
            exit;
        }

        // Modo debug: ?debug=1 devuelve info extra para diagnóstico
        $debug = !empty($_GET['debug']);

        $rdap = DominioRdapService::consultarConDebug($dominio, $debug);

        if (isset($rdap['_debug'])) {
            // En modo debug mostrar todo
            echo json_encode($rdap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$rdap) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo obtener datos RDAP']);
            exit;
        }

        echo json_encode([
            'ok'              => true,
            'fecha_registro'  => $rdap['fecha_registro'],
            'fecha_caducidad' => $rdap['fecha_caducidad'],
            'ultimo_cambio'   => $rdap['fecha_ultimo_cambio'],
            'estado_rdap'     => $rdap['estado_rdap'],
            'titular'         => $rdap['titular'],
            'registrador'     => $rdap['registrador'],
            'nameservers'     => $rdap['nameservers'],
        ]);
        exit;
    }

    // ── Recibir datos RDAP del browser y guardar en BD ────────────────────
    public function rdapDatos(string $id): void
    {
        require_once APP_PATH . '/Services/DominioRdapService.php';
        header('Content-Type: application/json');

        $dom = Dominio::findOrFail((int)$id);

        $fechaCaducidad = ($_POST['fecha_caducidad'] ?? '') ?: null;
        $ns = $_POST['rdap_nameservers'] ?? null;

        $update = [
            'fecha_caducidad'      => $fechaCaducidad,
            'fecha_registro'       => ($_POST['fecha_registro']     ?? '') ?: null,
            'fecha_ultimo_cambio'  => ($_POST['rdap_ultimo_cambio'] ?? '') ?: null,
            'titular'              => ($_POST['rdap_titular']       ?? '') ?: null,
            'registrador'          => ($_POST['rdap_registrador']   ?? '') ?: null,
            'estado_rdap'          => ($_POST['rdap_estado']        ?? '') ?: null,
            'nameservers'          => $ns ?: null,
            'ultima_consulta_rdap' => date('Y-m-d H:i:s'),
            'estado'               => DominioRdapService::calcularEstado($fechaCaducidad, (int)$dom['dias_alerta']),
        ];

        Dominio::update((int)$id, $update);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Diagnóstico RDAP ──────────────────────────────────────────────────
    public function diagnosticoRdap(): void
    {
        require_once APP_PATH . '/Services/DominioRdapService.php';
        header('Content-Type: application/json');
        $dominio = $_GET['dominio'] ?? 'nic.ec';
        echo json_encode(DominioRdapService::diagnostico($dominio), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}