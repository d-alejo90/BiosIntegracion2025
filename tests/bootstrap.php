<?php

// Test bootstrap file
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Set test environment constants if needed
if (!defined('TESTING')) {
    define('TESTING', true);
}

// You can add any global test setup here
// For example, setting up test database connections, mocking global functions, etc.