<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Webhooks\CreateOrderWebhook;

require_once __DIR__ . '/../src/Webhooks/CreateOrder.php';

$webhookData = file_get_contents('php://input');
$logFile = "create_order_wh_run.txt";
$webhook = new CreateOrderWebhook($webhookData, $logFile);
$webhook->handleWebhook();
