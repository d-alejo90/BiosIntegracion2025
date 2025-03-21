<?php

namespace App\Webhooks;

use App\Helpers\Logger;
use App\Services\CreateOrderService;

class CreateManualOrderWebhook extends BaseManualWebhook
{
    public function handleWebhook()
    {
        try {
            Logger::log($this->logFile, "Start Run " . date('Y-m-d H:i:s'));
            if (!$this->verifyWebhook()) {
                throw new \Exception("No se pudo verificar el webhook.", 1);
            }
            Logger::log($this->logFile, "Data JSON: " . print_r($this->data, true));
            // Obtener la URL de la tienda desde el payload de Shopify
            Logger::log($this->logFile, "Tienda: $this->storeUrl");
            // Crear el OrderService con la configuración de la tienda
            $orderService = new CreateOrderService($this->storeUrl);
            $orderService->processOrder($this->data);
            Logger::log($this->logFile, "Orden procesada con éxito");
            print "Orden procesada con éxito";
            http_response_code(200);
        } catch (\Exception $e) {
            Logger::log($this->logFile, "Exception procesando la orden: " . $e->getMessage());
            Logger::log($this->logFile, $e->getTraceAsString());
            print "Exception procesando la orden: " . $e->getMessage();
        } catch (\Error $err) {
            Logger::log($this->logFile, "Error procesando la orden: " . $err->getMessage());
            Logger::log($this->logFile, $err->getTraceAsString());
            print "Error procesando la orden: " . $err->getMessage();
        }
        http_response_code(200); // siempre debemos retornar 200 en los webhooks de Shopify
        Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
    }
}
