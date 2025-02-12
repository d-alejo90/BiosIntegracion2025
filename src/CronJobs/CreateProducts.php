<?php

namespace App\CronJobs;

use App\Repositories\ItemSiesaRepository;
use App\Repositories\ProductRepository;
use App\Models\Product;
use App\Helpers\StoreConfigFactory;
use App\Helpers\ShopifyHelper;
use App\Helpers\Logger;
use App\Config\Constants;

class CreateProducts
{
    private $itemSiesaRepository;
    private $productRepository;
    private $bodegas;
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
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->bodegas = Constants::BODEGAS[$this->storeName];
        $this->logFile = "cron_create_products_{$this->storeName}.txt";
    }

    public function run()
    {
        Logger::log($this->logFile, "Start Run " . date('Y-m-d H:i:s'));

        $products = $this->getSiesaProducts();
        $groupedItems = $this->groupProductsByTitle($products);
        $shopifyResponses = $this->createShopifyProducts($groupedItems);
        $this->saveProductsFromResponses($shopifyResponses);

        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
    }

    private function getSiesaProducts(): array
    {
        return $this->itemSiesaRepository->findByCia($this->codigoCia);
    }

    private function groupProductsByTitle(array $products, bool $removeWeight = true): array
    {
        $groupedItems = [];
        foreach ($products as $product) {
            $product->location_name = $this->bodegas[$product->location];
            $title = $removeWeight ? $this->removeWeightFromTitle($product->title) : $product->title;

            if (!isset($groupedItems[$title])) {
                $groupedItems[$title] = [];
            }

            $groupedItems[$title][] = $product;
        }

        return $groupedItems;
    }

    private function removeWeightFromTitle(string $title): string
    {
        $titleParts = explode(' ', trim($title));
        array_pop($titleParts);
        return implode(' ', $titleParts);
    }

    private function createShopifyProducts(array $groupedItems): array
    {
        $shopifyResponses = [];
        $bodegasValues = $this->formatBodegasValues();

        foreach ($groupedItems as $items) {
            $variables = $this->buildShopifyProductVariables($items, $bodegasValues);
            Logger::log($this->logFile, "Create Product: " . $variables["productSet"]["title"]);
            Logger::log($this->logFile, "Variables: " . json_encode($variables));
            $shopifyResponses[] = $this->shopifyHelper->createProducts($variables);
        }

        return $shopifyResponses;
    }

    private function formatBodegasValues(): array
    {
        return array_map(function ($bodega) {
            return ["name" => $bodega];
        }, array_values($this->bodegas));
    }

    private function buildShopifyProductVariables(array $items, array $bodegasValues): array
    {
        return $this->storeName === "mizooco"
            ? $this->buildShopifyProductVariablesForMizooco($items, $bodegasValues)
            : $this->buildShopifyProductVariablesForCampoAzul($items, $bodegasValues);
    }

    private function buildShopifyProductVariablesForMizooco(array $items, array $bodegasValues): array
    {
        $presentations = $this->getUniquePresentations($items);
        $variants = $this->buildVariantsForMizooco($items);

        return [
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
                "variants" => $variants,
            ],
        ];
    }

    private function buildShopifyProductVariablesForCampoAzul(array $items, array $bodegasValues): array
    {
        $variants = $this->buildVariantsForCampoAzul($items);

        return [
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
                ],
                "variants" => $variants,
            ],
        ];
    }

    private function getUniquePresentations(array $items): array
    {
        $uniquePresentations = array_unique(array_map(function ($item) {
            return $item->presentation;
        }, $items));

        // Formatea como un arreglo de objetos con el campo "name" y asegura índices secuenciales
        return array_values(array_map(function ($presentation) {
            return ["name" => $presentation];
        }, $uniquePresentations));
    }

    private function buildVariantsForMizooco(array $items): array
    {
        return array_map(function ($item) {
            return [
                "optionValues" => [
                    [
                        "optionName" => "Bodegas",
                        "name" => $this->bodegas[$item->location],
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
                    "tracked" => true,
                ],
            ];
        }, $items);
    }

    private function buildVariantsForCampoAzul(array $items): array
    {
        return array_map(function ($item) {
            return [
                "optionValues" => [
                    [
                        "optionName" => "Bodegas",
                        "name" => $this->bodegas[$item->location],
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
                    "tracked" => true,
                ],
            ];
        }, $items);
    }

    private function saveProductsFromResponses(array $shopifyResponses)
    {
        foreach ($shopifyResponses as $response) {
            if (isset($response['data']['productSet']['product']['variants']['nodes'])) {
                foreach ($response['data']['productSet']['product']['variants']['nodes'] as $node) {
                    $product = $this->createProductFromNode($node, $response);
                    Logger::log($this->logFile, "Creating product: " . $product->sku);
                    Logger::log($this->logFile, "Product: " . json_encode($product));
                    $this->productRepository->create($product);
                }
            }
        }
    }

    private function createProductFromNode(array $node, array $response): Product
    {
        $product = new Product();
        $product->sku = $node['sku'];
        $product->locacion = $this->extractId($node['inventoryItem']['inventoryLevels']['nodes'][0]['location']['id']);
        $product->nota = null;
        $product->audit_date = date('Y-m-d H:i:s');
        $product->estado = null;
        $product->prod_id = $this->extractId($response['data']['productSet']['product']['id']);
        $product->inve_id = $this->extractId($node['inventoryItem']['id']);
        $product->vari_id = $this->extractId($node['id']);
        $product->cia_cod = $this->codigoCia;

        return $product;
    }

    private function extractId(string $gid): string
    {
        $parts = explode('/', $gid);
        return end($parts);
    }
}
