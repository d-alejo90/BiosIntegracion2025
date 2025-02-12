<?php

namespace App\Repositories;

use App\Models\ItemSiesa;
use App\Config\Database;
use PDO;

class ItemSiesaRepository
{
    private $db;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Obtiene todos los productos.
     */
    public function findByCia($cia_cod)
    {
        $cia_cod = $cia_cod == '232P' ? '20' : $cia_cod;
        $query = "SELECT 
            vw.sku,
            vw.location,
            vw.body_html as title,
            vw.CODTIENDA as cia_cod,
            vw.presentacion as presentation
        FROM 
            vw_Items_Siesa vw
        LEFT JOIN 
            ctrlCreateProducts ctrl
        ON 
            TRIM(vw.sku) = TRIM(ctrl.sku) AND TRIM(vw.location) = TRIM(ctrl.locacion)
        WHERE 
            ctrl.sku IS NULL
          AND vw.CODTIENDA = :cia_cod
        ORDER BY vw.sku;";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':cia_cod', $cia_cod);

        // Habilitar errores en PDO
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }

        // Verificar si hay resultados
        if ($stmt->rowCount() === 0) {
            echo "No se encontraron resultados.";
        }
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = ItemSiesa::fromArray($row);
        }

        return $products;
    }
}
