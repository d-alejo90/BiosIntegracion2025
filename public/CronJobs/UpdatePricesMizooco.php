<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\UpdatePrices;

$storeUrl = 'mizooco.myshopify.com';
$cronJob = new UpdatePrices($storeUrl);
$cronJob->run();
