<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\UpdatePrices;

$storeUrl = 'mizooco.myshopify.com';
$location = $_GET["location"] ?? null;
$dryrun = isset($_GET["dryrun"]) ? (bool)$_GET["dryrun"] : false;
$saveMode = !$dryrun;
$cronJob = new UpdatePrices($storeUrl, $saveMode, $location);
$cronJob->run();
