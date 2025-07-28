<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\CreateProducts;

$storeUrl = 'friko-ecommerce.myshopify.com';
$skus = $_GET["skus"];
$cronJob = new CreateProducts($storeUrl, $skus);
$cronJob->run();
