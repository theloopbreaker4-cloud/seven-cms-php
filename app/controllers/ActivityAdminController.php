<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ActivityAdminController — read-only audit log viewer.
 *
 * Routes:
 *   GET /admin/activity                — paginated list with action/user filter
 *   GET /admin/activity/entity/:type/:id  — log scoped to one entity
 */
class ActivityAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePermission('users.view', $res);
        $this->app->setTitle('Activity Log');

        $where = [];
        $args  = [];
        if (!empty($_GET['user']))   { $where[] = 'user_id = :u';    $args[':u'] = (int)$_GET['user']; }
        if (!empty($_GET['action'])) { $where[] = 'action LIKE :a'; $args[':a'] = $_GET['action'] . '%'; }
        if (!empty($_GET['entity'])) { $where[] = 'entity_type = :e'; $args[':e'] = (string)$_GET['entity']; }

        $sql = 'SELECT a.*, u.email, u.user_name FROM activity_log a
                LEFT JOIN users u ON u.id = a.user_id';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY a.id DESC LIMIT 200';

        $log = DB::getAll($sql, $args) ?: [];
        return $this->app->view->render('activity/index', compact('log'));
    }

    private function requirePermission(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }
}
