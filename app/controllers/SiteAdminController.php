<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * SiteAdminController — manage tenants/sites and their host names.
 *
 *   GET  /admin/sites                  list sites + hosts
 *   GET  /admin/sites/edit/:id         editor (settings + hosts)
 *   POST /admin/sites/store            create
 *   POST /admin/sites/update/:id       save
 *   POST /admin/sites/host/add/:id     add host alias
 *   POST /admin/sites/host/remove/:id  remove host (param is host id)
 */
class SiteAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('sites.view', $res);
        $this->app->setTitle('Sites');

        $sites = DB::getAll('SELECT * FROM sites ORDER BY id ASC') ?: [];
        $hosts = DB::getAll('SELECT * FROM site_hosts ORDER BY site_id, is_primary DESC, host ASC') ?: [];
        $hostsBySite = [];
        foreach ($hosts as $h) $hostsBySite[(int)$h['site_id']][] = $h;

        return $this->app->view->render('sites/index', compact('sites', 'hostsBySite'));
    }

    public function edit($req, $res, $params)
    {
        $this->requirePerm('sites.manage', $res);
        $id = (int)($params[0] ?? 0);
        $site = DB::findOne('sites', ' id = :id ', [':id' => $id]);
        if (!$site) $res->errorCode(404);

        $hosts = DB::getAll('SELECT * FROM site_hosts WHERE site_id = :s ORDER BY is_primary DESC, host ASC',
            [':s' => $id]) ?: [];
        $this->app->setTitle('Edit site: ' . $site['name']);
        return $this->app->view->render('sites/edit', compact('site', 'hosts'));
    }

    public function store($req, $res, $params)
    {
        $this->requirePerm('sites.manage', $res);
        $slug = $this->slugify((string)($_POST['slug'] ?? $_POST['name'] ?? ''));
        DB::execute(
            'INSERT INTO sites (slug, name, theme, default_locale, settings, is_active)
             VALUES (:s, :n, :t, :l, "{}", 1)',
            [
                ':s' => $slug,
                ':n' => trim((string)($_POST['name'] ?? '')),
                ':t' => (string)($_POST['theme'] ?? '') ?: null,
                ':l' => (string)($_POST['default_locale'] ?? 'en'),
            ]
        );
        ActivityLog::log('sites.create', 'sites', (int)DB::lastInsertId());
        $this->back();
    }

    public function update($req, $res, $params)
    {
        $this->requirePerm('sites.manage', $res);
        $id = (int)($params[0] ?? 0);
        DB::execute(
            'UPDATE sites SET name = :n, theme = :t, default_locale = :l, is_active = :a WHERE id = :id',
            [
                ':n'  => trim((string)($_POST['name'] ?? '')),
                ':t'  => (string)($_POST['theme'] ?? '') ?: null,
                ':l'  => (string)($_POST['default_locale'] ?? 'en'),
                ':a'  => isset($_POST['is_active']) ? 1 : 0,
                ':id' => $id,
            ]
        );
        ActivityLog::log('sites.update', 'sites', $id);
        $this->backTo('/admin/sites/edit/' . $id);
    }

    public function hostAdd($req, $res, $params)
    {
        $this->requirePerm('sites.manage', $res);
        $siteId = (int)($params[0] ?? 0);
        $host   = strtolower(trim((string)($_POST['host'] ?? '')));
        if ($host === '') $this->backTo('/admin/sites/edit/' . $siteId);
        DB::execute(
            'INSERT IGNORE INTO site_hosts (site_id, host, is_primary)
             VALUES (:s, :h, :p)',
            [':s' => $siteId, ':h' => $host, ':p' => !empty($_POST['is_primary']) ? 1 : 0]
        );
        ActivityLog::log('sites.host.add', 'sites', $siteId, "Host {$host}");
        $this->backTo('/admin/sites/edit/' . $siteId);
    }

    public function hostRemove($req, $res, $params)
    {
        $this->requirePerm('sites.manage', $res);
        $hostId = (int)($params[0] ?? 0);
        $row = DB::findOne('site_hosts', ' id = :id ', [':id' => $hostId]);
        if ($row) DB::execute('DELETE FROM site_hosts WHERE id = :id', [':id' => $hostId]);
        ActivityLog::log('sites.host.remove', 'sites', (int)($row['site_id'] ?? 0));
        $this->backTo('/admin/sites/edit/' . (int)($row['site_id'] ?? 0));
    }

    private function requirePerm(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }

    private function slugify(string $value): string
    {
        $slug = preg_replace('~[^\pL\d]+~u', '-', $value);
        $slug = trim((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$slug), '-');
        return strtolower(preg_replace('~[^-a-z0-9_]+~i', '', (string)$slug)) ?: 'site-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }

    private function back(): void { $this->backTo('/admin/sites'); }
    private function backTo(string $path): void
    {
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . $path);
        exit;
    }
}
