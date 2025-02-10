<?php

namespace App\Models;

class PrecioItemSiesa
{
    public int $cod_compania;
    public string $compania;
    public ?float $precio;
    public string $precio_compare;
    public string $sku;
    public string $id_lista_precios;
    public ?string $bodega;
    public string $location;
    public ?string $cod_tienda;

    public function __construct(
        int $cod_compania,
        string $compania,
        ?float $precio,
        string $precio_compare,
        string $sku,
        string $id_lista_precios,
        ?string $bodega,
        string $location,
        ?string $cod_tienda,
    ) {
        $this->cod_compania = $cod_compania;
        $this->compania = $compania;
        $this->precio = $precio;
        $this->precio_compare = $precio_compare;
        $this->sku = $sku;
        $this->id_lista_precios = $id_lista_precios;
        $this->bodega = $bodega;
        $this->location = $location;
        $this->cod_tienda = $cod_tienda;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['cod_compania'],
            $data['cod_compania'],
            isset($data['precio']) ? (float)$data['precio'] : null,
            $data['precio_compare'],
            $data['sku'],
            $data['id_lista_precios'],
            $data['bodega'] ?? null,
            $data['location'],
            $data['cod_tienda'] ?? null,
        );
    }
}
