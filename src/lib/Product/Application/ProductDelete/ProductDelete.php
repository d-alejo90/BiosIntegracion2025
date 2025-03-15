<?php

namespace Product\Application\ProductDelete;

use App\lib\Product\Domain\IProductRepository;
use App\lib\Product\Domain\ValueObjects\ProductId;

class ProductDelete
{
  private $productRepository;

  public function __construct(IProductRepository $productRepository)
  {
    $this->productRepository = $productRepository;
  }

  public function run(string $id): void
  {
    $this->productRepository->delete(new ProductId($id));
  }
}
