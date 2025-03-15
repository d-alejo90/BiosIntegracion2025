<?php

namespace App\lib\Product\Domain\ValueObjects;

class ProductId
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
      throw new \InvalidArgumentException('Product id cannot be empty');
    }
  }
}
