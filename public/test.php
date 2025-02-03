<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Webhooks\CreateOrderWebhook;
use App\Helpers\Logger;

require_once __DIR__ . '/../src/Webhooks/CreateOrder.php';

$webhookData = file_get_contents('php://input');
$webhook = new CreateOrderWebhook($webhookData);
$webhook->handleWebhook();
