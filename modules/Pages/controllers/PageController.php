<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class PageController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function show($req, $res, $params) {
        $slug = $this->app->router->getSlug();
        if (!$slug) $res->errorCode(404);
        $lang = $this->app->router->getLanguage();
        $page = new Page();
        if (!$page->findBySlug($slug, $lang)) $res->errorCode(404);
        $this->app->setTitle($page->t('title', $lang));
        return $this->viewData(['page' => $page->toArray($lang)]);
    }
}
