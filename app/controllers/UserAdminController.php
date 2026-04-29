<?php

defined('_SEVEN') or die('No direct script access allowed');

class UserAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('users', 'nav'));
        $users = DB::getAll('SELECT * FROM user ORDER BY created_at DESC') ?: [];
        return $this->app->view->render('index', compact('users'));
    }

    public function edit($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id = (int)($params[0] ?? 0);
        $model = new User();
        $model->getOne($id);
        if (!$model->id) $res->errorCode(404);
        $this->app->setTitle(AdminLang::t('edit', 'common') . ' — ' . $model->userName);
        return $this->app->view->render('form', compact('model', 'id'));
    }

    public function update($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        Csrf::verify($req->getData() ?? []);
        $id    = (int)($params[0] ?? 0);
        $model = new User();
        $model->getOne($id);
        if (!$model->id) $res->errorCode(404);

        $model->firstName = $req->get('firstName', '');
        $model->lastName  = $req->get('lastName', '');
        $model->userName  = $req->get('userName', '');
        $model->mobile    = $req->get('mobile', '');
        $model->country   = $req->get('country', '');
        $model->role      = in_array($req->get('role'), ['admin', 'user']) ? $req->get('role') : 'user';
        $model->isActive  = isset($_POST['isActive']) ? 1 : 0;
        $model->save($id);
        Csrf::rotate();

        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/user');
        exit;
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id    = (int)($params[0] ?? 0);
        $admin = Auth::getCurrentUser();
        if ($admin && $admin->id === $id) $res->errorCode(403); // can't delete self
        (new User())->remove($id);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/user');
        exit;
    }
}
