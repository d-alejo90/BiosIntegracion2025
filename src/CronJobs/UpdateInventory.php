<?php

namespace App\CronJobs;

use App\Repositories\InventarioSiesaRepository;
use App\Repositories\CronJobControlRepository;
use App\Repositories\ProductRepository;
use App\Helpers\Logger;
use App\Helpers\ShopifyHelper;
use App\Helpers\StoreConfigFactory;
use App\Helpers\LocationHelper;
use App\Config\Constants;

class UpdateInventory
{
    private $inventarioSiesaRepository;
    private $productRepository;
    private $cronJobControlRepository;
    private $shopifyHelper;
    private $codigoCia;
    private $logFile;
    private $storeName;
    private $saveMode;
    private $locationFilter;

    public function __construct($storeUrl, $saveMode = true, $locationFilter = null)
    {
        $this->inventarioSiesaRepository = new InventarioSiesaRepository();
        $this->productRepository = new ProductRepository();
        $this->cronJobControlRepository = new CronJobControlRepository();
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->logFile = "cron_update_inventory_$this->storeName.txt";
        $this->saveMode = $saveMode;

        // Validate and normalize location filter
        if ($locationFilter !== null) {
            $normalized = LocationHelper::normalizeLocation($locationFilter, $this->storeName);

            if ($normalized === null) {
                // Invalid location - terminate with error
                $validLocations = implode(", ", array_keys(Constants::BODEGAS[$this->storeName]));
                $errorMsg = "Invalid location filter: '$locationFilter'. Valid locations for $this->storeName: $validLocations";
                Logger::log($this->logFile, "ERROR: $errorMsg");
                echo "ERROR: $errorMsg\n";
                exit(1);
            }

            $this->locationFilter = $normalized;
            $locationName = LocationHelper::getLocationName($normalized, $this->storeName);
            Logger::log($this->logFile, "Location filter active: $locationName ($normalized)");
        } else {
            $this->locationFilter = null;
        }
    }

    public function run()
    {
        $cronName = 'inventario_' . $this->storeName;
        Logger::log($this->logFile, "Start Run $cronName " . date('Y-m-d H:i:s'));
        echo "Start Run $cronName " . date('Y-m-d H:i:s') . "\n===========================\n";

        // Log location filter status
        if ($this->locationFilter !== null) {
            $locationName = LocationHelper::getLocationName($this->locationFilter, $this->storeName);
            Logger::log($this->logFile, "Processing only location: $locationName ($this->locationFilter)");
            echo "Processing only location: $locationName ($this->locationFilter)\n";
        } else {
            Logger::log($this->logFile, "Processing ALL locations");
            echo "Processing ALL locations\n";
        }

        $cronIsOn = $this->cronJobControlRepository->getStatusByCronName($cronName);
        if (!$cronIsOn) {
            echo "<p>El cron $cronName no esta activo </p>";
            echo "End Run $cronName" . date('Y-m-d H:i:s');
            return;
        }
        // Obtener productos (with location filter)
        $products = $this->productRepository->findByCia($this->codigoCia, $this->locationFilter);
        $skuList = array_map(fn($product) => $product->sku, $products);

        // Obtener inventario de Siesa (with location filter)
        $inventorySiesa = $this->inventarioSiesaRepository->findInventoryBySkuListAndCia($skuList, $this->codigoCia, $this->locationFilter);

        // Mapear los IDs en formato de Shopify
        $inventoryItemIdsList = array_map(fn($inventory) => "gid://shopify/InventoryItem/$inventory->inventory_id", $inventorySiesa);

        // **Dividir en chunks de 250 ya que es el limite que permite shopify**
        $chunks = array_chunk($inventoryItemIdsList, 250);

        $shopifyInventoryAvailable = [];

        // Iterar sobre los chunks y enviar en partes
        foreach ($chunks as $chunk) {
            $result = $this->shopifyHelper->getAvailableQuantityByInventoryItemIds($chunk);
            $filteredResult = array_filter($result, fn($item) => !empty($item));
            $shopifyInventoryAvailable = array_merge($shopifyInventoryAvailable, $filteredResult);
        }
        $mergedInventory = $this->mergeInventoryData($shopifyInventoryAvailable, $inventorySiesa);
        $adjustmentChanges = [];
        $skippedItems = [];

        foreach ($mergedInventory as $item) {
            $siesaQtyAvailable = $item->available_qty_siesa;
            $shopifyQtyAvailable = $item->available_qty_shopify;

            // Verificar si este item fue retornado por Shopify
            // Si shopify_qty es null y no está en la respuesta de Shopify, significa que no está tracked
            $inventoryItemGid = "gid://shopify/InventoryItem/" . $item->inventory_id;
            $wasReturnedByShopify = $this->wasInventoryItemReturnedByShopify($inventoryItemGid, $shopifyInventoryAvailable);

            if (!$wasReturnedByShopify) {
                // Este item no fue retornado por Shopify, no está tracked en esta location
                $skippedItems[] = [
                    'sku' => $item->sku,
                    'inventory_id' => $item->inventory_id,
                    'location' => $item->location,
                    'reason' => 'Item not returned by Shopify API (not tracked in location)'
                ];
                continue;
            }

            if ($siesaQtyAvailable == $shopifyQtyAvailable) {
                // no hay cambios
                continue;
            }
            // Restamos la cantidad de Shopify a la cantidad de Siesa para calcular el delta que es el valor del ajuste
            $availableAdjustment = (int)($siesaQtyAvailable - $shopifyQtyAvailable);

            $changes = [
                "delta" => $availableAdjustment,
                "inventoryItemId" => $inventoryItemGid,
                "locationId" => "gid://shopify/Location/" . $item->location
            ];
            $adjustmentChanges[] = $changes;
        }

        // Registrar items omitidos
        if (!empty($skippedItems)) {
            Logger::log($this->logFile, "Items omitidos (no retornados por Shopify): " . count($skippedItems));
            Logger::log($this->logFile, json_encode($skippedItems, JSON_PRETTY_PRINT));
            echo "Items omitidos: " . count($skippedItems) . " (ver log para detalles)\n";
        }

        // Validar que haya cambios antes de intentar ajustar
        if (empty($adjustmentChanges)) {
            Logger::log($this->logFile, "No hay cambios de inventario para procesar");
            echo "No hay cambios de inventario para procesar\n";
            Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
            echo "End Run " . date('Y-m-d H:i:s') . "\n===========================\n";
            return;
        }

        try {
            Logger::log($this->logFile, "Ajuste de inventario (" . count($adjustmentChanges) . " items): " . json_encode($adjustmentChanges));
            $responses = $this->shopifyHelper->adjustInventoryQty($adjustmentChanges, $this->saveMode);

            // Procesar respuestas por batch
            $totalBatches = is_array($responses) ? count($responses) : 0;
            Logger::log($this->logFile, "Procesados $totalBatches batch(es)");

            foreach ($responses as $batchIndex => $response) {
                $batchNumber = $batchIndex + 1;
                Logger::log($this->logFile, "Batch $batchNumber Response: " . json_encode($response, JSON_PRETTY_PRINT));

                // Verificar si hay errores en la respuesta
                if (isset($response['error'])) {
                    Logger::log($this->logFile, "ERROR en Batch $batchNumber: " . $response['error']);
                    echo "ERROR en Batch $batchNumber: " . $response['error'] . "\n";
                } elseif (is_array($response) && isset($response['data']['inventoryAdjustQuantities']['userErrors'])) {
                    $userErrors = $response['data']['inventoryAdjustQuantities']['userErrors'];
                    if (!empty($userErrors)) {
                        Logger::log($this->logFile, "UserErrors en Batch $batchNumber: " . json_encode($userErrors));
                        echo "UserErrors en Batch $batchNumber: " . json_encode($userErrors) . "\n";
                    } else {
                        Logger::log($this->logFile, "Batch $batchNumber procesado exitosamente");
                        echo "Batch $batchNumber procesado exitosamente\n";
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log($this->logFile, "Error Ajuste de inventario: " . $e->getMessage());
            echo "<pre>";
            echo "Error Ajuste de inventario: \n";
            echo $e->getMessage();
            echo "</pre>";
        }
        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
        echo "End Run " . date('Y-m-d H:i:s') . "\n===========================\n";
    }

    public function mergeInventoryData($shopifyInventory, $localInventory)
    {
        // Construimos un mapa [inventory_id][location_id] => available_qty
        $shopifyData = [];

        foreach ($shopifyInventory as $item) {
            $inventoryId = str_replace("gid://shopify/InventoryItem/", "", $item['id']);

            foreach ($item['inventoryLevels']['nodes'] as $node) {
                $locationId = str_replace("gid://shopify/Location/", "", $node['location']['id']);
                $availableQty = 0;

                foreach ($node['quantities'] as $quantity) {
                    if ($quantity['name'] === 'available') {
                        $availableQty = $quantity['quantity'];
                        break;
                    }
                }

                $shopifyData[$inventoryId][$locationId] = $availableQty;
            }
        }

        // Recorremos el inventario local y agregamos available_qty_shopify
        return array_map(function ($item) use ($shopifyData) {
            $inventoryId = $item->inventory_id;
            $locationId = $item->location;

            $item->available_qty_shopify = $shopifyData[$inventoryId][$locationId] ?? 0;

            return $item;
        }, $localInventory);
    }

    /**
     * Verifica si un inventory item fue retornado en la respuesta de Shopify
     * @param string $inventoryItemGid GID del inventory item (ej: "gid://shopify/InventoryItem/123")
     * @param array $shopifyInventoryData Respuesta de Shopify con los inventory items
     * @return bool
     */
    private function wasInventoryItemReturnedByShopify(string $inventoryItemGid, array $shopifyInventoryData): bool
    {
        foreach ($shopifyInventoryData as $item) {
            if (isset($item['id']) && $item['id'] === $inventoryItemGid) {
                return true;
            }
        }
        return false;
    }
}
