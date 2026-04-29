<?php

defined('_SEVEN') or die('No direct script access allowed');

class BlogController extends Controller
{
    public function __construct($app) {
        parent::__construct($app);
    }

    /** GET /{lang}/blog */
    public function index($req, $res, $params) {
        $this->app->setTitle(Lang::t('title', 'blog'));
        return $this->viewData();
    }

    /** GET /{lang}/blog/{slug} */
    public function show($req, $res, $params) {
        $slug = $this->app->router->getSlug();
        if (!$slug) $res->errorCode(404);
        $this->app->setTitle('Blog');
        return $this->viewData(['slug' => $slug]);
    }
}
