<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class NotificationsAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    /** GET /admin/notifications — full inbox view. */
    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Notifications');

        $userId = (int)Session::get('user_id');
        $items  = Notify::recent($userId, 100);
        $unread = Notify::unreadCount($userId);
        return $this->app->view->render('index', compact('items', 'unread'));
    }

    /** GET /admin/notifications/feed.json — payload for the bell dropdown. */
    public function feed($req, $res, $params)
    {
        $this->requireAdmin($res);
        $userId = (int)Session::get('user_id');

        header('Content-Type: application/json');
        echo json_encode([
            'unread' => Notify::unreadCount($userId),
            'items'  => Notify::recent($userId, 12),
        ]);
        exit;
    }

    public function read($req, $res, $params)
    {
        $this->requireAdmin($res);
        $userId = (int)Session::get('user_id');
        $id = (int)($params['id'] ?? 0);
        if ($id) Notify::markRead($userId, $id);

        // If the entry has a url, redirect there; otherwise back to inbox.
        $row = DB::findOne('notifications', ' id = :id ', [':id' => $id]);
        $target = $row && !empty($row['url']) ? $row['url'] : '/admin/notifications';
        $res->redirect($target);
    }

    public function readAll($req, $res, $params)
    {
        $this->requireAdmin($res);
        Notify::markAllRead((int)Session::get('user_id'));
        if ($req->isMethod('POST')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        $res->redirect('/admin/notifications');
    }
}
