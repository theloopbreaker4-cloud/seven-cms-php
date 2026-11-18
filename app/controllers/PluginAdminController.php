<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * PluginAdminController — admin UI for plugin lifecycle (/:lang/admin/plugin).
 * State-changing actions are POST-only so the global admin CSRF guard covers them.
 */
class PluginAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePermission('plugins.view', $res);
        $this->app->setTitle('Plugins');

        $byName = [];
        foreach (PluginManager::all() as $r) $byName[$r['name']] = $r;

        $modules = [];
        foreach (PluginManager::discover() as $name => $class) {
            $row = $byName[$name] ?? null;
            $modules[] = [
                'name'      => $name,
                'class'     => $class,
                'status'    => $row['status']  ?? 'uninstalled',
                'version'   => $row['version'] ?? PluginManager::readVersion($name),
                'installed' => $row !== null && $row['status'] !== 'uninstalled',
                'meta'      => PluginManager::readManifest($name),
            ];
        }
        return $this->app->view->render('index', compact('modules'));
    }

    public function install($req, $res, $params)
    {
        $this->act($req, $res, $params, 'plugins.install', fn($n) => PluginManager::install($n));
    }

    public function uninstall($req, $res, $params)
    {
        $this->act($req, $res, $params, 'plugins.toggle', fn($n) => PluginManager::uninstall($n));
    }

    public function enable($req, $res, $params)
    {
        $this->act($req, $res, $params, 'plugins.toggle', fn($n) => PluginManager::enable($n));
    }

    public function disable($req, $res, $params)
    {
        $this->act($req, $res, $params, 'plugins.toggle', fn($n) => PluginManager::disable($n));
    }

    // ──────────────────────────────────────────────────────────────────

    private function act($req, $res, $params, string $perm, callable $fn): void
    {
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $this->requirePermission($perm, $res);

        $name = trim((string)($params[0] ?? ''));
        if ($name !== '' && isset(PluginManager::discover()[$name])) {
            $r = $fn($name);
            if (empty($r['ok'])) Session::setFlash($r['message'] ?? 'Action failed');
        }

        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/plugin');
        exit;
    }

    private function requirePermission(string $perm, $res): void
    {
        if (!Permission::can($perm)) $res->errorCode(403);
    }
}
