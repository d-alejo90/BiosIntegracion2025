<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\CreateProducts;

$storeUrl = 'friko-ecommerce.myshopify.com';
$skus = $_GET["skus"] ?? null;
$location = $_GET["location"] ?? null;
$cronJob = new CreateProducts($storeUrl, $skus, true, $location);
$cronJob->run();
