<?php

namespace App\lib\Product\Domain\ValueObjects;

class ShopifyProductId
{
  public string $value;

  public function __construct(string $value)
  {
    $this->value = $value;
    $this->ensureIsValid($value);
  }

  private function ensureIsValid(string $value): void
  {
    if (empty($value)) {
      throw new \InvalidArgumentException('Shopify Product ID cannot be empty');
    }
  }
}
