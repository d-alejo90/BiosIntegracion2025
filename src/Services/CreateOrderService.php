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
        $this->zipCodes = $this->normalizeArray(Constants::ZIP_CODES[$this->storeName]);
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
    }

    public function processOrder($orderData)
    {
        $orderData = $this->isValidJson($orderData) ? json_decode($orderData, true) : false;
        if (!$orderData) {
            $message = "Fallo en la obtención de datos de Shopify";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }
        $orderId = $orderData['id'];
        $customerId = $orderData['customer']['id'] ?? 'NAN';

        if ($this->orderHeadRepository->exists($orderId)) {
            $message = "Orden con ID: $orderId ya existe en la base de datos";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }

        $shopifyOrderData = $this->getShopifyOrderData($orderId);
        $shopifyCustomer = $this->getShopifyCustomer($shopifyOrderData);

        [$cedula, $cedulaBilling] = $this->getCedulas($shopifyOrderData);

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

        $normalizedCity = $this->normalizeString($orderData['shipping_address']['city']);
        if (empty($this->zipCodes[$normalizedCity])) {
            $message = "Fallo en la obtención de zip code de Shopify con order ID: $orderId";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }

        $customer = $this->createCustomer($orderData, $shopifyCustomer, $cedulaBilling, $cedula);
        $this->customerRepository->create($customer);

        $orderHead = $this->createOrderHead($orderData);
        $this->orderHeadRepository->create($orderHead);

        $orderDetail = $this->createOrderDetails($orderData, $customerId);
        $this->orderDetailRepository->create($orderDetail);

        Logger::log("wh_run_$this->storeName.txt", "Order processed: $orderId");
    }

    private function normalizeString($string)
    {
        // Convierte la cadena a minúsculas y normaliza los caracteres especiales
        $string = mb_strtolower($string, 'UTF-8');
        // Remplaza los caracteres especiales manualmente
        $specialCharacters = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'à' => 'a',
            'è' => 'e',
            'ì' => 'i',
            'ò' => 'o',
            'ù' => 'u',
            'â' => 'a',
            'ê' => 'e',
            'î' => 'i',
            'ô' => 'o',
            'û' => 'u',
            'ä' => 'a',
            'ë' => 'e',
            'ï' => 'i',
            'ö' => 'o',
            'ü' => 'u',
            'ã' => 'a',
            'ñ' => 'n',
            'ç' => 'c',
            'í' => 'i',
        ];
        $string = strtr($string, $specialCharacters);
        return $string;
    }

    private function normalizeArray(array $array)
    {
        $normalizedArray = [];
        foreach ($array as $key => $value) {
            $normalizedArray[$this->normalizeString($key)] = $value;
        }
        return $normalizedArray;
    }

    private function isValidJson($data)
    {
        // Intenta decodificar el JSON
        json_decode($data);

        // Verifica si hubo errores
        return (json_last_error() === JSON_ERROR_NONE);
    }

    private function getShopifyOrderData($orderId)
    {
        $orderData = $this->shopifyHelper->getShopifyOrderDataByOrderId($orderId);
        if (!$orderData || !isset($orderData['data']) || empty($orderData['data']['order'])) {
            $message = "Fallo en la obtención de order de Shopify con ID: $orderId";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }
        Logger::log("wh_run_$this->storeName.txt", "Order de Shopify obtenido con éxito: \n " . json_encode($orderData));
        return $orderData['data']['order'];
    }

    private function getShopifyCustomer($orderData)
    {
        $shopifyCustomer = $orderData['customer'];
        if (!isset($shopifyCustomer)) {
            $message = "Fallo en la obtención del cliente de la orden";
            Logger::log("wh_run_$this->storeName.txt", $message);
            throw new \Exception($message, 1);
        }
        Logger::log("wh_run_$this->storeName.txt", "Cliente de Shopify obtenido con éxito: \n " . json_encode($shopifyCustomer));
        return $shopifyCustomer;
    }

    private function getCedulas($orderData)
    {
        $cedula = $orderData['cedula']['value'] ?? null;
        $cedulaBilling = $orderData['cedulaFacturacion']['value'] ?? $cedula;
        return [$cedula, $cedulaBilling];
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
        $customer->fecha_nacimiento = Constants::FECHA_DE_NACIMIENTO;
        $customer->billing_nombre = $orderData['billing_address']['first_name'];
        $customer->billing_apellido = $orderData['billing_address']['last_name'];
        $customer->CodigoCia = $this->codigoCia;
        $customer->audit_date = $orderData['created_at'];
        Logger::log("wh_run_$this->storeName.txt", "Cliente Para Guardarse: \n " . json_encode($customer));
        return $customer;
    }

    private function getZipCode($city)
    {
        $city = $this->normalizeString($city);
        $zipCode = $this->zipCodes[$city] ?? null;
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
        $orderHead->audit_date = $orderData['created_at'];
        $orderHead->status = 1;
        $orderHead->CodigoCia = $this->codigoCia;
        Logger::log("wh_run_$this->storeName.txt", "Order Head para guardar: \n " . json_encode($orderHead));
        return $orderHead;
    }

    private function createOrderDetails($orderData, $customerId)
    {
        $shippingData = $this->ciudadRepository->findByCityNameAndDepartmentName(
            $orderData['shipping_address']['city'],
            $orderData['shipping_address']['province']
        );
        Logger::log("wh_run_$this->storeName.txt", "shippingData: \n " . json_encode($shippingData));
        $billingData = $this->ciudadRepository->findByCityNameAndDepartmentName(
            $orderData['billing_address']['city'],
            $orderData['billing_address']['province']
        );
        Logger::log("wh_run_$this->storeName.txt", "billingData: \n " . json_encode($billingData));
        foreach ($orderData['line_items'] as $lineItem) {
            $orderDetail = new OrderDetail();
            $orderDetail->order_id = $orderData['id'];
            $orderDetail->customer_id = $customerId;
            $orderDetail->created_at = $orderData['created_at'];
            $orderDetail->audit_date = $orderData['created_at'];
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
            $orderDetail->city_code_shipping = $shippingData->f013_id ?? '000';
            $orderDetail->country_code_billing = $billingData->f013_id_pais ?? '000';
            $orderDetail->province_code_billing = $billingData->f013_id_depto ?? '00';
            $orderDetail->city_code_billing = $billingData->f013_id ?? '000';
            $orderDetail->tags = $orderData['customer']['tags'] ?? 'NaN';
            $orderDetail->CodigoCia = $this->codigoCia;
            $orderDetail->flete = $orderDetail->shipping_amount;
            Logger::log("wh_run_$this->storeName.txt", "OrderDetail Para Guardar: \n " . json_encode($orderDetail));
            return $orderDetail;
        }
    }
}
