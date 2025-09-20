<?php
namespace Segmentation;

use SafeMySQL;

class SegmentCalculator
{
    private SafeMySQL $analytics;

    public function __construct(SafeMySQL $analytics)
    {
        $this->analytics = $analytics;
    }

    public function buildMetrics(\DateTimeInterface $start, \DateTimeInterface $end): void
    {
        foreach (DimensionConfig::list() as $dimension => $config) {
            $metrics = $this->calculateDimensionMetrics($dimension, $start, $end);
            foreach ($metrics as $metric) {
                $this->analytics->insertOrUpdate('analytics_dimension_metrics', $metric, $metric);
            }

            $topProducts = $this->calculateTopProducts($dimension, $start, $end);
            foreach ($topProducts as $entry) {
                $this->analytics->insertOrUpdate('analytics_dimension_top_products', $entry, $entry);
            }
        }
    }

    private function calculateDimensionMetrics(string $dimension, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $config = DimensionConfig::list()[$dimension];
        $field = $config['field'];

        $sql = <<<SQL
SELECT
    COALESCE(u.$field, 'unknown') AS dimension_value,
    COUNT(DISTINCT o.id) AS total_orders,
    COUNT(DISTINCT o.source_user_id) AS total_customers,
    SUM(o.total_sum) AS total_revenue,
    SUM(o.total_items) AS total_items,
    SUM(CASE WHEN o.repeat_order = 1 THEN 1 ELSE 0 END) AS repeat_orders,
    SUM(CASE WHEN o.order_number = 1 THEN 1 ELSE 0 END) AS new_orders
FROM analytics_orders o
LEFT JOIN analytics_users u ON u.source_user_id = o.source_user_id
WHERE o.order_datetime >= ?s AND o.order_datetime < ?s
GROUP BY dimension_value
SQL;

        if (in_array($dimension, ['weekday', 'payment_type', 'city_id'], true)) {
            $sql = str_replace('COALESCE(u.' . $field, 'COALESCE(o.' . $field, $sql);
        }

        $params = [
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
        ];

        $rows = $this->analytics->getAll($sql, $params);
        $metrics = [];
        foreach ($rows as $row) {
            $totalOrders = (int) $row['total_orders'];
            $totalCustomers = (int) $row['total_customers'];
            $repeatOrders = (int) $row['repeat_orders'];
            $newOrders = (int) $row['new_orders'];
            $totalRevenue = (float) $row['total_revenue'];
            $totalItems = (int) $row['total_items'];

            $repeatCustomers = $totalCustomers > 0 ? (int) floor($repeatOrders) : 0;
            $repeatRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 2) : 0.0;
            $avgReceipt = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0;
            $avgFrequency = $totalCustomers > 0 ? round($totalOrders / $totalCustomers, 2) : 0.0;
            $avgItems = $totalOrders > 0 ? round($totalItems / $totalOrders, 2) : 0.0;

            $metrics[] = [
                'dimension' => $dimension,
                'dimension_value' => $row['dimension_value'] ?? 'unknown',
                'period_start' => $start->format('Y-m-d'),
                'period_end' => $end->format('Y-m-d'),
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'total_customers' => $totalCustomers,
                'new_customers' => $newOrders,
                'repeat_customers' => $repeatCustomers,
                'repeat_rate' => $repeatRate,
                'avg_receipt' => $avgReceipt,
                'avg_frequency' => $avgFrequency,
                'avg_items' => $avgItems,
            ];
        }

        return $metrics;
    }

    private function calculateTopProducts(string $dimension, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $config = DimensionConfig::list()[$dimension];
        $field = $config['field'];

        $joinField = 'u.' . $field;
        if (in_array($dimension, ['weekday', 'payment_type', 'city_id'], true)) {
            $joinField = 'o.' . $field;
        }

        $sql = <<<SQL
SELECT
    COALESCE($joinField, 'unknown') AS dimension_value,
    i.product_title,
    SUM(i.revenue) AS revenue,
    SUM(i.quantity) AS quantity
FROM analytics_order_items i
INNER JOIN analytics_orders o ON o.id = i.analytics_order_id
LEFT JOIN analytics_users u ON u.source_user_id = o.source_user_id
WHERE o.order_datetime >= ?s AND o.order_datetime < ?s
GROUP BY dimension_value, i.product_title
ORDER BY dimension_value, revenue DESC
SQL;

        $rows = $this->analytics->getAll($sql, [
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
        ]);

        $grouped = [];
        foreach ($rows as $row) {
            $dimensionValue = $row['dimension_value'] ?? 'unknown';
            if (!isset($grouped[$dimensionValue])) {
                $grouped[$dimensionValue] = [];
            }
            if (count($grouped[$dimensionValue]) < 5) {
                $grouped[$dimensionValue][] = [
                    'title' => $row['product_title'],
                    'revenue' => (float) $row['revenue'],
                    'quantity' => (int) $row['quantity'],
                ];
            }
        }

        $result = [];
        foreach ($grouped as $dimensionValue => $products) {
            $result[] = [
                'dimension' => $dimension,
                'dimension_value' => $dimensionValue,
                'period_start' => $start->format('Y-m-d'),
                'period_end' => $end->format('Y-m-d'),
                'products' => json_encode($products, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $result;
    }
}
