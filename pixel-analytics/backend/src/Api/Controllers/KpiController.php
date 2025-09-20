<?php
require_once __DIR__ . '/../../Config.php';
require_once __DIR__ . '/../../Db.php';
require_once __DIR__ . '/../../Helpers/Json.php';
require_once __DIR__ . '/../../Helpers/Cache.php';

class KpiController
{
    public static function handle($params)
    {
        Config::load(dirname(dirname(dirname(dirname(__DIR__)))));
        Cache::init(dirname(dirname(dirname(dirname(__DIR__)))));
        $periodParam = isset($params['period']) ? $params['period'] : '30d';
        $days = self::parseDays($periodParam);
        if ($days <= 0) {
            $days = 30;
        }
        $cacheKey = 'kpi:' . $days;
        $result = Cache::remember($cacheKey, 300, function () use ($days) {
            $db = Db::analytics();
            $lastDate = $db->getOne('SELECT MAX(stat_date) FROM summary_segments_daily');
            if (!$lastDate) {
                return array(
                    'period' => array('from' => null, 'to' => null),
                    'orders' => 0,
                    'revenue' => 0,
                    'avg_check' => 0,
                    'repeat_rate' => 0,
                );
            }
            $end = new DateTime($lastDate);
            $start = clone $end;
            $start->modify('-' . ($days - 1) . ' days');
            $row = $db->getRow('SELECT SUM(orders_cnt) AS orders_cnt, SUM(revenue_sum) AS revenue_sum, SUM(repeat_rate_90d * orders_cnt) AS repeat_weight
                FROM summary_segments_daily WHERE stat_date BETWEEN ?s AND ?s',
                $start->format('Y-m-d'), $end->format('Y-m-d'));
            $orders = $row && isset($row['orders_cnt']) ? (int)$row['orders_cnt'] : 0;
            $revenue = $row && isset($row['revenue_sum']) ? (float)$row['revenue_sum'] : 0.0;
            $repeatWeight = $row && isset($row['repeat_weight']) ? (float)$row['repeat_weight'] : 0.0;
            $avgCheck = $orders > 0 ? round($revenue / $orders, 2) : 0.0;
            $repeatRate = $orders > 0 ? round($repeatWeight / $orders, 3) : 0.0;
            return array(
                'period' => array('from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')),
                'orders' => $orders,
                'revenue' => round($revenue, 2),
                'avg_check' => $avgCheck,
                'repeat_rate' => $repeatRate,
            );
        });
        Json::response($result, 200, array(), 300);
    }

    private static function parseDays($period)
    {
        if (preg_match('~^(\d+)[dD]$~', $period, $m)) {
            return (int)$m[1];
        }
        return (int)$period;
    }
}
