<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Products admin: list / create / edit / delete + variants management.
 *
 * Routes (admin/ecom/products):
 *   GET  /                    list with filters
 *   GET  /create              new product form
 *   POST /store               persist new product
 *   GET  /edit/:id            edit form
 *   POST /update/:id          save changes
 *   POST /delete/:id          delete
 *   POST /variant/store/:productId    add a variant
 *   POST /variant/update/:variantId   update variant
 *   POST /variant/delete/:variantId   delete variant
 */
class EcomProductsAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('ecom.products.view', $res);
        $this->app->setTitle('Products');

        $kind   = (string)($_GET['kind']   ?? '');
        $search = (string)($_GET['q']      ?? '');
        $where  = []; $args = [];
        if ($kind)   { $where[] = 'kind = :k';        $args[':k'] = $kind; }
        if ($search) { $where[] = '(slug LIKE :q OR JSON_SEARCH(name, "one", :q) IS NOT NULL)'; $args[':q'] = '%' . $search . '%'; }

        $sql = 'SELECT * FROM ecom_products' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 200';
        $products = DB::getAll($sql, $args) ?: [];

        return $this->app->view->render('ecom/products/index', compact('products', 'kind', 'search'));
    }

    public function create($req, $res, $params)
    {
        $this->requirePerm('ecom.products.create', $res);
        $this->app->setTitle('New product');
        return $this->app->view->render('ecom/products/edit', ['product' => null, 'variants' => []]);
    }

    public function store($req, $res, $params)
    {
        $this->requirePerm('ecom.products.create', $res);
        $id = $this->save(null);
        $this->redirectAdmin('ecom/products/edit/' . $id);
    }

    public function edit($req, $res, $params)
    {
        $this->requirePerm('ecom.products.update', $res);
        $id = (int)($params[0] ?? 0);
        $product = Product::findById($id);
        if (!$product) $res->errorCode(404);
        $variants = $product->variants();
        $this->app->setTitle('Edit: ' . ($product->pickI18n('name', 'en') ?: $product->slug));
        return $this->app->view->render('ecom/products/edit', compact('product', 'variants'));
    }

    public function update($req, $res, $params)
    {
        $this->requirePerm('ecom.products.update', $res);
        $id = (int)($params[0] ?? 0);
        $this->save($id);
        $this->redirectAdmin('ecom/products/edit/' . $id);
    }

    public function delete($req, $res, $params)
    {
        $this->requirePerm('ecom.products.delete', $res);
        $id = (int)($params[0] ?? 0);
        DB::execute('DELETE FROM ecom_products WHERE id = :id', [':id' => $id]);
        ActivityLog::log('ecom.product.delete', 'ecom_products', $id);
        $this->redirectAdmin('ecom/products');
    }

    public function variantStore($req, $res, $params)
    {
        $this->requirePerm('ecom.products.update', $res);
        $productId = (int)($params[0] ?? 0);
        $currency  = (string)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.currency"') ?? 'USD');
        DB::execute(
            'INSERT INTO ecom_product_variants (product_id, sku, attributes, price, stock)
             VALUES (:p, :sku, :a, :pr, :st)',
            [
                ':p'   => $productId,
                ':sku' => (string)($_POST['sku'] ?? '') ?: null,
                ':a'   => json_encode((array)($_POST['attributes'] ?? []), JSON_UNESCAPED_UNICODE),
                ':pr'  => Money::fromInput((string)($_POST['price'] ?? '0'), $currency),
                ':st'  => (int)($_POST['stock'] ?? 0),
            ]
        );
        $this->redirectAdmin('ecom/products/edit/' . $productId);
    }

    public function variantUpdate($req, $res, $params)
    {
        $this->requirePerm('ecom.products.update', $res);
        $variantId = (int)($params[0] ?? 0);
        $row = DB::findOne('ecom_product_variants', ' id = :id ', [':id' => $variantId]);
        if (!$row) $res->errorCode(404);

        $currency = (string)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.currency"') ?? 'USD');
        DB::execute(
            'UPDATE ecom_product_variants
                SET sku = :sku, attributes = :a, price = :pr, stock = :st, is_active = :ia
              WHERE id = :id',
            [
                ':sku' => (string)($_POST['sku'] ?? '') ?: null,
                ':a'   => json_encode((array)($_POST['attributes'] ?? []), JSON_UNESCAPED_UNICODE),
                ':pr'  => Money::fromInput((string)($_POST['price'] ?? '0'), $currency),
                ':st'  => (int)($_POST['stock'] ?? 0),
                ':ia'  => !empty($_POST['is_active']) ? 1 : 0,
                ':id'  => $variantId,
            ]
        );
        $this->redirectAdmin('ecom/products/edit/' . (int)$row['product_id']);
    }

    public function variantDelete($req, $res, $params)
    {
        $this->requirePerm('ecom.products.update', $res);
        $variantId = (int)($params[0] ?? 0);
        $row = DB::findOne('ecom_product_variants', ' id = :id ', [':id' => $variantId]);
        if ($row) DB::execute('DELETE FROM ecom_product_variants WHERE id = :id', [':id' => $variantId]);
        $this->redirectAdmin('ecom/products/edit/' . (int)($row['product_id'] ?? 0));
    }

    // ──────────────────────────────────────────────────────────────────

    private function save(?int $id): int
    {
        $currency = (string)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.currency"') ?? 'USD');
        $payload = [
            ':slug'    => Product::slugify((string)($_POST['slug'] ?? $_POST['name_en'] ?? '')),
            ':kind'    => in_array(($_POST['kind'] ?? ''), ['physical','digital','service'], true) ? $_POST['kind'] : 'physical',
            ':name'    => json_encode($this->collectI18n('name'), JSON_UNESCAPED_UNICODE),
            ':short'   => json_encode($this->collectI18n('short_description'), JSON_UNESCAPED_UNICODE),
            ':desc'    => json_encode($this->collectI18n('description'), JSON_UNESCAPED_UNICODE),
            ':images'  => json_encode((array)($_POST['images']  ?? []), JSON_UNESCAPED_SLASHES),
            ':tags'    => json_encode((array)($_POST['tags']    ?? []), JSON_UNESCAPED_UNICODE),
            ':catId'   => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            ':isSub'   => !empty($_POST['is_subscription']) ? 1 : 0,
            ':bp'      => $_POST['billing_period']   ?? null,
            ':bi'      => isset($_POST['billing_interval']) ? (int)$_POST['billing_interval'] : null,
            ':td'      => isset($_POST['trial_days']) && $_POST['trial_days'] !== '' ? (int)$_POST['trial_days'] : null,
            ':base'    => Money::fromInput((string)($_POST['base_price']       ?? '0'), $currency),
            ':comp'    => $_POST['compare_at_price'] !== '' ? Money::fromInput((string)$_POST['compare_at_price'], $currency) : null,
            ':sku'     => (string)($_POST['sku']     ?? '') ?: null,
            ':track'   => !empty($_POST['track_inventory']) ? 1 : 0,
            ':stock'   => (int)($_POST['stock'] ?? 0),
            ':wt'      => $_POST['weight_grams'] !== '' ? (int)$_POST['weight_grams'] : null,
            ':taxC'    => (string)($_POST['tax_class'] ?? 'standard'),
            ':active'  => !empty($_POST['is_active'])   ? 1 : 0,
            ':feat'    => !empty($_POST['is_featured']) ? 1 : 0,
            ':pub'     => !empty($_POST['is_active']) ? date('Y-m-d H:i:s') : null,
        ];

        if ($id === null) {
            DB::execute(
                'INSERT INTO ecom_products
                    (slug, kind, name, short_description, description, images, tags, category_id,
                     is_subscription, billing_period, billing_interval, trial_days,
                     base_price, compare_at_price, sku, track_inventory, stock, weight_grams,
                     tax_class, is_active, is_featured, published_at)
                 VALUES
                    (:slug, :kind, :name, :short, :desc, :images, :tags, :catId,
                     :isSub, :bp, :bi, :td,
                     :base, :comp, :sku, :track, :stock, :wt,
                     :taxC, :active, :feat, :pub)',
                $payload
            );
            $id = (int)DB::lastInsertId();
            ActivityLog::log('ecom.product.create', 'ecom_products', $id);
        } else {
            $payload[':id'] = $id;
            DB::execute(
                'UPDATE ecom_products SET
                    slug = :slug, kind = :kind, name = :name, short_description = :short, description = :desc,
                    images = :images, tags = :tags, category_id = :catId,
                    is_subscription = :isSub, billing_period = :bp, billing_interval = :bi, trial_days = :td,
                    base_price = :base, compare_at_price = :comp, sku = :sku, track_inventory = :track,
                    stock = :stock, weight_grams = :wt, tax_class = :taxC,
                    is_active = :active, is_featured = :feat, published_at = :pub
                  WHERE id = :id',
                $payload
            );
            ActivityLog::log('ecom.product.update', 'ecom_products', $id);
        }
        return $id;
    }

    private function collectI18n(string $field): array
    {
        $out = [];
        foreach (['en','ru','ka','uk','az','hy'] as $lang) {
            $key = $field . '_' . $lang;
            if (isset($_POST[$key])) $out[$lang] = (string)$_POST[$key];
        }
        return $out;
    }

    private function requirePerm(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }

    private function redirectAdmin(string $path): void
    {
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/' . ltrim($path, '/'));
        exit;
    }
}
