<?php

namespace App\Repositories;

use App\Models\Product;
use App\Config\Database;

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
            $product = new Product();
            $product->id = $row['id'];
            $product->sku = $row['sku'];
            $product->locacion = $row['locacion'];
            $product->nota = $row['nota'];
            $product->audit_date = $row['audit_date'];
            $product->estado = $row['estado'];
            $product->prod_id = $row['prod_id'];
            $product->inve_id = $row['inve_id'];
            $product->vari_id = $row['vari_id'];
            $product->cia_cod = $row['cia_cod'];
            $products[] = $product;
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
            $product = new Product();
            $product->id = $row['id'];
            $product->sku = $row['sku'];
            $product->locacion = $row['locacion'];
            $product->nota = $row['nota'];
            $product->audit_date = $row['audit_date'];
            $product->estado = $row['estado'];
            $product->prod_id = $row['prod_id'];
            $product->inve_id = $row['inve_id'];
            $product->vari_id = $row['vari_id'];
            $product->cia_cod = $row['cia_cod'];
            return $product;
        }

        return null;
    }

    /**
     * Crea un nuevo producto.
     */
    public function create(Product $product)
    {
        $query = "INSERT INTO ctrlCreateProducts (sku, locacion, nota, audit_date, estado, prod_id, inve_id, vari_id, cia_cod) 
                  VALUES (:sku, :locacion, :nota, :audit_date, :estado, :prod_id, :inve_id, :vari_id, :cia_cod)";
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

        return $stmt->execute();
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(Product $product)
    {
        $query = "UPDATE ctrlCreateProducts 
                  SET sku = :sku, locacion = :locacion, nota = :nota, audit_date = :audit_date, estado = :estado, 
                      prod_id = :prod_id, inve_id = :inve_id, vari_id = :vari_id, cia_cod = :cia_cod 
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
