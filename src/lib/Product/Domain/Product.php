<?php

namespace App\lib\Product\Domain;

use App\lib\Product\Domain\ValueObjects\AuditDate;
use App\lib\Product\Domain\ValueObjects\CompanyCode;
use App\lib\Product\Domain\ValueObjects\GroupId;
use App\lib\Product\Domain\ValueObjects\LocationId;
use App\lib\Product\Domain\ValueObjects\Note;
use App\lib\Product\Domain\ValueObjects\ProductId;
use App\lib\Product\Domain\ValueObjects\ShopifyInventoryId;
use App\lib\Product\Domain\ValueObjects\ShopifyProductId;
use App\lib\Product\Domain\ValueObjects\ShopifyVariantId;
use App\lib\Product\Domain\ValueObjects\Sku;
use App\lib\Product\Domain\ValueObjects\State;

class Product
{
    public ProductId $id;
    public Sku $sku;
    public LocationId $locationId;
    public Note $note;
    public AuditDate $auditDate;
    public State $state;
    public ShopifyProductId $shopifyProductId;
    public ShopifyInventoryId $shopifyInventoryId;
    public ShopifyVariantId $shopifyVariantId;
    public CompanyCode $companyCode;
    public GroupId $groupId;


    public function __construct(
        ProductId $id,
        Sku $sku,
        LocationId $locationId,
        ?Note $note,
        AuditDate $auditDate,
        ?State $state,
        ShopifyProductId $shopifyProductId,
        ShopifyInventoryId $shopifyInventoryId,
        ShopifyVariantId $shopifyVariantId,
        CompanyCode $companyCode,
        ?GroupId $groupId
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->locationId = $locationId;
        $this->note = $note;
        $this->auditDate = $auditDate;
        $this->state = $state;
        $this->shopifyProductId = $shopifyProductId;
        $this->shopifyInventoryId = $shopifyInventoryId;
        $this->shopifyVariantId = $shopifyVariantId;
        $this->companyCode = $companyCode;
        $this->groupId = $groupId;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            new ProductId($data['id']),
            new Sku($data['sku']),
            new LocationId($data['locacion']),
            $data['note'] ? new Note($data['note']) : null,
            new AuditDate($data['audit_date']),
            $data['state'] ? new State($data['state']) : null,
            new ShopifyProductId($data['shopify_product_id']),
            new ShopifyInventoryId($data['shopify_inventory_id']),
            new ShopifyVariantId($data['shopify_variant_id']),
            new CompanyCode($data['company_code']),
            $data['group_id'] ? new GroupId($data['group_id']) : null
        );
    }
}
