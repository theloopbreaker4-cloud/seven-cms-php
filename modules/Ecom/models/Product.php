<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Product — catalog row, supports physical / digital / service kinds.
 *
 * Pricing/inventory live on the product row by default. When variants exist,
 * each variant overrides its own price+stock; the product row's `base_price`
 * acts as the "starting from" anchor for listings.
 *
 * Multilingual fields (`name`, `short_description`, `description`) follow the
 * project convention: JSON object `{ en: '', ru: '', … }`.
 */
class Product extends Model
{
    public const KIND_PHYSICAL = 'physical';
    public const KIND_DIGITAL  = 'digital';
    public const KIND_SERVICE  = 'service';

    public ?int    $id              = null;
    public string  $slug            = '';
    public string  $kind            = self::KIND_PHYSICAL;
    public string  $name            = '{}';
    public string  $shortDescription= '{}';
    public string  $description     = '{}';
    public string  $images          = '[]';
    public ?int    $categoryId      = null;
    public string  $tags            = '[]';
    public int     $isSubscription  = 0;
    public ?string $billingPeriod   = null;
    public ?int    $billingInterval = null;
    public ?int    $trialDays       = null;
    public int     $basePrice       = 0;
    public ?int    $compareAtPrice  = null;
    public ?string $sku             = null;
    public int     $trackInventory  = 0;
    public int     $stock           = 0;
    public ?int    $weightGrams     = null;
    public string  $taxClass        = 'standard';
    public int     $isActive        = 1;
    public int     $isFeatured      = 0;
    public ?string $publishedAt     = null;
    public ?string $createdAt       = null;
    public ?string $updatedAt       = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public static function findById(int $id): ?Product
    {
        $row = DB::findOne('ecom_products', ' id = :id ', [':id' => $id]);
        return $row ? new self($row) : null;
    }

    public static function findBySlug(string $slug): ?Product
    {
        $row = DB::findOne('ecom_products', ' slug = :s ', [':s' => $slug]);
        return $row ? new self($row) : null;
    }

    public static function listPublic(array $opts = []): array
    {
        $where = ['is_active = 1'];
        $args  = [];
        if (!empty($opts['category_id'])) { $where[] = 'category_id = :c'; $args[':c'] = (int)$opts['category_id']; }
        if (!empty($opts['kind']))        { $where[] = 'kind = :k';        $args[':k'] = (string)$opts['kind']; }
        if (!empty($opts['featured']))    { $where[] = 'is_featured = 1'; }
        if (!empty($opts['q'])) {
            $where[] = '(slug LIKE :q OR JSON_SEARCH(name, "one", :q) IS NOT NULL)';
            $args[':q'] = '%' . $opts['q'] . '%';
        }
        $limit  = (int)($opts['limit']  ?? 50);
        $offset = (int)($opts['offset'] ?? 0);
        $sql = 'SELECT * FROM ecom_products WHERE ' . implode(' AND ', $where)
            . ' ORDER BY is_featured DESC, id DESC LIMIT ' . max(1, min(200, $limit))
            . ' OFFSET ' . max(0, $offset);
        return DB::getAll($sql, $args) ?: [];
    }

    /** Return all variants of this product. */
    public function variants(): array
    {
        if (!$this->id) return [];
        return DB::getAll(
            'SELECT * FROM ecom_product_variants WHERE product_id = :p ORDER BY sort_order, id',
            [':p' => $this->id]
        ) ?: [];
    }

    /** Return digital assets attached to this product. */
    public function digitalAssets(): array
    {
        if (!$this->id) return [];
        return DB::getAll(
            'SELECT * FROM ecom_digital_assets WHERE product_id = :p',
            [':p' => $this->id]
        ) ?: [];
    }

    public function isPhysical(): bool      { return $this->kind === self::KIND_PHYSICAL; }
    public function isDigital(): bool       { return $this->kind === self::KIND_DIGITAL; }
    public function isServiceKind(): bool   { return $this->kind === self::KIND_SERVICE; }
    public function isRecurring(): bool     { return $this->isServiceKind() && (bool)$this->isSubscription; }

    public function pickI18n(string $field, string $locale = 'en'): string
    {
        $arr = json_decode($this->{$field} ?: '{}', true);
        if (!is_array($arr)) return '';
        return (string)($arr[$locale] ?? $arr['en'] ?? array_values($arr)[0] ?? '');
    }

    public function toArray(string $locale = 'en'): array
    {
        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'kind'             => $this->kind,
            'name'             => $this->pickI18n('name', $locale),
            'shortDescription' => $this->pickI18n('shortDescription', $locale),
            'description'      => $this->pickI18n('description', $locale),
            'images'           => json_decode($this->images ?: '[]', true) ?: [],
            'categoryId'       => $this->categoryId,
            'tags'             => json_decode($this->tags ?: '[]', true) ?: [],
            'isSubscription'   => (bool)$this->isSubscription,
            'billingPeriod'    => $this->billingPeriod,
            'billingInterval'  => $this->billingInterval,
            'trialDays'        => $this->trialDays,
            'basePrice'        => (int)$this->basePrice,
            'compareAtPrice'   => $this->compareAtPrice,
            'sku'              => $this->sku,
            'trackInventory'   => (bool)$this->trackInventory,
            'stock'            => (int)$this->stock,
            'weightGrams'      => $this->weightGrams,
            'taxClass'         => $this->taxClass,
            'isActive'         => (bool)$this->isActive,
            'isFeatured'       => (bool)$this->isFeatured,
            'publishedAt'      => $this->publishedAt,
        ];
    }

    public static function slugify(string $value): string
    {
        $slug = preg_replace('~[^\pL\d]+~u', '-', $value);
        $slug = trim((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$slug), '-');
        return strtolower(preg_replace('~[^-a-z0-9]+~i', '', (string)$slug)) ?: 'product-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }
}
