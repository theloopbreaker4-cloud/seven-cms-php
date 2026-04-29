<?php

defined('_SEVEN') or die('No direct script access allowed');

class PageController extends Controller
{
    public function __construct($app) {
        parent::__construct($app);
    }

    /** GET /{lang}/page/{slug} */
    public function show($req, $res, $params) {
        $slug = $this->app->router->getSlug();
        if (!$slug) $res->errorCode(404);
        $this->app->setTitle('Page');
        return $this->viewData(['slug' => $slug]);
    }
}
