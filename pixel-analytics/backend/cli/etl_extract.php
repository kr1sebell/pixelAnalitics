#!/usr/bin/php
<?php
require_once __DIR__ . '/../src/Etl/Extract.php';

try {
    $etl = new Extract();
    $etl->run();
} catch (Exception $e) {
    fwrite(STDERR, 'ETL extract failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
