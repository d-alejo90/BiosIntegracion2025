<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\CreateProducts;

$storeUrl = 'mizooco.myshopify.com';
$cronJob = new CreateProducts($storeUrl);
$cronJob->run();
