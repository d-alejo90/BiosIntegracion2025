<?php

namespace App\CronJobs;

use App\Repositories\InventarioSiesaRepository;
use App\Repositories\CronJobControlRepository;
use App\Repositories\ProductRepository;
use App\Helpers\Logger;
use App\Helpers\ShopifyHelper;
use App\Helpers\StoreConfigFactory;

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

    public function __construct($storeUrl, $saveMode = true)
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
    }

    public function run()
    {
        $cronName = 'inventario_' . $this->storeName;
        Logger::log($this->logFile, "Start Run $cronName " . date('Y-m-d H:i:s'));
        echo "Start Run $cronName " . date('Y-m-d H:i:s') . "\n===========================\n";

        $cronIsOn = $this->cronJobControlRepository->getStatusByCronName($cronName);
        if (!$cronIsOn) {
            echo "<p>El cron $cronName no esta activo </p>";
            echo "End Run $cronName" . date('Y-m-d H:i:s');
            return;
        }
        // Obtener productos
        $products = $this->productRepository->findByCia($this->codigoCia);
        $skuList = array_map(fn($product) => $product->sku, $products);

        // Obtener inventario de Siesa
        $inventorySiesa = $this->inventarioSiesaRepository->findInventoryBySkuListAndCia($skuList, $this->codigoCia);

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
        foreach ($mergedInventory as $item) {
            $siesaQtyAvailable = $item->available_qty_siesa;
            $shopifyQtyAvailable = $item->available_qty_shopify;
            if ($siesaQtyAvailable == $shopifyQtyAvailable) {
                // no hay cambios
                continue;
            }
            // Restamos la cantidad de Shopify a la cantidad de Siesa para calcular el delta que es el valor del ajuste
            $availableAdjustment = $siesaQtyAvailable - $shopifyQtyAvailable;

            $changes = [
                "delta" => $availableAdjustment,
                "inventoryItemId" => "gid://shopify/InventoryItem/" . $item->inventory_id,
                "locationId" => "gid://shopify/Location/" . $item->location
            ];
            $adjustmentChanges[] = $changes;
        }
        try {
            Logger::log($this->logFile, "Ajuste de inventario: " . json_encode($adjustmentChanges));
            $response = $this->shopifyHelper->adjustInventoryQty($adjustmentChanges, $this->saveMode);
            Logger::log($this->logFile, "Response: " . json_encode($response, JSON_PRETTY_PRINT));
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
}
