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
        $query = <<<QUERY
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
        QUERY;

        return $this->shopify->GraphQL->post($query);
    }

    public function getCustomerById($customerId)
    {
        $customerId = "gid://shopify/Customer/{$customerId}";
        $query = <<<QUERY
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
        QUERY;

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
}
