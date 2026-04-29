<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ShopController — public storefront pages.
 *
 * Renders thin HTML hosting a Vue 3 SPA that talks to the Shop REST API
 * under /api/v1/shop/*. Vue is loaded from a CDN — no extra Vite config
 * required. To compile it into the main bundle later, move the templates
 * into single-file components.
 */
class ShopController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        if (!class_exists('Product') || !DB::getCell("SHOW TABLES LIKE 'ecom_products'")) {
            return $this->app->view->render('disabled');
        }
        $this->app->setTitle('Shop');
        return $this->app->view->render('index');
    }

    public function product($req, $res, $params)
    {
        $slug = (string)($params['slug'] ?? $params[0] ?? '');
        if ($slug === '') $res->errorCode(404);
        $product = Product::findBySlug($slug);
        if (!$product || !$product->isActive) $res->errorCode(404);

        $lang = $this->app->router->getLanguage();
        $this->app->setTitle($product->t('title', $lang) ?: 'Product');
        return $this->app->view->render('product', ['product' => $product, 'lang' => $lang]);
    }

    public function cart($req, $res, $params)
    {
        $this->app->setTitle('Cart');
        return $this->app->view->render('cart');
    }
}
