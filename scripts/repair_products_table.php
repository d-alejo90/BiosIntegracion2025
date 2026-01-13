<?php

/**
 * Script de reparación para la tabla ctrlCreateProducts
 *
 * Este script:
 * 1. Consulta productos en Shopify por SKU
 * 2. Compara con los registros existentes en ctrlCreateProducts
 * 3. Inserta los registros faltantes
 *
 * Uso:
 * php scripts/repair_products_table.php --store=campo-azul --skus=SKU1,SKU2,SKU3
 * php scripts/repair_products_table.php --store=mizooco --all
 * php scripts/repair_products_table.php --store=campo-azul --skus=SKU1,SKU2 --dry-run
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repositories\ProductRepository;
use App\Helpers\StoreConfigFactory;
use App\Helpers\ShopifyHelper;
use App\Helpers\Logger;
use App\Models\Product;

class RepairProductsTable
{
    private $productRepository;
    private $shopifyHelper;
    private $storeName;
    private $codigoCia;
    private $logFile;
    private $dryRun;

    public function __construct($storeUrl, $dryRun = false)
    {
        $this->productRepository = new ProductRepository();
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->logFile = "repair_products_table_{$this->storeName}.txt";
        $this->dryRun = $dryRun;

        Logger::log($this->logFile, "=== Repair Script Started ===");
        Logger::log($this->logFile, "Store: {$this->storeName}");
        Logger::log($this->logFile, "Dry Run: " . ($dryRun ? 'YES' : 'NO'));
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
                productVariants(first: 250, query: $query, after: $cursor) {
                    edges {
                        node {
                            id
                            sku
                            product {
                                id
                            }
                            inventoryItem {
                                id
                                inventoryLevels(first: 50) {
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
                products(first: 50, after: $cursor) {
                    edges {
                        node {
                            id
                            title
                            variants(first: 100) {
                                edges {
                                    node {
                                        id
                                        sku
                                        inventoryItem {
                                            id
                                            inventoryLevels(first: 50) {
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
                $product->nota = 'Reparado por script - ' . date('Y-m-d H:i:s');
                $product->audit_date = date('Y-m-d H:i:s');
                $product->estado = '1';
                $product->prod_id = $productId;
                $product->inve_id = $inventoryItemId;
                $product->vari_id = $variantId;
                $product->cia_cod = $this->codigoCia == '232P' ? '20' : $this->codigoCia;

                if ($this->dryRun) {
                    echo "  [DRY-RUN] Would create: SKU=$sku, Location=$locationId, Product=$productId, Variant=$variantId\n";
                    Logger::log($this->logFile, "  [DRY-RUN] Would create record: " . json_encode($product));
                    $created++;
                } else {
                    try {
                        $result = $this->productRepository->create($product);
                        if ($result) {
                            echo "  ✓ Successfully created\n";
                            Logger::log($this->logFile, "  ✓ Successfully created record for SKU $sku at location $locationId");
                            $created++;
                        } else {
                            echo "  ✗ Failed to create (no exception but returned false)\n";
                            Logger::log($this->logFile, "  ✗ Failed to create record for SKU $sku at location $locationId");
                            $errors++;
                        }
                    } catch (\Exception $e) {
                        echo "  ✗ Error: " . $e->getMessage() . "\n";
                        Logger::log($this->logFile, "  ✗ Exception creating record: " . $e->getMessage());
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
}

// ===== MAIN SCRIPT EXECUTION =====

function printUsage()
{
    echo "\nUsage:\n";
    echo "  php scripts/repair_products_table.php --store=<store> --skus=<SKU1,SKU2,...>\n";
    echo "  php scripts/repair_products_table.php --store=<store> --all\n";
    echo "\nOptions:\n";
    echo "  --store      Store to process (campo-azul or mizooco)\n";
    echo "  --skus       Comma-separated list of SKUs to repair\n";
    echo "  --all        Process all products in Shopify\n";
    echo "  --dry-run    Preview changes without making them\n";
    echo "\nExamples:\n";
    echo "  php scripts/repair_products_table.php --store=campo-azul --skus=ABC123,DEF456\n";
    echo "  php scripts/repair_products_table.php --store=mizooco --all --dry-run\n";
    echo "\n";
}

// Parse command line arguments
$options = getopt('', ['store:', 'skus:', 'all', 'dry-run', 'help']);

if (isset($options['help']) || empty($options['store'])) {
    printUsage();
    exit(0);
}

$store = $options['store'];
$dryRun = isset($options['dry-run']);

// Validate store
$storeUrls = [
    'campo-azul' => 'campo-azul.myshopify.com',
    'mizooco' => 'mi-zooco.myshopify.com'
];

if (!isset($storeUrls[$store])) {
    echo "Error: Invalid store '$store'. Must be 'campo-azul' or 'mizooco'\n";
    printUsage();
    exit(1);
}

$storeUrl = $storeUrls[$store];

try {
    $repair = new RepairProductsTable($storeUrl, $dryRun);

    if (isset($options['all'])) {
        $stats = $repair->repairAll();
    } elseif (isset($options['skus'])) {
        $skus = explode(',', $options['skus']);
        $skus = array_map('trim', $skus);
        $stats = $repair->repairBySKUs($skus);
    } else {
        echo "Error: You must specify either --skus or --all\n";
        printUsage();
        exit(1);
    }

    // Print summary
    echo "\n=== SUMMARY ===\n";
    echo "Created: {$stats['created']}\n";
    echo "Skipped (already exist): {$stats['skipped']}\n";
    echo "Errors: {$stats['errors']}\n";
    echo "\nTotal processed: " . ($stats['created'] + $stats['skipped'] + $stats['errors']) . "\n";

    if ($dryRun) {
        echo "\n[DRY-RUN MODE] No changes were made to the database.\n";
        echo "Run without --dry-run to apply changes.\n";
    }

    echo "\nRepair completed successfully!\n";

} catch (\Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
