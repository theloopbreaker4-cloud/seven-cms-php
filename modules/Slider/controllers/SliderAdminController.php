<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class SliderAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('slider', 'nav'));
        $slides = DB::getAll('SELECT * FROM slide ORDER BY sort_order ASC, id ASC') ?: [];
        return $this->app->view->render('index', compact('slides'));
    }

    public function create($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('add', 'common'));
        return $this->app->view->render('form', ['model' => new Slide(), 'action' => 'create', 'id' => null]);
    }

    public function store($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data  = $req->getData() ?? [];
        Csrf::verify($data);

        $langs = $this->app->config['languages'];
        $model = new Slide();
        $model->title      = json_encode($this->extractI18n($data, 'title'),      JSON_UNESCAPED_UNICODE);
        $model->subtitle   = json_encode($this->extractI18n($data, 'subtitle'),   JSON_UNESCAPED_UNICODE);
        $model->buttonText = json_encode($this->extractI18n($data, 'buttonText'), JSON_UNESCAPED_UNICODE);
        $model->buttonUrl  = trim($req->get('buttonUrl', ''));
        $model->image      = trim($req->get('image', ''));
        $model->overlay    = $req->get('overlay', 'none');
        $model->sortOrder  = (int)$req->get('sortOrder', 0);
        $model->isActive   = isset($data['isActive']) ? 1 : 0;
        $id = $model->save();

        Csrf::rotate();
        Logger::channel('app')->info('Slide created', ['id' => $id]);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/slider');
        exit;
    }

    public function edit($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('slide', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);
        $model = new Slide($row);
        $this->app->setTitle(AdminLang::t('edit', 'common'));
        return $this->app->view->render('form', compact('model', 'id') + ['action' => 'edit']);
    }

    public function update($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data  = $req->getData() ?? [];
        Csrf::verify($data);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('slide', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);

        $model = new Slide($row);
        $model->title      = json_encode($this->extractI18n($data, 'title'),      JSON_UNESCAPED_UNICODE);
        $model->subtitle   = json_encode($this->extractI18n($data, 'subtitle'),   JSON_UNESCAPED_UNICODE);
        $model->buttonText = json_encode($this->extractI18n($data, 'buttonText'), JSON_UNESCAPED_UNICODE);
        $model->buttonUrl  = trim($req->get('buttonUrl', ''));
        $model->image      = trim($req->get('image', ''));
        $model->overlay    = $req->get('overlay', 'none');
        $model->sortOrder  = (int)$req->get('sortOrder', 0);
        $model->isActive   = isset($data['isActive']) ? 1 : 0;
        $model->save($id);

        Csrf::rotate();
        Logger::channel('app')->info('Slide updated', ['id' => $id]);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/slider');
        exit;
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('slide', ' id = :id ', [':id' => $id]);
        if ($row) \R::trash($row);
        Logger::channel('app')->info('Slide deleted', ['id' => $id]);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/slider');
        exit;
    }

    public function reorder($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $ids = array_filter((array)($req->getData()['ids'] ?? []), 'is_numeric');
        foreach (array_values($ids) as $order => $id) {
            DB::exec('UPDATE slide SET sort_order = :o WHERE id = :id', [':o' => $order, ':id' => (int)$id]);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}
