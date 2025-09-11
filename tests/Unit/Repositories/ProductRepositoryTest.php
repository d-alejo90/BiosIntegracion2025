<?php

namespace Tests\Unit\Repositories;

use App\Repositories\ProductRepository;
use App\Models\Product;
use App\Config\Database;
use Mockery;
use PDO;
use PDOStatement;

class ProductRepositoryTest extends \Tests\TestCase
{
    private $mockDatabase;
    private $mockConnection;
    private $mockStatement;
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConnection = Mockery::mock(PDO::class);
        $this->mockStatement = Mockery::mock(PDOStatement::class);
        $this->mockDatabase = Mockery::mock(Database::class);

        $this->mockDatabase
            ->shouldReceive('getInstance')
            ->andReturnSelf();

        $this->mockDatabase
            ->shouldReceive('getConnection')
            ->andReturn($this->mockConnection);

        $this->repository = new ProductRepository();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testFindAllReturnsAllProducts()
    {
        $expectedQuery = "SELECT * FROM ctrlCreateProducts";
        $mockData = [
            ['id' => 1, 'sku' => 'SKU001', 'locacion' => 'LOC1'],
            ['id' => 2, 'sku' => 'SKU002', 'locacion' => 'LOC2']
        ];

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('execute')
            ->once();

        $this->mockStatement
            ->shouldReceive('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn($mockData[0], $mockData[1], false);

        // Mock the Product::fromArray method
        $product1 = Mockery::mock(Product::class);
        $product2 = Mockery::mock(Product::class);

        $productMock = Mockery::mock('alias:' . Product::class);
        $productMock->shouldReceive('fromArray')
            ->with($mockData[0])
            ->andReturn($product1);

        $productMock->shouldReceive('fromArray')
            ->with($mockData[1])
            ->andReturn($product2);

        $result = $this->repository->findAll();

        $this->assertCount(2, $result);
        $this->assertEquals([$product1, $product2], $result);
    }

    public function testFindByCiaWithNormalCode()
    {
        $ciaCod = '123';
        $expectedQuery = "SELECT * FROM ctrlCreateProducts WHERE cia_cod = :cia_cod";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':cia_cod', '123')
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->once();

        $this->mockStatement
            ->shouldReceive('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(false); // No results

        $productMock = Mockery::mock('alias:' . Product::class);
        $productMock->shouldReceive('fromArray')->never();

        $result = $this->repository->findByCia($ciaCod);

        $this->assertEmpty($result);
    }

    public function testFindByCiaWithSpecialCode232P()
    {
        $ciaCod = '232P';
        $expectedQuery = "SELECT * FROM ctrlCreateProducts WHERE cia_cod = :cia_cod";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        // Should convert 232P to '20'
        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':cia_cod', '20')
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->once();

        $this->mockStatement
            ->shouldReceive('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(false);

        $productMock = Mockery::mock('alias:' . Product::class);
        $productMock->shouldReceive('fromArray')->never();

        $result = $this->repository->findByCia($ciaCod);

        $this->assertEmpty($result);
    }

    public function testFindByIdReturnsProduct()
    {
        $id = 123;
        $mockData = ['id' => 123, 'sku' => 'TEST-SKU'];
        $expectedQuery = "SELECT * FROM ctrlCreateProducts WHERE id = :id";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':id', $id)
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->once();

        $this->mockStatement
            ->shouldReceive('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn($mockData);

        $mockProduct = Mockery::mock(Product::class);
        $productMock = Mockery::mock('alias:' . Product::class);
        $productMock->shouldReceive('fromArray')
            ->with($mockData)
            ->andReturn($mockProduct);

        $result = $this->repository->findById($id);

        $this->assertEquals($mockProduct, $result);
    }

    public function testFindByIdReturnsNullWhenNotFound()
    {
        $id = 999;
        $expectedQuery = "SELECT * FROM ctrlCreateProducts WHERE id = :id";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':id', $id)
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->once();

        $this->mockStatement
            ->shouldReceive('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(false);

        $productMock = Mockery::mock('alias:' . Product::class);
        $productMock->shouldReceive('fromArray')->never();

        $result = $this->repository->findById($id);

        $this->assertNull($result);
    }

    public function testFindByGroupIdReturnsShopifyProductId()
    {
        $groupId = 'GROUP123';
        $mockData = ['shopify_product_id' => '7890123456'];
        $expectedQuery = "SELECT prod_id as shopify_product_id FROM ctrlCreateProducts WHERE agrupador = :group_id GROUP BY prod_id";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':group_id', $groupId, PDO::PARAM_STR)
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->once();

        $this->mockStatement
            ->shouldReceive('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn($mockData);

        $result = $this->repository->findByGroupId($groupId);

        $this->assertEquals('7890123456', $result);
    }

    public function testFindBySkuReturnsShopifyProductId()
    {
        $sku = 'TEST-SKU-001';
        $mockData = ['shopify_product_id' => '1234567890'];
        $expectedQuery = "SELECT prod_id as shopify_product_id FROM ctrlCreateProducts WHERE sku = TRIM(:sku)";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':sku', $sku)
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->once();

        $this->mockStatement
            ->shouldReceive('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn($mockData);

        $result = $this->repository->findBySku($sku);

        $this->assertEquals('1234567890', $result);
    }

    public function testCreateProduct()
    {
        $product = new Product();
        $product->sku = 'TEST-SKU';
        $product->locacion = 'LOC001';
        $product->nota = 'Test note';
        $product->estado = '1';
        $product->prod_id = '123456';
        $product->inve_id = '789012';
        $product->vari_id = '345678';
        $product->cia_cod = '001';

        $expectedQuery = "INSERT INTO ctrlCreateProducts (sku, locacion, nota, audit_date, estado, prod_id, inve_id, vari_id, cia_cod) 
                  VALUES (:sku, :locacion, :nota, GETDATE(), :estado, :prod_id, :inve_id, :vari_id, :cia_cod)";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':sku', $product->sku)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':locacion', $product->locacion)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':nota', $product->nota)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':estado', $product->estado)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':prod_id', $product->prod_id)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':inve_id', $product->inve_id)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':vari_id', $product->vari_id)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':cia_cod', $product->cia_cod)
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->andReturn(true)
            ->once();

        $result = $this->repository->create($product);

        $this->assertTrue($result);
    }

    public function testUpdateProduct()
    {
        $product = new Product();
        $product->id = 1;
        $product->sku = 'UPDATED-SKU';
        $product->locacion = 'LOC002';
        $product->nota = 'Updated note';
        $product->estado = '1';
        $product->prod_id = '654321';
        $product->inve_id = '210987';
        $product->vari_id = '876543';
        $product->cia_cod = '002';

        $expectedQuery = "UPDATE ctrlCreateProducts 
                  SET sku = :sku, locacion = :locacion, nota = :nota, audit_date = GETDATE(), estado = :estado, 
                      prod_id = :prod_id, inve_id = :inve_id, vari_id = :vari_id, cia_cod = :cia_cod 
                  WHERE id = :id";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':id', $product->id)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':sku', $product->sku)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':locacion', $product->locacion)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':nota', $product->nota)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':estado', $product->estado)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':prod_id', $product->prod_id)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':inve_id', $product->inve_id)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':vari_id', $product->vari_id)
            ->once();

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':cia_cod', $product->cia_cod)
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->andReturn(true)
            ->once();

        $result = $this->repository->update($product);

        $this->assertTrue($result);
    }

    public function testDeleteProduct()
    {
        $id = 123;
        $expectedQuery = "DELETE FROM ctrlCreateProducts WHERE id = :id";

        $this->mockConnection
            ->shouldReceive('prepare')
            ->with($expectedQuery)
            ->andReturn($this->mockStatement);

        $this->mockStatement
            ->shouldReceive('bindParam')
            ->with(':id', $id)
            ->once();

        $this->mockStatement
            ->shouldReceive('execute')
            ->andReturn(true)
            ->once();

        $result = $this->repository->delete($id);

        $this->assertTrue($result);
    }
}
