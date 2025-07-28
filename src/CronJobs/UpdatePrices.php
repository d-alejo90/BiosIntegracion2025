<?php

namespace App\CronJobs;

use App\Repositories\ProductRepository;
use App\Repositories\CronJobControlRepository;
use App\Helpers\Logger;
use App\Helpers\ShopifyHelper;
use App\Helpers\StoreConfigFactory;
use App\Repositories\PrecioItemSiesaRepository;

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

    public function __construct($storeUrl, $saveMode = true)
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
    }

    public function run()
    {
        $cronName = 'precios_' . $this->storeName;
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

        // Obtener precios de Siesa
        $preciosSiesa = $this->precioItemSiesaRepository->findPricesBySkuListAndCia($skuList, $this->codigoCia);
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
