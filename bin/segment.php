#!/usr/bin/env php
<?php
require __DIR__ . '/../bootstrap.php';

use Database\ConnectionManager;
use Segmentation\SegmentCalculator;

$options = getopt('', ['start::', 'end::']);

$end = isset($options['end']) ? new DateTime($options['end']) : new DateTime();
$start = isset($options['start']) ? new DateTime($options['start']) : (clone $end)->modify('-' . ($config['segmentation']['default_period_days'] - 1) . ' days');

if ($start > $end) {
    [$start, $end] = [$end, $start];
}

$connectionManager = new ConnectionManager($config);
$analytics = $connectionManager->getAnalytics();

$calculator = new SegmentCalculator($analytics);
$calculator->buildMetrics($start, $end);

echo sprintf(
    "Segmentation metrics generated for %s — %s" . PHP_EOL,
    $start->format('Y-m-d'),
    $end->format('Y-m-d')
);
