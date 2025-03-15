<?php

namespace App\lib\Product\Application\ProductFindById;

use App\lib\Product\Domain\Product;
use App\lib\Product\Domain\IProductRepository;
use App\lib\Product\Domain\ProductNotFoundError;
use App\lib\Product\Domain\ValueObjects\ProductId;

class ProductFindById
{
  private IProductRepository $productRepository;
  public function __construct(IProductRepository $productRepository)
  {
    $this->productRepository = $productRepository;
  }

  public function run(string $id): Product
  {
    $product = $this->productRepository->findById(new ProductId($id));
    if (!$product) {
      throw new ProductNotFoundError("Product not found");
    }
    return $product;
  }
}
