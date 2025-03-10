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
    private $skuList;

    public function __construct($storeUrl, $skuList = null)
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
        $this->skuList = $skuList ?? "";
    }

    public function run()
    {
        Logger::log($this->logFile, "Start Run " . date('Y-m-d H:i:s'));
        try {
            echo 'Start Run CreateProducts' . date('Y-m-d H:i:s') . "\n";
            $products = $this->getSiesaProducts();
            $groupedItems = $this->groupProducts($products);
            $shopifyResponses = $this->createShopifyProducts($groupedItems);
            $this->saveProductsFromResponses($shopifyResponses);
            echo 'End Run CreateProducts' . date('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Logger::log($this->logFile, "Exception: " . $e->getMessage());
        } catch (\Error $err) {
            Logger::log($this->logFile, "Error: " . $err->getMessage());
        }
        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
    }

    private function getSiesaProducts(): array
    {
        if ($this->skuList) {
            return $this->itemSiesaRepository->findByCiaAndSkus($this->codigoCia, $this->skuList);
        }
        return $this->itemSiesaRepository->findByCia($this->codigoCia);
    }

    private function groupProducts(array $products): array
    {
        $groupedItems = [];
        foreach ($products as $product) {
            $product->location_name = $this->bodegas[$product->location];
            $group_id = $product->group_id;

            if (!isset($groupedItems[$group_id])) {
                $groupedItems[$group_id] = [];
            }

            $groupedItems[$group_id][] = $product;
        }

        return $groupedItems;
    }

    private function createShopifyProducts(array $groupedItems): array
    {
        $shopifyResponses = [];
        $bodegasValues = $this->formatValues($this->bodegas);

        foreach ($groupedItems as $groupId => $items) {
            $existingProduct = $this->productRepository->findByGroupId($groupId);
            $variables = [];
            if (empty($existingProduct)) {
                $variables = $this->buildShopifyProductVariables($items, $bodegasValues);
                Logger::log($this->logFile, "Create Product: " . $variables["productSet"]["title"]);
                Logger::log($this->logFile, "Variables: " . json_encode($variables));
                echo "===============CREATE======================";
                $shopifyResponses[] = $this->shopifyHelper->createProducts($variables);
            } else {
                // Accedemos a shopify para obtener la data del producto
                $shopifyProduct = $this->shopifyHelper->getProductById($existingProduct);
                if (empty($shopifyProduct) || empty($shopifyProduct["data"]["product"])) {
                    Logger::log($this->logFile, "No se pudo obtener el producto de Shopify: " . $existingProduct->shopify_product_id);
                    continue;
                }
                $shopifyProduct = $shopifyProduct["data"]["product"];
                $productOptions = $shopifyProduct["options"];
                if (empty($productOptions)) {
                    Logger::log($this->logFile, "No se encontraron opciones para el producto: " . $existingProduct->shopify_product_id);
                    continue;
                }
                $presentationsValues = [];
                $existingOptionValues = [];
                foreach ($productOptions as $option) {
                    $formatedValues = $this->formatValues($option["values"]);
                    if ($option["name"] === "Peso" || $option["name"] === "peso") {
                        $presentationsValues[$option["id"]] = $formatedValues;
                    }

                    $existingOptionValues[$option["name"]] = [
                        "optionId" => $option["id"],
                        "optionValues" => $option["optionValues"],
                    ];
                }
                echo '<pre>';
                echo "====================UPDATE ====================";
                $variables = $this->buildShopifyVariantVariables($items, $shopifyProduct["id"], $presentationsValues, $existingOptionValues);
                Logger::log($this->logFile, "Update Product: " . $shopifyProduct["id"]);
                Logger::log($this->logFile, "Variables: " . json_encode($variables));
                $shopifyResponses[] = $this->shopifyHelper->productVariantsBulkCreate($variables);
            }
        }
        echo '<pre>';
        print_r($shopifyResponses);
        return $shopifyResponses;
    }

    private function formatValues($values): array
    {
        return array_map(function ($value) {
            return ["name" => $value];
        }, array_values($values));
    }

    private function buildShopifyProductVariables(array $items): array
    {
        return $this->storeName === "mizooco"
            ? $this->buildShopifyProductVariablesForMizooco($items)
            : $this->buildShopifyProductVariablesForCampoAzul($items);
    }

    private function buildShopifyVariantVariables(array $items, string $shopifyProductId = null, array $presentationsValues = [], array $existingOptionValues = []): array
    {
        return $this->storeName === "mizooco"
            ? $this->buildShopifyVariantVariablesForMizooco($items, $shopifyProductId, $presentationsValues, $existingOptionValues)
            : $this->buildShopifyVariantVariablesForCampoAzul($items, $shopifyProductId, $existingOptionValues);
    }

    private function buildShopifyVariantVariablesForCampoAzul(array $items, string $shopifyProductId = null, array $existingOptionValues = []): array
    {
        $variants = $this->buildVariantsForCampoAzul($items, $existingOptionValues);
        $result = [
            "productId" => $shopifyProductId,
            "variants" => $variants,
        ];
        return $result;
    }

    private function buildShopifyVariantVariablesForMizooco(array $items, string $shopifyProductId = null, $presentationsValues = [], $existingOptionValues = []): array
    {
        $variants = $this->buildVariantsForMizooco($items, $existingOptionValues);
        $result = [
            "productId" => $shopifyProductId,
            "variants" => $variants,
        ];
        return $result;
    }

    private function buildShopifyProductVariablesForMizooco(array $items): array
    {
        $bodegasValues = $this->formatValues($this->bodegas);
        $presentations = $this->getUniquePresentations($items);
        $variants = $this->buildVariantsForMizooco($items);

        $result = [
            "synchronous" => true,
            "productSet" => [
                "title" => $items[0]->title,
                "status" => "DRAFT",
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
        return $result;
    }

    public function removePresentationsFromItems(array $sourceArray, array $arrayToRemove): array
    {
        $presentationsToRemove = array_column($arrayToRemove, 'name');
        // Filtrar el primer array para excluir los elementos que coincidan con las presentaciones a eliminar
        $filteredArray = array_filter($sourceArray, function ($item) use ($presentationsToRemove) {
            return !in_array($item->presentation, $presentationsToRemove);
        });
        // Reindexar el array resultante
        $filteredArray = array_values($filteredArray);
        return $filteredArray;
    }

    // Función para verificar si el array está en el array multidimensional
    public function removeArrayFromArray($array1, $array2)
    {
        // Extraer los valores de 'name' del primer array
        $namesToRemove = array_map(function ($item) {
            return $item['name'];
        }, $array1);

        // Filtrar el segundo array para excluir los elementos que coincidan con los nombres del primer array
        $filteredArray = array_filter($array2, function ($item) use ($namesToRemove) {
            return !in_array($item['name'], $namesToRemove);
        });

        // Reindexar el array resultante
        $filteredArray = array_values($filteredArray);
        return $filteredArray;
    }

    private function buildShopifyProductVariablesForCampoAzul(array $items): array
    {
        $bodegasValues = $this->formatValues($this->bodegas);
        $variants = $this->buildVariantsForCampoAzul($items);

        $result = [
            "synchronous" => true,
            "productSet" => [
                "title" => $items[0]->title,
                "status" => "DRAFT",
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

        return $result;
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

    public function getIdByName($array, $name)
    {
        // Recorrer el array de optionValues
        foreach ($array['optionValues'] as $option) {
            // Si el name coincide, devolver el id
            if ($option['name'] === $name) {
                return $option['id'];
            }
        }
        return null;
    }


    public function removeItemByOptionName(&$optionValues, $optionName)
    {
        // Usar array_filter para excluir el elemento que coincida con optionName
        $optionValues = array_filter($optionValues, function ($item) use ($optionName) {
            return $item['optionName'] !== $optionName;
        });

        // Reindexar el array para eliminar huecos en las claves
        $optionValues = array_values($optionValues);
    }

    private function buildVariantsForMizooco(array $items, array $existingOptionValues = []): array
    {
        $result = array_map(function ($item) use ($existingOptionValues) {
            // Valores base para creacion ce nuevos productos
            $optionValues =  [
                [
                    "optionName" => "Bodegas",
                    "name" => $this->bodegas[$item->location],
                ],
                [
                    "optionName" => "Peso",
                    "name" => $item->presentation,
                ],
            ];
            $inventoryQuantities = [
                [
                    "locationId" => "gid://shopify/Location/$item->location",
                    "name" => "available",
                    "quantity" => 0,
                ],
            ];
            if (!empty($existingOptionValues)) {
                $optionValues = [];
                // Aqui modificamos $optionValues y $inventoryQuantities para que coincda con la creacion de variantes
                $optionValueIdPeso = $this->getIdByName($existingOptionValues['Peso'], $item->presentation);
                $optionValueIdBodega = $this->getIdByName($existingOptionValues['Bodegas'], $this->bodegas[$item->location]);
                $optionValuePesoToAdd = [
                    "optionId" => $existingOptionValues['Peso']['optionId'],
                ];
                $optionValueBodegaToAdd = [
                    "optionId" => $existingOptionValues['Bodegas']['optionId'],
                ];
                if (empty($optionValueIdPeso)) {
                    $optionValuePesoToAdd['name'] = $item->presentation;
                } else {
                    $optionValuePesoToAdd['id'] = $optionValueIdPeso;
                }
                if (empty($optionValueIdBodega)) {
                    $optionValueBodegaToAdd['name'] = $this->bodegas[$item->location];
                } else {
                    $optionValueBodegaToAdd['id'] = $optionValueIdBodega;
                }
                $optionValues[] = $optionValuePesoToAdd;
                $optionValues[] = $optionValueBodegaToAdd;
                $inventoryQuantities = [
                    [
                        "availableQuantity" => 0,
                        "locationId" =>  "gid://shopify/Location/$item->location",
                    ],
                ];
            }
            return [
                "optionValues" => $optionValues,
                "inventoryQuantities" => $inventoryQuantities,
                "inventoryItem" => [
                    "sku" => $item->sku,
                    "tracked" => true,
                ],
            ];
        }, $items);
        return $result;
    }

    private function buildVariantsForCampoAzul(array $items, array $existingOptionValues = []): array
    {

        $result = array_map(function ($item) use ($existingOptionValues) {
            $optionValues =  [
                [
                    "optionName" => "Bodegas",
                    "name" => $this->bodegas[$item->location],
                ],
            ];
            if (!empty($existingOptionValues)) {
                $optionValueIdBodega = $this->getIdByName($existingOptionValues['Bodegas'], $this->bodegas[$item->location]);
                if (!empty($optionValueIdBodega)) {
                    $optionValues = [
                        "optionName" => "Bodegas",
                        "optionId" => $optionValueIdBodega,
                    ];
                }
            }
            return [
                "optionValues" => $optionValues,
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
        return $result;
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
