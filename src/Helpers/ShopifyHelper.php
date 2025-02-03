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
}
