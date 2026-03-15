<?php

declare(strict_types=1);

// ── Lee variables de entorno desde .env ────────────────────────────────────
// Carga el .env — busca en la raíz del proyecto
// Si todo está en /home/brixs/sistema.brixs.cloud/, ROOT_PATH ya apunta ahí
$envFile = ROOT_PATH . '/.env';
if (!file_exists($envFile)) {
    // Intento alternativo: un nivel arriba (si public_html está separada)
    $envFile = dirname(ROOT_PATH) . '/.env';
}
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
    }
}

// ── Constantes de la aplicación ────────────────────────────────────────────
define('APP_NAME',       $_ENV['APP_NAME']       ?? 'ContratosPúblicos EC');
define('APP_URL',        rtrim($_ENV['APP_URL']  ?? 'http://localhost', '/'));
define('APP_ENV',        $_ENV['APP_ENV']        ?? 'production');
define('APP_DEBUG',      filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
define('APP_KEY',        $_ENV['APP_KEY']        ?? 'cambia-esta-clave-secreta-32chars!');
define('APP_TIMEZONE',   $_ENV['APP_TIMEZONE']   ?? 'America/Guayaquil');
define('APP_VERSION',    '1.0.0');

// ── OpenRouter / IA ────────────────────────────────────────────────────────
define('OPENROUTER_KEY',   $_ENV['OPENROUTER_KEY']   ?? '');
define('OPENROUTER_MODEL', $_ENV['OPENROUTER_MODEL'] ?? 'anthropic/claude-3-5-sonnet');
define('OPENROUTER_URL',   'https://openrouter.ai/api/v1/chat/completions');

// ── Mail (SMTP) ────────────────────────────────────────────────────────────
define('MAIL_HOST',       $_ENV['MAIL_HOST']       ?? 'localhost');
define('MAIL_PORT',       (int)($_ENV['MAIL_PORT'] ?? 587));
define('MAIL_USER',       $_ENV['MAIL_USER']       ?? '');
define('MAIL_PASS',       $_ENV['MAIL_PASS']       ?? '');
define('MAIL_FROM',       $_ENV['MAIL_FROM']       ?? 'noreply@tudominio.ec');
define('MAIL_FROM_NAME',  $_ENV['MAIL_FROM_NAME']  ?? APP_NAME);
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');

// ── Storage ────────────────────────────────────────────────────────────────
define('UPLOAD_MAX_MB',   (int)($_ENV['UPLOAD_MAX_MB'] ?? 20));
define('ALLOWED_MIMES',   ['application/pdf', 'image/jpeg', 'image/png', 'image/webp',
                            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);

// ── Paginación ─────────────────────────────────────────────────────────────
define('PER_PAGE', 20);

// ── Modo error ─────────────────────────────────────────────────────────────
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');
}
