<?php

namespace App\lib\Product\Infrastructure\MsSQLProductRepository;

use App\Config\Database;
use App\lib\Product\Domain\Product;
use App\lib\Product\Domain\IProductRepository;
use App\lib\Product\Domain\ValueObjects\CompanyCode;
use App\lib\Product\Domain\ValueObjects\GroupId;
use App\lib\Product\Domain\ValueObjects\ProductId;
use PDO;

class MsSQLProductRepository implements IProductRepository
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
    public function findAll(): array
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
    public function findByCompanyCode(CompanyCode $companyCode): array
    {
        $cia_cod = $companyCode->value;
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
    public function findById(ProductId $id): Product | null
    {
        $query = "SELECT * FROM ctrlCreateProducts WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id->value);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $product = Product::fromArray($row);
            return $product;
        }

        return null;
    }

    /**
     * Obtiene el shopify_product_id por su ID de agrupador.
     */
    public function findShopifyProductIdByGroupId(GroupId $group_id): string | null
    {
        echo "Group ID: " . $group_id->value . "\n";
        $query = "SELECT prod_id as shopify_product_id FROM ctrlCreateProducts WHERE agrupador = :group_id GROUP BY prod_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':group_id', $group_id->value, PDO::PARAM_STR);
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
    public function create(Product $product): void
    {
        $query = "INSERT INTO ctrlCreateProducts (sku, locacion, nota, audit_date, estado, prod_id, inve_id, vari_id, cia_cod, agrupador) 
                  VALUES (:sku, :locacion, :nota, :audit_date, :estado, :prod_id, :inve_id, :vari_id, :cia_cod, :agrupador)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':sku', $product->sku->value);
        $stmt->bindParam(':locacion', $product->locationId->value);
        $stmt->bindParam(':nota', $product->note->value);
        $stmt->bindParam(':audit_date', $product->auditDate->value);
        $stmt->bindParam(':estado', $product->state->value);
        $stmt->bindParam(':prod_id', $product->shopifyProductId->value);
        $stmt->bindParam(':inve_id', $product->shopifyInventoryId->value);
        $stmt->bindParam(':vari_id', $product->shopifyVariantId->value);
        $stmt->bindParam(':cia_cod', $product->companyCode->value);
        $stmt->bindParam(':agrupador', $product->groupId->value);

        $stmt->execute();
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(Product $product): void
    {
        $query = "UPDATE ctrlCreateProducts 
                  SET sku = :sku, locacion = :locacion, nota = :nota, audit_date = :audit_date, estado = :estado, 
                      prod_id = :prod_id, inve_id = :inve_id, vari_id = :vari_id, cia_cod = :cia_cod, agrupador = :agrupador
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':id', $product->id->value);
        $stmt->bindParam(':sku', $product->sku->value);
        $stmt->bindParam(':locacion', $product->locationId->value);
        $stmt->bindParam(':nota', $product->note->value);
        $stmt->bindParam(':audit_date', $product->auditDate->value);
        $stmt->bindParam(':estado', $product->state->value);
        $stmt->bindParam(':prod_id', $product->shopifyProductId->value);
        $stmt->bindParam(':inve_id', $product->shopifyInventoryId->value);
        $stmt->bindParam(':vari_id', $product->shopifyVariantId->value);
        $stmt->bindParam(':cia_cod', $product->companyCode->value);
        $stmt->bindParam(':agrupador', $product->groupId->value);
        $stmt->execute();
    }

    /**
     * Elimina un producto por su ID.
     */
    public function delete(ProductId $id): void
    {
        $query = "DELETE FROM ctrlCreateProducts WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id->value);
        $stmt->execute();
    }
}
