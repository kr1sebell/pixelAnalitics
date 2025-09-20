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

$periodLength = $start->diff($end)->days + 1;
$prevEnd = (clone $start)->modify('-1 day');
$prevStart = (clone $prevEnd)->modify('-' . ($periodLength - 1) . ' days');

$currentMetrics = fetchMetrics($analytics, $dimension, $start, $end);
$previousMetrics = fetchMetrics($analytics, $dimension, $prevStart, $prevEnd);
$topProducts = fetchTopProducts($analytics, $dimension, $start, $end);
$cityMap = fetchCityMap($analytics);

function fetchMetrics($analytics, string $dimension, DateTime $start, DateTime $end): array
{
    return $analytics->getAll(
        'SELECT * FROM analytics_dimension_metrics WHERE dimension = ?s AND period_start = ?s AND period_end = ?s ORDER BY total_revenue DESC',
        [$dimension, $start->format('Y-m-d'), $end->format('Y-m-d')]
    );
}

function fetchTopProducts($analytics, string $dimension, DateTime $start, DateTime $end): array
{
    $rows = $analytics->getAll(
        'SELECT dimension_value, products FROM analytics_dimension_top_products WHERE dimension = ?s AND period_start = ?s AND period_end = ?s',
        [$dimension, $start->format('Y-m-d'), $end->format('Y-m-d')]
    );
    $result = [];
    foreach ($rows as $row) {
        $result[$row['dimension_value']] = json_decode($row['products'], true) ?? [];
    }
    return $result;
}

function fetchCityMap($analytics): array
{
    $rows = $analytics->getAll('SELECT DISTINCT city_id, city_name FROM analytics_orders WHERE city_id IS NOT NULL');
    $map = [];
    foreach ($rows as $row) {
        if ($row['city_id'] !== null && $row['city_id'] !== '') {
            $map[(int) $row['city_id']] = $row['city_name'] ?? ('Город #' . $row['city_id']);
        }
    }
    return $map;
}

function formatDimensionValue(string $dimension, $value): string
{
    global $cityMap;
    if ($value === null || $value === '' || $value === 'unknown') {
        return 'Не определено';
    }
    if ($dimension === 'gender') {
        return match ($value) {
            'male' => 'Мужчины',
            'female' => 'Женщины',
            default => 'Не определено',
        };
    }
    if ($dimension === 'weekday') {
        $map = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];
        return $map[(int) $value] ?? 'Не определено';
    }
    if ($dimension === 'payment_type') {
        $map = [0 => 'Онлайн', 1 => 'Наличные', 2 => 'Терминал'];
        return $map[(int) $value] ?? 'Не определено';
    }
    if ($dimension === 'city_id') {
        return $cityMap[(int) $value] ?? ('Город #' . (int) $value);
    }
    return (string) $value;
}

function compareMetrics(array $current, array $previous, string $key): ?float
{
    $currentValue = $current[$key] ?? 0;
    $previousValue = $previous[$key] ?? 0;
    if ($previousValue == 0) {
        return null;
    }
    return round((($currentValue - $previousValue) / $previousValue) * 100, 2);
}

?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Маркетинговая аналитика</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .metric-delta { font-size: 0.9em; color: #666; }
        .metric-delta.positive { color: #0a8a0a; }
        .metric-delta.negative { color: #c60f0f; }
        .top-products { font-size: 0.9em; color: #555; }
    </style>
</head>
<body>
    <h1>Маркетинговая аналитика</h1>
    <form method="get">
        <label>
            Сегмент:
            <select name="dimension">
                <?php foreach ($dimensionList as $key => $info): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= $dimension === $key ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Начало периода:
            <input type="date" name="start" value="<?= htmlspecialchars($start->format('Y-m-d')) ?>">
        </label>
        <label>
            Конец периода:
            <input type="date" name="end" value="<?= htmlspecialchars($end->format('Y-m-d')) ?>">
        </label>
        <button type="submit">Обновить</button>
    </form>

    <h2>Текущий период: <?= $start->format('d.m.Y') ?> — <?= $end->format('d.m.Y') ?></h2>
    <table>
        <thead>
            <tr>
                <th>Значение сегмента</th>
                <th>Выручка</th>
                <th>Заказы</th>
                <th>Покупатели</th>
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
        <?php foreach ($currentMetrics as $metric): ?>
            <?php
                $prev = null;
                foreach ($previousMetrics as $candidate) {
                    if ($candidate['dimension_value'] === $metric['dimension_value']) {
                        $prev = $candidate;
                        break;
                    }
                }
            ?>
            <tr>
                <td><?= htmlspecialchars(formatDimensionValue($dimension, $metric['dimension_value'])) ?></td>
                <td>
                    <?= number_format($metric['total_revenue'], 2, ',', ' ') ?> ₽
                    <?php $delta = $prev ? compareMetrics($metric, $prev, 'total_revenue') : null; ?>
                    <?php if ($delta !== null): ?>
                        <div class="metric-delta <?= $delta >= 0 ? 'positive' : 'negative' ?>"><?= $delta >= 0 ? '+' : '' ?><?= $delta ?>%</div>
                    <?php endif; ?>
                </td>
                <td><?= (int) $metric['total_orders'] ?></td>
                <td><?= (int) $metric['total_customers'] ?></td>
                <td><?= (int) $metric['new_customers'] ?></td>
                <td><?= (int) $metric['repeat_customers'] ?></td>
                <td><?= number_format($metric['repeat_rate'], 2, ',', ' ') ?>%</td>
                <td><?= number_format($metric['avg_receipt'], 2, ',', ' ') ?> ₽</td>
                <td><?= number_format($metric['avg_frequency'], 2, ',', ' ') ?></td>
                <td><?= number_format($metric['avg_items'], 2, ',', ' ') ?></td>
                <td class="top-products">
                    <?php if (!empty($topProducts[$metric['dimension_value']])): ?>
                        <?php foreach ($topProducts[$metric['dimension_value']] as $product): ?>
                            <div><?= htmlspecialchars($product['title']) ?> — <?= number_format($product['revenue'], 2, ',', ' ') ?> ₽ (<?= (int) $product['quantity'] ?>)</div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div>Нет данных</div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($currentMetrics)): ?>
            <tr><td colspan="11">Нет агрегированных данных. Запустите пересчет сегментации.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Сравнение с предыдущим периодом: <?= $prevStart->format('d.m.Y') ?> — <?= $prevEnd->format('d.m.Y') ?></h2>
    <table>
        <thead>
            <tr>
                <th>Значение сегмента</th>
                <th>Выручка</th>
                <th>Заказы</th>
                <th>Покупатели</th>
                <th>Новые</th>
                <th>Повторные</th>
                <th>Повторяемость</th>
                <th>Средний чек</th>
                <th>Средняя частота</th>
                <th>Среднее кол-во позиций</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($previousMetrics as $metric): ?>
            <tr>
                <td><?= htmlspecialchars(formatDimensionValue($dimension, $metric['dimension_value'])) ?></td>
                <td><?= number_format($metric['total_revenue'], 2, ',', ' ') ?> ₽</td>
                <td><?= (int) $metric['total_orders'] ?></td>
                <td><?= (int) $metric['total_customers'] ?></td>
                <td><?= (int) $metric['new_customers'] ?></td>
                <td><?= (int) $metric['repeat_customers'] ?></td>
                <td><?= number_format($metric['repeat_rate'], 2, ',', ' ') ?>%</td>
                <td><?= number_format($metric['avg_receipt'], 2, ',', ' ') ?> ₽</td>
                <td><?= number_format($metric['avg_frequency'], 2, ',', ' ') ?></td>
                <td><?= number_format($metric['avg_items'], 2, ',', ' ') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($previousMetrics)): ?>
            <tr><td colspan="10">Нет данных за предыдущий период.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
