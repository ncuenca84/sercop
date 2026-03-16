<?php

declare(strict_types=1);

require_once APP_PATH . '/Controllers/Web/Controllers.php';

$r = Router::getInstance();

// ── Guest ────────────────────────────────────────────────────────────────
$r->get('/login',  'AuthController@showLogin',  ['GuestMiddleware']);
$r->post('/login', 'AuthController@login',      ['GuestMiddleware']);
$r->get('/logout', 'AuthController@logout');
$r->get('/',       'HomeController@index');

// ── Dashboard ─────────────────────────────────────────────────────────────
$r->get('/dashboard', 'DashboardController@index', ['AuthMiddleware']);

// ── Procesos ──────────────────────────────────────────────────────────────
$r->get('/procesos',                        'ProcesosController@index',          ['AuthMiddleware']);
$r->get('/procesos/crear',                  'ProcesosController@create',              ['AuthMiddleware']);
$r->post('/procesos/importar-sercop',       'ProcesosController@importarSercop',      ['AuthMiddleware']);
$r->post('/procesos/importar-sercop-html',  'ProcesosController@importarSercopHtml',  ['AuthMiddleware']);
$r->post('/procesos',                       'ProcesosController@store',          ['AuthMiddleware']);
$r->get('/procesos/{id}',                   'ProcesosController@show',           ['AuthMiddleware']);
$r->get('/procesos/{id}/editar',            'ProcesosController@edit',           ['AuthMiddleware']);
$r->post('/procesos/{id}',                  'ProcesosController@update',         ['AuthMiddleware']);
$r->post('/procesos/{id}/fase2',            'ProcesosController@storeFase2',     ['AuthMiddleware']);
$r->post('/procesos/{id}/estado',           'ProcesosController@cambiarEstado',  ['AuthMiddleware']);
$r->post('/procesos/{id}/eliminar',         'ProcesosController@destroy',        ['AuthMiddleware']);
$r->get('/procesos/{id}/documento',         'ProcesosController@generarDocumento',['AuthMiddleware']);
$r->get('/procesos/{id}/documento/editar',     'ProcesosController@editarDocumento',      ['AuthMiddleware']);
$r->post('/procesos/{id}/documento/generar',   'ProcesosController@generarDocumento',     ['AuthMiddleware']);
$r->post('/procesos/{id}/documento/generar-ia','ProcesosController@generarDocumentoConIa',['AuthMiddleware']);
$r->get('/procesos/{id}/documento/pdf',        'ProcesosController@descargarDocumentoPdf',['AuthMiddleware']);

// ── Documentos del proceso ─────────────────────────────────────────────────
$r->post('/procesos/{id}/documentos',             'DocumentosProcesoController@upload',   ['AuthMiddleware']);
$r->get('/documentos/{id}/descargar',             'DocumentosProcesoController@download', ['AuthMiddleware']);
$r->post('/documentos/{id}/eliminar',             'DocumentosProcesoController@destroy',  ['AuthMiddleware']);

// ── Entregables ────────────────────────────────────────────────────────────
$r->post('/procesos/{id}/entregables',             'EntregablesController@store',          ['AuthMiddleware']);
$r->post('/entregables/{id}/estado',                  'EntregablesController@cambiarEstado', ['AuthMiddleware']);

// ── Instituciones ──────────────────────────────────────────────────────────
$r->get('/instituciones',                   'InstitucionesController@index',   ['AuthMiddleware']);
$r->get('/instituciones/crear',             'InstitucionesController@create',  ['AuthMiddleware']);
$r->post('/instituciones',                  'InstitucionesController@store',   ['AuthMiddleware']);
$r->get('/instituciones/{id}',              'InstitucionesController@show',    ['AuthMiddleware']);
$r->get('/instituciones/{id}/editar',       'InstitucionesController@edit',    ['AuthMiddleware']);
$r->post('/instituciones/{id}',             'InstitucionesController@update',  ['AuthMiddleware']);
$r->post('/instituciones/{id}/eliminar',    'InstitucionesController@destroy', ['AuthMiddleware']);

// ── Documentos Habilitantes ───────────────────────────────────────────────
$r->get('/documentos-habilitantes',         'DocumentosHabilitantesController@index',   ['AuthMiddleware']);
$r->post('/documentos-habilitantes',        'DocumentosHabilitantesController@store',   ['AuthMiddleware']);
$r->post('/documentos-habilitantes/{id}',   'DocumentosHabilitantesController@update',  ['AuthMiddleware']);
$r->post('/documentos-habilitantes/{id}/eliminar', 'DocumentosHabilitantesController@destroy', ['AuthMiddleware']);

// ── Facturas ──────────────────────────────────────────────────────────────
$r->get('/facturas',                        'FacturasController@index',         ['AuthMiddleware']);
$r->post('/facturas',                       'FacturasController@store',         ['AuthMiddleware']);
$r->post('/facturas/{id}/pago',             'FacturasController@registrarPago', ['AuthMiddleware']);

// ── IA ────────────────────────────────────────────────────────────────────
$r->get('/ia',                              'IaController@index',              ['AuthMiddleware']);
$r->post('/ia/analizar',                    'IaController@analizar',           ['AuthMiddleware']);
$r->get('/ia/{id}/aplicar',                 'IaController@mostrarAplicar',     ['AuthMiddleware']);
$r->post('/ia/{id}/aplicar',                'IaController@aplicar',            ['AuthMiddleware']);

// ── Extractor PDF (gratis, sin IA) ────────────────────────────────────────
$r->get('/extractor',                       'ExtractorController@index',       ['AuthMiddleware']);
$r->post('/ia/extraer',                     'ExtractorController@extraer',            ['AuthMiddleware']);
$r->post('/ia/extraer-url',                 'ExtractorController@extraerUrl',         ['AuthMiddleware']);
$r->post('/ia/extraer-ajax',                'ExtractorController@extraerAjax',        ['AuthMiddleware']);
$r->post('/ia/extraer-secciones',           'ExtractorController@extraerSeccionesTdr',['AuthMiddleware']);
$r->post('/documentos/subir-adjunto',       'ExtractorController@subirAdjunto',['AuthMiddleware']);

// ── Notificaciones ────────────────────────────────────────────────────────
$r->get('/notificaciones',                  'NotificacionesController@index',          ['AuthMiddleware']);
$r->post('/notificaciones/{id}/leida',      'NotificacionesController@marcarLeida',    ['AuthMiddleware']);
$r->post('/notificaciones/todas-leidas',    'NotificacionesController@marcarTodasLeidas', ['AuthMiddleware']);

// ── Reportes ──────────────────────────────────────────────────────────────
$r->get('/reportes',                        'ReportesController@dashboard',    ['AuthMiddleware']);

// ── Dominios ──────────────────────────────────────────────────────────────
$r->get('/dominios',                        'DominiosController@index',          ['AuthMiddleware']);
$r->get('/dominios/proxy-rdap',             'DominiosController@proxyRdap',      ['AuthMiddleware']);
$r->get('/dominios/diagnostico-rdap',       'DominiosController@diagnosticoRdap',['AuthMiddleware']);
$r->post('/dominios',                       'DominiosController@store',          ['AuthMiddleware']);
$r->post('/dominios/{id}',                  'DominiosController@update',         ['AuthMiddleware']);
$r->post('/dominios/{id}/eliminar',         'DominiosController@destroy',        ['AuthMiddleware']);
$r->post('/dominios/{id}/rdap',             'DominiosController@consultarRdap',  ['AuthMiddleware']);
$r->post('/dominios/{id}/rdap-datos',       'DominiosController@rdapDatos',      ['AuthMiddleware']);

// ── Configuración ─────────────────────────────────────────────────────────
$r->get('/configuracion',                   'ConfiguracionController@index',         ['AuthMiddleware']);
$r->post('/configuracion',                  'ConfiguracionController@update',        ['AuthMiddleware']);
$r->get('/configuracion/usuarios',                      'ConfiguracionController@usuarios',       ['AuthMiddleware']);
$r->post('/configuracion/usuarios',                     'ConfiguracionController@storeUsuario',   ['AuthMiddleware']);
$r->post('/configuracion/usuarios/{id}',                'ConfiguracionController@updateUsuario',  ['AuthMiddleware']);
$r->post('/configuracion/usuarios/{id}/toggle',         'ConfiguracionController@toggleUsuario',  ['AuthMiddleware']);
$r->post('/configuracion/usuarios/{id}/eliminar',       'ConfiguracionController@destroyUsuario', ['AuthMiddleware']);
$r->get('/configuracion/plantillas',        'ConfiguracionController@plantillas',    ['AuthMiddleware']);
$r->post('/configuracion/plantillas',       'ConfiguracionController@storePlantilla',['AuthMiddleware']);

// ── Proformas HTML → PDF ───────────────────────────────────────────────────
$r->get('/procesos/{id}/proforma',          'ProformaController@ver',          ['AuthMiddleware']);
$r->get('/procesos/{id}/proforma/pdf',      'ProformaController@descargarPdf', ['AuthMiddleware']);
$r->get('/configuracion/proforma',          'ProformaController@configurar',   ['AuthMiddleware']);
$r->post('/configuracion/proforma',         'ProformaController@guardarConfig',['AuthMiddleware']);
$r->post('/configuracion/proforma/plantilla','ProformaController@guardarPlantilla',['AuthMiddleware']);
$r->post('/configuracion/proforma/logo',    'ProformaController@subirLogo',    ['AuthMiddleware']);

// ── Cron (protegido por token) ─────────────────────────────────────────────
$r->get('/cron/run', 'CronController@run');
// ── DEBUG TEMPORAL (remover en producción) ────────────────────────────────
$r->post('/debug/sercop-html', 'ProcesosController@debugSercopHtml', ['AuthMiddleware']);