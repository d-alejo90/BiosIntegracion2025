<?php

namespace App\lib\Product\Domain\ValueObjects;

class AuditDate
{
  public string $value;

  public function __construct(string $value)
  {
    $this->value = $value;
    $this->ensureIsValid($value);
  }


  private function ensureIsValid(?string $value): void
  {
    if (strtotime($value)) {
      throw new \InvalidArgumentException('Invalid audit date format');
    }
    if (!\DateTime::createFromFormat('Y-m-d H:i:s', $value)) {
      throw new \InvalidArgumentException('Invalid audit date format. Expected format: Y-m-d H:i:s');
    }
  }
}
