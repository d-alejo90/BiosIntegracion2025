<?php

namespace App\lib\Product\Application\ProductFindAll;

use App\lib\Product\Domain\IProductRepository;

class ProductFindAll
{
  private $productRepository;

  public function __construct(IProductRepository $productRepository)
  {
    $this->productRepository = $productRepository;
  }

  public function run(): array
  {
    return $this->productRepository->findAll();
  }
}
