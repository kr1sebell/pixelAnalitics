<?php
require __DIR__ . '/../bootstrap.php';

use Analytics\MarketingDashboardService;
use Database\ConnectionManager;
use Segmentation\DimensionConfig;

$connectionManager = new ConnectionManager($config);
$analytics = $connectionManager->getAnalytics();

$dimensionList = DimensionConfig::list();
$startParam = $_GET['start'] ?? (new DateTime('-30 days'))->format('Y-m-d');
$endParam   = $_GET['end']   ?? (new DateTime())->format('Y-m-d');

$start = new DateTime($startParam);
$end   = new DateTime($endParam);
if ($start > $end) {
    [$start, $end] = [$end, $start];
}

$periodLength = $start->diff($end)->days + 1;
$prevEnd = (clone $start)->modify('-1 day');
$prevStart = (clone $prevEnd)->modify('-' . ($periodLength - 1) . ' days');

$dashboardService = new MarketingDashboardService($analytics);
$availableStatuses = $dashboardService->getAvailableStatuses();

$statusLabels = [
    1 => 'Оплачен',
    6 => 'Перебит',
    3 => 'Отменён',
    0 => 'Черновик',
];

if ($availableStatuses === []) {
    $availableStatuses = array_keys($statusLabels);
}

$statusOptions = [];
foreach ($availableStatuses as $statusValue) {
    $statusInt = (int) $statusValue;
    $statusOptions[$statusInt] = $statusLabels[$statusInt] ?? ('Статус #' . $statusInt);
}

$defaultStatuses = array_values(array_intersect([1, 6], array_keys($statusOptions)));
if ($defaultStatuses === []) {
    $defaultStatuses = array_keys($statusOptions);
}

$statusParam = $_GET['status'] ?? $defaultStatuses;
if (!is_array($statusParam)) {
    $statusParam = [$statusParam];
}

$selectedStatuses = [];
foreach ($statusParam as $statusValue) {
    if (is_numeric($statusValue)) {
        $statusInt = (int) $statusValue;
        if (array_key_exists($statusInt, $statusOptions)) {
            $selectedStatuses[] = $statusInt;
        }
    }
}
$selectedStatuses = array_values(array_unique($selectedStatuses));
if ($selectedStatuses === []) {
    $selectedStatuses = $defaultStatuses;
}
sort($selectedStatuses);

$cityMap = $dashboardService->getCityMap($selectedStatuses);

$allMetrics = [];
foreach (array_keys($dimensionList) as $dimension) {
    $allMetrics[$dimension] = [
        'current' => $dashboardService->getMetrics($dimension, $start, $end, $selectedStatuses),
        'previous' => $dashboardService->getMetrics($dimension, $prevStart, $prevEnd, $selectedStatuses),
        'products' => $dashboardService->getTopProducts($dimension, $start, $end, $selectedStatuses),
    ];
}

$totals = $dashboardService->getTotals($start, $end, $selectedStatuses);
$totalsPrev = $dashboardService->getTotals($prevStart, $prevEnd, $selectedStatuses);

require __DIR__ . '/templates/index13.phtml';
