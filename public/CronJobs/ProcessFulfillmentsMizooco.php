<?php

require_once('/../vendor/autoload.php');

use App\CronJobs\ProcessFulfillments;
$storeUrl = 'mizooco.myshopify.com';
$cronJob = new ProcessFulfillments($storeUrl);
$cronJob->run();
