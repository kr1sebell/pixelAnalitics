#!/usr/bin/php
<?php
require_once __DIR__ . '/../src/Etl/EnrichVk.php';

try {
    $etl = new EnrichVk();
    $etl->run();
} catch (Exception $e) {
    fwrite(STDERR, 'ETL VK update failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
