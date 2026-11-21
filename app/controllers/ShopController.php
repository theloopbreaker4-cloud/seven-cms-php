<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ShopController — public storefront pages (catalog, product, cart + checkout).
 *
 * The pages are part of the Vue site bundle (src/site/pages/Shop*.vue) and talk to the Shop REST
 * API under /api/v1/shop/*; this controller only checks that the shop is enabled and hands over
 * to the SPA. (They used to load Vue from a CDN with in-DOM templates, which the CSP blocks.)
 */
class ShopController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    private function enabled(): bool
    {
        return class_exists('Product') && (bool)DB::getCell("SHOW TABLES LIKE 'ecom_products'");
    }

    public function index($req, $res, $params)
    {
        if (!$this->enabled()) return $this->app->view->render('disabled');
        $this->app->setTitle('Shop');
        return $this->viewData([]);
    }

    public function product($req, $res, $params)
    {
        if (!$this->enabled()) return $this->app->view->render('disabled');
        $slug = (string)($params['slug'] ?? $params[0] ?? '');
        $product = $slug !== '' ? Product::findBySlug($slug) : null;
        if (!$product || !$product->isActive) $res->errorCode(404);
        $name = json_decode((string)$product->name, true);
        $lang = $this->app->router->getLanguage();
        $this->app->setTitle(is_array($name) ? ($name[$lang] ?? $name['en'] ?? 'Product') : 'Product');
        return $this->viewData([]);
    }

    public function cart($req, $res, $params)
    {
        if (!$this->enabled()) return $this->app->view->render('disabled');
        $this->app->setTitle('Cart');
        return $this->viewData([]);
    }
}
