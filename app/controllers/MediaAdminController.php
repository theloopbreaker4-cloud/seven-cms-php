<?php

defined('_SEVEN') or die('No direct script access allowed');

class MediaAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('media', 'nav'));
        $media = DB::getAll('SELECT * FROM media ORDER BY created_at DESC') ?: [];
        return $this->app->view->render('index', compact('media'));
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('media', ' id = :id ', [':id' => $id]);
        if ($row) {
            $file = ROOT_DIR . '/public' . ($row['path'] ?? '');
            if ($file && file_exists($file)) @unlink($file);
            \R::trash($row);
        }
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/media');
        exit;
    }
}
