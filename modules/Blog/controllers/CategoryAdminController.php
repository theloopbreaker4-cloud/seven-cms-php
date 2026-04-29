<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class CategoryAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('categories', 'nav'));
        $categories = DB::getAll('SELECT * FROM category ORDER BY sort_order ASC, id ASC') ?: [];
        return $this->app->view->render('index', compact('categories'));
    }

    public function create($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('add', 'common'));
        $model = new Category();
        return $this->app->view->render('form', ['model' => $model, 'action' => 'create', 'id' => null]);
    }

    public function store($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $langs  = $this->app->config['languages'];
        $model  = new Category();
        $model->slug      = $req->get('slug', '');
        $model->type      = $req->get('type', 'post');
        $model->sortOrder = (int)$req->get('sortOrder', 0);
        $name = [];
        foreach ($langs as $l) $name[$l] = $req->get('name_' . $l, '');
        $model->name = json_encode($name, JSON_UNESCAPED_UNICODE);
        $model->save();
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/category');
        exit;
    }

    public function edit($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('category', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);
        $model = new Category($row);
        $this->app->setTitle(AdminLang::t('edit', 'common'));
        return $this->app->view->render('form', compact('model', 'id') + ['action' => 'edit']);
    }

    public function update($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $id   = (int)($params[0] ?? 0);
        $row  = DB::findOne('category', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);
        $langs = $this->app->config['languages'];
        $model = new Category($row);
        $model->slug      = $req->get('slug', '');
        $model->type      = $req->get('type', 'post');
        $model->sortOrder = (int)$req->get('sortOrder', 0);
        $name = [];
        foreach ($langs as $l) $name[$l] = $req->get('name_' . $l, '');
        $model->name = json_encode($name, JSON_UNESCAPED_UNICODE);
        $model->save($id);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/category');
        exit;
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('category', ' id = :id ', [':id' => $id]);
        if ($row) \R::trash($row);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/category');
        exit;
    }
}
