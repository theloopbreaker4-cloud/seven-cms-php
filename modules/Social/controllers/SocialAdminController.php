<?php

defined('_SEVEN') or die('No direct script access allowed');

class SocialAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Social Links');
        $links = (new SocialLink())->getAll();
        return $this->app->view->render('index', compact('links'));
    }

    public function create($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('New Social Link');
        return $this->app->view->render('form', ['model' => new SocialLink(), 'action' => 'create']);
    }

    public function store($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data = $req->getData() ?? [];
        Csrf::verify($data);
        $sl            = new SocialLink();
        $sl->platform  = strtolower(trim($req->get('platform')));
        $sl->url       = trim($req->get('url'));
        $sl->label     = trim($req->get('label'));
        $sl->sortOrder = (int)($data['sortOrder'] ?? 0);
        $sl->isActive  = isset($data['isActive']) ? 1 : 0;
        $id = $sl->save();
        Csrf::rotate();
        Logger::channel('app')->info('Social link created', ['id' => $id]);
        $res->redirect('social', 'index');
    }

    public function edit($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id    = (int)($params[0] ?? 0);
        $model = new SocialLink();
        $model->getOne($id);
        if (!$model->id) $res->errorCode(404);
        $this->app->setTitle('Edit Social Link');
        return $this->app->view->render('form', ['model' => $model, 'action' => 'edit', 'id' => $id]);
    }

    public function update($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data = $req->getData() ?? [];
        Csrf::verify($data);
        $id   = (int)($params[0] ?? 0);
        $sl   = new SocialLink();
        $sl->getOne($id);
        if (!$sl->id) $res->errorCode(404);
        $sl->platform  = strtolower(trim($req->get('platform')));
        $sl->url       = trim($req->get('url'));
        $sl->label     = trim($req->get('label'));
        $sl->sortOrder = (int)($data['sortOrder'] ?? 0);
        $sl->isActive  = isset($data['isActive']) ? 1 : 0;
        $sl->save($id);
        Csrf::rotate();
        Logger::channel('app')->info('Social link updated', ['id' => $id]);
        $res->redirect('social', 'index');
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id = (int)($params[0] ?? 0);
        (new SocialLink())->remove($id);
        Logger::channel('app')->info('Social link deleted', ['id' => $id]);
        $res->redirect('social', 'index');
    }
}
