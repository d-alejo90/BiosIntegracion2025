<?php

namespace App\lib\Product\Domain\ValueObjects;

class Note
{
  public ?string $value;

  public function __construct(?string $value = null)
  {
    $this->value = $value;
    $this->ensureIsValid($value);
  }

  private function ensureIsValid(?string $value): void
  {
    if ($value !== null && strlen($value) > 500) {
      throw new \InvalidArgumentException('Note cannot exceed 500 characters');
    }
  }
}
