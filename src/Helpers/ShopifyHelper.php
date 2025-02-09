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


    public function getCedulasByOrderId($orderId)
    {
        $orderId = "gid://shopify/Order/{$orderId}";
        $query = <<<GQL
        query {
            order(id: "{$orderId}") {
                id
                name
                customer {
                    id
                }
                cedula: metafield(namespace: "checkoutblocks", key: "cedula") {
                    value
                }
                cedulaFacturacion: metafield(namespace: "custom", key: "cedula_de_facturacion") {
                    value
                }
            }
        }
        GQL;

        return $this->shopify->GraphQL->post($query);
    }

    public function getCustomerById($customerId)
    {
        $customerId = "gid://shopify/Customer/{$customerId}";
        $query = <<<GQL
        query {
            customer(id: "{$customerId}") {
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

        return $this->shopify->GraphQL->post($query);
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

}
