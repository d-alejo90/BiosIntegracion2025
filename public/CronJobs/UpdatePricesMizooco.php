<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\UpdatePrices;

$storeUrl = 'mizooco.myshopify.com';
$location = $_GET["location"] ?? null;
$cronJob = new UpdatePrices($storeUrl, true, $location);
$cronJob->run();
