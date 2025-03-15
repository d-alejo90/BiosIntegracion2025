<?php

namespace App\lib\Product\Application\ProductUpdate;

use App\lib\Product\Domain\Product;
use App\lib\Product\Domain\IProductRepository;
use App\lib\Product\Domain\ValueObjects\ProductId;
use App\lib\Product\Domain\ValueObjects\Sku;
use App\lib\Product\Domain\ValueObjects\LocationId;
use App\lib\Product\Domain\ValueObjects\Note;
use App\lib\Product\Domain\ValueObjects\AuditDate;
use App\lib\Product\Domain\ValueObjects\State;
use App\lib\Product\Domain\ValueObjects\ShopifyProductId;
use App\lib\Product\Domain\ValueObjects\ShopifyInventoryId;
use App\lib\Product\Domain\ValueObjects\ShopifyVariantId;
use App\lib\Product\Domain\ValueObjects\CompanyCode;
use App\lib\Product\Domain\ValueObjects\GroupId;

final class ProductCreate
{
  private readonly IProductRepository $productRepository;
  public function __construct(IProductRepository $productRepository)
  {
    $this->productRepository = $productRepository;
  }

  public function run(
    string $productId,
    string $sku,
    string $locationId,
    ?string $note,
    string $auditDate,
    ?string $state,
    string $shopifyProductId,
    string $shopifyInventoryId,
    string $shopifyVariantId,
    string $companyCode,
    ?string $groupId
  ): void {
    $product = new Product(
      new ProductId($productId),
      new Sku($sku),
      new LocationId($locationId),
      new Note($note),
      new AuditDate($auditDate),
      new State($state),
      new ShopifyProductId($shopifyProductId),
      new ShopifyInventoryId($shopifyInventoryId),
      new ShopifyVariantId($shopifyVariantId),
      new CompanyCode($companyCode),
      new GroupId($groupId)
    );
    $this->productRepository->update($product);
  }
}
