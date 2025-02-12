<?php

namespace App\Models;

class ItemSiesa
{
    public ?string $sku;
    public ?string $location;
    public ?string $title;
    public ?string $cia_cod;
    public ?string $presentation;
    public ?string $location_name;

    public function __construct(
        ?string $sku = null,
        ?string $location = null,
        ?string $title = null,
        ?string $cia_cod = null,
        ?string $presentation = null,
        ?string $location_name = null
    ) {
        $this->sku = $sku;
        $this->location = $location;
        $this->title = $title;
        $this->cia_cod = $cia_cod;
        $this->presentation = $presentation;
        $this->location_name = $location_name;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['sku'] ?? null,
            $data['location'] ?? null,
            $data['title'] ?? null,
            $data['cia_cod'] ?? null,
            $data['presentation'] ?? null,
            $data['location_name'] ?? null
        );
    }
}
