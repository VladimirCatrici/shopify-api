<?php
require __DIR__ . '/../src/Shopify/API.php';
require __DIR__ . '/../src/Shopify/Collection.php';
require __DIR__ . '/../src/Shopify/Exceptions/RequestException.php';

$dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
$dotenv->load();
