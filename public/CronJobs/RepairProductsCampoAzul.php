<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\CronJobs\RepairProducts;

$storeUrl = 'campo-azul.myshopify.com';
$skus = $_GET['skus'] ?? null;
$processAll = isset($_GET['all']) && ($_GET['all'] === '1' || $_GET['all'] === 'true');
$dryRun = isset($_GET['dry-run']) && ($_GET['dry-run'] === '1' || $_GET['dry-run'] === 'true');

$cronJob = new RepairProducts($storeUrl, $skus, $processAll, $dryRun);
$cronJob->run();
