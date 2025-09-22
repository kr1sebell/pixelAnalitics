<?php
require __DIR__ . '/../bootstrap.php';

use Analytics\MarketingDashboardService;
use Analytics\MarketingDashboardFormatter; // добавляем сюда!
use Database\ConnectionManager;
use Segmentation\DimensionConfig;

$connectionManager = new ConnectionManager($config);
$analytics = $connectionManager->getAnalytics();

$yandexApiKey = $config['yandex_api_key'] ?? '';

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
$availableStatusesFromDb = $dashboardService->getAvailableStatuses();

$statusLabels = [
    1 => 'Оплачен',
    6 => 'Перебит',
    3 => 'Отменён',
    0 => 'Не оплачен',
];

// Всегда показываем ключевые статусы первыми, даже если их нет в текущей выборке.
$preferredStatusOrder = [1, 6, 3, 0];
$statusOrder = [];
foreach (array_merge($preferredStatusOrder, $availableStatusesFromDb) as $statusValue) {
    $statusInt = (int) $statusValue;
    if (!in_array($statusInt, $statusOrder, true)) {
        $statusOrder[] = $statusInt;
    }
}

if ($statusOrder === []) {
    $statusOrder = array_keys($statusLabels);
}

$statusSummary = $dashboardService->getStatusSummary($start, $end);

$statusOptions = [];
$statusStats = [];
foreach ($statusOrder as $statusValue) {
    $statusInt = (int) $statusValue;
    $statusOptions[$statusInt] = $statusLabels[$statusInt] ?? ('Статус #' . $statusInt);
    $summary = $statusSummary[$statusInt] ?? ['total_orders' => 0, 'total_revenue' => 0.0];
    $statusStats[$statusInt] = [
        'total_orders' => (int) ($summary['total_orders'] ?? 0),
        'total_revenue' => (float) ($summary['total_revenue'] ?? 0),
    ];
}

$preferredDefaults = [1, 6, 3, 0];
$defaultStatus = null;
if ($statusOptions !== []) {
foreach ($preferredDefaults as $preferred) {
        if (!array_key_exists($preferred, $statusOptions)) {
            continue;
        }

        if (($statusStats[$preferred]['total_orders'] ?? 0) > 0) {
        $defaultStatus = $preferred;
        break;
    }

        if ($defaultStatus === null) {
            $defaultStatus = $preferred;
        }
    }

    if ($defaultStatus !== null && ($statusStats[$defaultStatus]['total_orders'] ?? 0) === 0) {
        foreach ($statusOptions as $statusValue => $_label) {
            if (($statusStats[$statusValue]['total_orders'] ?? 0) > 0) {
                $defaultStatus = $statusValue;
                break;
            }
        }
    }

if ($defaultStatus === null) {
    $defaultStatus = array_key_first($statusOptions);
}
}

$statusParam = $_GET['status'] ?? null;
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

if ($selectedStatus === null && $statusOptions !== []) {
    $selectedStatus = array_key_first($statusOptions);
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


// === строим профили из $allMetrics ===
//$profiles = [];
//
//// список измерений, которые учитываем
//$dimensions = ['gender','age_group','city_id','occupation','weekday','payment_type'];
//
//foreach ($allMetrics as $dim => $block) {
//    // игнорим измерения, которых нет в списке
//    if (!in_array($dim, $dimensions, true)) {
//        continue;
//    }
//    foreach ($block['current'] as $row) {
//        $value = MarketingDashboardFormatter::formatDimensionValue($dim, $row['dimension_value'], $cityMap);
//        $keyParts = [];
//        foreach ($dimensions as $d) {
//            // если есть метрика для этого измерения
//            if ($d === $dim) {
//                $keyParts[] = $value;
//            } else {
//                $keyParts[] = null; // placeholder
//            }
//        }
//        $profileKey = implode(' / ', $keyParts);
//
//        if (!isset($profiles[$profileKey])) {
//            $profiles[$profileKey] = [
//                'gender' => $dim === 'gender' ? $value : '—',
//                'age_group' => $dim === 'age_group' ? $value : '—',
//                'city' => $dim === 'city_id' ? $value : '—',
//                'occupation' => $dim === 'occupation' ? $value : '—',
//                'weekday' => $dim === 'weekday' ? $value : '—',
//                'payment_type' => $dim === 'payment_type' ? $value : '—',
//                'total_revenue' => 0,
//                'total_orders' => 0,
//                'total_customers' => 0,
//            ];
//        }
//
//        $profiles[$profileKey]['total_revenue'] += (float)($row['total_revenue'] ?? 0);
//        $profiles[$profileKey]['total_orders']  += (int)($row['total_orders'] ?? 0);
//        $profiles[$profileKey]['total_customers'] += (int)($row['total_customers'] ?? 0);
//    }
//}
//
//// сортируем по выручке
//usort($profiles, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);
//
//// оставляем ТОП-3
//$topProfiles = array_slice($profiles, 0, 3);


//Новый вариант
//$topProfiles = [];
//
//// копия метрик для работы
//$metricsCopy = $allMetrics;
//
//for ($i = 1; $i <= 3; $i++) {
//    $profile = [
//        'gender' => '—',
//        'age_group' => '—',
//        'city' => '—',
//        'occupation' => '—',
//        'weekday' => '—',
//        'payment_type' => '—',
//        'total_revenue' => 0,
//        'total_orders' => 0,
//        'total_customers' => 0,
//    ];
//
//    foreach ($metricsCopy as $dim => $block) {
//        if (empty($block['current'])) {
//            continue;
//        }
//
//        // ищем максимальный сегмент по выручке
//        $topRow = null;
//        foreach ($block['current'] as $row) {
//            if ($topRow === null || ($row['total_revenue'] ?? 0) > ($topRow['total_revenue'] ?? 0)) {
//                $topRow = $row;
//            }
//        }
//
//        if ($topRow) {
//            $profile[$dim] = MarketingDashboardFormatter::formatDimensionValue($dim, $topRow['dimension_value'], $cityMap);
//            $profile['total_revenue'] += (float) ($topRow['total_revenue'] ?? 0);
//            $profile['total_orders'] += (int) ($topRow['total_orders'] ?? 0);
//            $profile['total_customers'] += (int) ($topRow['total_customers'] ?? 0);
//        }
//    }
//
//    $topProfiles[] = $profile;
//
//    // чтобы следующий портрет был другим, вырезаем выбранные сегменты
//    foreach ($metricsCopy as $dim => &$block) {
//        $block['current'] = array_filter($block['current'], function($row) use ($profile, $dim, $cityMap) {
//            $label = MarketingDashboardFormatter::formatDimensionValue($dim, $row['dimension_value'], $cityMap);
//            return $label !== $profile[$dim];
//        });
//    }
//    unset($block);
//}

//Без Не определено
$topProfiles = [];

// копия метрик для работы
$metricsCopy = $allMetrics;

for ($i = 1; $i <= 3; $i++) {
    $profile = [
        'gender' => '—',
        'age_group' => '—',
        'city' => '—',
        'occupation' => '—',
        'weekday' => '—',
        'payment_type' => '—',
        'total_revenue' => 0,
        'total_orders' => 0,
        'total_customers' => 0,
    ];

    foreach ($metricsCopy as $dim => $block) {
        if (empty($block['current'])) {
            continue;
        }

        // фильтруем "Не определено"
        $filtered = array_filter($block['current'], function($row) use ($dim, $cityMap) {
            $label = MarketingDashboardFormatter::formatDimensionValue($dim, $row['dimension_value'], $cityMap);
            return $label !== 'Не определено';
        });

        if (empty($filtered)) {
            continue;
        }

        // ищем максимальный сегмент по выручке
        $topRow = null;
        foreach ($filtered as $row) {
            if ($topRow === null || ($row['total_revenue'] ?? 0) > ($topRow['total_revenue'] ?? 0)) {
                $topRow = $row;
            }
        }

        if ($topRow) {
            $profile[$dim] = MarketingDashboardFormatter::formatDimensionValue($dim, $topRow['dimension_value'], $cityMap);
            $profile['total_revenue'] += (float) ($topRow['total_revenue'] ?? 0);
            $profile['total_orders'] += (int) ($topRow['total_orders'] ?? 0);
            $profile['total_customers'] += (int) ($topRow['total_customers'] ?? 0);
        }
    }

    // если в профиле не набралось данных — пропускаем
    if ($profile['total_revenue'] > 0) {
        $topProfiles[] = $profile;
    }

    // убираем использованные сегменты, чтобы в следующем портрете были другие
    foreach ($metricsCopy as $dim => &$block) {
        $block['current'] = array_filter($block['current'], function($row) use ($profile, $dim, $cityMap) {
            $label = MarketingDashboardFormatter::formatDimensionValue($dim, $row['dimension_value'], $cityMap);
            return $label !== $profile[$dim] && $label !== 'Не определено';
        });
    }
    unset($block);
}



$allSegments = [];
foreach ($allMetrics as $dim => $block) {
    foreach ($block['current'] as $row) {
        $allSegments[] = [
            'dimension' => $dim,
            'label' => MarketingDashboardFormatter::formatDimensionValue($dim, $row['dimension_value'], $cityMap),
            'total_revenue' => (float)($row['total_revenue'] ?? 0),
            'total_orders' => (int)($row['total_orders'] ?? 0),
            'total_customers' => (int)($row['total_customers'] ?? 0),
        ];
    }
}

// сортируем по выручке
usort($allSegments, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

// берём ТОП-3
$topSegments = array_slice($allSegments, 0, 3);

// ВАЖНО: добавляем выборку заказов для карты
$ordersWithCoords = $dashboardService->getOrdersWithCoords($start, $end, $selectedStatuses);

// отфильтровать мусор
$ordersWithCoords = array_values(array_filter($ordersWithCoords, function ($o) {
    return isset($o['latitude'], $o['longitude'])
        && is_numeric($o['latitude'])
        && is_numeric($o['longitude']);
}));


require __DIR__ . '/templates/index13.phtml';
