<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * RoleAdminController — admin UI for roles and permissions.
 *
 * Routes (under /:lang/admin/roles):
 *   GET  /                    — list roles + their assigned permissions
 *   GET  /create              — new role form
 *   POST /store               — create role
 *   GET  /edit/:id            — edit role + permission matrix
 *   POST /update/:id          — save role name/description + permissions[]
 *   GET  /delete/:id          — delete role (system roles refused)
 *
 *   POST /assign              — body: user_id, role_slugs[] — replace user's roles
 */
class RoleAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePermission('users.view', $res);
        $this->app->setTitle('Roles & Permissions');

        $roles = DB::getAll('SELECT * FROM roles ORDER BY is_system DESC, name ASC') ?: [];
        $perms = DB::getAll('SELECT * FROM permissions ORDER BY module, action') ?: [];
        $matrix = [];
        foreach (DB::getAll(
            'SELECT rp.role_id, p.slug FROM role_permissions rp
              JOIN permissions p ON p.id = rp.permission_id'
        ) ?: [] as $row) {
            $matrix[(int)$row['role_id']][$row['slug']] = true;
        }

        return $this->app->view->render('rbac/index', compact('roles', 'perms', 'matrix'));
    }

    public function create($req, $res, $params)
    {
        $this->requirePermission('users.update', $res);
        $this->app->setTitle('New Role');
        return $this->app->view->render('rbac/edit', ['role' => null, 'assigned' => []]);
    }

    public function store($req, $res, $params)
    {
        $this->requirePermission('users.update', $res);
        $slug = $this->slugify((string)($_POST['slug'] ?? $_POST['name'] ?? ''));
        DB::execute(
            'INSERT INTO roles (slug, name, description, is_system) VALUES (:s, :n, :d, 0)',
            [
                ':s' => $slug,
                ':n' => trim((string)($_POST['name'] ?? '')),
                ':d' => trim((string)($_POST['description'] ?? '')) ?: null,
            ]
        );
        $id = (int)DB::lastInsertId();
        $this->syncPermissions($id, (array)($_POST['permissions'] ?? []));
        ActivityLog::log('rbac.role.create', 'roles', $id, "Created role {$slug}");
        $this->back();
    }

    public function edit($req, $res, $params)
    {
        $this->requirePermission('users.update', $res);
        $id   = (int)($params[0] ?? 0);
        $role = DB::findOne('roles', ' id = :id ', [':id' => $id]);
        if (!$role) $res->errorCode(404);

        $assigned = array_column(DB::getAll(
            'SELECT p.slug FROM role_permissions rp
              JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :r',
            [':r' => $id]
        ) ?: [], 'slug');

        $this->app->setTitle('Edit Role: ' . $role['name']);
        return $this->app->view->render('rbac/edit', compact('role', 'assigned'));
    }

    public function update($req, $res, $params)
    {
        $this->requirePermission('users.update', $res);
        $id = (int)($params[0] ?? 0);

        DB::execute(
            'UPDATE roles SET name = :n, description = :d WHERE id = :id AND is_system = 0',
            [
                ':n'  => trim((string)($_POST['name'] ?? '')),
                ':d'  => trim((string)($_POST['description'] ?? '')) ?: null,
                ':id' => $id,
            ]
        );
        // System roles still allow permission editing.
        $this->syncPermissions($id, (array)($_POST['permissions'] ?? []));
        ActivityLog::log('rbac.role.update', 'roles', $id, 'Updated role permissions');
        $this->back();
    }

    public function delete($req, $res, $params)
    {
        $this->requirePermission('users.update', $res);
        $id = (int)($params[0] ?? 0);
        DB::execute('DELETE FROM roles WHERE id = :id AND is_system = 0', [':id' => $id]);
        ActivityLog::log('rbac.role.delete', 'roles', $id, 'Deleted role');
        $this->back();
    }

    public function assign($req, $res, $params)
    {
        $this->requirePermission('users.update', $res);
        $userId = (int)($_POST['user_id'] ?? 0);
        $slugs  = array_filter((array)($_POST['role_slugs'] ?? []), 'is_string');
        if ($userId > 0) Permission::syncRoles($userId, $slugs);
        $this->back();
    }

    // ──────────────────────────────────────────────────────────────────

    private function syncPermissions(int $roleId, array $permSlugs): void
    {
        DB::execute('DELETE FROM role_permissions WHERE role_id = :r', [':r' => $roleId]);
        if (!$permSlugs) return;
        $placeholders = implode(',', array_fill(0, count($permSlugs), '?'));
        $rows = DB::getAll(
            "SELECT id FROM permissions WHERE slug IN ({$placeholders})",
            array_values($permSlugs)
        ) ?: [];
        foreach ($rows as $r) {
            DB::execute(
                'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)',
                [':r' => $roleId, ':p' => (int)$r['id']]
            );
        }
    }

    private function slugify(string $value): string
    {
        $slug = preg_replace('~[^\pL\d]+~u', '-', $value);
        $slug = trim((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$slug), '-');
        return strtolower(preg_replace('~[^-a-z0-9_]+~i', '', (string)$slug)) ?: 'role-' . substr(bin2hex(random_bytes(4)), 0, 6);
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
        header('Location: /' . $lang . '/admin/roles');
        exit;
    }
}
