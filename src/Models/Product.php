<?php

namespace App\Models;

class Product
{
    public ?string $id;
    public ?string $sku;
    public ?string $locacion;
    public ?string $nota;
    public ?string $audit_date;
    public ?string $estado;
    public ?string $prod_id;
    public ?string $inve_id;
    public ?string $vari_id;
    public ?string $cia_cod;
    public ?string $agrupador;

    public function __construct(
        ?string $id = null,
        ?string $sku = null,
        ?string $locacion = null,
        ?string $nota = null,
        ?string $audit_date = null,
        ?string $estado = null,
        ?string $prod_id = null,
        ?string $inve_id = null,
        ?string $vari_id = null,
        ?string $cia_cod = null,
        ?string $agrupador = null
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->locacion = $locacion;
        $this->nota = $nota;
        $this->audit_date = $audit_date;
        $this->estado = $estado;
        $this->prod_id = $prod_id;
        $this->inve_id = $inve_id;
        $this->vari_id = $vari_id;
        $this->cia_cod = $cia_cod;
        $this->agrupador = $agrupador;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['sku'] ?? null,
            $data['locacion'] ?? null,
            $data['nota'] ?? null,
            isset($data['audit_date']) ? date('Y-m-d H:i:s', strtotime($data['audit_date'])) : null,
            $data['estado'] ?? null,
            $data['prod_id'] ?? null,
            $data['inve_id'] ?? null,
            $data['vari_id'] ?? null,
            $data['cia_cod'] ?? null,
            $data['agrupador'] ?? null
        );
    }
}
