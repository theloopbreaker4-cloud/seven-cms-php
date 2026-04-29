<?php

defined('_SEVEN') or die('No direct script access allowed');

class HomeController extends Controller
{
    public function __construct($app) {
        parent::__construct($app);
    }

    public function index($req, $res, $params) {
        $this->app->setTitle(Lang::t('home', 'nav'));
        $lang     = $this->app->router->getLanguage();
        $page     = new Page();
        $homePage = null;
        if ($page->findBySlug('home', $lang)) {
            $homePage = [
                'title'   => $page->t('title', $lang),
                'content' => $page->t('content', $lang),
            ];
        }
        return $this->viewData([
            'homePage' => $homePage,
        ]);
    }
}
