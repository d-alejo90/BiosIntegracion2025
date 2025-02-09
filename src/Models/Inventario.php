<?php

namespace App\Models;

class Inventario
{
    public ?string $sku;
    public ?string $location;
    public ?string $product_id;
    public ?string $inventory_id;
    public ?string $variant_id;
    public ?string $company_code;
    public ?float $available_qty_siesa;
    public ?float $available_qty_shopify;


    public function __construct(
        ?string $sku = null,
        ?string $location = null,
        ?string $product_id = null,
        ?string $inventory_id = null,
        ?string $variant_id = null,
        ?string $company_code = null,
        ?float $available_qty_siesa = null,
        ?float $available_qty_shopify = null
    ) {
        $this->sku = $sku;
        $this->location = $location;
        $this->product_id = $product_id;
        $this->inventory_id = $inventory_id;
        $this->variant_id = $variant_id;
        $this->company_code = $company_code;
        $this->available_qty_siesa = $available_qty_siesa;
        $this->available_qty_shopify = $available_qty_shopify;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['sku'],
            $data['location'],
            $data['product_id'],
            $data['inventory_id'],
            $data['variant_id'],
            $data['company_code'],
            $data['available_qty_siesa']
        );
    }
}
