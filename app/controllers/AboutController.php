<?php

defined('_SEVEN') or die('No direct script access allowed');

class AboutController extends Controller
{
    public function __construct($app) {
        parent::__construct($app);
    }

    /** GET /{lang}/about — Vue SPA page */
    public function index($req, $res, $params) {
        $this->app->setTitle(Lang::t('title', 'about'));
        return $this->viewData([
            'version' => Seven::VERSION,
        ]);
    }
}
