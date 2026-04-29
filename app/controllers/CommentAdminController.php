<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class CommentAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('comments', 'nav'));
        $comments = DB::getAll(
            'SELECT c.*, u.user_name FROM comment c
             LEFT JOIN user u ON u.id = c.user_id
             ORDER BY c.created_at DESC LIMIT 200'
        ) ?: [];
        return $this->app->view->render('index', compact('comments'));
    }

    public function approve($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('comment', ' id = :id ', [':id' => $id]);
        if ($row) { $row->is_approved = 1; \R::store($row); }
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/comment');
        exit;
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('comment', ' id = :id ', [':id' => $id]);
        if ($row) \R::trash($row);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/comment');
        exit;
    }
}
