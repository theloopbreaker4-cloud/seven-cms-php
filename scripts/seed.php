<?php
/**
 * Seed root admin user.
 * Usage: wsl.exe -d Ubuntu bash -c 'php /mnt/d/Works/SevenCMSProjects/sevenPHP/scripts/seed.php'
 */

define('_SEVEN', true);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', dirname(__DIR__));
define('ENVIRONMENT', 'dev');
define('ROOT_URL', 'localhost');
define('PROTOCOL', 'http://');

if (!defined('STDIN')) define('STDIN', 'php://input');

require_once ROOT_DIR . DS . 'lib' . DS . 'env.class.php';
Env::load(ROOT_DIR . DS . '.env');

// Load interfaces eagerly (same as General::__construct)
require_once ROOT_DIR . DS . 'lib' . DS . 'cachedriver.interface.php';
require_once ROOT_DIR . DS . 'lib' . DS . 'middleware.interface.php';
require_once ROOT_DIR . DS . 'lib' . DS . 'moduleinterface.interface.php';

// Minimal autoloader
spl_autoload_register(function (string $class) {
    $lower = strtolower($class);
    $paths = [
        ROOT_DIR . DS . 'lib'               . DS . $lower . '.class.php',
        ROOT_DIR . DS . 'lib'               . DS . $lower . '.interface.php',
        ROOT_DIR . DS . 'app' . DS . 'models'      . DS . $lower . '.php',
        ROOT_DIR . DS . 'app' . DS . 'controllers' . DS . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) { require_once $path; return; }
    }
});

// Load config
$files = glob(ROOT_DIR . DS . 'config' . DS . '*.config.php');
foreach ($files as $f) require_once $f;

// Load RedBeanPHP and connect DB
/** @var array $dbConfig */
require_once ROOT_DIR . DS . 'lib' . DS . 'extension' . DS . 'rb' . DS . 'rb.php';
\R::setup(
    'mysql:host=' . $dbConfig['dbHost'] . ';dbname=' . $dbConfig['dbname'] . ';charset=utf8mb4',
    $dbConfig['user'],
    $dbConfig['password']
);
\R::setAutoResolve(true);

// -- Seed admin ---------------------------------------------------------

$email    = $argv[1] ?? 'admin@sevencms.local';
$password = $argv[2] ?? 'Admin123!';
$userName = $argv[3] ?? 'admin';

$existing = \R::findOne('user', ' role = "admin" ');
if ($existing) {
    echo "Admin already exists (id={$existing->id}, email={$existing->email})\n";
    \R::close();
    exit(0);
}

$bean                   = \R::dispense('user');
$bean->first_name       = 'Root';
$bean->last_name        = 'Admin';
$bean->user_name        = $userName;
$bean->email            = strtolower($email);
$bean->password         = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
$bean->avatar           = '';
$bean->mobile           = '';
$bean->country          = '';
$bean->role             = 'admin';
$bean->is_active        = 1;
$bean->provider         = 'local';
$bean->provider_id      = null;
$bean->two_factor_enabled = 0;
$bean->two_factor_secret  = '';
$bean->last_login_at    = null;
$bean->created_at       = date('Y-m-d H:i:s');
$bean->updated_at       = $bean->created_at;

$id = \R::store($bean);
\R::close();

echo "Admin created:\n";
echo "  id:       $id\n";
echo "  email:    $email\n";
echo "  username: $userName\n";
echo "  password: $password\n";
echo "\nChange the password after first login!\n";
