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
    0 => 'Не оплачен',
];

if ($availableStatuses === []) {
    $availableStatuses = array_keys($statusLabels);
}

$statusOptions = [];
foreach ($availableStatuses as $statusValue) {
    $statusInt = (int) $statusValue;
    $statusOptions[$statusInt] = $statusLabels[$statusInt] ?? ('Статус #' . $statusInt);
}

$preferredDefaults = [1, 6];
$defaultStatus = null;
foreach ($preferredDefaults as $preferred) {
    if (array_key_exists($preferred, $statusOptions)) {
        $defaultStatus = $preferred;
        break;
    }
}
if ($defaultStatus === null) {
    $defaultStatus = array_key_first($statusOptions);
}

$statusParam = $_GET['status'] ?? $defaultStatus;
if (is_array($statusParam)) {
    $statusParam = reset($statusParam);
}

$selectedStatus = $defaultStatus;
if ($statusParam !== null && $statusParam !== '') {
    if (is_numeric($statusParam)) {
        $candidate = (int) $statusParam;
        if (array_key_exists($candidate, $statusOptions)) {
            $selectedStatus = $candidate;
        }
    }
}

$selectedStatuses = $selectedStatus !== null ? [$selectedStatus] : [];
if ($selectedStatuses !== []) {
    sort($selectedStatuses);
}

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
