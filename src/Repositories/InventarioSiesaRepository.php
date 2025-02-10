<?php

namespace App\Repositories;

use App\Config\Database;
use App\Models\Inventario;
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
     * Obtiene los datos combinados etre product e vw_Inventario_Siesa.
     */
    public function findInventoryBySkuListAndCia($skuList, $ciacod)
    {
        // Corregir la asignación de ciacod
        $ciacod = ($ciacod == '232P') ? '20' : $ciacod;

        // Generar los placeholders (?,?,?)
        $placeholders = implode(',', array_fill(0, count($skuList), '?'));

        $query = "SELECT
          TRIM(ctrlCreateProducts.sku) as sku, 
          ctrlCreateProducts.locacion as location, 
          ctrlCreateProducts.prod_id as product_id, 
          ctrlCreateProducts.inve_id as inventory_id,
          ctrlCreateProducts.vari_id as variant_id, 
          vw_Inventario_Siesa.Cod_Compañia as company_code, 
          vw_Inventario_Siesa.available as available_qty_siesa
        FROM
          dbo.ctrlCreateProducts
        INNER JOIN
          dbo.vw_Inventario_Siesa
        ON
          TRIM(ctrlCreateProducts.sku) = TRIM(vw_Inventario_Siesa.Sku) 
          AND ctrlCreateProducts.locacion = vw_Inventario_Siesa.location
        WHERE
          TRIM(vw_Inventario_Siesa.Sku) IN ($placeholders) 
          AND vw_Inventario_Siesa.CODTIENDA = ?
        ORDER BY sku DESC";

        $stmt = $this->db->prepare($query);
        $params = array_merge($skuList, [$ciacod]);

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

        $inventarios = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inventarios[] = Inventario::fromArray($row);
        }

        return $inventarios;
    }
}
