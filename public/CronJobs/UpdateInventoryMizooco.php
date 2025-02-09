<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\UpdateInventory;

$storeUrl = 'mizooco.myshopify.com';
$cronJob = new UpdateInventory($storeUrl);
$cronJob->run();
