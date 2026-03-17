<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? '') ?> — Contratación Pública EC</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body{background:linear-gradient(135deg,#1B4F72 0%,#0d2d45 50%,#117A65 100%);min-height:100vh;display:flex;align-items:center;}
    .login-card{max-width:420px;width:100%;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
    .login-header{background:linear-gradient(135deg,#1B4F72,#2E86C1);border-radius:16px 16px 0 0;padding:32px;text-align:center;color:#fff;}
    .login-header h1{font-size:22px;font-weight:700;margin:0;}
    .login-header small{opacity:.8;font-size:12px;}
    .login-body{padding:32px;}
    .form-floating>.form-control{border-radius:10px;}
    .btn-login{background:linear-gradient(135deg,#1B4F72,#2E86C1);border:none;color:#fff;width:100%;padding:12px;border-radius:10px;font-weight:600;font-size:15px;}
    .btn-login:hover{background:linear-gradient(135deg,#154360,#1a5276);color:#fff;}
    .flag-ec{font-size:24px;}
  </style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-5">
      <div class="login-card card border-0">
        <div class="login-header">
          <div class="flag-ec mb-2">🇪🇨</div>
          <h1><i class="bi bi-bank me-2"></i>Contratación Pública EC</h1>
          <small>Sistema de Gestión de Contratación Pública</small>
        </div>
        <div class="login-body">
          <?php foreach (View::getFlash() as $f): ?>
            <div class="alert alert-<?= $f['type']==='error'?'danger':$f['type'] ?> alert-dismissible fade show">
              <?= e($f['message']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endforeach; ?>
          <?= $content ?>
        </div>
        <div class="card-footer text-center text-muted small py-3" style="border-radius:0 0 16px 16px">
          &copy; <?= date('Y') ?> Contratación Pública EC — LOSNCP Ecuador
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
