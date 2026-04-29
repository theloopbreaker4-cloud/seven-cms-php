<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ProductVariant — overrides product price/stock/weight per attribute combo.
 *
 * `attributes` is a JSON map: { "color": "red", "size": "M" }.
 */
class ProductVariant extends Model
{
    public ?int    $id              = null;
    public ?int    $productId       = null;
    public ?string $sku             = null;
    public string  $attributes      = '{}';
    public int     $price           = 0;
    public ?int    $compareAtPrice  = null;
    public int     $stock           = 0;
    public ?int    $weightGrams     = null;
    public ?int    $imageId         = null;
    public int     $isActive        = 1;
    public int     $sortOrder       = 0;
    public ?string $createdAt       = null;
    public ?string $updatedAt       = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public static function findById(int $id): ?ProductVariant
    {
        $row = DB::findOne('ecom_product_variants', ' id = :id ', [':id' => $id]);
        return $row ? new self($row) : null;
    }

    public function attributesArray(): array
    {
        return json_decode($this->attributes ?: '{}', true) ?: [];
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'productId'       => $this->productId,
            'sku'             => $this->sku,
            'attributes'      => $this->attributesArray(),
            'price'           => (int)$this->price,
            'compareAtPrice'  => $this->compareAtPrice,
            'stock'           => (int)$this->stock,
            'weightGrams'     => $this->weightGrams,
            'imageId'         => $this->imageId,
            'isActive'        => (bool)$this->isActive,
            'sortOrder'       => (int)$this->sortOrder,
        ];
    }
}
