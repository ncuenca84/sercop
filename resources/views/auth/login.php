<form method="POST" action="/login">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label fw-semibold"><i class="bi bi-envelope me-1"></i>Correo electrónico</label>
    <input type="email" name="email" class="form-control form-control-lg"
           placeholder="usuario@empresa.ec" required autofocus autocomplete="email">
  </div>
  <div class="mb-4">
    <label class="form-label fw-semibold"><i class="bi bi-lock me-1"></i>Contraseña</label>
    <input type="password" name="password" class="form-control form-control-lg"
           placeholder="••••••••" required autocomplete="current-password">
  </div>
  <button type="submit" class="btn btn-login">
    <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
  </button>
</form>
