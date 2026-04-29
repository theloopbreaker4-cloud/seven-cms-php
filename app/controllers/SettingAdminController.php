<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class SettingAdminController extends Controller
{
    public function index(mixed $req, mixed $res): mixed
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Settings');

        $settings   = DB::getAll('SELECT * FROM setting ORDER BY `group`, `key`') ?: [];
        $adminLangs = AdminLang::getAvailable();
        $lang       = Seven::app()->router->getLanguage();

        // Site languages tab data
        $siteLangs    = Language::getActive();
        $siteArchived = array_values(array_filter(
            DB::findAll('language', ' 1 ORDER BY code ASC ') ?: [],
            fn($r) => !$r->is_active
        ));
        $siteActive   = array_map(fn($r) => $r->code, $siteLangs);
        $knownLangs   = Language::KNOWN;

        // Cache tab data
        $activeDriver = get_class(Cache::driver());
        $isRedis      = $activeDriver === 'CacheRedisDriver';
        $redisAvail   = $this->probeRedis();
        $cfg          = $this->app->config['cache'] ?? [];
        $redisCfg     = $this->app->config['redis'] ?? [];

        return $this->app->view->render('index', compact(
            'settings', 'adminLangs', 'lang',
            'siteLangs', 'siteArchived', 'siteActive', 'knownLangs',
            'activeDriver', 'isRedis', 'redisAvail', 'cfg', 'redisCfg'
        ));
    }

    private function probeRedis(): bool
    {
        if (!extension_loaded('redis')) return false;
        $cfg = $this->app->config['redis'] ?? [];
        try {
            $r  = new \Redis();
            $ok = @$r->connect($cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 6379), 1.0);
            if ($ok) $r->close();
            return $ok;
        } catch (\Throwable) { return false; }
    }

    // POST /admin/setting/save — save arbitrary settings
    public function save(mixed $req, mixed $res): void
    {
        $this->requireAdmin($res);
        $allowed = ['site_name','site_tagline','site_email',
                    'captcha_on_login','captcha_on_register','captcha_on_forgot'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $_POST)) {
                Setting::set($key, $_POST[$key]);
            }
        }
        $lang = Seven::app()->router->getLanguage();
        header('Location: /' . $lang . '/admin/setting');
        exit;
    }

    // POST /admin/setting/uploadbrand — upload brand.svg or favicon.svg
    public function uploadbrand(mixed $req, mixed $res): void
    {
        $this->requireAdmin($res);
        $lang    = Seven::app()->router->getLanguage();
        $target  = trim($_POST['target'] ?? '');
        $tab     = trim($_GET['tab'] ?? 'site');

        if (!in_array($target, ['brand', 'favicon'], true)) {
            Session::setFlash('Invalid target.');
            header('Location: /' . $lang . '/admin/setting?tab=' . $tab); exit;
        }
        $file = $_FILES['svg'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::setFlash('Upload error.');
            header('Location: /' . $lang . '/admin/setting?tab=' . $tab); exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'svg') {
            Session::setFlash('Only SVG files are allowed.');
            header('Location: /' . $lang . '/admin/setting?tab=' . $tab); exit;
        }
        $content = file_get_contents($file['tmp_name']);
        $clean   = SvgSanitizer::clean((string)$content);
        if ($clean === null) {
            Session::setFlash('Invalid or unsafe SVG file.');
            header('Location: /' . $lang . '/admin/setting?tab=' . $tab); exit;
        }
        file_put_contents(ROOT_DIR . DS . 'public' . DS . $target . '.svg', $clean);
        Session::setFlash(ucfirst($target) . '.svg updated.');
        header('Location: /' . $lang . '/admin/theme'); exit;
    }

    // POST /admin/setting/resetbrand — restore SVG from active theme folder
    public function resetbrand(mixed $req, mixed $res): void
    {
        $this->requireAdmin($res);
        $lang    = Seven::app()->router->getLanguage();
        $target  = trim($_POST['target'] ?? '');

        if (!in_array($target, ['brand', 'favicon'], true)) {
            header('Location: /' . $lang . '/admin/theme'); exit;
        }

        $palette  = Setting::get('theme_palette', 'default');
        $src      = ROOT_DIR . DS . 'src' . DS . 'themes' . DS . $palette . DS . 'svg' . DS . $target . '.svg';
        if (file_exists($src)) {
            copy($src, ROOT_DIR . DS . 'public' . DS . $target . '.svg');
            Session::setFlash(ucfirst($target) . '.svg reset to ' . $palette . ' theme.');
        } else {
            Session::setFlash('No theme SVG found for ' . $palette . '.');
        }
        header('Location: /' . $lang . '/admin/theme'); exit;
    }

    // POST /admin/setting/setuilang  — set admin UI language via cookie
    public function setuilang(mixed $req, mixed $res): void
    {
        $this->requireAdmin($res);
        $code = trim($_POST['code'] ?? '');
        if ($code && preg_match('/^[a-z]{2}$/', $code)) {
            AdminLang::setCookie($code);
        }
        $lang = Seven::app()->router->getLanguage();
        header('Location: /' . $lang . '/admin/setting');
        exit;
    }

    // POST /admin/setting/adduilang  — add a new admin UI language
    public function adduilang(mixed $req, mixed $res): void
    {
        $this->requireAdmin($res);
        $code = strtolower(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $lang = Seven::app()->router->getLanguage();

        if (!$code || !preg_match('/^[a-z]{2}$/', $code) || !$name) {
            header('Location: /' . $lang . '/admin/setting');
            exit;
        }

        $exists = \R::findOne('adminlang', ' code = :c ', [':c' => $code]);
        if (!$exists) {
            $b            = \R::dispense('adminlang');
            $b->code       = $code;
            $b->name       = $name;
            $b->is_default = 0;
            \R::store($b);
        }

        header('Location: /' . $lang . '/admin/setting');
        exit;
    }

    // POST /admin/setting/archiveuilang — deactivate (archive) an admin UI language
    public function archiveuilang(mixed $req, mixed $res): void
    {
        $this->requireAdmin($res);
        $code = strtolower(trim($_POST['code'] ?? ''));
        $lang = Seven::app()->router->getLanguage();

        if ($code && $code !== 'en') {
            $row = \R::findOne('adminlang', ' code = :c ', [':c' => $code]);
            if ($row) {
                $row->is_active = 0;
                \R::store($row);
            }
        }

        header('Location: /' . $lang . '/admin/setting');
        exit;
    }

    // POST /admin/setting/restoreuilang — reactivate an archived admin UI language
    public function restoreuilang(mixed $req, mixed $res): void
    {
        $this->requireAdmin($res);
        $code = strtolower(trim($_POST['code'] ?? ''));
        $lang = Seven::app()->router->getLanguage();

        if ($code) {
            $row = \R::findOne('adminlang', ' code = :c ', [':c' => $code]);
            if ($row) {
                $row->is_active = 1;
                \R::store($row);
            }
        }

        header('Location: /' . $lang . '/admin/setting');
        exit;
    }
}
