<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ModuleAdminController — admin UI for plugin lifecycle.
 *
 * Routes (under /:lang/admin/module):
 *   GET  /                  — list installed + discovered modules
 *   GET  /install/:name     — run install (migrations + onInstall hook)
 *   GET  /uninstall/:name   — fire onUninstall hook (DOES NOT drop tables)
 *   GET  /enable/:name      — enable module
 *   GET  /disable/:name     — disable module
 *
 * Uses the new PluginManager when available, falls back to the legacy Module class.
 */
class ModuleAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePermission('plugins.view', $res);
        $this->app->setTitle('Modules');

        $hasPM     = class_exists('PluginManager');
        $discovered = $hasPM ? PluginManager::discover() : Module::discover();
        $rows       = $hasPM ? PluginManager::all() : [];
        $byName     = [];
        foreach ($rows as $r) $byName[$r['name']] = $r;

        // Build display list
        $modules = [];
        foreach ($discovered as $name => $classOrFile) {
            $row = $byName[$name] ?? null;
            $modules[] = [
                'name'      => $name,
                'class'     => is_string($classOrFile) ? $classOrFile : ($name . 'Module'),
                'status'    => $row['status']  ?? 'uninstalled',
                'version'   => $row['version'] ?? '0.0.0',
                'installed' => $row !== null,
                'meta'      => $this->readManifest($name),
            ];
        }
        return $this->app->view->render('plugins/index', compact('modules'));
    }

    public function install($req, $res, $params)
    {
        $this->requirePermission('plugins.install', $res);
        $name = trim((string)($params[0] ?? ''));
        if ($name && class_exists('PluginManager')) {
            $r = PluginManager::install($name);
            if (!$r['ok']) Session::setFlash($r['message'] ?? 'Install failed');
        }
        $this->back();
    }

    public function uninstall($req, $res, $params)
    {
        $this->requirePermission('plugins.toggle', $res);
        $name = trim((string)($params[0] ?? ''));
        if ($name && class_exists('PluginManager')) PluginManager::uninstall($name);
        $this->back();
    }

    public function enable($req, $res, $params)
    {
        $this->requirePermission('plugins.toggle', $res);
        $name = trim((string)($params[0] ?? ''));
        if (!$name) $this->back();

        if (class_exists('PluginManager')) PluginManager::enable($name);
        elseif (class_exists('Module'))    Module::enable($name);

        $this->back();
    }

    public function disable($req, $res, $params)
    {
        $this->requirePermission('plugins.toggle', $res);
        $name = trim((string)($params[0] ?? ''));
        if (!$name) $this->back();

        if (class_exists('PluginManager')) PluginManager::disable($name);
        elseif (class_exists('Module'))    Module::disable($name);

        $this->back();
    }

    // ──────────────────────────────────────────────────────────────────

    private function readManifest(string $name): array
    {
        $file = ROOT_DIR . "/modules/{$name}/plugin.json";
        if (!is_file($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function requirePermission(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }

    private function back(): void
    {
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/module');
        exit;
    }
}
