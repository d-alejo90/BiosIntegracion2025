<?php

namespace App\Webhooks;

use App\Services\CancelOrderService;
use App\Helpers\Logger;


class CancelOrderWebhook extends BaseWebhook
{
  public function handleWebhook()
  {
    try {
      Logger::log($this->logFile, "Start Run " . date('Y-m-d H:i:s'));
      if (!$this->verifyWebhook()) {
        throw new \Exception("No se pudo verificar el webhook.", 1);
      }
      Logger::log($this->logFile, "Data JSON: " . json_encode($this->data));
      // Obtener la URL de la tienda desde el payload de Shopify
      Logger::log($this->logFile, "Tienda: $this->storeUrl");
      // $storeUrl = 'friko-ecommerce.myshopify.com';
      // Crear el OrderService con la configuración de la tienda
      $orderService = new CancelOrderService($this->storeUrl);
      $orderService->cancelOrder($this->data);
      Logger::log($this->logFile, "Orden Cancelada");
      print "Orden Cancelada";
      http_response_code(200);
    } catch (\Exception $e) {
      Logger::log($this->logFile, "Error cancelando la orden: " . $e->getMessage());
      print "Error cancelando la orden: " . $e->getMessage();
      http_response_code(200); // siempre debemos retornar 200 en los webhooks de Shopify
    }

    Logger::log($this->logFile, "End Run " . date('Y-m-d H:i:s') . "\n===========================\n");
  }
}
