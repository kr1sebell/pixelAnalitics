<?php
use Analytics\MarketingDashboardFormatter;

$formatValue = static function (float|int|null $value, string $format): string {
    $value ??= 0;
    switch ($format) {
        case 'currency':
            return number_format((float) $value, 0, ',', ' ') . ' ₽';
        case 'decimal':
            return number_format((float) $value, 2, ',', ' ');
        default:
            return number_format((float) $value, 0, ',', ' ');
    }
};

$summaryMetrics = [
    ['label' => 'Выручка', 'key' => 'total_revenue', 'format' => 'currency'],
    ['label' => 'Заказы', 'key' => 'total_orders', 'format' => 'number'],
    ['label' => 'Клиенты', 'key' => 'total_customers', 'format' => 'number'],
    ['label' => 'Средний чек', 'key' => 'avg_receipt', 'format' => 'currency'],
    ['label' => 'Новые клиенты', 'key' => 'new_customers', 'format' => 'number'],
    ['label' => 'Повторные клиенты', 'key' => 'repeat_customers', 'format' => 'number'],
    ['label' => 'Частота заказов', 'key' => 'avg_frequency', 'format' => 'decimal'],
];
$chartConfigs = [];
$currentPeriodDays = $start->diff($end)->days + 1;
$previousPeriodDays = $prevStart->diff($prevEnd)->days + 1;
$selectedStatusValue = isset($selectedStatuses[0]) ? (int) $selectedStatuses[0] : null;
$positiveDeltaIsGood = $selectedStatusValue !== 3;
$selectedStatusStats = $selectedStatusValue !== null
    ? ($statusStats[$selectedStatusValue] ?? ['total_orders' => 0, 'total_revenue' => 0.0])
    : ['total_orders' => 0, 'total_revenue' => 0.0];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Маркетинговая аналитика</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .delta-up { color:#0a0; font-weight:bold; }
        .delta-down { color:#c00; font-weight:bold; }
        .delta-null { color:#999; }
        .table-sm td, .table-sm th { padding: .3rem; }
        .product-card { border:1px solid #ddd; border-radius:6px; padding:3px 10px; margin:3px 0; background:#fafafa; }
        .product-title { font-weight:500; }
        .product-meta { font-size:0.85em; color:#666; }
        .summary-chart { position: relative; height: 120px; }
        .summary-chart canvas { max-height: 120px; }
        .status-select-group { gap: 0.75rem; }
        .status-select-group .form-check { align-items: flex-start; }
        .status-select-group .status-meta { display:block; font-size:0.78rem; color:#6c757d; }
        .table-compact td, .table-compact th { padding: .2rem .3rem; font-size: 0.85rem; }
.metric-main { font-size: 1rem; font-weight: 600; }
.metric-secondary { font-size: 0.8rem; color: #666; }
.metric-delta { font-size: 0.75rem; }

.segment-chart-wrapper {
    max-width: 600px;
    margin: 0 auto;
}
.segment-chart-wrapper canvas {
    width: 100% !important;
    height: auto !important;
}
    /* кастом легенды */
    .chartjs-legend ul {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        padding-left: 0;
        list-style: none;
        margin: 0;
    }
    .chartjs-legend li {
        margin: 4px 12px;
        font-size: 0.85rem;
        white-space: nowrap;
    }

    .segment-chart-wrapper {
    max-width: 1200px; /* или сколько нужно */
    margin: 0 auto;
}

.segment-chart-wrapper canvas {
    width: 100% !important;
    height: 500px !important; /* фиксированная высота */
}

    </style>
</head>
<body class="bg-light p-3">
<div class="container-fluid">
    <h1 class="mb-4">Маркетинговая аналитика</h1>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Начало</label>
            <input type="date" class="form-control" name="start" value="<?=htmlspecialchars($start->format('Y-m-d'))?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Конец</label>
            <input type="date" class="form-control" name="end" value="<?=htmlspecialchars($end->format('Y-m-d'))?>">
        </div>
        <div class="col-md-4">
            <label class="form-label d-block">Статус заказов</label>
            <div class="d-flex flex-wrap status-select-group">
                <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                    <?php
                    $statusId = 'status-' . $statusValue;
                    $stats = $statusStats[$statusValue] ?? ['total_orders' => 0, 'total_revenue' => 0.0];
                    ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="status" value="<?=htmlspecialchars((string) $statusValue)?>" id="<?=htmlspecialchars($statusId)?>" <?=$selectedStatusValue === (int) $statusValue ? 'checked' : ''?>>
                        <label class="form-check-label" for="<?=htmlspecialchars($statusId)?>">
                            <span><?=htmlspecialchars($statusLabel)?></span>
                            <span class="status-meta">
                                <?=number_format((float) ($stats['total_revenue'] ?? 0), 0, ',', ' ')?> ₽ ·
                                <?=number_format((int) ($stats['total_orders'] ?? 0), 0, ',', ' ')?> заказов
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-text">Показатели рассчитываются по выбранному статусу.</div>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">Обновить</button>
        </div>
    </form>

    <?php if ($selectedStatusValue !== null): ?>
        <div class="mb-4">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small">Выбран статус:</span>
            <span class="badge bg-secondary">
                <?=htmlspecialchars($statusOptions[$selectedStatusValue] ?? ('Статус #' . $selectedStatusValue))?>
            </span>
            <span class="text-muted small">
                <?=number_format((int) ($selectedStatusStats['total_orders'] ?? 0), 0, ',', ' ')?> заказов ·
                <?=number_format((float) ($selectedStatusStats['total_revenue'] ?? 0), 0, ',', ' ')?> ₽
            </span>
                </div>
        </div>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
        <div class="col">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted small mb-1">Текущий период</h6>
                    <strong><?=$start->format('d.m.Y')?> — <?=$end->format('d.m.Y')?></strong>
                    <div class="text-muted small">Длительность: <?=$currentPeriodDays?> дн.</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted small mb-1">Предыдущий период</h6>
                    <strong><?=$prevStart->format('d.m.Y')?> — <?=$prevEnd->format('d.m.Y')?></strong>
                    <div class="text-muted small">Длительность: <?=$previousPeriodDays?> дн.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3 mb-4">
        <?php foreach ($summaryMetrics as $metric): ?>
            <?php
            $key = $metric['key'];
            $currentValue = $totals[$key] ?? 0;
            $previousValue = $totalsPrev[$key] ?? 0;
            $delta = MarketingDashboardFormatter::calculateDelta($currentValue, $previousValue);
            $summaryChartId = 'summary_' . $key;
            $chartConfigs[] = [
                'id' => $summaryChartId,
                'current' => (float) $currentValue,
                'previous' => (float) $previousValue,
                'label' => $metric['label'],
                'format' => $metric['format'],
            ];
            ?>
            <div class="col">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h6><?=htmlspecialchars($metric['label'])?></h6>
                        <strong><?=$formatValue($currentValue, $metric['format'])?></strong>

                        <div class="small <?=MarketingDashboardFormatter::deltaClass($delta, $positiveDeltaIsGood)?> mt-2">
                            Было: <?=$formatValue($previousValue, $metric['format'])?>
                            (<?=MarketingDashboardFormatter::formatDelta($delta)?>)
                        </div>

                        <div class="summary-chart mt-3">
                            <canvas id="<?=htmlspecialchars($summaryChartId)?>"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <ul class="nav nav-tabs" id="segmentTabs" role="tablist">
        <?php $first = true; foreach ($allMetrics as $dim => $block): ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link <?=$first ? 'active' : ''?>"
                    id="tab-<?=$dim?>"
                    data-bs-toggle="tab"
                    data-bs-target="#pane-<?=$dim?>"
                    type="button"
                    role="tab"
                >
                    <?=htmlspecialchars($dimensionList[$dim]['label'])?>
                </button>
            </li>
            <?php $first = false; endforeach; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-topca"
                        data-bs-toggle="tab" data-bs-target="#pane-topca"
                        type="button" role="tab">
                    ТОП ЦА
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-map"
                        data-bs-toggle="tab" data-bs-target="#pane-map"
                        type="button" role="tab">
                    Карта заказов
                </button>
            </li>

    </ul>

    <div class="tab-content mt-3">
        <?php $first = true; foreach ($allMetrics as $dim => $block): ?>
            <?php
            $previousMap = [];
            foreach ($block['previous'] as $prevRow) {
                $previousMap[$prevRow['dimension_value']] = $prevRow;
            }
            $defaultPrev = [
                'total_revenue' => 0,
                'total_orders' => 0,
                'total_customers' => 0,
                'avg_receipt' => 0,
                'new_customers' => 0,
                'repeat_customers' => 0,
                'avg_frequency' => 0,
            ];
            ?>
            <div class="tab-pane fade <?=$first ? 'show active' : ''?>" id="pane-<?=$dim?>" role="tabpanel">

            <?php
    // собираем данные для сводной диаграммы по сегменту
    $chartAllId = 'chart_all_' . $dim;
    $segmentLabels = [];
    $segmentValues = [];
    foreach ($block['current'] as $row) {
        $segmentLabels[] = MarketingDashboardFormatter::formatDimensionValue($dim, $row['dimension_value'], $cityMap);
        $segmentValues[] = (float) ($row['total_revenue'] ?? 0);
    }
    if ($segmentLabels && $segmentValues) {
        $chartConfigs[] = [
            'id' => $chartAllId,
            'labels' => $segmentLabels,
            'dataset' => $segmentValues,
            'label' => 'Выручка по сегменту',
            'format' => 'currency',
            'type' => 'pie',
        ];
    }
    ?>

<?php if ($segmentLabels): ?>
<div class="mb-3 d-flex justify-content-center">
    <div class="segment-chart-wrapper">
        <h6 class="text-center mb-2">Распределение по сегменту (<?=$dimensionList[$dim]['label']?>)</h6>
        <canvas id="<?=$chartAllId?>" height="400"></canvas>
        <div id="legend-<?=$chartAllId?>" class="chartjs-legend mt-2"></div>
    </div>
</div>
<?php endif; ?>

                    <div class="row g-3">
                        <?php foreach ($block['current'] as $metricRow): ?>
                        <?php
                        $value = $metricRow['dimension_value'];
                        $prev = $previousMap[$value] ?? $defaultPrev;
                        $chartId = 'chart_' . $dim . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $value);
                        $revenueDelta = MarketingDashboardFormatter::calculateDelta($metricRow['total_revenue'] ?? 0, $prev['total_revenue'] ?? 0);
                        $ordersDelta = MarketingDashboardFormatter::calculateDelta($metricRow['total_orders'] ?? 0, $prev['total_orders'] ?? 0);
                        $customersDelta = MarketingDashboardFormatter::calculateDelta($metricRow['total_customers'] ?? 0, $prev['total_customers'] ?? 0);
                        $avgReceiptDelta = MarketingDashboardFormatter::calculateDelta($metricRow['avg_receipt'] ?? 0, $prev['avg_receipt'] ?? 0);
                        $newCustomersDelta = MarketingDashboardFormatter::calculateDelta($metricRow['new_customers'] ?? 0, $prev['new_customers'] ?? 0);
                        $repeatCustomersDelta = MarketingDashboardFormatter::calculateDelta($metricRow['repeat_customers'] ?? 0, $prev['repeat_customers'] ?? 0);
                        $frequencyDelta = MarketingDashboardFormatter::calculateDelta($metricRow['avg_frequency'] ?? 0, $prev['avg_frequency'] ?? 0);
                        $chartConfigs[] = [
                            'id' => $chartId,
                            'current' => (float) ($metricRow['total_revenue'] ?? 0),
                            'previous' => (float) ($prev['total_revenue'] ?? 0),
                            'label' => 'Выручка',
                            'format' => 'currency',
                        ];
                        ?>
                        <div class="col-lg-3 col-md-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5><?=htmlspecialchars(MarketingDashboardFormatter::formatDimensionValue($dim, $value, $cityMap))?></h5>
                                    <p class="text-muted small">
                                        Текущий: <?=$start->format('d.m.Y')?> — <?=$end->format('d.m.Y')?><br>
                                        Предыдущий: <?=$prevStart->format('d.m.Y')?> — <?=$prevEnd->format('d.m.Y')?>
                                    </p>

                                    <table class="table table-sm align-middle">
                                        <tr>
                                            <th>Выручка</th>
                                            <td>
                                                <?=number_format((float) ($metricRow['total_revenue'] ?? 0), 0, ',', ' ')?> ₽<br>
                                                <small class="<?=MarketingDashboardFormatter::deltaClass($revenueDelta, $positiveDeltaIsGood)?>">
                                                    Было: <?=number_format((float) ($prev['total_revenue'] ?? 0), 0, ',', ' ')?> ₽
                                                    (<?=MarketingDashboardFormatter::formatDelta($revenueDelta)?>)
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Заказы</th>
                                            <td>
                                                <?=number_format((float) ($metricRow['total_orders'] ?? 0), 0, ',', ' ')?><br>
                                                <small class="<?=MarketingDashboardFormatter::deltaClass($ordersDelta, $positiveDeltaIsGood)?>">
                                                    Было: <?=number_format((float) ($prev['total_orders'] ?? 0), 0, ',', ' ')?>
                                                    (<?=MarketingDashboardFormatter::formatDelta($ordersDelta)?>)
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Клиенты</th>
                                            <td>
                                                <?=number_format((float) ($metricRow['total_customers'] ?? 0), 0, ',', ' ')?><br>
                                                <small class="<?=MarketingDashboardFormatter::deltaClass($customersDelta, $positiveDeltaIsGood)?>">
                                                    Было: <?=number_format((float) ($prev['total_customers'] ?? 0), 0, ',', ' ')?>
                                                    (<?=MarketingDashboardFormatter::formatDelta($customersDelta)?>)
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Средний чек</th>
                                            <td>
                                                <?=number_format((float) ($metricRow['avg_receipt'] ?? 0), 0, ',', ' ')?> ₽<br>
                                                <small class="<?=MarketingDashboardFormatter::deltaClass($avgReceiptDelta, $positiveDeltaIsGood)?>">
                                                    Было: <?=number_format((float) ($prev['avg_receipt'] ?? 0), 0, ',', ' ')?> ₽
                                                    (<?=MarketingDashboardFormatter::formatDelta($avgReceiptDelta)?>)
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Новые клиенты</th>
                                            <td>
                                                <?=number_format((float) ($metricRow['new_customers'] ?? 0), 0, ',', ' ')?><br>
                                                <small class="<?=MarketingDashboardFormatter::deltaClass($newCustomersDelta, $positiveDeltaIsGood)?>">
                                                    Было: <?=number_format((float) ($prev['new_customers'] ?? 0), 0, ',', ' ')?>
                                                    (<?=MarketingDashboardFormatter::formatDelta($newCustomersDelta)?>)
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Повторные клиенты</th>
                                            <td>
                                                <?=number_format((float) ($metricRow['repeat_customers'] ?? 0), 0, ',', ' ')?><br>
                                                <small class="<?=MarketingDashboardFormatter::deltaClass($repeatCustomersDelta, $positiveDeltaIsGood)?>">
                                                    Было: <?=number_format((float) ($prev['repeat_customers'] ?? 0), 0, ',', ' ')?>
                                                    (<?=MarketingDashboardFormatter::formatDelta($repeatCustomersDelta)?>)
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Частота заказов</th>
                                            <td>
                                                <?=number_format((float) ($metricRow['avg_frequency'] ?? 0), 2, ',', ' ')?><br>
                                                <small class="<?=MarketingDashboardFormatter::deltaClass($frequencyDelta, $positiveDeltaIsGood)?>">
                                                    Было: <?=number_format((float) ($prev['avg_frequency'] ?? 0), 2, ',', ' ')?>
                                                    (<?=MarketingDashboardFormatter::formatDelta($frequencyDelta)?>)
                                                </small>
                                            </td>
                                        </tr>
                                    </table>

                                    <canvas id="<?=htmlspecialchars($chartId)?>" height="120"></canvas>

                                    <?php if (!empty($block['products'][$value] ?? [])): ?>
                <div class="accordion mt-3" id="productsAccordion-<?=$dim?>-<?=$value?>">
                    <div class="accordion-item">
                        <h6 class="accordion-header" id="heading-<?=$dim?>-<?=$value?>">
                            <button class="accordion-button collapsed btn-sm" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse-<?=$dim?>-<?=$value?>"
                                    aria-expanded="false"
                                    aria-controls="collapse-<?=$dim?>-<?=$value?>">
                                Топ товары
                            </button>
                        </h6>
                        <div id="collapse-<?=$dim?>-<?=$value?>" class="accordion-collapse collapse"
                             aria-labelledby="heading-<?=$dim?>-<?=$value?>"
                             data-bs-parent="#productsAccordion-<?=$dim?>-<?=$value?>">
                            <div class="accordion-body">
                                    <table class="table table-sm table-striped align-middle mb-0" style="font-size: 10px;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Товар</th>
                                                <th class="text-end">Кол-во</th>
                                                <th class="text-end">Выручка</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($block['products'][$value] as $product): ?>
                                            <tr>
                                                <td><?=htmlspecialchars($product['title'])?></td>
                                                <td class="text-end"><?=$product['quantity']?></td>
                                                <td class="text-end"><?=number_format((float) $product['revenue'], 0, ',', ' ')?> ₽</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                        </div>
                                    </div>
                                </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php if (empty($block['current'])): ?>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                Нет данных по этому сегменту для выбранного статуса и периода.
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
            </div>
            <?php $first = false; endforeach; ?>
            <div class="tab-pane fade" id="pane-topca" role="tabpanel">
                <h5 class="mb-3">ТОП-3 целевых аудитории - собрано из всех сегментов</h5>
<!--                <div class="row g-3">-->
<!--                    --><?php //foreach ($topProfiles as $i => $profile): ?>
<!--                    <div class="col-lg-4 col-md-6">-->
<!--                        <div class="card shadow-sm h-100">-->
<!--                            <div class="card-body">-->
<!--                                <h6 class="mb-2">Портрет #--><?//=($i+1)?><!--</h6>-->
<!--                                <ul class="list-unstyled mb-2">-->
<!--                                    <li>Пол: <strong>--><?//=$profile['gender']?><!--</strong></li>-->
<!--                                    <li>Возраст: <strong>--><?//=$profile['age_group']?><!--</strong></li>-->
<!--                                    <li>Город: <strong>--><?//=$profile['city']?><!--</strong></li>-->
<!--                                    <li>Профессия: <strong>--><?//=$profile['occupation']?><!--</strong></li>-->
<!--                                    <li>День недели заказа: <strong>--><?//=$profile['weekday']?><!--</strong></li>-->
<!--                                    <li>Тип оплаты: <strong>--><?//=$profile['payment_type']?><!--</strong></li>-->
<!--                                </ul>-->
<!--                                <div>-->
<!--                                    Выручка: <strong>--><?//=number_format($profile['total_revenue'], 0, ',', ' ')?><!-- ₽</strong><br>-->
<!--                                    Заказы: <strong>--><?//=$profile['total_orders']?><!--</strong><br>-->
<!--                                    Клиенты: <strong>--><?//=$profile['total_customers']?><!--</strong>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                    --><?php //endforeach; ?>
<!--                </div>-->
<!--                <div class="row g-3">-->
<!--        --><?php //foreach ($topSegments as $i => $seg): ?>
<!--            <div class="col-lg-4 col-md-6">-->
<!--                <div class="card shadow-sm h-100">-->
<!--                    <div class="card-body">-->
<!--                        <h6 class="mb-2">Портрет #--><?//=($i+1)?><!--</h6>-->
<!--                        <ul class="list-unstyled mb-2">-->
<!--                            <li>Сегмент: <strong>--><?//=htmlspecialchars($dimensionList[$seg['dimension']]['label'])?><!--</strong></li>-->
<!--                            <li>Значение: <strong>--><?//=htmlspecialchars($seg['label'])?><!--</strong></li>-->
<!--                        </ul>-->
<!--                        <div>-->
<!--                            Выручка: <strong>--><?//=number_format($seg['total_revenue'], 0, ',', ' ')?><!-- ₽</strong><br>-->
<!--                            Заказы: <strong>--><?//=$seg['total_orders']?><!--</strong><br>-->
<!--                            Клиенты: <strong>--><?//=$seg['total_customers']?><!--</strong>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--        --><?php //endforeach; ?>
<!--    </div>-->

    <div class="row g-3">
        <?php foreach ($topProfiles as $i => $profile): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="mb-2">Портрет #<?=($i+1)?></h6>
                        <ul class="list-unstyled mb-2">
                            <li>Пол: <strong><?=htmlspecialchars($profile['gender'])?></strong></li>
                            <li>Возраст: <strong><?=htmlspecialchars($profile['age_group'])?></strong></li>
                            <li>Город: <strong><?=htmlspecialchars($profile['city'])?></strong></li>
                            <li>Профессия: <strong><?=htmlspecialchars($profile['occupation'])?></strong></li>
                            <li>День недели заказа: <strong><?=htmlspecialchars($profile['weekday'])?></strong></li>
                            <li>Тип оплаты: <strong><?=htmlspecialchars($profile['payment_type'])?></strong></li>
                        </ul>
                        <div>
                            Выручка: <strong><?=number_format($profile['total_revenue'], 0, ',', ' ')?> ₽</strong><br>
                            Заказы: <strong><?=$profile['total_orders']?></strong><br>
                            Клиенты: <strong><?=$profile['total_customers']?></strong>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
            </div>

            <div class="tab-pane fade" id="pane-map" role="tabpanel">
    <div id="ordersMap" style="width:100%; height:600px;"></div>
    <div class="mt-2">
    <button class="btn btn-sm btn-outline-primary" onclick="showMarkers('all')">Метки: все</button>
    <button class="btn btn-sm btn-outline-primary" onclick="showMarkers('delivery')">Метки: доставка</button>
    <button class="btn btn-sm btn-outline-primary" onclick="showMarkers('pickup')">Метки: самовывоз</button>
    <button class="btn btn-sm btn-outline-danger" onclick="showHeatmap('all')">Тепловая карта: все</button>
    <button class="btn btn-sm btn-outline-danger" onclick="showHeatmap('delivery')">Тепловая карта: доставка</button>
    <button class="btn btn-sm btn-outline-danger" onclick="showHeatmap('pickup')">Тепловая карта: самовывоз</button>

    <button class="btn btn-sm btn-outline-success" onclick="showGrid('all')">Сетка: все</button>
    <button class="btn btn-sm btn-outline-success" onclick="showGrid('delivery')">Сетка: доставка</button>
    <button class="btn btn-sm btn-outline-success" onclick="showGrid('pickup')">Сетка: самовывоз</button>

</div>

</div>


    </div>
</div>


<script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?=$yandexApiKey?>" type="text/javascript"></script>
<script src="https://yastatic.net/s3/mapsapi-jslibs/heatmap/0.0.1/heatmap.min.js"></script>
<script>
const ordersData = <?=json_encode($ordersWithCoords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;

let map, heatmap, markers = [];

ymaps.ready(init);

function init() {
    let center = [55.76, 37.64];
    const validOrders = ordersData.filter(o =>
        !isNaN(parseFloat(o.latitude)) && !isNaN(parseFloat(o.longitude))
    );

    if (validOrders.length) {
        const avgLat = validOrders.reduce((s, o) => s + parseFloat(o.latitude), 0) / validOrders.length;
        const avgLon = validOrders.reduce((s, o) => s + parseFloat(o.longitude), 0) / validOrders.length;
        center = [avgLat, avgLon];
    }

    map = new ymaps.Map("ordersMap", {
        center: center,
        zoom: 11,
        controls: ["zoomControl"]
    });

    showMarkers();
}

function showMarkers(filterType = 'all') {
    clearMap();

    const clusterer = new ymaps.Clusterer({
        preset: 'islands#invertedRedClusterIcons',
        groupByCoordinates: false,
        gridSize: 58
    });

    const placemarks = [];

    ordersData.forEach(o => {
        if (filterType !== 'all' && o.order_type !== filterType) return;
        const lat = parseFloat(o.latitude);
        const lon = parseFloat(o.longitude);
        if (isNaN(lat) || isNaN(lon)) return;

        const placemark = new ymaps.Placemark([lat, lon], {
            balloonContent: `
                <b>${o.order_type === 'delivery' ? 'Доставка' : 'Самовывоз'}</b><br>
                Заказов: ${o.order_count}<br>
                Сумма: ${o.total_sum} ₽
            `
        }, {
            preset: o.order_type === "delivery" ? "islands#redIcon" : "islands#blueIcon"
        });

        placemarks.push(placemark);
    });

    clusterer.add(placemarks);
    map.geoObjects.add(clusterer);
    markers = placemarks;
}

function showHeatmap(filterType = 'all') {
    clearMap();

    ymaps.modules.require(['Heatmap'], function (Heatmap) {
        const points = [];

        ordersData
            .filter(o =>
                (filterType === 'all' || o.order_type === filterType) &&
                !isNaN(parseFloat(o.latitude)) &&
                !isNaN(parseFloat(o.longitude))
            )
            .forEach(o => {
                const lat = parseFloat(o.latitude);
                const lon = parseFloat(o.longitude);
                const count = parseInt(o.order_count, 10) || 1;

                // дублируем точку count раз
                for (let i = 0; i < count; i++) {
                    points.push([lat, lon]);
                }
            });

        if (!points.length) {
            alert('Нет данных для тепловой карты');
            return;
        }

        heatmap = new Heatmap(points, {
            radius: 20,
            opacity: 0.7
        });

        heatmap.setMap(map);
    });
}

function showGrid(filterType = 'all') {
    clearMap();

    const bounds = map.getBounds(); // [[lat_sw, lon_sw], [lat_ne, lon_ne]]
    const sw = bounds[0];
    const ne = bounds[1];

    const latStep = (ne[0] - sw[0]) / 20; // делим карту на 20 рядов
    const lonStep = (ne[1] - sw[1]) / 20; // и 20 колонок

    for (let lat = sw[0]; lat < ne[0]; lat += latStep) {
        for (let lon = sw[1]; lon < ne[1]; lon += lonStep) {
            // фильтруем заказы в ячейке
            const ordersInCell = ordersData.filter(o => {
                if (filterType !== 'all' && o.order_type !== filterType) return false;
                const oLat = parseFloat(o.latitude);
                const oLon = parseFloat(o.longitude);
                return !isNaN(oLat) && !isNaN(oLon) &&
                       oLat >= lat && oLat < lat + latStep &&
                       oLon >= lon && oLon < lon + lonStep;
            });

            if (!ordersInCell.length) continue;

            // считаем суммарное количество заказов в ячейке
            const count = ordersInCell.reduce((s, o) => s + (parseInt(o.order_count) || 1), 0);

            // подбираем прозрачность в зависимости от числа заказов
            const opacity = Math.min(0.1 + count / 50, 0.8);

            const rect = new ymaps.Rectangle(
                [[lat, lon], [lat + latStep, lon + lonStep]],
                {
                    hintContent: `Заказов: ${count}`
                },
                {
                    fillColor: `rgba(0, 128, 255, ${opacity})`,
                stroke: false,
                    fillOpacity: opacity
                }
            );

            map.geoObjects.add(rect);
        }
    }
}

function clearMap() {
    map.geoObjects.removeAll();
    if (heatmap) {
        heatmap.setMap(null);
        heatmap = null;
    }
    markers = [];
}
</script>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const chartConfigs = <?=json_encode($chartConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
    const formatters = {
        currency: new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }),
        decimal: new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        number: new Intl.NumberFormat('ru-RU'),
    };

    chartConfigs.forEach((config) => {
        const canvas = document.getElementById(config.id);
        if (!canvas) return;

        // уничтожаем старый график, если он уже есть
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    // если это общая диаграмма по сегменту (pie)
    if (config.labels && config.dataset) {
        new Chart(canvas, {
            type: config.type || 'pie',
            data: {
                labels: config.labels,
                datasets: [{
                    label: config.label || 'Показатель',
                    data: config.dataset,
                    backgroundColor: config.labels.map((_, i) =>
                        `hsl(${i * 40 % 360}, 70%, 60%)` // авто-цвета для сегментов
                    ),
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio:true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed || 0;
                                return `${context.label}: ${formatters.currency.format(value)}`;
                            },
                        },
                    },
                },
            },
        });
        return; // для pie дальше не идём
    }

    // иначе это карточка (bar-сравнение текущий/предыдущий)
        const formatKey = config.format && formatters[config.format] ? config.format : 'number';
        const formatter = formatters[formatKey];
        const datasetLabel = config.label || 'Показатель';
        const dataPoints = [Number(config.current) || 0, Number(config.previous) || 0];

        new Chart(canvas, {
            type: config.type || 'bar',
            data: {
                labels: ['Текущий', 'Предыдущий'],
                datasets: [{
                    label: datasetLabel,
                    data: dataPoints,
                    backgroundColor: ['rgba(54, 162, 235, 0.7)', 'rgba(200, 200, 200, 0.7)'],
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = dataPoints[context.dataIndex] ?? 0;
                                return `${context.dataset.label}: ${formatter.format(value)}`;
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => formatter.format(Number(value) || 0),
                        },
                    },
                },
            },
        });
    });
</script>
</body>
</html>
