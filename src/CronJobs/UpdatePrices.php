<?php

namespace App\CronJobs;

use App\Repositories\ProductRepository;
use App\Repositories\CronJobControlRepository;
use App\Helpers\Logger;
use App\Helpers\ShopifyHelper;
use App\Helpers\StoreConfigFactory;
use App\Helpers\LocationHelper;
use App\Repositories\PrecioItemSiesaRepository;
use App\Config\Constants;

class UpdatePrices
{
    private $precioItemSiesaRepository;
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
        $this->precioItemSiesaRepository = new PrecioItemSiesaRepository();
        $this->productRepository = new ProductRepository();
        $this->cronJobControlRepository = new CronJobControlRepository();
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->logFile = "cron_update_prices_$this->storeName.txt";
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
        $cronName = 'precios_' . $this->storeName;
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

        // Obtener precios de Siesa (with location filter)
        $preciosSiesa = $this->precioItemSiesaRepository->findPricesBySkuListAndCia($skuList, $this->codigoCia, $this->locationFilter);
        $this->mapProductsToPrices($products, $preciosSiesa);
        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
        echo "End Run " . date('Y-m-d H:i:s') . "\n===========================\n";
    }

    public function mapProductsToPrices($products, $preciosSiesa)
    {
        // Indexar los productos por SKU+Locación ya que este es el identificador unico entre siesa y shopify
        $productMap = [];
        foreach ($products as $product) {
            $productMap["$product->sku+$product->locacion"] = $product;
        }

        // Agrupar variantes por producto
        $groupedProducts = [];

        foreach ($preciosSiesa as $priceItem) {
            $sku = $priceItem->sku;
            $location = $priceItem->location;
            // Verificar si el producto con ese SKU+location existe
            if (isset($productMap["$sku+$location"])) {
                $product = $productMap["$sku+$location"];
                // Crear la estructura de producto si no existe
                if (!isset($groupedProducts[$product->prod_id])) {
                    $groupedProducts[$product->prod_id] = [
                        "productId" => trim("gid://shopify/Product/{$product->prod_id}"),
                        "variants" => []
                    ];
                }

                // Construir el identificador de la variante
                $variantId = trim("gid://shopify/ProductVariant/{$product->vari_id}");
                // Evitar duplicados en variants
                $existingVariants = array_column($groupedProducts[$product->prod_id]["variants"], 'id');
                if (!in_array($variantId, $existingVariants)) {
                    $groupedProducts[$product->prod_id]["variants"][] = [
                        "id" => $variantId,
                        "price" => $priceItem->precio
                    ];
                }
            }
        }

        // Convertir en un array de valores (para eliminar las claves asociativas)
        $priceVariables = array_values($groupedProducts);

        try {
            // Enviamos precios a Shopify
            foreach ($priceVariables as $priceVariable) {
                Logger::log($this->logFile, "Updating prices for product: " . $priceVariable['productId']);
                Logger::log($this->logFile, "Variants: " . json_encode($priceVariable));
                $this->shopifyHelper->updateVariantPrices($priceVariable, $this->saveMode);
                Logger::log($this->logFile, "Prices updated for product: " . $priceVariable['productId']);
            }
        } catch (\Exception $e) {
            Logger::log($this->logFile, "Error Updating Prices: " . $e->getMessage());
            echo "Error Updating Prices: " . $e->getMessage() . "\n";
        }
    }
}
