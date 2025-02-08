<?php

namespace App\Services;

use App\Repositories\OrderHeadRepository;
use App\Repositories\OrderDetailRepository;
use App\Repositories\CiudadRepository;
use App\Repositories\CustomerRepository;
use App\Helpers\ShopifyHelper;
use App\Helpers\Logger;
use App\Helpers\StoreConfigFactory;
use App\Models\Customer;
use App\Models\OrderHead;
use App\Models\OrderDetail;
use App\Config\Constants;

class CreateOrderService
{
    private $orderHeadRepository;
    private $orderDetailRepository;
    private $ciudadRepository;
    private $customerRepository;
    private $shopifyHelper;
    private $codigoCia;
    private $storeName;
    private $zipCodes;

    public function __construct($storeUrl)
    {
        $this->orderHeadRepository = new OrderHeadRepository();
        $this->orderDetailRepository = new OrderDetailRepository();
        $this->ciudadRepository = new CiudadRepository();
        $this->customerRepository = new CustomerRepository();

        // Obtener la configuración de la tienda basada en la URL
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->codigoCia = $config['codigoCia'];
        $this->storeName = $config['storeName'];
        $this->zipCodes = Constants::ZIP_CODES[$this->storeName];
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
    }

    public function processOrder($orderData)
    {
        $orderId = $orderData['id'];
        $customerId = $orderData['customer']['id'] ?? 'NAN';

        if ($this->orderHeadRepository->exists($orderId)) {
            $message = "Orden con ID: $orderId ya existe en la base de datos";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }

        $shopifyCustomer = $this->getShopifyCustomer($customerId);

        $cedulas = $this->getCedulas($orderId);

        $cedula = $cedulas['data']['order']['cedula']['value'];
        $cedulaBilling = $cedulas['data']['order']['cedulaFacturacion']['value'] ?? $cedula;

        if (empty($cedula)) {
            $message = "Fallo en la obtención de cedula de Shopify con order ID: $orderId";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }

        if (empty($cedulaBilling)) {
            $message = "Fallo en la obtención de cedula de facturacion de Shopify con order ID: $orderId";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }

        if (empty($this->zipCodes[$orderData['shipping_address']['city']])) {
            $message = "Fallo en la obtención de zip code de Shopify con order ID: $orderId";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }

        $customer = $this->createCustomer($orderData, $shopifyCustomer, $cedulaBilling, $cedula);
        $this->customerRepository->create($customer);

        $orderHead = $this->createOrderHead($orderData);
        $this->orderHeadRepository->create($orderHead);

        $this->createOrderDetails($orderData, $customerId);

        Logger::log("wh_run_$this->storeName.txt", "Order processed: $orderId");
    }

    private function getShopifyCustomer($customerId)
    {
        $shopifyCustomer = $this->shopifyHelper->getCustomerById($customerId);
        if (!$shopifyCustomer || !isset($shopifyCustomer['data']) || empty($shopifyCustomer['data']['customer'])) {
            $message = "Fallo en la obtención del cliente de Shopify con ID: $customerId";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }
        Logger::log("wh_run_$this->storeName.txt", "Cliente de Shopify obtenido con éxito: \n " . json_encode($shopifyCustomer));
        return $shopifyCustomer['data']['customer'];
    }

    private function getCedulas($orderId)
    {
        $cedulas = $this->shopifyHelper->getCedulasByOrderId($orderId);
        if (!$cedulas) {
            $message = "Fallo en la obtención de cedulas de Shopify con order ID: $orderId";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }
        Logger::log("wh_run_$this->storeName.txt", "Cedulas de Shopify obtenidas con éxito: \n " . json_encode($cedulas));
        return $cedulas;
    }

    private function createCustomer($orderData, $shopifyCustomer, $cedulaBilling, $cedula)
    {
        $customer = new Customer();
        $customer->order_id = $orderData['id'];
        $customer->customer_id = $orderData['customer']['id'] ?? 'NAN';
        $customer->company = $cedulaBilling;
        $customer->first_name = $shopifyCustomer['firstName'];
        $customer->last_name = $shopifyCustomer['lastName'];
        $customer->name = $shopifyCustomer['firstName'] . ' ' . $shopifyCustomer['lastName'];
        $customer->email = $shopifyCustomer['email'];
        $customer->address1 = $shopifyCustomer['defaultAddress']['address1'];
        $customer->address2 = $shopifyCustomer['defaultAddress']['address2'] ?? '';
        $customer->billing_address1 = $orderData['billing_address']['address1'];
        $customer->billing_address2 = $orderData['billing_address']['address2'] ?? '';
        $customer->shipping_address1 = $orderData['shipping_address']['address1'];
        $customer->shipping_address2 = $orderData['shipping_address']['address2'] ?? '';
        $customer->currency = $orderData['currency'];
        $customer->phone = $orderData['shipping_address']['phone'];
        $customer->zip = $this->getZipCode($orderData['shipping_address']['city']);
        $customer->tags = $orderData['tags'];
        $customer->cc_registro = $cedula;
        $customer->nombre_registro = $shopifyCustomer['firstName'];
        $customer->apellido_registro = $shopifyCustomer['lastName'];
        $customer->fecha_nacimiento = FECHA_DE_NACIMIENTO;
        $customer->billing_nombre = $orderData['billing_address']['first_name'];
        $customer->billing_apellido = $orderData['billing_address']['last_name'];
        $customer->CodigoCia = $this->codigoCia;

        return $customer;
    }

    private function getZipCode($city)
    {
        $zipCode = ZIP_CODES[$city] ?? null;
        if (!$zipCode) {
            Logger::log("wh_run_$this->storeName.txt", "Failed to retrieve zip code for city: $city");
        }
        return $zipCode;
    }

    private function createOrderHead($orderData)
    {
        $orderHead = new OrderHead();
        $orderHead->order_id = $orderData['id'];
        $orderHead->order_name = $orderData['name'];
        $orderHead->status = 1;
        $orderHead->CodigoCia = $this->codigoCia;

        return $orderHead;
    }

    private function createOrderDetails($orderData, $customerId)
    {
        $shippingData = $this->ciudadRepository->findByCityNameAndDepartmentName(
            $orderData['shipping_address']['city'],
            $orderData['shipping_address']['province']
        );

        $billingData = $this->ciudadRepository->findByCityNameAndDepartmentName(
            $orderData['billing_address']['city'],
            $orderData['billing_address']['province']
        );

        foreach ($orderData['line_items'] as $lineItem) {
            $orderDetail = new OrderDetail();
            $orderDetail->order_id = $orderData['id'];
            $orderDetail->customer_id = $customerId;
            $orderDetail->created_at = $orderData['created_at'];
            $orderDetail->currency = $orderData['currency'];
            $orderDetail->notes = $orderData['note'] ?? '';
            $orderDetail->sku = $lineItem['sku'];
            $orderDetail->quantity = $lineItem['quantity'];
            $orderDetail->variant_title = $lineItem['variant_title'];
            $orderDetail->price = $lineItem['price'];
            $orderDetail->price_taxes = $lineItem['tax_lines'][0]['price'] ?? 0;
            $orderDetail->discount_amount = $lineItem['discount_allocations'][0]['amount'] ?? 0;
            $orderDetail->discount_target_type = 'NAN';
            $orderDetail->shipping_amount = $orderData['total_shipping_price_set']['shop_money']['amount'] ?? 0;
            $orderDetail->country_code_shipping = $shippingData->f013_id_pais ?? '000';
            $orderDetail->province_code_shipping = $shippingData->f013_id_depto ?? '00';
            $orderDetail->city_code_shipping = $shippingData->f_013_id ?? '000';
            $orderDetail->country_code_billing = $billingData->f013_id_pais ?? '000';
            $orderDetail->province_code_billing = $billingData->f013_id_depto ?? '00';
            $orderDetail->city_code_billing = $billingData->f_013_id ?? '000';
            $orderDetail->tags = $orderData['customer']['tags'] ?? 'NaN';
            $orderDetail->CodigoCia = $this->codigoCia;
            $orderDetail->flete = $orderDetail->shipping_amount;

            $this->orderDetailRepository->create($orderDetail);
        }
    }
}
