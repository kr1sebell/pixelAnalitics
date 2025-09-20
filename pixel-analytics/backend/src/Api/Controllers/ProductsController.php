<?php
require_once __DIR__ . '/../../Config.php';
require_once __DIR__ . '/../../Db.php';
require_once __DIR__ . '/../../Helpers/Json.php';
require_once __DIR__ . '/../../Helpers/Cache.php';
require_once __DIR__ . '/../../Helpers/Date.php';

class ProductsController
{
    public static function handle($params)
    {
        Config::load(dirname(dirname(dirname(dirname(__DIR__)))));
        Cache::init(dirname(dirname(dirname(dirname(__DIR__)))));
        $from = isset($params['from']) ? $params['from'] : null;
        $to = isset($params['to']) ? $params['to'] : null;
        if (!$from || !$to) {
            Json::response(array('error' => 'from/to required'), 400);
            return;
        }
        $cacheKey = 'products:' . $from . ':' . $to;
        $data = Cache::remember($cacheKey, 600, function () use ($from, $to) {
            $db = Db::analytics();
            $rows = $db->getAll('SELECT stat_date, SUM(orders_cnt) AS orders_cnt, SUM(revenue_sum) AS revenue_sum
                FROM summary_segments_daily
                WHERE stat_date BETWEEN ?s AND ?s
                GROUP BY stat_date
                ORDER BY stat_date ASC', $from, $to);
            $result = array();
            foreach ($rows as $row) {
                $result[] = array(
                    'date' => $row['stat_date'],
                    'orders' => (int)$row['orders_cnt'],
                    'revenue' => round((float)$row['revenue_sum'], 2),
                );
            }
            return $result;
        });
        Json::response(array('points' => $data), 200, array(), 600);
    }
}
