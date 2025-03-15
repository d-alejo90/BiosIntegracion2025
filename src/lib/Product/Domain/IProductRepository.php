<?php

namespace App\lib\Product\Domain;

use App\lib\Product\Domain\ValueObjects\CompanyCode;
use App\lib\Product\Domain\ValueObjects\GroupId;
use App\lib\Product\Domain\ValueObjects\ProductId;

interface IProductRepository
{
    public function create(Product $product): void;
    public function update(Product $product): void;
    public function delete(ProductId $id): void;
    public function findAll(): array;
    public function findById(ProductId $id): Product | null;
    public function findShopifyProductIdByGroupId(GroupId $group_id): string | null;
    public function findByCompanyCode(CompanyCode $companyCode): array;
}
