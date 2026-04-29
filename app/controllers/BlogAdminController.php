<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class BlogAdminController extends Controller
{
    public function __construct($app) {
        parent::__construct($app);
    }

    public function index($req, $res, $params) {
        $this->requireAdmin($res);
        $this->app->setTitle('Blog Posts');
        $posts = DB::getAll('SELECT * FROM `post` ORDER BY created_at DESC LIMIT 200') ?: [];
        Logger::channel('app')->debug('Admin blog list loaded', ['count' => count($posts)]);
        return $this->app->view->render('index', compact('posts'));
    }

    public function create($req, $res, $params) {
        $this->requireAdmin($res);
        $this->app->setTitle('New Post');
        return $this->app->view->render('form', ['model' => new Post(), 'action' => 'create']);
    }

    public function store($req, $res, $params) {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data  = $req->getData() ?? [];
        $admin = Auth::getCurrentUser();
        $log   = Logger::channel('app');
        Csrf::verify($data);
        $model = new Post();
        $model->title       = json_encode($this->extractI18n($data, 'title'),   JSON_UNESCAPED_UNICODE);
        $model->excerpt     = json_encode($this->extractI18n($data, 'excerpt'), JSON_UNESCAPED_UNICODE);
        $model->content     = json_encode($this->extractI18n($data, 'content'), JSON_UNESCAPED_UNICODE);
        $model->coverImage  = $req->get('coverImage');
        $model->isPublished = isset($data['isPublished']) ? 1 : 0;
        $id = $model->save();
        if (!$id) {
            Session::setFlash('Failed to create post.');
            $res->redirect('blog', 'create');
        }
        $slugErrors = Slug::saveForEntity('post', $id, $this->extractI18n($data, 'slug'));
        if ($slugErrors) {
            Session::setFlash('Slug conflict: ' . implode(' ', $slugErrors));
        }
        Csrf::rotate();
        $log->info('Post created', ['id' => $id, 'adminId' => $admin->id ?? null]);
        $res->redirect('blog', 'index');
    }

    public function edit($req, $res, $params) {
        $this->requireAdmin($res);
        $id    = (int)($params[0] ?? 0);
        $model = new Post();
        $model->getOne($id);
        $this->app->setTitle('Edit Post');
        Logger::channel('app')->debug('Admin post edit loaded', ['id' => $id]);
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
        $model = new Post();
        $model->title       = json_encode($this->extractI18n($data, 'title'),   JSON_UNESCAPED_UNICODE);
        $model->excerpt     = json_encode($this->extractI18n($data, 'excerpt'), JSON_UNESCAPED_UNICODE);
        $model->content     = json_encode($this->extractI18n($data, 'content'), JSON_UNESCAPED_UNICODE);
        $model->coverImage  = $req->get('coverImage');
        $model->isPublished = isset($data['isPublished']) ? 1 : 0;
        $model->save($id);
        $slugErrors = Slug::saveForEntity('post', $id, $this->extractI18n($data, 'slug'));
        if ($slugErrors) {
            Session::setFlash('Slug conflict: ' . implode(' ', $slugErrors));
        }
        Csrf::rotate();
        $log->info('Post updated', ['id' => $id, 'adminId' => $admin->id ?? null]);
        $res->redirect('blog', 'index');
    }

    public function delete($req, $res, $params) {
        $this->requireAdmin($res);
        $id    = (int)($params[0] ?? 0);
        $admin = Auth::getCurrentUser();
        (new Post())->remove($id);
        Slug::deleteForEntity('post', $id);
        Logger::channel('app')->info('Post deleted', ['id' => $id, 'adminId' => $admin->id ?? null]);
        $res->redirect('blog', 'index');
    }
}
