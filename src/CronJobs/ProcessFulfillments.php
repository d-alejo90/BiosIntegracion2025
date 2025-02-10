<?php

namespace App\CronJobs;

use App\Repositories\OrderHeadRepository;
use App\Helpers\Logger;
use App\Helpers\ShopifyHelper;
use App\Helpers\StoreConfigFactory;

class ProcessFulfillments
{
    private $orderHeadRepository;
    private $shopifyHelper;
    private $codigoCia;
    private $logFile;
    private $storeName;

    public function __construct($storeUrl)
    {
        $this->orderHeadRepository = new OrderHeadRepository();

        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->logFile = "cron_fulfillment_$this->storeName.txt";
    }

    public function run()
    {
        Logger::log($this->logFile, "Start Run " . date('Y-m-d H:i:s'));

        $despachos = $this->orderHeadRepository->getPendingFulfillments($this->codigoCia);

        if (empty($despachos)) {
            Logger::log($this->logFile, "No hay órdenes para procesar.");
            echo "NO SE TIENEN ORDENES PARA PROCESAR FULFILLMENT (ARREGLO VACIO).";
            return;
        }
        $orderIdsQuery = "id:";
        $first = 0;
        foreach ($despachos as $despacho) {
            $orderId = $despacho['order_id'];
            $orderIdsQuery = $orderIdsQuery . $orderId . " OR id:";
            $first++;
        }
        $orderIdsQuery = substr($orderIdsQuery, 0, -12);

        try {
            $fulfillmentOrders = $this->shopifyHelper->getFulfillmentDataByOrderIds($orderIdsQuery, $first);
            if ($fulfillmentOrders) {
                foreach ($fulfillmentOrders as $fulfillmentOrder) {
                    $orderId = $fulfillmentOrder['id'];
                    $fulfillmentData = $fulfillmentOrder['fulfillmentOrders']['nodes'][0];
                    if (isset($fulfillmentData['status']) && $fulfillmentData['status'] == 'OPEN') {

                        $response = $this->shopifyHelper->createFulfillment($fulfillmentData['id']);

                        if (isset($response['data']['fulfillmentCreate']['userErrors']) && !empty($response['data']['fulfillmentCreate']['userErrors'])) {
                            Logger::log($this->logFile, "Error en la solicitud para la orden $orderId: " . json_encode($response['data']['fulfillmentCreate']['userErrors']));
                        } else {
                            $this->orderHeadRepository->updateOrderFulfillmentStatus($orderId, $this->codigoCia);
                            Logger::log($this->logFile, "Orden $orderId procesada con éxito.");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log($this->logFile, "Error procesando el fulfillment para la orden $orderId: " . $e->getMessage());

        }


        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
        echo "Termino!";
    }
}
