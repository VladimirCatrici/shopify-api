<?php
require __DIR__ . '/../src/API.php';
require __DIR__ . '/../src/Collection.php';
require __DIR__ . '/../src/Exception/RequestException.php';
require __DIR__ . '/../src/PaginationType.php';
require __DIR__ . '/../src/functions.php';

if (file_exists(__DIR__ . '/../.env')) {
    /*
     * This file is missing on running tests in Travis.
     * The file_exists() condition added to avoid Exception.
     */
    $dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
    $dotenv->load();
}
