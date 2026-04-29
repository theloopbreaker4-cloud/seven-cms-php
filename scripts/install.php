<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */
/**
 * Seven CMS — Install Script
 *
 * Usage:
 *   wsl.exe -d Ubuntu bash -c 'php /mnt/d/Works/SevenCMSProjects/sevenPHP/scripts/install.php'
 *
 * Options (interactive prompts, or pass as args):
 *   php install.php [admin_email] [admin_password] [admin_username] [site_name]
 *
 * What it does:
 *   1. Tests DB connection
 *   2. Seeds default languages
 *   3. Seeds root admin user
 *   4. Seeds home page content
 *   5. Seeds default settings
 */

define('_SEVEN', true);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', dirname(__DIR__));
define('ENVIRONMENT', 'dev');
define('ROOT_URL', 'localhost');
define('PROTOCOL', 'http://');
if (!defined('STDIN')) define('STDIN', fopen('php://stdin', 'r'));

require_once ROOT_DIR . DS . 'lib' . DS . 'env.class.php';
Env::load(ROOT_DIR . DS . '.env');

require_once ROOT_DIR . DS . 'lib' . DS . 'cachedriver.interface.php';
require_once ROOT_DIR . DS . 'lib' . DS . 'middleware.interface.php';
require_once ROOT_DIR . DS . 'lib' . DS . 'moduleinterface.interface.php';

spl_autoload_register(function (string $class) {
    $lower = strtolower($class);
    foreach ([
        ROOT_DIR . DS . 'lib'                    . DS . $lower . '.class.php',
        ROOT_DIR . DS . 'app' . DS . 'models'    . DS . $lower . '.php',
        ROOT_DIR . DS . 'app' . DS . 'models'    . DS . $class . '.php',
    ] as $p) {
        if (file_exists($p)) { require_once $p; return; }
    }
});

$files = glob(ROOT_DIR . DS . 'config' . DS . '*.config.php');
foreach ($files as $f) require_once $f;

require_once ROOT_DIR . DS . 'lib' . DS . 'extension' . DS . 'rb' . DS . 'rb.php';

// ── helpers ────────────────────────────────────────────────────────────────
function ask(string $prompt, string $default = ''): string {
    echo $prompt . ($default ? " [{$default}]" : '') . ': ';
    $val = trim(fgets(STDIN));
    return $val !== '' ? $val : $default;
}

function line(string $msg, string $color = ''): void {
    $colors = ['green' => "\033[32m", 'yellow' => "\033[33m", 'red' => "\033[31m", 'cyan' => "\033[36m", '' => ''];
    echo ($colors[$color] ?? '') . $msg . ($color ? "\033[0m" : '') . "\n";
}

function step(string $label): void { line("  ▸ {$label}", 'cyan'); }
function ok(string $msg): void    { line("  ✓ {$msg}", 'green'); }
function warn(string $msg): void  { line("  ⚠ {$msg}", 'yellow'); }

// ── header ─────────────────────────────────────────────────────────────────
echo "\n";
line('╔══════════════════════════════════════╗', 'cyan');
line('║       Seven CMS — Install Wizard     ║', 'cyan');
line('╚══════════════════════════════════════╝', 'cyan');
echo "\n";

// ── DB connection ──────────────────────────────────────────────────────────
/** @var array $dbConfig */
step('Connecting to database...');
try {
    \R::setup(
        'mysql:host=' . $dbConfig['dbHost'] . ';dbname=' . $dbConfig['dbname'] . ';charset=utf8mb4',
        $dbConfig['user'],
        $dbConfig['password']
    );
    \R::setAutoResolve(true);
    \R::getCell('SELECT 1');
    ok('Database connected: ' . $dbConfig['dbname'] . '@' . $dbConfig['dbHost']);
} catch (Throwable $e) {
    line('  ✗ DB connection failed: ' . $e->getMessage(), 'red');
    exit(1);
}

// ── admin credentials ──────────────────────────────────────────────────────
echo "\n";
line('── Admin Account ──────────────────────', '');
$adminEmail = $argv[1] ?? ask('Admin email',    'admin@sevencms.local');
$adminPass  = $argv[2] ?? ask('Admin password', 'Admin123!');
$adminUser  = $argv[3] ?? ask('Admin username', 'admin');
$siteName   = $argv[4] ?? ask('Site name',      'Seven CMS');

// ── 1. Languages ───────────────────────────────────────────────────────────
echo "\n";
line('── Seeding languages ──────────────────', '');

$defaultLangs = [
    ['code'=>'en','name'=>'English',    'native'=>'English',    'flag'=>'🇬🇧','default'=>1],
    ['code'=>'ru','name'=>'Russian',    'native'=>'Русский',    'flag'=>'🇷🇺','default'=>0],
    ['code'=>'ka','name'=>'Georgian',   'native'=>'ქართული',    'flag'=>'🇬🇪','default'=>0],
    ['code'=>'uk','name'=>'Ukrainian',  'native'=>'Українська', 'flag'=>'🇺🇦','default'=>0],
    ['code'=>'az','name'=>'Azerbaijani','native'=>'Azərbaycanca','flag'=>'🇦🇿','default'=>0],
    ['code'=>'hy','name'=>'Armenian',   'native'=>'Հայերեն',    'flag'=>'🇦🇲','default'=>0],
];

foreach ($defaultLangs as $i => $ldata) {
    $exists = \R::findOne('language', ' code = :c ', [':c' => $ldata['code']]);
    if ($exists) { warn("Language {$ldata['code']} already exists — skip"); continue; }
    $b = \R::dispense('language');
    $b->code        = $ldata['code'];
    $b->name        = $ldata['name'];
    $b->native_name = $ldata['native'];
    $b->flag        = $ldata['flag'];
    $b->is_active   = 1;
    $b->is_default  = $ldata['default'];
    $b->sort_order  = $i;
    $b->created_at  = date('Y-m-d H:i:s');
    \R::store($b);
    ok("Language: {$ldata['flag']} {$ldata['native']} ({$ldata['code']})");
}

// ── 2. Admin user ──────────────────────────────────────────────────────────
echo "\n";
line('── Seeding admin user ─────────────────', '');

$existing = \R::findOne('user', ' role = "admin" ');
if ($existing) {
    warn("Admin already exists (id={$existing->id}, email={$existing->email}) — skip");
} else {
    $b = \R::dispense('user');
    $b->first_name        = 'Root';
    $b->last_name         = 'Admin';
    $b->user_name         = $adminUser;
    $b->email             = strtolower($adminEmail);
    $b->password          = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]);
    $b->avatar            = '';
    $b->mobile            = '';
    $b->country           = '';
    $b->role              = 'admin';
    $b->is_active         = 1;
    $b->provider          = 'local';
    $b->provider_id       = null;
    $b->two_factor_enabled = 0;
    $b->two_factor_secret  = '';
    $b->last_login_at     = null;
    $b->created_at        = date('Y-m-d H:i:s');
    $b->updated_at        = $b->created_at;
    $id = \R::store($b);
    ok("Admin created: id={$id}, email={$adminEmail}");
}

// ── 3. Home page content ───────────────────────────────────────────────────
echo "\n";
line('── Seeding home page ──────────────────', '');

$existingPage = \R::findOne('page', ' slug = "home" ');
if ($existingPage) {
    warn('Home page already exists — skip');
} else {
    $b = \R::dispense('page');
    $b->slug         = 'home';
    $b->template     = 'home';
    $b->title        = json_encode([
        'en' => 'Welcome to Seven CMS',
        'ru' => 'Добро пожаловать в Seven CMS',
        'ka' => 'კეთილი იყოს თქვენი მობრძანება',
        'uk' => 'Ласкаво просимо до Seven CMS',
        'az' => 'Seven CMS-ə xoş gəlmisiniz',
        'hy' => 'Բարի գալուստ Seven CMS',
    ], JSON_UNESCAPED_UNICODE);
    $b->content      = json_encode([
        'en' => '<p>A modern, multilingual CMS built on PHP 8.4 + Vue 3. Fast, flexible, open source.</p>',
        'ru' => '<p>Современная многоязычная CMS на PHP 8.4 + Vue 3. Быстрая, гибкая, с открытым кодом.</p>',
        'ka' => '<p>თანამედროვე მრავალენოვანი CMS PHP 8.4 + Vue 3-ზე. სწრაფი, მოქნილი, ღია კოდი.</p>',
        'uk' => '<p>Сучасна багатомовна CMS на PHP 8.4 + Vue 3. Швидка, гнучка, з відкритим кодом.</p>',
        'az' => '<p>PHP 8.4 + Vue 3 üzərində qurulmuş müasir çoxdilli CMS. Sürətli, çevik, açıq mənbəli.</p>',
        'hy' => '<p>Ժամանակակից բազմալեզու CMS PHP 8.4 + Vue 3-ի վրա: Արագ, ճկուն, բաց կոդ:</p>',
    ], JSON_UNESCAPED_UNICODE);
    $b->meta_title   = json_encode(['en' => 'Seven CMS — Modern Multilingual CMS'], JSON_UNESCAPED_UNICODE);
    $b->meta_desc    = json_encode(['en' => 'A modern multilingual CMS built on PHP 8.4, Vue 3, Tailwind and RedBeanPHP.'], JSON_UNESCAPED_UNICODE);
    $b->is_published = 1;
    $b->sort_order   = 0;
    $b->created_at   = date('Y-m-d H:i:s');
    $b->updated_at   = $b->created_at;
    \R::store($b);
    ok('Home page seeded');
}

// ── 4. Default settings ────────────────────────────────────────────────────
echo "\n";
line('── Seeding settings ───────────────────', '');

$settings = [
    ['key'=>'site_name',     'value'=>$siteName,     'group'=>'general','type'=>'string','label'=>'Site Name'],
    ['key'=>'site_tagline',  'value'=>'Modern Multilingual CMS','group'=>'general','type'=>'string','label'=>'Tagline'],
    ['key'=>'site_email',    'value'=>$adminEmail,   'group'=>'general','type'=>'string','label'=>'Contact Email'],
    ['key'=>'posts_per_page','value'=>'10',           'group'=>'general','type'=>'int',   'label'=>'Posts Per Page'],
    ['key'=>'comments_on',   'value'=>'1',            'group'=>'general','type'=>'bool',  'label'=>'Comments Enabled'],
    ['key'=>'registration_on','value'=>'1',           'group'=>'general','type'=>'bool',  'label'=>'Registration Enabled'],
    ['key'=>'meta_description',   'value'=>'A modern multilingual CMS.',  'group'=>'seo',      'type'=>'text', 'label'=>'Default Meta Description'],
    ['key'=>'captcha_on_login',   'value'=>'0',                          'group'=>'security', 'type'=>'bool', 'label'=>'Captcha on Login'],
    ['key'=>'captcha_on_register','value'=>'1',                          'group'=>'security', 'type'=>'bool', 'label'=>'Captcha on Register'],
    ['key'=>'captcha_on_forgot',  'value'=>'1',                          'group'=>'security', 'type'=>'bool', 'label'=>'Captcha on Forgot Password'],
];

foreach ($settings as $s) {
    $exists = \R::findOne('setting', ' `key` = :k ', [':k' => $s['key']]);
    if ($exists) { warn("Setting {$s['key']} already exists — skip"); continue; }
    $b = \R::dispense('setting');
    $b->key        = $s['key'];
    $b->value      = $s['value'];
    $b->group      = $s['group'];
    $b->type       = $s['type'];
    $b->label      = $s['label'];
    $b->updated_at = date('Y-m-d H:i:s');
    \R::store($b);
    ok("Setting: {$s['key']} = {$s['value']}");
}

// ── Admin UI languages ─────────────────────────────────────────────────────
$adminLangs = [
    ['code' => 'en', 'name' => 'English',    'is_default' => 1],
    ['code' => 'de', 'name' => 'Deutsch',    'is_default' => 0],
    ['code' => 'fr', 'name' => 'Français',   'is_default' => 0],
    ['code' => 'nl', 'name' => 'Nederlands', 'is_default' => 0],
];

foreach ($adminLangs as $al) {
    $exists = \R::findOne('adminlang', ' code = :c ', [':c' => $al['code']]);
    if ($exists) { warn("Admin lang {$al['code']} already exists — skip"); continue; }
    $b             = \R::dispense('adminlang');
    $b->code       = $al['code'];
    $b->name       = $al['name'];
    $b->is_default = $al['is_default'];
    \R::store($b);
    ok("Admin lang: {$al['code']} ({$al['name']})");
}

\R::close();

// ── done ───────────────────────────────────────────────────────────────────
echo "\n";
line('╔══════════════════════════════════════╗', 'green');
line('║        Installation complete!        ║', 'green');
line('╚══════════════════════════════════════╝', 'green');
echo "\n";
line("  Site:   http://localhost:8085/en/", 'cyan');
line("  Admin:  http://localhost:8085/en/admin", 'cyan');
line("  Login:  {$adminEmail}", 'cyan');
line("  Pass:   {$adminPass}", 'cyan');
echo "\n";
line('  ⚠  Change your password after first login!', 'yellow');
echo "\n";
