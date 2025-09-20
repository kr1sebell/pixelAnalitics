<?php

declare(strict_types=1);

namespace Analytics;

use DateTime;
use InvalidArgumentException;
use SafeMySQL;

class MarketingDashboardService
{
    private const DIMENSION_FIELD_MAP = [
        'gender' => 'u.gender',
        'occupation' => 'u.occupation',
        'age_group' => 'u.age_group',
        'city' => 'u.city',
        'payment_type' => 'o.payment_type',
        'weekday' => 'o.weekday',
        'city_id' => 'o.city_id',
    ];

    public function __construct(private readonly SafeMySQL $analytics)
    {
    }

    /**
     * @param array<int, int> $statuses
     * @return array<int, array<string, mixed>>
     */
    public function getMetrics(string $dimension, DateTime $start, DateTime $end, array $statuses): array
    {
        $field = $this->resolveField($dimension);
        [$statusSql, $statusParams] = $this->buildStatusClause('o', $statuses);

        $rows = $this->analytics->getAll(
            "SELECT
                {$field} AS dimension_value,
                COUNT(o.id) AS total_orders,
                SUM(o.total_sum) AS total_revenue,
                COUNT(DISTINCT o.source_user_id) AS total_customers,

                COUNT(DISTINCT CASE
                    WHEN DATE(u.first_order_at) BETWEEN ?s AND ?s
                    THEN o.source_user_id END
                ) AS new_customers,

                COUNT(DISTINCT CASE
                    WHEN DATE(u.first_order_at) < ?s OR u.first_order_at IS NULL
                    THEN o.source_user_id END
                ) AS repeat_customers,

                ROUND(SUM(o.total_sum)/NULLIF(COUNT(o.id),0),2) AS avg_receipt,
                ROUND(AVG(o.total_items),2) AS avg_items,
                COALESCE(ROUND(COUNT(o.id)/NULLIF(COUNT(DISTINCT o.source_user_id),0),2),0) AS avg_frequency,
                COALESCE(ROUND(
                    (COUNT(o.id)/NULLIF(COUNT(DISTINCT o.source_user_id),0))
                    / NULLIF(DATEDIFF(?s,?s)/30,0), 2
                ),0) AS avg_frequency_month
             FROM analytics_orders o
             JOIN analytics_users u ON u.source_user_id=o.source_user_id
            WHERE o.order_date BETWEEN ?s AND ?s{$statusSql}
            GROUP BY dimension_value
            ORDER BY total_revenue DESC",
            array_merge([
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $start->format('Y-m-d'),
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ], $statusParams)
        );

        foreach ($rows as &$row) {
            $row['total_orders'] = (int) ($row['total_orders'] ?? 0);
            $row['total_revenue'] = (float) ($row['total_revenue'] ?? 0);
            $row['total_customers'] = (int) ($row['total_customers'] ?? 0);
            $row['avg_receipt'] = (float) ($row['avg_receipt'] ?? 0);
            $row['avg_items'] = (float) ($row['avg_items'] ?? 0);
            $row['new_customers'] = (int) ($row['new_customers'] ?? 0);
            $row['repeat_customers'] = (int) ($row['repeat_customers'] ?? 0);
            $row['avg_frequency'] = (float) ($row['avg_frequency'] ?? 0);
            $row['avg_frequency_month'] = (float) ($row['avg_frequency_month'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int, int> $statuses
     * @return array<string, mixed>
     */
    public function getTotals(DateTime $start, DateTime $end, array $statuses): array
    {
        [$statusSql, $statusParams] = $this->buildStatusClause('o', $statuses);
        $row = $this->analytics->getRow(
            "SELECT
                COUNT(o.id) AS total_orders,
                SUM(o.total_sum) AS total_revenue,
                COUNT(DISTINCT o.source_user_id) AS total_customers,
                ROUND(SUM(o.total_sum)/NULLIF(COUNT(o.id),0),2) AS avg_receipt,
                COUNT(DISTINCT CASE
                    WHEN DATE(u.first_order_at) BETWEEN ?s AND ?s
                    THEN o.source_user_id END
                ) AS new_customers,
                COUNT(DISTINCT CASE
                    WHEN DATE(u.first_order_at) < ?s OR u.first_order_at IS NULL
                    THEN o.source_user_id END
                ) AS repeat_customers,
                COALESCE(ROUND(COUNT(o.id)/NULLIF(COUNT(DISTINCT o.source_user_id),0),2),0) AS avg_frequency
             FROM analytics_orders o
             JOIN analytics_users u ON u.source_user_id=o.source_user_id
            WHERE o.order_date BETWEEN ?s AND ?s{$statusSql}",
            array_merge([
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $start->format('Y-m-d'),
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ], $statusParams)
        );

        if (!$row) {
            return [
                'total_orders' => 0,
                'total_revenue' => 0,
                'total_customers' => 0,
                'avg_receipt' => 0,
                'new_customers' => 0,
                'repeat_customers' => 0,
                'avg_frequency' => 0,
            ];
        }

        return [
            'total_orders' => (int) ($row['total_orders'] ?? 0),
            'total_revenue' => (float) ($row['total_revenue'] ?? 0),
            'total_customers' => (int) ($row['total_customers'] ?? 0),
            'avg_receipt' => (float) ($row['avg_receipt'] ?? 0),
            'new_customers' => (int) ($row['new_customers'] ?? 0),
            'repeat_customers' => (int) ($row['repeat_customers'] ?? 0),
            'avg_frequency' => (float) ($row['avg_frequency'] ?? 0),
        ];
    }

    /**
     * @param array<int, int> $statuses
     * @return array<string, array<int, array<string, float|int|string>>>
     */
    public function getTopProducts(string $dimension, DateTime $start, DateTime $end, array $statuses): array
    {
        $field = $this->resolveField($dimension);
        [$statusSql, $statusParams] = $this->buildStatusClause('o', $statuses);

        $rows = $this->analytics->getAll(
            "SELECT
                {$field} AS dimension_value,
                i.product_title,
                SUM(i.quantity) AS quantity,
                SUM(i.revenue) AS revenue
             FROM analytics_order_items i
             JOIN analytics_orders o ON o.id=i.analytics_order_id
             JOIN analytics_users u ON u.source_user_id=o.source_user_id
            WHERE o.order_date BETWEEN ?s AND ?s{$statusSql}
            GROUP BY dimension_value, i.product_title
            ORDER BY revenue DESC",
            array_merge([$start->format('Y-m-d'), $end->format('Y-m-d')], $statusParams)
        );

        $result = [];
        foreach ($rows as $row) {
            $key = $row['dimension_value'];
            $result[$key][] = [
                'title' => (string) $row['product_title'],
                'quantity' => (int) $row['quantity'],
                'revenue' => (float) $row['revenue'],
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int> $statuses
     * @return array<int, string>
     */
    public function getCityMap(array $statuses): array
    {
        [$statusSql, $statusParams] = $this->buildStatusClause('o', $statuses);
        $rows = $this->analytics->getAll(
            'SELECT DISTINCT o.city_id,o.city_name FROM analytics_orders o WHERE o.city_id IS NOT NULL' . $statusSql,
            $statusParams
        );
        $map = [];
        foreach ($rows as $row) {
            if ($row['city_id']) {
                $cityId = (int) $row['city_id'];
                $map[$cityId] = $row['city_name'] ?? ('Город #' . $cityId);
            }
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function getAvailableStatuses(): array
    {
        $rows = $this->analytics->getCol('SELECT DISTINCT status FROM analytics_orders ORDER BY status');
        if (!$rows) {
            return [];
        }

        return array_map(static fn ($value): int => (int) $value, $rows);
    }

    /**
     * @param array<int, int> $statuses
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildStatusClause(string $alias, array $statuses): array
    {
        $clean = array_values(array_unique(array_map(static fn ($value): int => (int) $value, $statuses)));
        sort($clean);

        if ($clean === []) {
            return ['', []];
        }

        return [sprintf(' AND %s.status IN (?a)', $alias), [$clean]];
    }

    private function resolveField(string $dimension): string
    {
        if (!isset(self::DIMENSION_FIELD_MAP[$dimension])) {
            throw new InvalidArgumentException(sprintf('Unknown dimension: %s', $dimension));
        }

        return self::DIMENSION_FIELD_MAP[$dimension];
    }
}
