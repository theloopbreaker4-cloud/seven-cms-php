<?php

defined('_SEVEN') or die('No direct script access allowed');

class SetupController extends Controller
{
    private string $lockFile;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->lockFile = ROOT_DIR . DS . 'storage' . DS . 'installed.lock';
    }

    public function index($req, $res, $params)
    {
        if (file_exists($this->lockFile)) {
            header('Location: /' . ($this->app->config['defaultLanguage'] ?? 'en') . '/');
            exit;
        }

        $step   = 1;
        $errors = [];

        print($this->app->view->render('index', compact('step', 'errors')));
        exit;
    }

    public function install($req, $res, $params)
    {
        if (file_exists($this->lockFile)) {
            header('Location: /' . ($this->app->config['defaultLanguage'] ?? 'en') . '/');
            exit;
        }

        if (!$req->isMethod('POST')) $res->errorCode(405);
        Csrf::verify($_POST);

        $errors = [];

        // ── Validate input ────────────────────────────────────────────────
        $siteName  = trim($req->get('site_name', ''));
        $email     = trim(strtolower($req->get('email', '')));
        $username  = trim($req->get('username', ''));
        $password  = $req->get('password', '');
        $password2 = $req->get('password2', '');

        if ($siteName === '')  $errors['site_name'] = 'Site name is required.';
        if ($email === '')     $errors['email']     = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email address.';
        if ($username === '')  $errors['username']  = 'Username is required.';
        elseif (!preg_match('/^[a-z0-9_]{3,32}$/i', $username)) $errors['username'] = 'Username: 3–32 chars, letters/numbers/underscore.';
        if (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
        elseif ($password !== $password2) $errors['password2'] = 'Passwords do not match.';

        if ($errors) {
            $step = 1;
            print($this->app->view->render('index', compact('step', 'errors')));
            exit;
        }

        // ── Seed DB ───────────────────────────────────────────────────────
        try {
            $this->seedLanguages();
            $this->seedAdmin($email, $username, $password);
            $this->seedSettings($siteName, $email);
            $this->seedAdminLangs();
        } catch (Throwable $e) {
            $errors['db'] = 'Database error: ' . $e->getMessage();
            $step = 1;
            print($this->app->view->render('index', compact('step', 'errors')));
            exit;
        }

        // ── Write lock ────────────────────────────────────────────────────
        file_put_contents($this->lockFile, date('Y-m-d H:i:s'));

        $lang = $this->app->config['defaultLanguage'] ?? 'en';
        header('Location: /' . $lang . '/admin');
        exit;
    }

    // ── Seeders ───────────────────────────────────────────────────────────────

    private function seedLanguages(): void
    {
        $langs = [
            ['code'=>'en','name'=>'English',     'native'=>'English',     'flag'=>'🇬🇧','default'=>1],
            ['code'=>'ru','name'=>'Russian',     'native'=>'Русский',     'flag'=>'🇷🇺','default'=>0],
            ['code'=>'ka','name'=>'Georgian',    'native'=>'ქართული',     'flag'=>'🇬🇪','default'=>0],
            ['code'=>'uk','name'=>'Ukrainian',   'native'=>'Українська',  'flag'=>'🇺🇦','default'=>0],
            ['code'=>'az','name'=>'Azerbaijani', 'native'=>'Azərbaycanca','flag'=>'🇦🇿','default'=>0],
            ['code'=>'hy','name'=>'Armenian',    'native'=>'Հայերեն',     'flag'=>'🇦🇲','default'=>0],
        ];
        foreach ($langs as $i => $l) {
            if (\R::findOne('language', ' code = :c ', [':c' => $l['code']])) continue;
            $b = \R::dispense('language');
            $b->code        = $l['code'];
            $b->name        = $l['name'];
            $b->native_name = $l['native'];
            $b->flag        = $l['flag'];
            $b->is_active   = 1;
            $b->is_default  = $l['default'];
            $b->sort_order  = $i;
            $b->created_at  = date('Y-m-d H:i:s');
            \R::store($b);
        }
    }

    private function seedAdmin(string $email, string $username, string $password): void
    {
        if (\R::findOne('user', ' role = "admin" ')) return;
        $b = \R::dispense('user');
        $b->first_name         = 'Admin';
        $b->last_name          = '';
        $b->user_name          = $username;
        $b->email              = $email;
        $b->password           = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $b->avatar             = '';
        $b->mobile             = '';
        $b->country            = '';
        $b->role               = 'admin';
        $b->is_active          = 1;
        $b->provider           = 'local';
        $b->provider_id        = null;
        $b->two_factor_enabled = 0;
        $b->two_factor_secret  = '';
        $b->last_login_at      = null;
        $b->created_at         = date('Y-m-d H:i:s');
        $b->updated_at         = $b->created_at;
        \R::store($b);
    }

    private function seedSettings(string $siteName, string $email): void
    {
        $settings = [
            ['key'=>'site_name',          'value'=>$siteName, 'group'=>'general',  'type'=>'string', 'label'=>'Site Name'],
            ['key'=>'site_tagline',       'value'=>'Modern Multilingual CMS', 'group'=>'general', 'type'=>'string', 'label'=>'Tagline'],
            ['key'=>'site_email',         'value'=>$email,    'group'=>'general',  'type'=>'string', 'label'=>'Contact Email'],
            ['key'=>'posts_per_page',     'value'=>'10',      'group'=>'general',  'type'=>'int',    'label'=>'Posts Per Page'],
            ['key'=>'comments_on',        'value'=>'1',       'group'=>'general',  'type'=>'bool',   'label'=>'Comments Enabled'],
            ['key'=>'registration_on',    'value'=>'1',       'group'=>'general',  'type'=>'bool',   'label'=>'Registration Enabled'],
            ['key'=>'captcha_on_login',   'value'=>'0',       'group'=>'security', 'type'=>'bool',   'label'=>'Captcha on Login'],
            ['key'=>'captcha_on_register','value'=>'1',       'group'=>'security', 'type'=>'bool',   'label'=>'Captcha on Register'],
            ['key'=>'captcha_on_forgot',  'value'=>'1',       'group'=>'security', 'type'=>'bool',   'label'=>'Captcha on Forgot Password'],
        ];
        foreach ($settings as $s) {
            if (\R::findOne('setting', ' `key` = :k ', [':k' => $s['key']])) continue;
            $b = \R::dispense('setting');
            $b->key        = $s['key'];
            $b->value      = $s['value'];
            $b->group      = $s['group'];
            $b->type       = $s['type'];
            $b->label      = $s['label'];
            $b->updated_at = date('Y-m-d H:i:s');
            \R::store($b);
        }
    }

    private function seedAdminLangs(): void
    {
        $langs = [
            ['code'=>'en','name'=>'English',    'is_default'=>1],
            ['code'=>'de','name'=>'Deutsch',    'is_default'=>0],
            ['code'=>'fr','name'=>'Français',   'is_default'=>0],
            ['code'=>'nl','name'=>'Nederlands', 'is_default'=>0],
        ];
        foreach ($langs as $l) {
            if (\R::findOne('adminlang', ' code = :c ', [':c' => $l['code']])) continue;
            $b             = \R::dispense('adminlang');
            $b->code       = $l['code'];
            $b->name       = $l['name'];
            $b->is_default = $l['is_default'];
            \R::store($b);
        }
    }
}
