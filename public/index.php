<?php
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    exit("Seven CMS PHP requires PHP version 7.4 or greater (running " . PHP_VERSION . ")\n");
}

define('_SEVEN', TRUE);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', dirname(__DIR__));

// Load Env class and .env file before anything else
require_once(ROOT_DIR . DS . 'lib' . DS . 'env.class.php');
Env::load(ROOT_DIR . DS . '.env');

define('ENVIRONMENT', Env::get('SEVEN_ENV', $_SERVER['SEVEN_ENV'] ?? 'dev'));
define('ROOT_URL', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '_UNKNOWN_'));
define('PROTOCOL', stripos($_SERVER['SERVER_PROTOCOL'] ?? '', 'https') !== false ? 'https://' : 'http://');

if (!defined('STDIN')) define('STDIN', 'php://input');

// Generate a per-request CSP nonce — used by inline <script>/<style> in views.
// Every inline block in the codebase (Master.html + feature views) is stamped
// with `nonce="<?= Csp::nonce() ?>"`, so we can drop 'unsafe-inline' entirely.
require_once(ROOT_DIR . DS . 'lib' . DS . 'csp.class.php');
$_nonce = Csp::nonce();

// Security headers — sent before any output
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// In dev, Vite serves modules from localhost:5173 with HMR. We allow that
// origin and keep 'unsafe-inline' as a fallback only for the dev case
// (Vite injects unsigned inline shims).
$_viteSrc = ENVIRONMENT === 'dev' ? ' http://localhost:5173 ws://localhost:5173' : '';
$_devInline = ENVIRONMENT === 'dev' ? " 'unsafe-inline'" : '';
header("Content-Security-Policy: default-src 'self'{$_viteSrc}; script-src 'self' 'nonce-{$_nonce}'{$_devInline}{$_viteSrc}; style-src 'self' 'nonce-{$_nonce}'{$_devInline}{$_viteSrc}; img-src 'self' data: https://flagcdn.com; font-src 'self'{$_viteSrc}; connect-src 'self'{$_viteSrc}; frame-ancestors 'none';");
if (PROTOCOL === 'https://') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

switch (ENVIRONMENT) {
    case 'dev':
        error_reporting(-1);
        ini_set('display_errors', 1);
        break;
    case 'test':
    case 'prod':
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        break;
    default:
        header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
        echo 'The application environment is not set correctly.';
        exit(1);
}

require_once(ROOT_DIR . DS . 'lib' . DS . 'seven.class.php');

// Pretty error page for dev — install BEFORE the autoloader runs so that
// class-loading exceptions, parse errors, and module boot failures get the
// styled page instead of a raw PHP whitescreen.
require_once(ROOT_DIR . DS . 'lib' . DS . 'errorpage.class.php');
ErrorPage::register();

// Composer is optional. When `vendor/autoload.php` exists we register it so plugins
// can rely on `composer require` packages (Stripe SDK, AWS SDK, PHPMailer, …).
require_once(ROOT_DIR . DS . 'lib' . DS . 'composerbridge.class.php');
ComposerBridge::boot();

Seven::createWebApp()->process();
