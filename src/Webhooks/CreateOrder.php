<?php

namespace App\Webhooks;

use App\Services\OrderService;
use App\Helpers\Logger;


class CreateOrderWebhook extends BaseWebhook
{
    public function handleWebhook()
    {
        try {
            Logger::log('wh_run.txt', "Start Run " . date('Y-m-d H:i:s'));
            // $data = file_get_contents(__DIR__ . '/../Helpers/OrderTest.json');
            if (!$this->verifyWebhook()) {
                throw new \Exception("No se pudo verificar el webhook.", 1);
            }
            Logger::log('wh_run.txt', "Data JSON: " . json_encode($this->data));
            // Obtener la URL de la tienda desde el payload de Shopify
            Logger::log('wh_run.txt', "Tienda: $this->storeUrl");
            // $storeUrl = 'friko-ecommerce.myshopify.com';
            // Crear el OrderService con la configuración de la tienda
            $orderService = new OrderService($this->storeUrl);
            $orderService->processOrder($this->data);
            Logger::log('wh_run.txt', "Orden procesada con éxito");
            print "Orden procesada con éxito";
            http_response_code(200);
        } catch (\Exception $e) {
            Logger::log('wh_run.txt', "Error procesando la orden: " . $e->getMessage());
            print "Error procesando la orden: " . $e->getMessage();
            http_response_code(200); // siempre debemos retornar 200 en los webhooks de Shopify
        }

        Logger::log('wh_run.txt', "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
    }
}
