<?php
namespace PixelAnalytics\Api\Controllers;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Json;

class KpiController
{
    public function index($query)
    {
        $period = isset($query['period']) ? $query['period'] : '30d';
        $days = $this->parseDays($period);
        $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $endDate = date('Y-m-d');

        $db = Db::analytics();
        $sql = 'SELECT
                SUM(revenue_sum) AS revenue,
                SUM(orders_cnt) AS orders,
                SUM(revenue_sum) / NULLIF(SUM(orders_cnt), 0) AS avg_check,
                SUM(repeat_rate_90d * users_cnt) / NULLIF(SUM(users_cnt), 0) AS retention
            FROM summary_segments_daily
            WHERE stat_date BETWEEN ? AND ?';
        $rows = Db::query($db, $sql, array($startDate, $endDate));
        $row = !empty($rows) ? $rows[0] : array('revenue' => 0, 'orders' => 0, 'avg_check' => 0, 'retention' => 0);

        return Json::success(array(
            'period' => $period,
            'revenue' => (float) $row['revenue'],
            'orders' => (int) $row['orders'],
            'avg_check' => isset($row['avg_check']) ? (float) $row['avg_check'] : 0,
            'retention' => isset($row['retention']) ? (float) $row['retention'] : 0,
        ));
    }

    private function parseDays($period)
    {
        if (preg_match('/^(\d+)d$/', $period, $matches)) {
            return max(1, (int) $matches[1]);
        }
        return 30;
    }
}
