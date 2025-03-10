<?php

namespace App\Repositories;

use App\Models\Product;
use App\Config\Database;
use PDO;

class ProductRepository
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
    public function findAll()
    {
        $query = "SELECT * FROM ctrlCreateProducts";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = Product::fromArray($row);
        }

        return $products;
    }

    /**
     * Obtiene productos por su cia_cod
     */
    public function findByCia($cia_cod)
    {
        $cia_cod = $cia_cod == '232P' ? '20' : $cia_cod; // Para esta tabla el cia_cod 232P se cambia por 20
        $query = "SELECT * FROM ctrlCreateProducts WHERE cia_cod = :cia_cod";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':cia_cod', $cia_cod);
        $stmt->execute();
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = Product::fromArray($row);
        }
        return $products;
    }


    /**
     * Obtiene un producto por su ID.
     */
    public function findById($id)
    {
        $query = "SELECT * FROM ctrlCreateProducts WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $product = Product::fromArray($row);
            return $product;
        }

        return null;
    }

    /**
     * Obtiene un producto por su ID de agrupador.
     */
    public function findByGroupId($group_id)
    {
        echo "Group ID: " . $group_id . "\n";
        $query = "SELECT prod_id as shopify_product_id FROM ctrlCreateProducts WHERE agrupador = :group_id GROUP BY prod_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row['shopify_product_id'];
        }

        return null;
    }

    /**
     * Crea un nuevo producto.
     */
    public function create(Product $product)
    {
        $query = "INSERT INTO ctrlCreateProducts (sku, locacion, nota, audit_date, estado, prod_id, inve_id, vari_id, cia_cod, agrupador) 
                  VALUES (:sku, :locacion, :nota, :audit_date, :estado, :prod_id, :inve_id, :vari_id, :cia_cod, :agrupador)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':sku', $product->sku);
        $stmt->bindParam(':locacion', $product->locacion);
        $stmt->bindParam(':nota', $product->nota);
        $stmt->bindParam(':audit_date', $product->audit_date);
        $stmt->bindParam(':estado', $product->estado);
        $stmt->bindParam(':prod_id', $product->prod_id);
        $stmt->bindParam(':inve_id', $product->inve_id);
        $stmt->bindParam(':vari_id', $product->vari_id);
        $stmt->bindParam(':cia_cod', $product->cia_cod);
        $stmt->bindParam(':agrupador', $product->agrupador);

        return $stmt->execute();
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(Product $product)
    {
        $query = "UPDATE ctrlCreateProducts 
                  SET sku = :sku, locacion = :locacion, nota = :nota, audit_date = :audit_date, estado = :estado, 
                      prod_id = :prod_id, inve_id = :inve_id, vari_id = :vari_id, cia_cod = :cia_cod, agrupador = :agrupador
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':id', $product->id);
        $stmt->bindParam(':sku', $product->sku);
        $stmt->bindParam(':locacion', $product->locacion);
        $stmt->bindParam(':nota', $product->nota);
        $stmt->bindParam(':audit_date', $product->audit_date);
        $stmt->bindParam(':estado', $product->estado);
        $stmt->bindParam(':prod_id', $product->prod_id);
        $stmt->bindParam(':inve_id', $product->inve_id);
        $stmt->bindParam(':vari_id', $product->vari_id);
        $stmt->bindParam(':cia_cod', $product->cia_cod);
        $stmt->bindParam(':agrupador', $product->agrupador);
        return $stmt->execute();
    }

    /**
     * Elimina un producto por su ID.
     */
    public function delete($id)
    {
        $query = "DELETE FROM ctrlCreateProducts WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
