<?php

namespace App\CronJobs;

use App\Repositories\ProductRepository;
use App\Models\Product;
use App\Helpers\StoreConfigFactory;
use App\Helpers\ShopifyHelper;
use App\Helpers\Logger;

/**
 * CronJob para reparar la tabla ctrlCreateProducts
 *
 * Este CronJob:
 * 1. Consulta productos en Shopify por SKU o todos
 * 2. Compara con los registros existentes en ctrlCreateProducts
 * 3. Inserta los registros faltantes
 *
 * Uso via GET parameters:
 * - skus: Lista de SKUs separados por coma (ej: SKU1,SKU2,SKU3)
 * - all: Flag para procesar todos los productos (ej: all=1)
 * - dry-run: Flag para previsualizar cambios sin aplicarlos (ej: dry-run=1)
 */
class RepairProducts
{
    private $productRepository;
    private $shopifyHelper;
    private $storeName;
    private $codigoCia;
    private $logFile;
    private $dryRun;
    private $skuList;
    private $processAll;

    public function __construct($storeUrl, $skuList = null, $processAll = false, $dryRun = false)
    {
        $this->productRepository = new ProductRepository();
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->logFile = "cron_repair_products_{$this->storeName}.txt";
        $this->dryRun = $dryRun;
        $this->skuList = $skuList;
        $this->processAll = $processAll;
    }

    /**
     * Ejecuta el proceso de reparación
     */
    public function run()
    {
        Logger::log($this->logFile, "=== Repair Products Started ===");
        Logger::log($this->logFile, "Store: {$this->storeName}");
        Logger::log($this->logFile, "Dry Run: " . ($this->dryRun ? 'YES' : 'NO'));

        echo "=== Repair Products for {$this->storeName} ===\n";
        echo "Dry Run: " . ($this->dryRun ? 'YES' : 'NO') . "\n";

        try {
            // Validar que se especificó modo de operación
            if (!$this->processAll && empty($this->skuList)) {
                $errorMsg = "Error: Debe especificar 'skus' o 'all' como parámetro GET.";
                Logger::log($this->logFile, $errorMsg);
                echo $errorMsg . "\n";
                echo "Uso:\n";
                echo "  ?skus=SKU1,SKU2,SKU3 - Reparar SKUs específicos\n";
                echo "  ?all=1 - Reparar todos los productos\n";
                echo "  ?dry-run=1 - Previsualizar sin aplicar cambios\n";
                return;
            }

            $stats = [];

            if ($this->processAll) {
                $stats = $this->repairAll();
            } else {
                $skus = is_array($this->skuList)
                    ? $this->skuList
                    : array_map('trim', explode(',', $this->skuList));
                $stats = $this->repairBySKUs($skus);
            }

            // Mostrar resumen
            $this->printSummary($stats);

        } catch (\Exception $e) {
            Logger::log($this->logFile, "FATAL ERROR: " . $e->getMessage());
            echo "FATAL ERROR: " . $e->getMessage() . "\n";
        }

        Logger::log($this->logFile, "=== Repair Products Ended ===\n");
    }

    /**
     * Query GraphQL para buscar variantes por SKUs
     */
    private function getGraphQLQueryBySKUs($skus)
    {
        // Construir la query de búsqueda: sku:SKU1 OR sku:SKU2 OR sku:SKU3
        $skuQueries = array_map(function ($sku) {
            return "sku:" . trim($sku);
        }, $skus);
        $searchQuery = implode(' OR ', $skuQueries);

        return [
            'query' => 'query GetProductVariantsBySKU($query: String!, $cursor: String) {
                productVariants(first: 50, query: $query, after: $cursor) {
                    edges {
                        node {
                            id
                            sku
                            product {
                                id
                            }
                            inventoryItem {
                                id
                                inventoryLevels(first: 10) {
                                    nodes {
                                        location {
                                            id
                                            name
                                        }
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }',
            'variables' => [
                'query' => $searchQuery
            ]
        ];
    }

    /**
     * Query GraphQL para obtener todos los productos
     */
    private function getGraphQLQueryAllProducts($cursor = null)
    {
        return [
            'query' => 'query GetAllProductsWithVariants($cursor: String) {
                products(first: 5, after: $cursor) {
                    edges {
                        node {
                            id
                            title
                            variants(first: 20) {
                                edges {
                                    node {
                                        id
                                        sku
                                        inventoryItem {
                                            id
                                            inventoryLevels(first: 10) {
                                                nodes {
                                                    location {
                                                        id
                                                        name
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }',
            'variables' => [
                'cursor' => $cursor
            ]
        ];
    }

    /**
     * Ejecuta query GraphQL en Shopify
     */
    private function executeGraphQL($query, $variables)
    {
        $response = $this->shopifyHelper->graphQL($query, $variables);

        if (isset($response['errors'])) {
            Logger::log($this->logFile, "GraphQL Errors: " . json_encode($response['errors']));
            throw new \Exception("GraphQL query failed: " . json_encode($response['errors']));
        }

        return $response;
    }

    /**
     * Extrae el ID numérico de un GID de Shopify
     */
    private function extractId($gid)
    {
        $parts = explode('/', $gid);
        return end($parts);
    }

    /**
     * Verifica si un registro ya existe en la base de datos
     */
    private function recordExists($sku, $locationId)
    {
        $ciaCod = $this->codigoCia == '232P' ? '20' : $this->codigoCia;
        $products = $this->productRepository->findByCia($ciaCod);

        foreach ($products as $product) {
            if ($product->sku === $sku && $product->locacion === $locationId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Procesa variantes de Shopify y crea registros faltantes
     */
    private function processVariants($variants)
    {
        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($variants as $variant) {
            $sku = $variant['sku'];

            // Skip variants without SKU
            if (empty($sku)) {
                Logger::log($this->logFile, "Skipping variant without SKU: " . $variant['id']);
                continue;
            }

            $variantId = $this->extractId($variant['id']);
            $productId = $this->extractId($variant['product']['id']);
            $inventoryItemId = $this->extractId($variant['inventoryItem']['id']);

            $inventoryLevels = $variant['inventoryItem']['inventoryLevels']['nodes'] ?? [];

            echo "\nProcessing SKU: $sku\n";
            Logger::log($this->logFile, "Processing SKU: $sku (Product: $productId, Variant: $variantId, InvItem: $inventoryItemId)");

            foreach ($inventoryLevels as $level) {
                $locationId = $this->extractId($level['location']['id']);
                $locationName = $level['location']['name'];

                // Verificar si ya existe
                if ($this->recordExists($sku, $locationId)) {
                    echo "  - Location $locationName ($locationId): ALREADY EXISTS\n";
                    Logger::log($this->logFile, "  SKU $sku at location $locationId already exists - SKIPPED");
                    $skipped++;
                    continue;
                }

                // Crear nuevo registro
                echo "  - Location $locationName ($locationId): MISSING - Creating...\n";

                $product = new Product();
                $product->sku = $sku;
                $product->locacion = $locationId;
                $product->nota = 'Reparado por CronJob - ' . date('Y-m-d H:i:s');
                $product->audit_date = date('Y-m-d H:i:s');
                $product->estado = '1';
                $product->prod_id = $productId;
                $product->inve_id = $inventoryItemId;
                $product->vari_id = $variantId;
                $product->cia_cod = $this->codigoCia == '232P' ? '20' : $this->codigoCia;

                if ($this->dryRun) {
                    echo "  [DRY-RUN] Would create: SKU=$sku, Location=$locationId, Product=$productId, Variant=$variantId\n";
                    Logger::log($this->logFile, "  [DRY-RUN] Would create record for SKU $sku at location $locationId");
                    $created++;
                } else {
                    try {
                        $this->productRepository->create($product);
                        echo "  + Successfully created\n";
                        Logger::log($this->logFile, "  + Successfully created record for SKU $sku at location $locationId");
                        $created++;
                    } catch (\PDOException $e) {
                        // Verificar si es error de duplicado (ya existe)
                        if ($this->isDuplicateKeyError($e->getMessage())) {
                            echo "  ~ Already exists (duplicate key)\n";
                            Logger::log($this->logFile, "  ~ Duplicate key for SKU $sku at location $locationId - treating as success");
                            $skipped++;
                        } else {
                            echo "  ! Error: " . $e->getMessage() . "\n";
                            Logger::log($this->logFile, "  ! Exception creating record: " . $e->getMessage());
                            $errors++;
                        }
                    } catch (\Exception $e) {
                        echo "  ! Error: " . $e->getMessage() . "\n";
                        Logger::log($this->logFile, "  ! Exception creating record: " . $e->getMessage());
                        $errors++;
                    }
                }
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Detecta si un error es de clave duplicada
     */
    private function isDuplicateKeyError(string $errorMessage): bool
    {
        $duplicateKeywords = [
            'duplicate key',
            'SQLSTATE[23000]',
            'Violation of PRIMARY KEY constraint',
            'Cannot insert duplicate key',
            '2627',
            '2601'
        ];

        $lowerMessage = strtolower($errorMessage);

        foreach ($duplicateKeywords as $keyword) {
            if (strpos($lowerMessage, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Repara productos por lista de SKUs
     */
    public function repairBySKUs($skus)
    {
        echo "\n=== Repairing Products by SKUs ===\n";
        echo "SKUs: " . implode(', ', $skus) . "\n";

        Logger::log($this->logFile, "Repairing by SKUs: " . implode(', ', $skus));

        $graphqlData = $this->getGraphQLQueryBySKUs($skus);
        $cursor = null;
        $allVariants = [];

        do {
            if ($cursor) {
                $graphqlData['variables']['cursor'] = $cursor;
            }

            $response = $this->executeGraphQL($graphqlData['query'], $graphqlData['variables']);

            $variants = $response['data']['productVariants']['edges'] ?? [];
            foreach ($variants as $edge) {
                $allVariants[] = $edge['node'];
            }

            $pageInfo = $response['data']['productVariants']['pageInfo'] ?? [];
            $cursor = $pageInfo['hasNextPage'] ? $pageInfo['endCursor'] : null;

        } while ($cursor);

        echo "\nFound " . count($allVariants) . " variants in Shopify\n";
        Logger::log($this->logFile, "Found " . count($allVariants) . " variants in Shopify");

        return $this->processVariants($allVariants);
    }

    /**
     * Repara todos los productos
     */
    public function repairAll()
    {
        echo "\n=== Repairing ALL Products ===\n";
        Logger::log($this->logFile, "Repairing ALL products");

        $cursor = null;
        $allVariants = [];
        $productsProcessed = 0;

        do {
            $graphqlData = $this->getGraphQLQueryAllProducts($cursor);
            $response = $this->executeGraphQL($graphqlData['query'], $graphqlData['variables']);

            $products = $response['data']['products']['edges'] ?? [];
            $productsProcessed += count($products);

            foreach ($products as $productEdge) {
                $variants = $productEdge['node']['variants']['edges'] ?? [];
                foreach ($variants as $variantEdge) {
                    $variant = $variantEdge['node'];
                    $variant['product'] = ['id' => $productEdge['node']['id']];
                    $allVariants[] = $variant;
                }
            }

            $pageInfo = $response['data']['products']['pageInfo'] ?? [];
            $cursor = $pageInfo['hasNextPage'] ? $pageInfo['endCursor'] : null;

            echo "Processed $productsProcessed products so far...\n";

        } while ($cursor);

        echo "\nFound " . count($allVariants) . " total variants in Shopify\n";
        Logger::log($this->logFile, "Found " . count($allVariants) . " total variants in Shopify");

        return $this->processVariants($allVariants);
    }

    /**
     * Imprime el resumen de la operación
     */
    private function printSummary($stats)
    {
        echo "\n=== SUMMARY ===\n";
        echo "Created: {$stats['created']}\n";
        echo "Skipped (already exist): {$stats['skipped']}\n";
        echo "Errors: {$stats['errors']}\n";
        echo "\nTotal processed: " . ($stats['created'] + $stats['skipped'] + $stats['errors']) . "\n";

        Logger::log($this->logFile, "SUMMARY - Created: {$stats['created']}, Skipped: {$stats['skipped']}, Errors: {$stats['errors']}");

        if ($this->dryRun) {
            echo "\n[DRY-RUN MODE] No changes were made to the database.\n";
            echo "Run without dry-run=1 to apply changes.\n";
        }

        echo "\nRepair completed!\n";
    }
}
