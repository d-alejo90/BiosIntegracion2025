<?php

namespace App\CronJobs;

use App\Repositories\InventarioSiesaRepository;
use App\Repositories\ProductRepository;
use App\Helpers\Logger;
use App\Helpers\ShopifyHelper;
use App\Helpers\StoreConfigFactory;

class UpdateInventory
{
    private $inventarioSiesaRepository;
    private $productRepository;
    private $shopifyHelper;
    private $codigoCia;
    private $logFile;
    private $storeName;

    public function __construct($storeUrl)
    {
        $this->inventarioSiesaRepository = new InventarioSiesaRepository();
        $this->productRepository = new ProductRepository();
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->logFile = "cron_update_inventory_$this->storeName.txt";
    }

    public function run()
    {
        Logger::log($this->logFile, "Start Run " . date('Y-m-d H:i:s'));

        // Obtener productos
        $products = $this->productRepository->findByCia($this->codigoCia);
        $skuList = array_map(fn ($product) => $product->sku, $products);

        // Obtener inventario de Siesa
        $inventorySiesa = $this->inventarioSiesaRepository->findInventoryBySkuListAndCia($skuList, $this->codigoCia);

        // Mapear los IDs en formato de Shopify
        $inventoryItemIdsList = array_map(fn ($inventory) => "gid://shopify/InventoryItem/$inventory->inventory_id", $inventorySiesa);

        // **Dividir en chunks de 250 ya que es el limite que permite shopify**
        $chunks = array_chunk($inventoryItemIdsList, 250);

        $shopifyInventoryAvailable = [];

        // Iterar sobre los chunks y enviar en partes
        foreach ($chunks as $chunk) {
            $result = $this->shopifyHelper->getAvailableQuantityByInventoryItemIds($chunk);
            $filteredResult = array_filter($result, fn ($item) => !empty($item));
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
            $response = $this->shopifyHelper->adjustInventoryQty($adjustmentChanges);
            Logger::log($this->logFile, "Response: " . json_encode($response));
        } catch (\Exception $e) {
            Logger::log($this->logFile, "Error: " . $e->getMessage());
        }
        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
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
