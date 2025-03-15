<?php

namespace App\lib\Product\Domain\ValueObjects;

class ShopifyInventoryId
{
  public string $value;

  public function __construct(string $value = null)
  {
    $this->value = $value;
    $this->ensureIsValid($value);
  }

  private function ensureIsValid(string $value): void
  {
    if (empty($value)) {
      throw new \InvalidArgumentException('Shopify Inventory ID cannot be empty');
    }
  }
}
