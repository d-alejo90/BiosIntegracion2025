<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Webhooks\CancelOrderWebhook;

require_once __DIR__ . '/../src/Webhooks/CreateOrder.php';

$webhookData = file_get_contents('php://input');
$logFile = "cancel_order_wh_run.txt";
$webhook = new CancelOrderWebhook($webhookData, $logFile);
$webhook->handleWebhook();
