<?php
require_once __DIR__ . '/../backend/vendor/autoload.php';

use PixelAnalytics\Config;
use PixelAnalytics\Etl\BuildSummaries;
use PixelAnalytics\Helpers\Logger;

Config::init();

$logFile = __DIR__ . '/../scripts/logs/etl_build_summaries.log';
$logger = new Logger($logFile);
$builder = new BuildSummaries($logger);

try {
    $builder->run();
    echo "Summaries rebuilt\n";
    exit(0);
} catch (Exception $e) {
    $logger->error('Build summaries failed', array('error' => $e->getMessage()));
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
