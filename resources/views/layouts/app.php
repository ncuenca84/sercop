<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Sistema') ?> — <?= APP_NAME ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

:root{
--primary:#1B4F72;
--secondary:#2E86C1;
--accent:#117A65;
--sidebar-w:260px;
}

body{
background:#f0f4f8;
font-family:'Segoe UI',Arial,sans-serif;
}

/* SIDEBAR */

#sidebar{
width:var(--sidebar-w);
background:linear-gradient(180deg,var(--primary) 0%,#0d2d45 100%);
height:100vh;
overflow-y:auto;
position:fixed;
top:0;
left:0;
z-index:1040;
transition:.3s;
}

#sidebar .brand{
padding:20px 16px;
border-bottom:1px solid rgba(255,255,255,.1);
}

#sidebar .brand h5{
color:#fff;
margin:0;
font-size:15px;
font-weight:700;
}

#sidebar .brand small{
color:#85C1E9;
font-size:11px;
}

#sidebar .nav-link{
color:rgba(255,255,255,.8);
padding:9px 16px;
border-radius:8px;
margin:2px 8px;
font-size:13.5px;
display:flex;
align-items:center;
gap:8px;
transition:.2s;
}

#sidebar .nav-link:hover,
#sidebar .nav-link.active{
background:rgba(255,255,255,.15);
color:#fff;
}

#sidebar .nav-link i{
font-size:16px;
width:20px;
text-align:center;
}

#sidebar .section-title{
color:rgba(255,255,255,.4);
font-size:10px;
text-transform:uppercase;
letter-spacing:1px;
padding:12px 24px 4px;
}

/* MAIN */

#main{
margin-left:var(--sidebar-w);
min-height:100vh;
display:flex;
flex-direction:column;
}

#topbar{
background:#fff;
border-bottom:1px solid #e0e7ef;
padding:0 24px;
height:60px;
display:flex;
align-items:center;
justify-content:space-between;
position:sticky;
top:0;
z-index:1030;
}

#content{
flex:1;
padding:24px;
}

/* CARDS */

.card{
border:none;
border-radius:12px;
box-shadow:0 2px 8px rgba(0,0,0,.07);
}

.card-header{
background:transparent;
border-bottom:1px solid #f0f0f0;
padding:16px 20px;
font-weight:600;
}

.stat-card{
border-radius:12px;
padding:20px;
color:#fff;
position:relative;
overflow:hidden;
}

.stat-card::after{
content:'';
position:absolute;
top:-20px;
right:-20px;
width:80px;
height:80px;
border-radius:50%;
background:rgba(255,255,255,.1);
}

.stat-card .stat-icon{
font-size:28px;
opacity:.9;
}

.stat-card .stat-value{
font-size:26px;
font-weight:700;
margin:8px 0 2px;
}

.stat-card .stat-label{
font-size:12px;
opacity:.85;
}

/* ALERTS */

.alert-vencimiento{
border-left:4px solid #e74c3c;
}

/* TABLE */

.table-hover tbody tr:hover{
background:#f8fbff;
}

.table th{
font-size:12px;
text-transform:uppercase;
letter-spacing:.5px;
color:#666;
font-weight:600;
background:#f8f9fc;
}

/* BADGES */

.badge{
font-size:11px;
font-weight:500;
}

/* PROGRESS */

.progress{
height:8px;
border-radius:4px;
}

/* FORMS */

.form-label{
font-size:13px;
font-weight:500;
color:#444;
}

.form-control,
.form-select{
border-radius:8px;
border-color:#d0dce8;
font-size:13.5px;
}

.form-control:focus,
.form-select:focus{
border-color:var(--secondary);
box-shadow:0 0 0 3px rgba(46,134,193,.15);
}

/* BUTTONS */

.btn{
border-radius:8px;
font-size:13.5px;
}

.btn-primary{
background:var(--primary);
border-color:var(--primary);
}

.btn-primary:hover{
background:#154360;
}

/* MOBILE */

@media(max-width:768px){

#sidebar{
transform:translateX(-100%);
}

#sidebar.open{
transform:translateX(0);
}

#main{
margin-left:0;
}

}

/* PRINT */

@media print{
#sidebar,#topbar,.no-print{
display:none!important;
}

#main{
margin:0;
}
}

/* SCROLL SIDEBAR */

#sidebar::-webkit-scrollbar{
width:6px;
}

#sidebar::-webkit-scrollbar-thumb{
background:#2E86C1;
border-radius:4px;
}

</style>
</head>

<body>

<!-- SIDEBAR -->

<nav id="sidebar">

<div class="brand">
<h5><i class="bi bi-bank me-2"></i>Contratación Pública EC</h5>
<small><?= e($_SESSION['tenant_nombre'] ?? '') ?></small>
</div>

<nav class="nav flex-column mt-2">

<a href="/dashboard" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/dashboard')?'active':'' ?>">
<i class="bi bi-speedometer2"></i> Dashboard
</a>

<div class="section-title">Contratación</div>

<a href="/procesos/crear" class="nav-link <?= $_SERVER['REQUEST_URI']==='/procesos/crear'?'active':'' ?>" style="<?= $_SERVER['REQUEST_URI']==='/procesos/crear'?'':'background:rgba(255,255,255,.08);' ?>">
<i class="bi bi-plus-circle"></i> Nuevo Proceso
</a>

<a href="/procesos" class="nav-link <?= preg_match('#^/procesos(/\d|$|\?)#', $_SERVER['REQUEST_URI'])?'active':'' ?>">
<i class="bi bi-folder2-open"></i> Mis Procesos
</a>

<a href="/instituciones" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/instituciones')?'active':'' ?>">
<i class="bi bi-building"></i> Instituciones
</a>

<a href="/documentos-habilitantes" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/documentos-habilitantes')?'active':'' ?>">
<i class="bi bi-shield-check"></i> Docs. Habilitantes
</a>

<div class="section-title">Finanzas</div>

<a href="/facturas" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/facturas')?'active':'' ?>">
<i class="bi bi-receipt"></i> Facturas y Pagos
</a>

<div class="section-title">Herramientas</div>

<a href="/extractor" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/extractor')?'active':'' ?>">
<i class="bi bi-file-earmark-pdf"></i> Extraer PDF TDR
</a>

<a href="/ia" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/ia')?'active':'' ?>">
<i class="bi bi-cpu"></i> Análisis con IA
</a>

<a href="/reportes" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/reportes')?'active':'' ?>">
<i class="bi bi-bar-chart-line"></i> Reportes / BI
</a>

<div class="section-title">Sistema</div>

<a href="/notificaciones" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/notificaciones')?'active':'' ?>">
<i class="bi bi-bell"></i> Notificaciones
<?php $nCount = count($notificaciones ?? []); if($nCount>0): ?>
<span class="badge bg-danger ms-auto"><?= $nCount ?></span>
<?php endif; ?>
</a>


<a href="/configuracion" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/configuracion')?'active':'' ?>">
<i class="bi bi-gear"></i> Configuración
</a>

</nav>
</nav>

<!-- MAIN -->

<div id="main">

<div id="topbar">

<div class="d-flex align-items-center gap-3">

<button class="btn btn-sm btn-light d-md-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
<i class="bi bi-list fs-5"></i>
</button>

<nav aria-label="breadcrumb" class="d-none d-md-block">
<ol class="breadcrumb mb-0 small">
<li class="breadcrumb-item">
<a href="/dashboard" class="text-muted text-decoration-none">Inicio</a>
</li>
<li class="breadcrumb-item active text-dark fw-semibold">
<?= e($title ?? '') ?>
</li>
</ol>
</nav>

</div>

<div class="d-flex align-items-center gap-2">

<a href="/notificaciones" class="btn btn-light btn-sm position-relative">
<i class="bi bi-bell"></i>

<?php if(($nCount??0)>0): ?>

<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px">
<?= $nCount ?>
</span>

<?php endif; ?>

</a>

<div class="dropdown">

<button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
<i class="bi bi-person-circle me-1"></i><?= e($auth['nombre'] ?? '') ?>
</button>

<ul class="dropdown-menu dropdown-menu-end shadow-sm">

<li>
<span class="dropdown-item-text small text-muted">
<?= e($auth['rol'] ?? '') ?>
</span>
</li>

<li><hr class="dropdown-divider"></li>

<li>
<a class="dropdown-item" href="/configuracion">
<i class="bi bi-gear me-2"></i>Configuración
</a>
</li>

<li>
<a class="dropdown-item text-danger" href="/logout">
<i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
</a>
</li>

</ul>

</div>

</div>

</div>

<div id="content">

<?php foreach ($flash_messages ?? [] as $flash): ?>

<div class="alert alert-<?= $flash['type']==='error'?'danger':$flash['type'] ?> alert-dismissible fade show shadow-sm" role="alert">

<?php if($flash['type']==='success'): ?>
<i class="bi bi-check-circle-fill me-2"></i>
<?php endif; ?>

<?php if($flash['type']==='error'): ?>
<i class="bi bi-exclamation-triangle-fill me-2"></i>
<?php endif; ?>

<?= e($flash['message']) ?>

<button type="button" class="btn-close" data-bs-dismiss="alert"></button>

</div>

<?php endforeach; ?>

<?= $content ?>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>

setTimeout(()=>{
document.querySelectorAll('.alert-success').forEach(a=>{
let bsa=bootstrap.Alert.getOrCreateInstance(a);
bsa.close();
});
},5000);

</script>

</body>
</html>