<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Webhooks\CancelOrderWebhook;

$webhookData = file_get_contents('php://input');
$logFile = "cancel_order_wh_run.txt";
$webhook = new CancelOrderWebhook($webhookData, $logFile);
$webhook->handleWebhook();
