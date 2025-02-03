<?php

namespace App\Repositories;

use App\Models\PrecioItemSiesa;
use App\Config\Database;

class PrecioItemSiesaRepository
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
        $query = "SELECT * FROM vw_Precios_Items_Siesa";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $preciosItems = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $precioItem = new PrecioItemSiesa();
            $precioItem->Cod_Compania = $row['Cod_Compania'];
            $precioItem->Compania = $row['Compania'];
            $precioItem->precio = $row['precio'];
            $precioItem->precio_compare = $row['precio_compare'];
            $precioItem->sku = $row['sku'];
            $precioItem->id_lista_precios = $row['id_lista_precios'];
            $precioItem->bodega = $row['bodega'];
            $precioItem->location = $row['location'];
            $precioItem->CODTIENDA = $row['CODTIENDA'];
            $preciosItems[] = $precioItem;
        }

        return $preciosItems;
    }

    /**
     * Obtiene registros filtrados por SKU.
     */
    public function findBySku($sku)
    {
        $query = "SELECT * FROM vw_Precios_Items_Siesa WHERE sku = :sku";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sku', $sku);
        $stmt->execute();

        $preciosItems = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $precioItem = new PrecioItemSiesa();
            $precioItem->Cod_Compania = $row['Cod_Compania'];
            $precioItem->Compania = $row['Compania'];
            $precioItem->precio = $row['precio'];
            $precioItem->precio_compare = $row['precio_compare'];
            $precioItem->sku = $row['sku'];
            $precioItem->id_lista_precios = $row['id_lista_precios'];
            $precioItem->bodega = $row['bodega'];
            $precioItem->location = $row['location'];
            $precioItem->CODTIENDA = $row['CODTIENDA'];
            $preciosItems[] = $precioItem;
        }

        return $preciosItems;
    }

    /**
     * Obtiene registros filtrados por Código de Compañía.
     */
    public function findByCodCompania($codCompania)
    {
        $query = "SELECT * FROM vw_Precios_Items_Siesa WHERE Cod_Compania = :codCompania";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':codCompania', $codCompania);
        $stmt->execute();

        $preciosItems = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $precioItem = new PrecioItemSiesa();
            $precioItem->Cod_Compania = $row['Cod_Compania'];
            $precioItem->Compania = $row['Compania'];
            $precioItem->precio = $row['precio'];
            $precioItem->precio_compare = $row['precio_compare'];
            $precioItem->sku = $row['sku'];
            $precioItem->id_lista_precios = $row['id_lista_precios'];
            $precioItem->bodega = $row['bodega'];
            $precioItem->location = $row['location'];
            $precioItem->CODTIENDA = $row['CODTIENDA'];
            $preciosItems[] = $precioItem;
        }

        return $preciosItems;
    }
}
