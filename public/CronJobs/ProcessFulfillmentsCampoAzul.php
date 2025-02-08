<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\ProcessFulfillments;

$storeUrl = 'friko-ecommerce.myshopify.com';
$cronJob = new ProcessFulfillments($storeUrl);
$cronJob->run();
