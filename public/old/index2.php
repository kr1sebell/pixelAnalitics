<?php
require __DIR__ . '/../bootstrap.php';

use Database\ConnectionManager;
use Segmentation\DimensionConfig;

$connectionManager = new ConnectionManager($config);
$analytics = $connectionManager->getAnalytics();

$dimensionList = DimensionConfig::list();
$dimension = $_GET['dimension'] ?? 'gender';
if (!isset($dimensionList[$dimension])) {
    $dimension = 'gender';
}

$startParam = $_GET['start'] ?? (new DateTime('-6 days'))->format('Y-m-d');
$endParam = $_GET['end'] ?? (new DateTime())->format('Y-m-d');

$start = new DateTime($startParam);
$end = new DateTime($endParam);
if ($start > $end) {
    [$start, $end] = [$end, $start];
}

$currentMetrics = fetchMetrics($analytics, $dimension, $start, $end);
$topProducts = fetchTopProducts($analytics, $dimension, $start, $end);
$cityMap = fetchCityMap($analytics);

function fetchMetrics($analytics, string $dimension, DateTime $start, DateTime $end): array {
    return $analytics->getAll(
        'SELECT dimension_value,
                SUM(total_orders) AS total_orders,
                SUM(total_revenue) AS total_revenue,
                SUM(total_customers) AS total_customers,
                SUM(new_customers) AS new_customers,
                SUM(repeat_customers) AS repeat_customers,
                ROUND(AVG(repeat_rate),2) AS repeat_rate,
                ROUND(AVG(avg_receipt),2) AS avg_receipt,
                ROUND(AVG(avg_frequency),2) AS avg_frequency,
                ROUND(AVG(avg_items),2) AS avg_items
           FROM analytics_dimension_metrics
          WHERE dimension = ?s
            AND period_start BETWEEN ?s AND ?s
          GROUP BY dimension_value
          ORDER BY total_revenue DESC',
        [$dimension, $start->format('Y-m-d'), $end->format('Y-m-d')]
    );
}

function fetchTopProducts($analytics, string $dimension, DateTime $start, DateTime $end): array {
    $rows = $analytics->getAll(
        'SELECT dimension_value, products
           FROM analytics_dimension_top_products
          WHERE dimension = ?s
            AND period_start BETWEEN ?s AND ?s',
        [$dimension, $start->format('Y-m-d'), $end->format('Y-m-d')]
    );

    $result = [];
    foreach ($rows as $row) {
        $products = json_decode($row['products'], true) ?? [];
        foreach ($products as $p) {
            $key = $row['dimension_value'];
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $title = $p['title'];
            if (!isset($result[$key][$title])) {
                $result[$key][$title] = $p;
            } else {
                $result[$key][$title]['revenue'] += $p['revenue'];
                $result[$key][$title]['quantity'] += $p['quantity'];
            }
        }
    }
    foreach ($result as $dim => $prods) {
        $result[$dim] = array_values($prods);
    }
    return $result;
}

function fetchCityMap($analytics): array {
    $rows = $analytics->getAll('SELECT DISTINCT city_id, city_name FROM analytics_orders WHERE city_id IS NOT NULL');
    $map = [];
    foreach ($rows as $row) {
        if ($row['city_id'] !== null && $row['city_id'] !== '') {
            $map[(int) $row['city_id']] = $row['city_name'] ?? ('Город #' . $row['city_id']);
        }
    }
    return $map;
}

function formatDimensionValue(string $dimension, $value): string {
    global $cityMap;
    if ($value === null || $value === '' || $value === 'unknown') return 'Не определено';
    if ($dimension === 'gender') {
        return match ($value) {
        'male' => 'Мужчины',
            'female' => 'Женщины',
            default => 'Не определено',
        };
    }
    if ($dimension === 'weekday') {
        $map = [1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота',7=>'Воскресенье'];
        return $map[(int)$value] ?? 'Не определено';
    }
    if ($dimension === 'payment_type') {
        $map = [0=>'Онлайн',1=>'Наличные',2=>'Терминал'];
        return $map[(int)$value] ?? 'Не определено';
    }
    if ($dimension === 'city_id') {
        return $cityMap[(int)$value] ?? ('Город #' . (int)$value);
    }
    return (string)$value;
}

$totalRevenue = array_sum(array_column($currentMetrics, 'total_revenue'));
$totalOrders = array_sum(array_column($currentMetrics, 'total_orders'));
$totalCustomers = array_sum(array_column($currentMetrics, 'total_customers'));
$avgReceipt = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Маркетинговая аналитика</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="p-4 bg-light">
<div class="container-fluid">

    <h1 class="mb-4">Маркетинговая аналитика</h1>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Сегмент</label>
            <select name="dimension" class="form-select">
                <?php foreach ($dimensionList as $key=>$info): ?>
                    <option value="<?=htmlspecialchars($key)?>" <?=$dimension===$key?'selected':''?>><?=htmlspecialchars($info['label'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Начало периода</label>
            <input type="date" name="start" value="<?=htmlspecialchars($start->format('Y-m-d'))?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Конец периода</label>
            <input type="date" name="end" value="<?=htmlspecialchars($end->format('Y-m-d'))?>" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Обновить</button>
        </div>
    </form>

    <!-- KPI -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6 class="text-muted">Выручка</h6>
                    <h3><?=number_format($totalRevenue,2,',',' ')?> ₽</h3>
                </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6 class="text-muted">Заказы</h6>
                    <h3><?=$totalOrders?></h3>
                </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6 class="text-muted">Покупатели</h6>
                    <h3><?=$totalCustomers?></h3>
                </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6 class="text-muted">Средний чек</h6>
                    <h3><?=number_format($avgReceipt,2,',',' ')?> ₽</h3>
                </div></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm"><div class="card-body">
                    <h5 class="card-title">Заказы по сегменту</h5>
                    <canvas id="ordersPie"></canvas>
                </div></div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm"><div class="card-body">
                    <h5 class="card-title">Выручка по сегменту</h5>
                    <canvas id="revenueBar"></canvas>
                </div></div>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title">Детализация по сегменту</h5>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Значение</th>
                    <th>Выручка</th>
                    <th>Заказы</th>
                    <th>Клиенты</th>
                    <th>Новые</th>
                    <th>Повторные</th>
                    <th>Повторяемость</th>
                    <th>Средний чек</th>
                    <th>Средняя частота</th>
                    <th>Среднее кол-во позиций</th>
                    <th>Топ товаров</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($currentMetrics as $m): ?>
                    <tr>
                        <td><?=htmlspecialchars(formatDimensionValue($dimension,$m['dimension_value']))?></td>
                        <td><?=number_format($m['total_revenue'],2,',',' ')?> ₽</td>
                        <td><?=$m['total_orders']?></td>
                        <td><?=$m['total_customers']?></td>
                        <td><?=$m['new_customers']?></td>
                        <td><?=$m['repeat_customers']?></td>
                        <td><?=$m['repeat_rate']?>%</td>
                        <td><?=$m['avg_receipt']?> ₽</td>
                        <td><?=$m['avg_frequency']?></td>
                        <td><?=$m['avg_items']?></td>
                        <td>
                            <?php if (!empty($topProducts[$m['dimension_value']])): ?>
                                <?php foreach ($topProducts[$m['dimension_value']] as $p): ?>
                                    <div><?=htmlspecialchars($p['title'])?> — <?=number_format($p['revenue'],2,',',' ')?> ₽ (<?=$p['quantity']?>)</div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">Нет данных</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($currentMetrics)): ?>
                    <tr><td colspan="11">Нет данных. Запустите пересчёт сегментации.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    new Chart(document.getElementById('ordersPie'), {
        type: 'doughnut',
        data: {
            labels: <?=json_encode(array_map(fn($m)=>formatDimensionValue($dimension,$m['dimension_value']), $currentMetrics))?>,
            datasets: [{
                data: <?=json_encode(array_column($currentMetrics,'total_orders'))?>,
                backgroundColor: ['#36a2eb','#ff6384','#ffce56','#4bc0c0','#9966ff','#ff9f40','#8bc34a']
            }]
        }
    });

    new Chart(document.getElementById('revenueBar'), {
        type: 'bar',
        data: {
            labels: <?=json_encode(array_map(fn($m)=>formatDimensionValue($dimension,$m['dimension_value']), $currentMetrics))?>,
            datasets: [{
                label: 'Выручка',
                data: <?=json_encode(array_column($currentMetrics,'total_revenue'))?>,
                backgroundColor: '#36a2eb'
            }]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });
</script>

</body>
</html>
