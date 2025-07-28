<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Webhooks/CreateOrder.php';
require_once __DIR__ . '/../../src/Helpers/Logger.php';

use App\Webhooks\CreateOrderWebhook;
use App\Helpers\Logger;

$webhookData = file_get_contents('php://input');
$logFile = "create_order_wh_run.txt";
Logger::log($logFile, $webhookData);

$webhook = new CreateOrderWebhook($webhookData, $logFile, false);
$webhook->handleWebhook();
