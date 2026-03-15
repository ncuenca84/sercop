<?php
declare(strict_types=1);

// ROOT_PATH: usa __DIR__ si todo el sistema está en la raíz del subdominio/dominio
// (ejemplo: /home/brixs/sistema.brixs.cloud/)
// Usa dirname(__DIR__) solo si public_html está separada del resto del sistema
define('ROOT_PATH',      __DIR__);
define('APP_PATH',       ROOT_PATH . '/app');
define('CORE_PATH',      ROOT_PATH . '/core');
define('STORAGE_PATH',   ROOT_PATH . '/storage');
define('RESOURCES_PATH', ROOT_PATH . '/resources');
define('PUBLIC_PATH',    __DIR__);
define('START_TIME',     microtime(true));

spl_autoload_register(function (string $class): void {
    $map = [
        'Controllers\\Api\\'  => APP_PATH . '/Controllers/Api/',
        'Controllers\\Web\\'  => APP_PATH . '/Controllers/Web/',
        'Models\\'            => APP_PATH . '/Models/',
        'Services\\'          => APP_PATH . '/Services/',
        'Middleware\\'        => APP_PATH . '/Middleware/',
        'Helpers\\'           => APP_PATH . '/Helpers/',
        'Core\\Router\\'      => CORE_PATH . '/Router/',
        'Core\\Database\\'    => CORE_PATH . '/Database/',
        'Core\\Auth\\'        => CORE_PATH . '/Auth/',
        'Core\\View\\'        => CORE_PATH . '/View/',
        'Core\\Validator\\'   => CORE_PATH . '/Validator/',
        'Core\\Mail\\'        => CORE_PATH . '/Mail/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

require_once ROOT_PATH . '/config/app.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/core/Database/DB.php';
require_once ROOT_PATH . '/core/Router/Router.php';
require_once ROOT_PATH . '/core/Auth/Auth.php';
require_once ROOT_PATH . '/core/View/View.php';
require_once ROOT_PATH . '/core/Validator/Validator.php';
require_once ROOT_PATH . '/core/Mail/Mailer.php';
require_once ROOT_PATH . '/app/Helpers/helpers.php';
require_once ROOT_PATH . '/app/Middleware/AuthMiddleware.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '7200');
    session_name('CPUB_SESS');
    session_start();
}

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'America/Guayaquil');

if (!empty($_SESSION['tenant_id'])) {
    DB::setTenant((int)$_SESSION['tenant_id']);
}

set_exception_handler(function (\Throwable $e): void {
    $msg = (defined('APP_DEBUG') && APP_DEBUG)
        ? $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine()
        : 'Error interno. Intente más tarde.';
    error_log('[EXCEPTION] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'error' => $msg]);
    } else {
        http_response_code(500);
        echo "<h2>Error del sistema</h2><p>{$msg}</p><a href='/dashboard'>← Inicio</a>";
    }
    exit;
});

require_once ROOT_PATH . '/routes/web.php';
require_once ROOT_PATH . '/routes/api.php';

Router::getInstance()->dispatch();
