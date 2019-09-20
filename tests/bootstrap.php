<?php
require __DIR__ . '/../src/API.php';
require __DIR__ . '/../src/Collection.php';
require __DIR__ . '/../src/Exception/RequestException.php';
require __DIR__ . '/../src/PaginationType.php';

$dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
$dotenv->load();
