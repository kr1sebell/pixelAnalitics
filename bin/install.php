#!/usr/bin/env php
<?php
require __DIR__ . '/../bootstrap.php';

use Analytics\AnalyticsInstaller;
use Database\ConnectionManager;

$connectionManager = new ConnectionManager($config);
$analytics = $connectionManager->getAnalytics();

$installer = new AnalyticsInstaller($analytics);
$installer->install();

echo "Analytics schema installed successfully." . PHP_EOL;
