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

        $userId = $this->currentUserId();
        $items  = Notify::recent($userId, 100);
        $unread = Notify::unreadCount($userId);
        return $this->app->view->render('index', compact('items', 'unread'));
    }

    /** GET /admin/notifications/feed.json — payload for the bell dropdown. */
    public function feed($req, $res, $params)
    {
        $this->requireAdmin($res);
        $userId = $this->currentUserId();

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
        $userId = $this->currentUserId();
        $id = (int)($params[0] ?? 0);
        if ($id) Notify::markRead($userId, $id);

        // Follow the entry's own link if it has one (same-origin only); else the inbox.
        $row    = $id ? DB::findOne('notifications', ' id = :id AND (user_id = :u OR user_id IS NULL) ', [':id' => $id, ':u' => $userId]) : null;
        $lang   = $this->app->router->getLanguage();
        $target = $row && !empty($row['url']) ? (string)$row['url'] : "/{$lang}/admin/notifications";
        $res->redirectUrl($target);
    }

    public function readAll($req, $res, $params)
    {
        $this->requireAdmin($res);
        Notify::markAllRead($this->currentUserId());
        if ($req->isMethod('POST')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        $res->redirectUrl('/' . $this->app->router->getLanguage() . '/admin/notifications');
    }
}
