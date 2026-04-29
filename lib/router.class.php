<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Router — handles URL parsing.
 *
 * URL patterns:
 *   /{lang}/{controller}/{action}/{params...}
 *   /{lang}/page/{slug}
 *   /{lang}/blog/{slug}
 *   /admin/{controller}/{action}
 *   /api/{controller}/{action}
 */
class Router
{
    protected $uri;
    protected $routerName;
    protected $controller;
    protected $action;
    protected $params    = [];
    protected $apiController;
    protected $apiAction;
    protected $apiParams = [];
    protected $methodPrefix;
    protected $language;
    protected $slug;
    protected $config;

    public function __construct($config, $uri) {
        $this->uri    = urldecode(trim($uri, '/'));
        $this->config = $config;

        $this->routerName    = $config['defaultRouter'];
        $this->controller    = $config['defaultController'];
        $this->apiController = $config['defaultApiController'];
        $this->action        = $config['defaultAction'];
        $this->apiAction     = $config['defaultApiAction'];
        $this->language      = $config['defaultLanguage'];
        $this->methodPrefix  = '';

        $path      = explode('?', $this->uri)[0];
        $parts     = array_values(array_filter(explode('/', $path)));

        if (!count($parts)) return;

        $i = 0;

        // 1. Language prefix
        if (isset($parts[$i]) && in_array(strtolower($parts[$i]), $config['languages'])) {
            $this->language = strtolower($parts[$i]);
            $i++;
        }

        if (!isset($parts[$i])) return;

        // 2. Router prefix: admin | api
        $segment = strtolower($parts[$i]);
        if (isset($config['keyRouters'][$segment])) {
            $this->methodPrefix  = $config['keyRouters'][$segment];
            $this->routerName    = $segment;
            $i++;
        }

        if (!isset($parts[$i])) return;

        // 3. Controller
        $seg = strtolower($parts[$i]);
        if ($this->methodPrefix === 'api') {
            $this->apiController = $seg;
        } else {
            $this->controller = $seg;
        }
        $i++;

        // 4. Action or slug
        if (isset($parts[$i])) {
            $seg = $parts[$i];
            if ($this->methodPrefix === 'api') {
                $this->apiAction = strtolower($seg);
            } else {
                // For page/blog on site: next segment is a slug, not an action
                if (empty($this->methodPrefix) && in_array($this->controller, ['page', 'blog'])) {
                    $this->slug   = $seg;
                    $this->action = 'show';
                } else {
                    $this->action = strtolower($seg);
                }
            }
            $i++;
        }

        // 5. Remaining params
        $this->params = array_slice($parts, $i);
    }

    public function setLanguage(string $lang): void { $this->language = $lang; }

    public function getUri()           { return $this->uri; }
    public function getController()    { return $this->controller; }
    public function getAction()        { return $this->action; }
    public function getParams()        { return $this->params; }
    public function getSlug()          { return $this->slug; }
    public function getApiController() { return $this->apiController; }
    public function getApiAction()     { return $this->apiAction; }
    public function getApiParams()     { return $this->apiParams; }
    public function getRouter()        { return $this->routerName; }
    public function getMethodPrefix()  { return $this->methodPrefix; }
    public function getLanguage()      { return $this->language; }
}
