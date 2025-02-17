<?php

namespace App\Webhooks;

use App\Helpers\Logger;
use App\Helpers\StoreConfigFactory;

abstract class BaseManualWebhook
{
    protected $storeUrl;
    protected $topic;
    protected $data;
    protected $appSecretKey;
    protected $logFile;

    public function __construct($webhookData, $logFile, $storeUrl, $topic)
    {
        $this->logFile = $logFile ?? "wh_run.txt";
        $this->storeUrl = $storeUrl;
        Logger::log($this->logFile, "Tienda: $this->storeUrl");
        Logger::log($this->logFile, "webhookData: " . json_encode($webhookData));
        $this->topic = $topic;
        $this->data = $webhookData;
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($this->storeUrl);
        $this->appSecretKey = $config['appSecretKey'];
    }

    public function verifyWebhook()
    {
        return true;
    }

    abstract public function handleWebhook();
}
