<?php
require __DIR__ . '/../src/API.php';
require __DIR__ . '/../src/Collection.php';
require __DIR__ . '/../src/Exceptions/RequestException.php';

$dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
$dotenv->load();
