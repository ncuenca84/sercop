<?php

declare(strict_types=1);

$r = Router::getInstance();

// API REST — todas requieren ApiAuthMiddleware
$r->get('/api/v1/procesos',                'ApiProcesosController@index',    ['ApiAuthMiddleware']);
$r->post('/api/v1/procesos',               'ApiProcesosController@store',    ['ApiAuthMiddleware']);
$r->get('/api/v1/procesos/{id}',           'ApiProcesosController@show',     ['ApiAuthMiddleware']);
$r->put('/api/v1/procesos/{id}',           'ApiProcesosController@update',   ['ApiAuthMiddleware']);
$r->delete('/api/v1/procesos/{id}',        'ApiProcesosController@destroy',  ['ApiAuthMiddleware']);

$r->get('/api/v1/instituciones',           'ApiInstitucionesController@index', ['ApiAuthMiddleware']);
$r->post('/api/v1/instituciones',          'ApiInstitucionesController@store', ['ApiAuthMiddleware']);

$r->get('/api/v1/facturas',                'ApiFacturasController@index',    ['ApiAuthMiddleware']);
$r->get('/api/v1/facturas/pendientes',     'ApiFacturasController@pendientes',['ApiAuthMiddleware']);

$r->get('/api/v1/notificaciones',          'ApiNotificacionesController@index', ['ApiAuthMiddleware']);
$r->get('/api/v1/dashboard',               'ApiDashboardController@index',   ['ApiAuthMiddleware']);
