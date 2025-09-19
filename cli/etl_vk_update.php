<?php
require_once __DIR__ . '/../backend/vendor/autoload.php';

use PixelAnalytics\Config;
use PixelAnalytics\Etl\EnrichVk;
use PixelAnalytics\Etl\VkClient;
use PixelAnalytics\Helpers\Logger;

Config::init();

$logFile = __DIR__ . '/../scripts/logs/etl_vk_update.log';
$logger = new Logger($logFile);
$client = new VkClient($logger);
$enrich = new EnrichVk($logger, $client);

try {
    $candidates = $enrich->findPendingUsers(500);
    $result = $enrich->updateProfiles($candidates);
    echo sprintf("Updated: %d, Missed: %d\n", $result['updated'], $result['missed']);
    exit(0);
} catch (Exception $e) {
    $logger->error('VK update failed', array('error' => $e->getMessage()));
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
