<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class MenuAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('menus', 'nav'));
        $menus = DB::getAll('SELECT * FROM menu ORDER BY id ASC') ?: [];
        return $this->app->view->render('index', compact('menus'));
    }

    public function create($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('add', 'common'));
        $model = new Menu();
        return $this->app->view->render('form', ['model' => $model, 'action' => 'create', 'id' => null]);
    }

    public function store($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $model = new Menu();
        $model->name   = trim($req->get('name', ''));
        $model->handle = trim($req->get('slug', ''));
        if ($model->name === '' || $model->handle === '') $res->errorCode(422);
        $model->save();
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/menu');
        exit;
    }

    public function edit($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('menu', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);
        $model = new Menu($row);
        $this->app->setTitle(AdminLang::t('edit', 'common'));
        return $this->app->view->render('form', compact('model', 'id') + ['action' => 'edit']);
    }

    public function update($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('menu', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);
        $model = new Menu($row);
        $model->name   = trim($req->get('name', ''));
        $model->handle = trim($req->get('slug', ''));
        if ($model->name === '' || $model->handle === '') $res->errorCode(422);
        $model->save($id);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/menu');
        exit;
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('menu', ' id = :id ', [':id' => $id]);
        if ($row) \R::trash($row);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/menu');
        exit;
    }
}
