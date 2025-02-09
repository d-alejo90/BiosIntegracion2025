<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\UpdateInventory;

$storeUrl = 'friko-ecommerce.myshopify.com';
$cronJob = new UpdateInventory($storeUrl);
$cronJob->run();
