<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class LanguageAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Languages');
        $rows     = Language::getActive();
        $archived = array_values(array_filter(
            DB::findAll('language', ' 1 ORDER BY code ASC ') ?: [],
            fn($r) => !$r->is_active
        ));
        $known  = Language::KNOWN;
        $active = array_map(fn($r) => $r->code, $rows);
        return $this->app->view->render('index', compact('rows', 'archived', 'known', 'active'));
    }

    public function store($req, $res, $params)
    {
        $this->requireAdmin($res);
        $code = strtolower(trim($req->getPost('code') ?? ''));
        if (!$code || !preg_match('/^[a-z]{2,5}$/', $code)) {
            Session::setFlash('Invalid language code.');
            $res->redirect('language', 'index', [], 'admin');
        }

        $existing = DB::findOne('language', ' code = :c ', [':c' => $code]);
        if ($existing) {
            // Re-activate if was inactive
            $existing->is_active = 1;
            DB::store($existing);
        } else {
            $known = Language::KNOWN[$code] ?? null;
            $bean = DB::dispense('language');
            $bean->code        = $code;
            $bean->name        = $known['name']   ?? ucfirst($code);
            $bean->native_name = $known['native']  ?? ucfirst($code);
            $bean->flag        = $known['flag']    ?? '🌐';
            $bean->is_active   = 1;
            $bean->is_default  = 0;
            $bean->sort_order  = (int)(DB::getCell('SELECT MAX(sort_order) FROM `language`') ?? 0) + 1;
            $bean->created_at  = date('Y-m-d H:i:s');
            DB::store($bean);

            // Create lang file if missing
            $file = ROOT_DIR . DS . 'lang' . DS . $code . '.php';
            if (!file_exists($file)) {
                $en = include ROOT_DIR . DS . 'lang' . DS . 'en.php';
                file_put_contents($file, "<?php\nreturn " . var_export($en, true) . ";\n");
            }
        }

        Session::setFlash('Language added.');
        $res->redirect('language', 'index', [], 'admin');
    }

    public function setDefault($req, $res, $params)
    {
        $this->requireAdmin($res);
        $code = strtolower(trim($params[0] ?? ''));
        DB::exec('UPDATE `language` SET is_default = 0');
        $row = DB::findOne('language', ' code = :c ', [':c' => $code]);
        if ($row) { $row->is_default = 1; DB::store($row); }
        Session::setFlash("Default language set to «{$code}».");
        $res->redirect('language', 'index', [], 'admin');
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $code = strtolower(trim($params[0] ?? ''));
        $row  = DB::findOne('language', ' code = :c ', [':c' => $code]);
        if ($row && !$row->is_default) {
            $row->is_active = 0;
            DB::store($row);
            Session::setFlash("Language «{$code}» archived.");
        } else {
            Session::setFlash('Cannot archive the default language.');
        }
        $res->redirect('language', 'index', [], 'admin');
    }

    public function restore($req, $res, $params)
    {
        $this->requireAdmin($res);
        $code = strtolower(trim($params[0] ?? ''));
        $row  = DB::findOne('language', ' code = :c ', [':c' => $code]);
        if ($row) {
            $row->is_active = 1;
            DB::store($row);
            Session::setFlash("Language «{$code}» restored.");
        }
        $res->redirect('language', 'index', [], 'admin');
    }
}
