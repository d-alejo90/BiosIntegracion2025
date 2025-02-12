<?php

namespace App\CronJobs;

use App\Repositories\ItemSiesaRepository;
use App\Repositories\ProductRepository;
use App\Helpers\StoreConfigFactory;
use App\Helpers\ShopifyHelper;
use App\Helpers\Logger;
use App\Config\Constants;

class CreateProducts
{
    private $itemSiesaRepository;
    private $productRepository;
    private $shopifyHelper;
    private $codigoCia;
    private $logFile;
    private $storeName;

    public function __construct($storeUrl)
    {
        $this->itemSiesaRepository = new ItemSiesaRepository();
        $this->productRepository = new ProductRepository();
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->logFile = "cron_create_products_$this->storeName.txt";
    }

    public function run()
    {
        Logger::log($this->logFile, "Start Run " . date('Y-m-d H:i:s'));

        // Obtener productos de siesa que no estan en shopify
        $products = $this->itemSiesaRepository->findByCia($this->codigoCia);
        echo '<pre>';
        $groupedItems = [];
        foreach ($products as $product) {
            $bodega = Constants::BODEGAS_MIZOOCO[$product->location];
            $product->location_name = $bodega;
            $titleWithoutWeight = explode(' ', trim($product->title)); // Removemos el peso del final del titulo
            array_pop($titleWithoutWeight);
            $title = implode(' ', $titleWithoutWeight);
            // Si el title no existe en el array agrupado, lo inicializamos como un array vacío
            if (!isset($groupedItems[$title])) {
                $groupedItems[$title] = [];
            }

            // Añadimos el item al array correspondiente a su titulo
            $groupedItems[$title][] = $product;
        }
        // Ahora genereamos las variables de shopify a partir de los items agrupados

        $bodegasValues = array_map(function ($b) {
            return ["name" => $b];
        }, array_values(Constants::BODEGAS_MIZOOCO));
        $shopifyResponses = [];
        foreach ($groupedItems as $items) {
            // Obtener un array con los valores de la propiedad "presentation"
            $presentations = array_unique(array_map(function ($item) {
                return ["name" => $item->presentation];
            }, $items));
            $variables = [
              "synchronous" => true,
              "productSet" => [
                "status" => "DRAFT",
                "title" => $items[0]->title,
                "productOptions" => [
                  [
                    "name" => "Bodegas",
                    "position" => 1,
                    "values" => $bodegasValues,
                  ],
                  [
                    "name" => "Peso",
                    "position" => 2,
                    "values" => $presentations,
                  ],
                ],
                "variants" => [],
              ],
            ];
            $variants = [];
            foreach ($items as $item) {
                $variants[] = [
                    "optionValues" => [
                      [
                        "optionName" => "Bodegas",
                        "name" => Constants::BODEGAS_MIZOOCO[$item->location],
                      ],
                      [
                        "optionName" => "Peso",
                        "name" => $item->presentation,
                      ],
                    ],
                    "inventoryQuantities" => [
                      [
                        "locationId" => "gid://shopify/Location/$item->location",
                        "name" => "available",
                        "quantity" => 0,
                      ],
                    ],
                    "inventoryItem" => [
                      "sku" => $item->sku,
                      "tracked" => true
                    ],
                  ];
            }
            $variables['productSet']['variants'] = $variants;
            $shopifyResponses[] = $this->shopifyHelper->createProducts($variables);
        }
        print_r($shopifyResponses);
        echo "======================================================";

        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
    }
}
