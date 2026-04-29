<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class PageAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params) {
        $this->requireAdmin($res);
        $this->app->setTitle('Pages');
        $pages = DB::getAll('SELECT * FROM `page` ORDER BY sort_order ASC, created_at DESC') ?: [];
        Logger::channel('app')->debug('Admin page list loaded', ['count' => count($pages)]);
        return $this->app->view->render('index', compact('pages'));
    }

    public function create($req, $res, $params) {
        $this->requireAdmin($res);
        $this->app->setTitle('New Page');
        return $this->app->view->render('form', ['model' => new Page(), 'action' => 'create']);
    }

    public function store($req, $res, $params) {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data  = $req->getData() ?? [];
        $admin = Auth::getCurrentUser();
        $log   = Logger::channel('app');
        Csrf::verify($data);
        $model = new Page();
        $model->title       = json_encode($this->extractI18n($data, 'title'),    JSON_UNESCAPED_UNICODE);
        $model->content     = json_encode($this->extractI18n($data, 'content'),  JSON_UNESCAPED_UNICODE);
        $model->metaDesc    = json_encode($this->extractI18n($data, 'metaDesc'), JSON_UNESCAPED_UNICODE);
        $model->isPublished = isset($data['isPublished']) ? 1 : 0;
        $id = $model->save();
        if (!$id) { Session::setFlash('Failed to create page.'); $res->redirect('page', 'create'); }
        $slugErrors = Slug::saveForEntity('page', $id, $this->extractI18n($data, 'slug'));
        if ($slugErrors) Session::setFlash('Slug conflict: ' . implode(' ', $slugErrors));
        Csrf::rotate();
        $log->info('Page created', ['id' => $id, 'adminId' => $admin->id ?? null]);
        $res->redirect('page', 'index');
    }

    public function edit($req, $res, $params) {
        $this->requireAdmin($res);
        $id    = (int)($params[0] ?? 0);
        $model = new Page();
        $model->getOne($id);
        $this->app->setTitle('Edit Page');
        return $this->app->view->render('form', ['model' => $model, 'action' => 'edit', 'id' => $id]);
    }

    public function update($req, $res, $params) {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data  = $req->getData() ?? [];
        $admin = Auth::getCurrentUser();
        $log   = Logger::channel('app');
        Csrf::verify($data);
        $id    = (int)($params[0] ?? 0);
        $model = new Page();
        $model->title       = json_encode($this->extractI18n($data, 'title'),    JSON_UNESCAPED_UNICODE);
        $model->content     = json_encode($this->extractI18n($data, 'content'),  JSON_UNESCAPED_UNICODE);
        $model->metaDesc    = json_encode($this->extractI18n($data, 'metaDesc'), JSON_UNESCAPED_UNICODE);
        $model->isPublished = isset($data['isPublished']) ? 1 : 0;
        $model->save($id);
        $slugErrors = Slug::saveForEntity('page', $id, $this->extractI18n($data, 'slug'));
        if ($slugErrors) Session::setFlash('Slug conflict: ' . implode(' ', $slugErrors));
        Csrf::rotate();
        $log->info('Page updated', ['id' => $id, 'adminId' => $admin->id ?? null]);
        $res->redirect('page', 'index');
    }

    public function delete($req, $res, $params) {
        $this->requireAdmin($res);
        $id    = (int)($params[0] ?? 0);
        $admin = Auth::getCurrentUser();
        (new Page())->remove($id);
        Slug::deleteForEntity('page', $id);
        Logger::channel('app')->info('Page deleted', ['id' => $id, 'adminId' => $admin->id ?? null]);
        $res->redirect('page', 'index');
    }
}
