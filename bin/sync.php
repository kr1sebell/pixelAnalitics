#!/usr/bin/env php
<?php
require __DIR__ . '/../bootstrap.php';

use Database\ConnectionManager;
use ETL\DataSyncService;
use ETL\VkProfileSyncService;
use Vk\VkApiClient;

$connectionManager = new ConnectionManager($config);
$source = $connectionManager->getSource();
$analytics = $connectionManager->getAnalytics();

$sync = new DataSyncService($source, $analytics);
$full = in_array('--full', $argv, true);
//$sync->sync($full);

echo "Orders and users synchronized." . PHP_EOL;

$vkIds = $analytics->getCol(
    'SELECT vk_id FROM analytics_users 
     WHERE vk_id IS NOT NULL AND vk_id <> 0 AND vk_synced = 0'
);

if ($vkIds) {
    $vkClient = new VkApiClient($config['vk']);
    $vkSync = new VkProfileSyncService($analytics, $vkClient);
    $vkSync->sync(array_map('intval', $vkIds));
    echo "VK profiles synchronized." . PHP_EOL;
} else {
    echo "No VK profiles to synchronize." . PHP_EOL;
}
