<?php

namespace App\lib\Product\Domain\ValueObjects;

class GroupId
{
  public ?string $value;

  public function __construct(?string $value = null)
  {
    $this->value = $value;
    $this->ensureIsValid($value);
  }

  private function ensureIsValid(?string $value): void
  {
    if ($value !== null && empty($value)) {
      throw new \InvalidArgumentException('Group ID cannot be empty if provided');
    }
  }
}
