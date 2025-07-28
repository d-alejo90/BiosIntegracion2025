<?php

namespace App\CronJobs;

use App\Repositories\OrderHeadRepository;
use App\Repositories\CronJobControlRepository;
use App\Helpers\Logger;
use App\Helpers\ShopifyHelper;
use App\Helpers\StoreConfigFactory;

class ProcessFulfillments
{
    private $orderHeadRepository;
    private $cronJobControlRepository;
    private $shopifyHelper;
    private $codigoCia;
    private $logFile;
    private $storeName;
    private $saveMode;

    public function __construct($storeUrl, $saveMode = true)
    {
        $this->orderHeadRepository = new OrderHeadRepository();
        $this->cronJobControlRepository = new CronJobControlRepository();
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->storeName = $config['storeName'];
        $this->codigoCia = $config['codigoCia'];
        $this->shopifyHelper = new ShopifyHelper($config['shopifyConfig']);
        $this->logFile = "cron_fulfillment_$this->storeName.txt";
        $this->saveMode = $saveMode;
    }

    public function run()
    {
        $cronName = 'fulfillments_' . $this->storeName;
        Logger::log($this->logFile, "Start Run $cronName " . date('Y-m-d H:i:s'));
        echo "Start Run $cronName " . date('Y-m-d H:i:s') . "\n===========================\n";

        $cronIsOn = $this->cronJobControlRepository->getStatusByCronName($cronName);
        if (!$cronIsOn) {
            echo "<p>El cron $cronName no esta activo </p>";
            echo "End Run $cronName" . date('Y-m-d H:i:s');
            return;
        }
        $despachos = $this->orderHeadRepository->getPendingFulfillments($this->codigoCia);

        if (empty($despachos)) {
            Logger::log($this->logFile, "No hay órdenes para procesar.");
            echo "NO SE TIENEN ORDENES PARA PROCESAR FULFILLMENT (ARREGLO VACIO).\n";
            return;
        }
        $orderIdsQuery = "id:";
        $first = 0;
        foreach ($despachos as $despacho) {
            $orderId = $despacho['order_id'];
            $orderIdsQuery = $orderIdsQuery . $orderId . " OR id:";
            $first++;
        }
        $orderIdsQuery = substr($orderIdsQuery, 0, -7);

        try {
            $fulfillmentOrders = $this->shopifyHelper->getFulfillmentDataByOrderIds($orderIdsQuery, $first);
            sleep(1); // Esperamos 1 segundo para evitar problemas de rate limit
            if ($fulfillmentOrders) {
                foreach ($fulfillmentOrders as $fulfillmentOrder) {
                    $orderId = $fulfillmentOrder['id'];
                    $fulfillmentData = $fulfillmentOrder['fulfillmentOrders']['nodes'][0];

                    if (isset($fulfillmentData['status']) && $fulfillmentData['status'] == 'CLOSED') {
                        $this->orderHeadRepository->updateOrderFulfillmentStatus($orderId, $this->codigoCia);
                        continue;
                    }

                    if (isset($fulfillmentData['status']) && $fulfillmentData['status'] == 'OPEN') {

                        $response = $this->shopifyHelper->createFulfillment($fulfillmentData['id'], $this->saveMode);

                        if (isset($response['data']['fulfillmentCreate']['userErrors']) && !empty($response['data']['fulfillmentCreate']['userErrors']) && $this->saveMode) {
                            Logger::log($this->logFile, "Error en la solicitud para la orden $orderId: " . json_encode($response['data']['fulfillmentCreate']['userErrors']));
                            echo "Error en la solicitud para la orden $orderId\n";
                        } else {
                            if ($this->saveMode) {
                                $this->orderHeadRepository->updateOrderFulfillmentStatus($orderId, $this->codigoCia);
                            }
                            Logger::log($this->logFile, "Orden $orderId procesada con éxito.");
                            echo "Orden $orderId procesada con éxito.\n";
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log($this->logFile, "Error procesando el fulfillment para la orden $orderId: " . $e->getMessage());
            echo "Error procesando el fulfillment para la orden $orderId\n";
        }


        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
        echo "End Run " . date('Y-m-d H:i:s') . "\n===========================\n";
    }
}
