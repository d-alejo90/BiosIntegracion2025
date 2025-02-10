<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\UpdatePrices;

$storeUrl = 'friko-ecommerce.myshopify.com';
$cronJob = new UpdatePrices($storeUrl);
$cronJob->run();
