<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class General
{
    public $router   = null;
    public $logger   = null;
    public $view     = null;
    public $config   = [];
    public $dbConfig = [];
    public $db       = null;
    public $languageData;
    public $title;

    public function __toString() { return 'General'; }

    public function __construct()
    {
        // Load all config files
        $files = glob(ROOT_DIR . DS . 'config' . DS . '*.config.php');
        foreach ($files as $filename) {
            require_once $filename;
        }

        // Load interfaces before autoloader so implementing classes resolve correctly
        require_once ROOT_DIR . DS . 'lib' . DS . 'cachedriver.interface.php';
        require_once ROOT_DIR . DS . 'lib' . DS . 'middleware.interface.php';
        require_once ROOT_DIR . DS . 'lib' . DS . 'moduleinterface.interface.php';

        // Autoload class files
        spl_autoload_register([$this, 'autoload']);

        // Logger — default channel 'app', also expose via Logger::channel()
        Logger::$PATH      = ROOT_DIR . DS . 'logs';
        Logger::$MIN_LEVEL = (ENVIRONMENT === 'prod') ? Logger::INFO : Logger::DEBUG;
        $this->logger      = Logger::channel('app');

        $this->title    = $config['siteName'];
        $this->config   = $config;
        $this->dbConfig = $dbConfig;

        $this->logger->info('Bootstrap started', ['env' => ENVIRONMENT, 'uri' => $_SERVER['REQUEST_URI'] ?? '/']);

        // Load RedBeanPHP
        Utils::loadExtension('rb' . DS . 'rb', $this->logger);

        // Ensure database exists (creates it if missing)
        Db::isSetDatabase($this->dbConfig, $this->logger);

        // Open the RedBean connection BEFORE modules boot — plugins may read
        // settings or query DB inside boot() (e.g. EcomModule reading
        // ecom.multi_currency_enabled to decide cron registration).
        $constr = 'mysql:host=' . $this->dbConfig['dbHost'] . ';dbname=' . $this->dbConfig['dbname'] . ';charset=utf8mb4';
        Db::setup($constr, $this->dbConfig['user'], $this->dbConfig['password']);

        // Router
        $this->router = new Router($config, $this->getURI());

        // Core named routes
        require_once ROOT_DIR . DS . 'config' . DS . 'routes.php';

        // Auto-load modules — modules may add their own routes
        Module::loadAll();

        $this->logger->debug('Bootstrap complete', [
            'controller' => $this->router->getController(),
            'action'     => $this->router->getAction(),
            'prefix'     => $this->router->getMethodPrefix(),
            'lang'       => $this->router->getLanguage(),
            'modules'    => array_keys(Module::all()),
        ]);
    }

    private function autoload(string $class): void
    {
        $lower = strtolower($class);

        // Lib classes first
        $libPaths = [
            ROOT_DIR . DS . 'lib' . DS . $lower . '.class.php',
            ROOT_DIR . DS . 'lib' . DS . $lower . '.interface.php',
        ];
        foreach ($libPaths as $path) {
            if (file_exists($path)) { require_once $path; return; }
        }

        // Module classes take priority over app/ (modules override app defaults)
        foreach (glob(ROOT_DIR . DS . 'modules' . DS . '*', GLOB_ONLYDIR) as $mod) {
            $modPaths = [
                $mod . DS . 'controllers'    . DS . $class . '.php',
                $mod . DS . 'apiControllers' . DS . $class . '.php',
                $mod . DS . 'models'         . DS . $lower . '.php',
                $mod . DS . 'models'         . DS . $class . '.php',
            ];
            foreach ($modPaths as $path) {
                if (file_exists($path)) { require_once $path; return; }
            }
        }

        // Fallback: app/ directories and module entry points
        $appPaths = [
            ROOT_DIR . DS . 'app' . DS . 'controllers'    . DS . $class . '.php',
            ROOT_DIR . DS . 'app' . DS . 'apiControllers' . DS . $class . '.php',
            ROOT_DIR . DS . 'app' . DS . 'models'         . DS . $lower . '.php',
            ROOT_DIR . DS . 'app' . DS . 'middleware'     . DS . $class . '.php',
            ROOT_DIR . DS . 'modules'                     . DS . $class . DS . 'Module.php',
        ];
        foreach ($appPaths as $path) {
            if (file_exists($path)) { require_once $path; return; }
        }

        $this->logger->warn('Class not found in autoload', ['class' => $class]);
    }

    public function process(): void
    {
        try {
            Config::set('timeCookie', 3600);
            Session::start();
            $this->bootCache();

            // DB connection is opened in __construct() before Module::loadAll()
            // so plugins can query during boot().

            // Load active languages from DB, fall back to config array
            $this->bootLanguages();

            // ── Setup guard ───────────────────────────────────────────────
            $this->checkInstalled();

            $response = new Response();
            $request  = Request::getRequestData();
            Auth::setToken($request->getCookieToken());

            $method = $request->getMethod();
            $uri    = $_SERVER['REQUEST_URI'] ?? '/';
            $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // Access log
            Logger::channel('access')->info($method . ' ' . $uri, ['ip' => $ip]);

            // Global CSRF guard for state-changing admin requests. The API
            // uses JWT (origin-bound) so it's exempt; site-public POSTs would
            // need their own per-form token. Specific actions can be allowlisted
            // by setting `data-csrf-skip` in route metadata (none currently).
            if ($this->router->getMethodPrefix() === 'admin'
                && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                Csrf::verify($_POST + $_GET);
            }

            if ($this->router->getMethodPrefix() === 'api') {
                $this->dispatchApi($request, $response);
            } else {
                $this->dispatchWeb($request, $response);
            }
        } catch (Throwable $e) {
            Logger::channel('error')->error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            if (ENVIRONMENT === 'dev') {
                ErrorPage::render($e);
            } else {
                (new Response())->errorCode(500);
            }
        }
    }

    private function dispatchApi(mixed $request, mixed $response): void
    {
        $controllerClass = ucfirst($this->router->getApiController()) . 'ApiController';
        if (!class_exists($controllerClass)) {
            Logger::channel('app')->warn('API controller not found', ['class' => $controllerClass]);
            $response->errorCode(404, '"' . $this->router->getApiController() . '" controller not found');
        }
        $obj    = new $controllerClass($this);
        $method = strtolower($this->router->getApiAction());
        if (!method_exists($obj, $method)) {
            Logger::channel('app')->warn('API action not found', ['class' => $controllerClass, 'action' => $method]);
            $response->errorCode(404, '"' . $method . '" action not found');
        }
        Logger::channel('app')->debug('API dispatch', ['controller' => $controllerClass, 'action' => $method]);
        print($obj->$method($request, $response, $this->router->getParams()));
    }

    private function dispatchWeb(mixed $request, mixed $response): void
    {
        $this->view = new View();
        if (!Lang::load()) {
            Logger::channel('app')->warn('Language file not found', ['lang' => $this->router->getLanguage()]);
        }
        AdminLang::load();

        $controllerClass = ucfirst($this->router->getController())
                         . ucfirst($this->router->getMethodPrefix())
                         . 'Controller';

        if (!class_exists($controllerClass)) {
            Logger::channel('app')->warn('Controller not found', ['class' => $controllerClass]);
            $response->errorCode(404);
            return;
        }

        $obj    = new $controllerClass($this);
        $method = strtolower($this->router->getAction());

        if (!method_exists($obj, $method)) {
            Logger::channel('app')->warn('Action not found', ['class' => $controllerClass, 'action' => $method]);
            $response->errorCode(404);
            return;
        }

        Logger::channel('app')->debug('Web dispatch', ['controller' => $controllerClass, 'action' => $method]);

        $result = $obj->$method($request, $response, $this->router->getParams());

        // Controllers may return an array of view data (Vue hybrid) or a string (legacy PHP render)
        if (is_array($result)) {
            $this->view->setData($result);
        } else {
            $this->view->setData(['content' => $result]);
        }

        print($this->view->renderLayout($this->router->getMethodPrefix()));
    }

    private function getURI(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function link(string $controller = '', string $action = ''): string
    {
        $ds         = ($controller && $action) ? '/' : '';
        $prefixLang = !empty($this->router->getMethodPrefix())
                    ? $this->router->getMethodPrefix()
                    : $this->router->getLanguage();
        return $ds . $prefixLang . $ds . $controller . $ds . $action;
    }

    private function bootCache(): void
    {
        $cfg   = $this->config['cache'] ?? [];
        $redis = $this->config['redis'] ?? [];

        if (($cfg['driver'] ?? 'file') === 'redis') {
            $driver = new CacheRedisDriver(
                $redis['host']   ?? '127.0.0.1',
                (int)($redis['port'] ?? 6379),
                $redis['prefix'] ?? 'seven:'
            );
            if (!$driver->isConnected()) {
                $this->logger->warn('Redis unavailable, falling back to file cache');
                $driver = new CacheFileDriver();
            }
        } else {
            $driver = new CacheFileDriver();
        }

        Cache::boot($driver);
        $this->logger->debug('Cache booted', ['driver' => get_class($driver)]);
    }

    private function bootLanguages(): void
    {
        // Admin panel has its own language system — redirect non-admin-lang URLs to default admin lang
        if ($this->router->getMethodPrefix() === 'admin') {
            $urlLang     = $this->router->getLanguage();
            $adminLangs  = array_column(AdminLang::getAvailable(), 'code');
            if (!in_array($urlLang, $adminLangs, true)) {
                // Find default admin lang
                $default = 'en';
                foreach (AdminLang::getAvailable() as $l) {
                    if ($l['isDefault']) { $default = $l['code']; break; }
                }
                // Redirect to same admin path with correct lang prefix
                $uri  = $_SERVER['REQUEST_URI'] ?? '/';
                $new  = preg_replace('#^/' . preg_quote($urlLang, '#') . '/#', '/' . $default . '/', $uri, 1);
                if ($new !== $uri) {
                    header('Location: ' . $new, true, 302);
                    exit;
                }
            }
            return;
        }

        try {
            $codes = Language::getActiveCodes();
            if (!empty($codes)) {
                $this->config['languages'] = $codes;
                // Re-validate current language against DB list
                if (!in_array($this->router->getLanguage(), $codes, true)) {
                    // Language not active — use default
                    $default = Language::getDefault();
                    $this->router->setLanguage($default);
                }
            }
        } catch (Throwable $e) {
            // DB not ready yet (e.g. fresh install) — keep config defaults
            $this->logger->debug('bootLanguages: using config fallback', ['err' => $e->getMessage()]);
        }
    }

    private function checkInstalled(): void
    {
        $lockFile = ROOT_DIR . DS . 'storage' . DS . 'installed.lock';

        // Already installed
        if (file_exists($lockFile)) return;

        // Allow setup route through without redirect
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (str_contains($uri, '/setup')) return;

        // Check if any admin exists in DB
        try {
            $hasAdmin = \R::findOne('user', ' role = "admin" ');
            if ($hasAdmin) {
                // DB has data but no lock file — create it (migration from old install)
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
                return;
            }
        } catch (Throwable) {
            // DB tables don't exist yet — definitely not installed
        }

        // Not installed — redirect to setup
        $lang = $this->config['defaultLanguage'] ?? 'en';
        header('Location: /' . $lang . '/setup');
        exit;
    }

    public function setTitle(string $text = ''): void { $this->title = $text; }
    public function getTitle(): string               { return $this->title; }
}
