<?php

defined('_SEVEN') or die('No direct script access allowed');

class AuthAdminController extends Controller
{
    protected $model;

    public function __construct($app) {
        parent::__construct($app);
        $this->model = new User();
    }

    public function index($req, $res, $params) {
        $this->app->setTitle(Lang::t('signin', 'auth'));
        if (Auth::isLogin()) $res->redirect('home', 'index');
        $error = count($params) ? Lang::t($params[0], 'validation') : null;
        return $this->app->view->render('index', compact('error'));
    }

    public function login($req, $res, $params) {
        if (!$req->isMethod('POST')) $res->errorCode(405);
        $data    = $req->getData() ?? [];
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $authLog = Logger::channel('auth');
        Csrf::verify($data);
        RateLimit::check('admin_login');

        $login    = $req->get('login');
        $password = $data['password'] ?? '';

        if (!$login || !$password) {
            RateLimit::hit('admin_login');
            $authLog->warn('Admin login with empty fields', ['ip' => $ip]);
            $res->redirect('auth', 'index', ['emptyFields']);
        }

        $tokenValue = $this->model->auth($login, $password);
        if ($tokenValue === false) {
            RateLimit::hit('admin_login');
            $authLog->warn('Admin login failed — wrong credentials', ['login' => $login, 'ip' => $ip]);
            $res->redirect('auth', 'index', ['userNotFound']);
        }

        $user = Auth::getCurrentUser();
        if (!$user || $user->role !== 'admin') {
            RateLimit::hit('admin_login');
            Auth::clearToken();
            $authLog->warn('Admin login denied — insufficient role', [
                'login' => $login,
                'role'  => $user->role ?? 'none',
                'ip'    => $ip,
            ]);
            $res->redirect('auth', 'index', ['accessDenied']);
        }

        RateLimit::clear('admin_login');
        Session::regenerate();
        Csrf::rotate();
        $authLog->info('Admin logged in', ['login' => $login, 'userId' => $user->id, 'ip' => $ip]);
        $res->createCookie('token', $tokenValue);
        $res->redirect('home', 'index');
    }

    public function logout($req, $res, $params) {
        $user    = Auth::getCurrentUser();
        $authLog = Logger::channel('auth');
        if ($user) {
            $authLog->info('Admin logged out', ['userId' => $user->id, 'email' => $user->email]);
        }
        $res->removeCookie('token');
        Auth::clearToken();
        Session::regenerate();
        $res->redirect('auth', 'index');
    }
}
