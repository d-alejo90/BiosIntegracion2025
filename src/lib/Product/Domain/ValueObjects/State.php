<?php

namespace App\lib\Product\Domain\ValueObjects;

class State
{
  public ?string $value;

  public function __construct(?string $value = null)
  {
    $this->value = $value;
    $this->ensureIsValid($value);
  }

  private function ensureIsValid(?string $value): void
  {
    $allowedStates = ['0', '1'];

    if ($value !== null && !in_array($value, $allowedStates)) {
      throw new \InvalidArgumentException('Invalid state');
    }
  }
}
