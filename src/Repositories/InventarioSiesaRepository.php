<?php

namespace App\Repositories;

use App\Models\InventarioSiesa;
use App\Config\Database;
use PDO;

class InventarioSiesaRepository
{
    private $db;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Obtiene todos los registros de la vista.
     */
    public function findAll()
    {
        $query = "SELECT * FROM vw_Inventario_Siesa";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $inventarios = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inventario = new InventarioSiesa();
            $inventario->Cod_Compania = $row['Cod_Compania'];
            $inventario->Compania = $row['Compania'];
            $inventario->available = $row['available'];
            $inventario->Bodega = $row['Bodega'];
            $inventario->Sku = $row['Sku'];
            $inventario->location = $row['location'];
            $inventario->CODTIENDA = $row['CODTIENDA'];
            $inventarios[] = $inventario;
        }

        return $inventarios;
    }

    /**
     * Obtiene registros filtrados por SKU.
     */
    public function findBySku($sku)
    {
        $query = "SELECT * FROM vw_Inventario_Siesa WHERE Sku = :sku";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sku', $sku);
        $stmt->execute();

        $inventarios = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inventario = new InventarioSiesa();
            $inventario->Cod_Compania = $row['Cod_Compania'];
            $inventario->Compania = $row['Compania'];
            $inventario->available = $row['available'];
            $inventario->Bodega = $row['Bodega'];
            $inventario->Sku = $row['Sku'];
            $inventario->location = $row['location'];
            $inventario->CODTIENDA = $row['CODTIENDA'];
            $inventarios[] = $inventario;
        }

        return $inventarios;
    }

    /**
     * Obtiene registros filtrados por Código de Compañía.
     */
    public function findByCodCompania($codCompania)
    {
        $query = "SELECT * FROM vw_Inventario_Siesa WHERE Cod_Compania = :codCompania";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':codCompania', $codCompania);
        $stmt->execute();

        $inventarios = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inventario = new InventarioSiesa();
            $inventario->Cod_Compania = $row['Cod_Compania'];
            $inventario->Compania = $row['Compania'];
            $inventario->available = $row['available'];
            $inventario->Bodega = $row['Bodega'];
            $inventario->Sku = $row['Sku'];
            $inventario->location = $row['location'];
            $inventario->CODTIENDA = $row['CODTIENDA'];
            $inventarios[] = $inventario;
        }

        return $inventarios;
    }

    /**
     * Obtiene registros filtrados por Bodega.
     */
    public function findByBodega($bodega)
    {
        $query = "SELECT * FROM vw_Inventario_Siesa WHERE Bodega = :bodega";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bodega', $bodega);
        $stmt->execute();

        $inventarios = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inventario = new InventarioSiesa();
            $inventario->Cod_Compania = $row['Cod_Compania'];
            $inventario->Compania = $row['Compania'];
            $inventario->available = $row['available'];
            $inventario->Bodega = $row['Bodega'];
            $inventario->Sku = $row['Sku'];
            $inventario->location = $row['location'];
            $inventario->CODTIENDA = $row['CODTIENDA'];
            $inventarios[] = $inventario;
        }

        return $inventarios;
    }
}
