<?php

declare(strict_types=1);

// ── URL ────────────────────────────────────────────────────────────────────
function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    View::redirect($path);
}

// ── Texto ──────────────────────────────────────────────────────────────────
function e(mixed $val): string
{
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function truncate(string $text, int $length = 80): string
{
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

// ── Fechas ─────────────────────────────────────────────────────────────────
function formatDate(?string $date, string $format = 'd/m/Y'): string
{
    if (!$date) return '—';
    $d = \DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $d ? $d->format($format) : '—';
}

function formatDateTime(?string $dt): string
{
    if (!$dt) return '—';
    $d = new \DateTime($dt);
    return $d->format('d/m/Y H:i');
}

function daysUntil(?string $date): int
{
    if (!$date) return 0;
    return (int) ceil((strtotime($date) - time()) / 86400);
}

function diasDesde(?string $date): int
{
    if (!$date) return 0;
    return (int) floor((time() - strtotime($date)) / 86400);
}

// ── Dinero ─────────────────────────────────────────────────────────────────
function money(float|string|null $amount): string
{
    return '$' . number_format((float)($amount ?? 0), 2, '.', ',');
}

// ── Seguridad ──────────────────────────────────────────────────────────────
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . Auth::csrfToken() . '">';
}

function csrf_token(): string
{
    return Auth::csrfToken();
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::verifyCsrf($token)) {
        http_response_code(419);
        exit('Token CSRF inválido.');
    }
}

// ── Auth shortcuts ─────────────────────────────────────────────────────────
function auth(): ?array   { return Auth::user(); }
function authId(): ?int   { return Auth::id(); }
function tenantId(): ?int { return Auth::tenantId(); }
function can(string $perm): bool { return Auth::can($perm); }

// ── Flash ──────────────────────────────────────────────────────────────────
function flash(string $type, string $msg): void  { View::flash($type, $msg); }
function getFlash(): array                         { return View::getFlash(); }

// ── Archivos / Storage ────────────────────────────────────────────────────
function storagePath(string $path = ''): string
{
    return STORAGE_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

function tenantStoragePath(string $path = ''): string
{
    $base = STORAGE_PATH . '/documents/' . (tenantId() ?? 'global');
    if (!is_dir($base)) mkdir($base, 0755, true);
    return $base . ($path ? '/' . ltrim($path, '/') : '');
}

function uploadFile(array $file, string $destDir, array $allowedMimes = []): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Error al subir el archivo.');
    }
    $mime = mime_content_type($file['tmp_name']);
    $allowed = $allowedMimes ?: ALLOWED_MIMES;
    if (!in_array($mime, $allowed)) {
        throw new \RuntimeException("Tipo de archivo no permitido: {$mime}");
    }
    if ($file['size'] > UPLOAD_MAX_MB * 1024 * 1024) {
        throw new \RuntimeException('El archivo supera el tamaño máximo permitido.');
    }
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(12)) . '.' . strtolower($ext);
    $dest     = rtrim($destDir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new \RuntimeException('No se pudo mover el archivo.');
    }
    return [
        'filename'  => $filename,
        'original'  => $file['name'],
        'path'      => $dest,
        'mime'      => $mime,
        'size'      => $file['size'],
        'hash'      => hash_file('sha256', $dest),
    ];
}

// ── Paginación HTML ───────────────────────────────────────────────────────
function paginationLinks(array $paginator, string $baseUrl): string
{
    if ($paginator['last_page'] <= 1) return '';
    $html = '<nav><ul class="pagination mb-0">';
    for ($i = 1; $i <= $paginator['last_page']; $i++) {
        $active = $i === $paginator['current_page'] ? ' active' : '';
        $html .= "<li class='page-item{$active}'><a class='page-link' href='{$baseUrl}?page={$i}'>{$i}</a></li>";
    }
    return $html . '</ul></nav>';
}

// ── Badges de estado ──────────────────────────────────────────────────────
function estadoBadge(string $estado): string
{
    $map = [
        'borrador'               => ['secondary', 'Borrador'],
        'adjudicado'             => ['primary',   'Adjudicado'],
        'en_ejecucion'           => ['info',      'En Ejecución'],
        'entregado_provisional'  => ['warning',   'Entregado Provisional'],
        'entregado_definitivo'   => ['success',   'Entregado Definitivo'],
        'facturado'              => ['dark',       'Facturado'],
        'pagado'                 => ['success',   'Pagado'],
        'cerrado'                => ['secondary', 'Cerrado'],
        'cancelado'              => ['danger',    'Cancelado'],
        'pendiente'              => ['warning',   'Pendiente'],
        'en_progreso'            => ['info',      'En Progreso'],
        'aprobado'               => ['success',   'Aprobado'],
        'observado'              => ['danger',    'Observado'],
        'vigente'                => ['success',   'Vigente'],
        'por_vencer'             => ['warning',   'Por Vencer'],
        'vencido'                => ['danger',    'Vencido'],
        'emitida'                => ['primary',   'Emitida'],
        'presentada'             => ['info',      'Presentada'],
        'en_tramite'             => ['warning',   'En Trámite'],
        'activo'                 => ['success',   'Activo'],
        'inactivo'               => ['secondary', 'Inactivo'],
    ];
    [$color, $label] = $map[$estado] ?? ['secondary', ucfirst($estado)];
    return "<span class='badge bg-{$color}'>{$label}</span>";
}

// ── Tipos de proceso legibles ─────────────────────────────────────────────
function tipoProceso(string $tipo): string
{
    return match($tipo) {
        'infima_cuantia'       => 'Ínfima Cuantía',
        'catalogo'             => 'Catálogo Electrónico',
        'subasta'              => 'Subasta Inversa',
        'licitacion'           => 'Licitación',
        'menor_cuantia'        => 'Menor Cuantía',
        'contratacion_directa' => 'Contratación Directa',
        default                => ucwords(str_replace('_', ' ', $tipo)),
    };
}

// ── JSON limpio ────────────────────────────────────────────────────────────
function jsonResponse(mixed $data, int $status = 200): never
{
    View::json(is_array($data) ? $data : ['data' => $data], $status);
}

function jsonSuccess(mixed $data = null, string $message = 'OK'): never
{
    View::json(['success' => true, 'message' => $message, 'data' => $data]);
}

function jsonError(string $message, int $status = 400, array $errors = []): never
{
    View::json(['success' => false, 'error' => $message, 'errors' => $errors], $status);
}

// ── Generar número de proceso ─────────────────────────────────────────────
function generarNumeroProceso(): string
{
    return 'PRO-' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
}

// ── Logging ───────────────────────────────────────────────────────────────
function logInfo(string $msg, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] INFO: ' . $msg;
    if ($context) $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    error_log($line . PHP_EOL, 3, STORAGE_PATH . '/logs/app.log');
}

function logError(string $msg, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $msg;
    if ($context) $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    error_log($line . PHP_EOL, 3, STORAGE_PATH . '/logs/app.log');
}
