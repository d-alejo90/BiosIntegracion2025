<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Webhooks\CreateManualOrderWebhook;

require_once __DIR__ . '/../../src/Webhooks/CreateManualOrder.php';

// $webhookData = file_get_contents('php://input');
$webhookData = file_get_contents(__DIR__ . '/test_order.json');
$logFile = "create_manual_order_wh_run.txt";
// $webhook = new CreateManualOrderWebhook($webhookData, $logFile, "friko-ecommerce.myshopify.com", "orders/create");
$webhook = new CreateManualOrderWebhook($webhookData, $logFile, "mizooco.myshopify.com", "orders/create");
$webhook->handleWebhook();
