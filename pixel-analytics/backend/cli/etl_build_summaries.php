#!/usr/bin/php
<?php
require_once __DIR__ . '/../src/Etl/BuildSummaries.php';

try {
    $etl = new BuildSummaries();
    $etl->run();
} catch (Exception $e) {
    fwrite(STDERR, 'ETL build summaries failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
