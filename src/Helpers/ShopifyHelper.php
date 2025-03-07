<?php

namespace App\Helpers;

use PHPShopify\ShopifySDK;

class ShopifyHelper
{
  private $shopify;

  public function __construct($config)
  {
    $this->shopify = new ShopifySDK($config);
  }


  public function getShopifyOrderDataByOrderId($orderId)
  {
    $orderId = "gid://shopify/Order/$orderId";
    $response = $this->shopify->GraphQL->post(<<<GQL
        query getShopifyOrderDataByOrderId (\$orderId: ID!) {
            order(id: \$orderId) {
                id
                name
                customer {
                    id
                    firstName
                    lastName
                    defaultAddress {
                      address1
                      address2
                    }
                }
                cedula: metafield(namespace: "checkoutblocks", key: "cedula") {
                    value
                }
                cedulaFacturacion: metafield(namespace: "custom", key: "cedula_de_facturacion") {
                    value
                }
            }
        }
        GQL, null, null, ['orderId' => $orderId]);

    return $response;
  }

  public function getCustomerById($customerId)
  {
    $customerId = "gid://shopify/Customer/$customerId";
    $query = <<<GQL
        query getCustomerById (\$customerId: ID!){
            customer(id: \$customerId) {
                id
                firstName
                lastName
                email
                phone
                numberOfOrders
                createdAt
                updatedAt
                note
                verifiedEmail
                validEmailAddress
                defaultAddress {
                    formattedArea
                    address1
                    company
                }
                addresses {
                    address1
                }
            }
        }
        GQL;

    return $this->shopify->GraphQL->post($query, null, null, ['customerId' => $customerId]);
  }

  public function getFulfillmentId($orderId)
  {
    $response = $this->shopify->GraphQL->post(<<<GQL
        query getFulfillmentOrderByOrderId(\$id: ID!) {
            order(id: \$id) {
                id
                fulfillmentOrders(first: 1) {
                    nodes {
                        id
                        status
                    }
                }
            }
        }
        GQL, null, null, ['id' => "gid://shopify/Order/$orderId"]);

    return $response["data"]["order"]["fulfillmentOrders"]["nodes"][0]['id'] ?? null;
  }

  public function getFulfillmentDataByOrderIds($orderIdsQuery, $first)
  {
    $response = $this->shopify->GraphQL->post(<<<GQL
        query getFulfillmentDataByOrderIds(\$orderIdsQuery: String, \$first: Int) {
          orders(first: \$first, query: \$orderIdsQuery) {
            nodes {
              id
              fulfillmentOrders(first: 1) {
                nodes {
                  id
                  status
                }
              }
            }
          }
        }
        GQL, null, null, ['orderIdsQuery' => $orderIdsQuery, 'first' => $first]);
    return $response["data"]["orders"]["nodes"] ?? null;
  }

  public function createFulfillment($fulfillmentId)
  {
    $createFulfillmentMutation = <<<GQL
        mutation fulfillmentCreate(\$fulfillment: FulfillmentInput!) {
            fulfillmentCreate(fulfillment: \$fulfillment) {
                fulfillment {
                    id
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GQL;

    $variables = [
      'fulfillment' => [
        'lineItemsByFulfillmentOrder' => [
          [
            'fulfillmentOrderId' => $fulfillmentId
          ]
        ],
        'notifyCustomer' => true
      ]
    ];

    return $this->shopify->GraphQL->post($createFulfillmentMutation, null, null, $variables);
  }

  public function getAvailableQuantityByInventoryItemIds($inventoryItemIds)
  {
    $query = <<<GQL
          query GetInventoryQuantities(\$inventoryItemIds: [ID!]!) {
            nodes(ids: \$inventoryItemIds) {
              id
              ... on InventoryItem {
                id
                sku
                inventoryLevels(first: 10) {
                  nodes {
                    location {
                      id
                      name
                    }
                    quantities(names: ["available"]) {
                      name
                      quantity
                    }
                  }
                }
              }
            }
          }
        GQL;

    $variables = [
      "inventoryItemIds" => $inventoryItemIds,
    ];
    $result = $this->shopify->GraphQL->post($query, null, null, $variables);

    return $result['data']['nodes'];
  }

  public function adjustInventoryQty($adjustmentChanges)
  {
    $inventoryAdjustmentQuantitiesInput = [
      "input" => [
        "reason" => "correction",
        "name" => "available",
        "changes" => $adjustmentChanges,
      ],
    ];
    $query = <<<GQL
          mutation inventoryAdjustQuantities(\$input: InventoryAdjustQuantitiesInput!) {
              inventoryAdjustQuantities(input: \$input) {
                  userErrors {
                      field
                      message
                  }
                  inventoryAdjustmentGroup {
                      createdAt
                      reason
                      referenceDocumentUri
                      changes {
                        name
                        delta
                      }
                  }
              }
          } 
        GQL;
    $response = $this->shopify->GraphQL->post($query, null, null, $inventoryAdjustmentQuantitiesInput);
    return $response;
  }

  public function updateVariantPrices($variantPricesVariable)
  {

    $query = <<<GQL
        mutation productVariantsBulkUpdate(\$productId: ID!, \$variants: [ProductVariantsBulkInput!]!) {
            productVariantsBulkUpdate(productId: \$productId, variants: \$variants) {
                product {
                    id
                    title
                }
                productVariants {
                    id
                    title
                    price
                    compareAtPrice
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GQL;

    $response = $this->shopify->GraphQL->post($query, null, null, $variantPricesVariable);
    return $response;
  }

  public function createProducts($productsVariable)
  {
    $query = <<<GQL
        mutation createProduct(\$productSet: ProductSetInput!, \$synchronous: Boolean!) {
          productSet(synchronous: \$synchronous, input: \$productSet) {
            product {
              id
              variants(first: 15) {
                nodes {
                  id
                  title
                  sku
                  inventoryQuantity
                  inventoryItem {
                    id
                    inventoryLevels(first: 1) {
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
            userErrors {
              field
              message
            }
          }
        }
        GQL;
    $response = $this->shopify->GraphQL->post($query, null, null, $productsVariable);
    return $response;
  }

  public function getProductById(string $productId)
  {
    $productId = "gid://shopify/Product/{$productId}";
    $query = <<<GQL
          query getProductById(\$productId: ID!) {
            product(id: \$productId) {
              id
              title
              options {
                id
                name
                values
                optionValues {
                  id
                  name
                }
              }
            }
          }
        GQL;
    return $this->shopify->GraphQL->post($query, null, null, ['productId' => $productId]);
  }

  public function createOptionValues($optionId, $options, $productId)
  {
    $option = [
      "id" => $optionId,
    ];
    $variables = [
      "productId" => $productId,
      "option" => $option,
      "optionValuesToAdd" => $options,
    ];
    print_r($variables);
    $query = <<<GQL
          mutation updateOption(\$productId: ID!, \$option: OptionUpdateInput!, \$optionValuesToAdd: [OptionValueCreateInput!], \$optionValuesToUpdate: [OptionValueUpdateInput!], \$optionValuesToDelete: [ID!], \$variantStrategy: ProductOptionUpdateVariantStrategy) {
            productOptionUpdate(productId: \$productId, option: \$option, optionValuesToAdd: \$optionValuesToAdd, optionValuesToUpdate: \$optionValuesToUpdate, optionValuesToDelete: \$optionValuesToDelete, variantStrategy: \$variantStrategy) {
              userErrors {
                field
                message
                code
              }
              product {
                id
                options {
                  id
                  name
                  values
                  position
                  optionValues {
                    id
                    name
                    hasVariants
                  }
                }
              }
            }
          }
        GQL;
    return $this->shopify->GraphQL->post($query, null, null, $variables);
  }

  public function productVariantsBulkCreate(array $variantes)
  {

    $query = <<<GQL
          mutation productVariantsBulkCreate(\$productId: ID!, \$variants: [ProductVariantsBulkInput!]!) {
            productVariantsBulkCreate(productId: \$productId, variants: \$variants) {
              userErrors {
                code
                field
                message
              }
              product {
                id
                options {
                  id
                  name
                  values
                  position
                  optionValues {
                    id
                    name
                    hasVariants
                  }
                }
              }
              productVariants {
                id
                title
                selectedOptions {
                  name
                  value
                }
              }
            }
          }
        GQL;
    try {
      $response = $this->shopify->GraphQL->post($query, null, null, $variantes);
    } catch (\Exception $e) {
      echo '<pre>';
      print_r($e);
    }
    return $response;
  }
}
