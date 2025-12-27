<?php

namespace App\Repositories;

use App\Models\PrecioItemSiesa;
use App\Config\Database;
use PDO;

class PrecioItemSiesaRepository
{
    private $db;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Obtiene registros filtrados por Código de Compañía.
     */
    public function findPricesBySkuListAndCia($skuList, $codCompania, $locationFilter = null)
    {
        // Corregir la asignación de codCompania
        $codCompania = ($codCompania == '232P') ? '20' : $codCompania;

        // Generar los placeholders (?,?,?)
        $placeholders = implode(',', array_fill(0, count($skuList), '?'));

        $query = "SELECT
          pis.Cod_Compañia as cod_compania,
          pis.Compañia as compania,
          pis.precio as precio,
          pis.precio_compare as precio_compare,
          TRIM(pis.sku) as sku,
          pis.id_lista_precios as id_lista_precios,
          pis.bodega as bodega,
          pis.location as location,
          pis.CODTIENDA as cod_tienda
          FROM vw_Precios_Items_Siesa as pis
          WHERE TRIM(pis.sku) IN ($placeholders)
          AND pis.CODTIENDA = ?";

        // Add location filter if provided
        if ($locationFilter !== null) {
            $query .= " AND pis.location = ?";
        }

        $query .= " ORDER BY pis.sku DESC";

        $stmt = $this->db->prepare($query);
        $params = array_merge($skuList, [$codCompania]);

        if ($locationFilter !== null) {
            $params[] = $locationFilter;
        }

        // Habilitar errores en PDO
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $stmt->execute($params);
        } catch (\PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }

        // Verificar si hay resultados
        if ($stmt->rowCount() === 0) {
            echo "No se encontraron resultados.";
        }

        $preciosItems = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $preciosItems[] = PrecioItemSiesa::fromArray($row);
        }

        return $preciosItems;
    }
}
