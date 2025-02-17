<?php

namespace App\Webhooks;

use App\Helpers\Logger;
use App\Helpers\StoreConfigFactory;

abstract class BaseWebhook
{
    protected $storeUrl;
    protected $topic;
    protected $shopifyHmac;
    protected $data;
    protected $appSecretKey;
    protected $logFile;

    public function __construct($webhookData, $logFile)
    {
        $this->logFile = $logFile ?? "wh_run.txt";
        Logger::log($this->logFile, "Server: " . json_encode($_SERVER));
        $this->storeUrl = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
        Logger::log($this->logFile, "Tienda: $this->storeUrl");
        $this->topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC'];
        $this->shopifyHmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
        $this->data = $webhookData;
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($this->storeUrl);
        $this->appSecretKey = $config['appSecretKey'];
    }

    public function verifyWebhook()
    {
        // Verificar la firma HMAC
        if (!$this->shopifyHmac) {
            Logger::log($this->logFile, "No se recibió una firma HMAC válida.");
            return false;
        }
        if (!$this->data) {
            Logger::log($this->logFile, "No se recibió un payload válido.");
            return false;
        }

        $calculatedHmac = base64_encode(hash_hmac('sha256', $this->data, $this->appSecretKey, true));
        if (!hash_equals($this->shopifyHmac, $calculatedHmac)) {
            Logger::log($this->logFile, "Firma HMAC no válida.");
            return false;
        }
        return true;
    }

    abstract public function handleWebhook();
}
