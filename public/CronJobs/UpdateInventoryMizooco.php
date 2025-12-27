<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\UpdateInventory;

$storeUrl = 'mizooco.myshopify.com';
$location = $_GET["location"] ?? null;
$cronJob = new UpdateInventory($storeUrl, true, $location);
$cronJob->run();
