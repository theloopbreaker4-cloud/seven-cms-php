<?php

defined('_SEVEN') or die('No direct script access allowed');

class ThemeAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('themes', 'nav'));

        $themes  = $this->scanThemes();
        $active  = Setting::get('theme_palette', 'default');

        return $this->app->view->render('index', compact('themes', 'active'));
    }

    public function activate($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $palette = trim($req->get('palette', ''));
        $themes  = $this->scanThemes();

        if ($palette === '' || !isset($themes[$palette])) $res->errorCode(422);

        Setting::set('theme_palette', $palette);
        $this->copyThemeSvgs($palette);

        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/theme');
        exit;
    }

    private function copyThemeSvgs(string $palette): void
    {
        $themeDir = dirname(__DIR__, 2) . '/src/themes/' . $palette . '/svg';
        $publicDir = dirname(__DIR__, 2) . '/public';
        foreach (['brand', 'favicon'] as $name) {
            $src = $themeDir . '/' . $name . '.svg';
            if (file_exists($src)) {
                copy($src, $publicDir . '/' . $name . '.svg');
            }
        }
    }

    private function scanThemes(): array
    {
        $dir    = dirname(__DIR__, 2) . '/src/themes';
        $themes = [];

        if (!is_dir($dir)) return $themes;

        foreach (scandir($dir) as $entry) {
            if ($entry[0] === '.') continue;
            if (!is_dir($dir . '/' . $entry)) continue;

            $metaFile = $dir . '/' . $entry . '/theme.json';
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true) ?: [];
            } else {
                $meta = [];
            }

            $themes[$entry] = [
                'slug'     => $entry,
                'name'     => $meta['name']     ?? ucfirst($entry),
                'author'   => $meta['author']   ?? '',
                'desc'     => $meta['desc']      ?? '',
                'hasDark'  => file_exists($dir . '/' . $entry . '/_dark.scss'),
                'hasLight' => file_exists($dir . '/' . $entry . '/_light.scss'),
                'preview'  => $meta['preview']  ?? [],
            ];
        }

        return $themes;
    }
}
