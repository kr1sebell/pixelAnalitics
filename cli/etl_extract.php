<?php
require_once __DIR__ . '/../backend/vendor/autoload.php';

use PixelAnalytics\Config;
use PixelAnalytics\Etl\Extract;
use PixelAnalytics\Etl\WatermarkStore;
use PixelAnalytics\Helpers\Logger;

Config::init();

$logFile = __DIR__ . '/../scripts/logs/etl_extract.log';
$logger = new Logger($logFile);
$watermarks = new WatermarkStore();
$extract = new Extract($logger, $watermarks);

try {
    $result = $extract->run();
    echo sprintf("Orders: %d, Items: %d, Users: %d\n", $result['orders'], $result['order_items'], $result['users']);
    exit(0);
} catch (Exception $e) {
    $logger->error('ETL extract failed', array('error' => $e->getMessage()));
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
