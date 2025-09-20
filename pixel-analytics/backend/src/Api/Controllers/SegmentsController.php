<?php
require_once __DIR__ . '/../../Config.php';
require_once __DIR__ . '/../../Db.php';
require_once __DIR__ . '/../../Helpers/Json.php';
require_once __DIR__ . '/../../Helpers/Cache.php';

class SegmentsController
{
    public static function handle($params)
    {
        Config::load(dirname(dirname(dirname(dirname(__DIR__)))));
        Cache::init(dirname(dirname(dirname(dirname(__DIR__)))));
        $granularity = isset($params['granularity']) ? $params['granularity'] : 'week';
        if (!in_array($granularity, array('week', 'month'))) {
            $granularity = 'week';
        }
        $periods = isset($params['periods']) ? (int)$params['periods'] : 2;
        if ($periods < 2) {
            $periods = 2;
        }
        $cacheKey = 'segments:' . $granularity . ':' . $periods;
        $data = Cache::remember($cacheKey, 600, function () use ($granularity, $periods) {
            $db = Db::analytics();
            $lastDate = $db->getOne('SELECT MAX(stat_date) FROM summary_segments_daily');
            if (!$lastDate) {
                return array('segments' => array(), 'meta' => array());
            }
            list($currentRange, $previousRange) = self::calculateRanges($granularity, $lastDate);
            if (!$currentRange || !$previousRange) {
                return array('segments' => array(), 'meta' => array());
            }
            $currentData = self::fetchRange($db, $currentRange['from'], $currentRange['to']);
            $previousData = self::fetchRange($db, $previousRange['from'], $previousRange['to']);
            $segments = self::combineSegments($currentData, $previousData);
            usort($segments, function ($a, $b) {
                if ($b['revenue_this'] == $a['revenue_this']) {
                    return 0;
                }
                return ($b['revenue_this'] > $a['revenue_this']) ? 1 : -1;
            });
            return array(
                'segments' => $segments,
                'meta' => array('current' => $currentRange, 'previous' => $previousRange),
            );
        });
        Json::response($data, 200, array(), 600);
    }

    private static function calculateRanges($granularity, $lastDate)
    {
        $last = new DateTime($lastDate);
        if ($granularity === 'week') {
            while ((int)$last->format('N') !== 7) {
                $last->modify('-1 day');
            }
            $currentTo = clone $last;
            $currentFrom = clone $currentTo;
            $currentFrom->modify('-6 days');
            $previousTo = clone $currentFrom;
            $previousTo->modify('-1 day');
            $previousFrom = clone $previousTo;
            $previousFrom->modify('-6 days');
        } else {
            $lastDayOfMonth = (int)$last->format('t');
            $currentDay = (int)$last->format('j');
            if ($currentDay !== $lastDayOfMonth) {
                $last->modify('last day of previous month');
            }
            $currentTo = clone $last;
            $currentFrom = new DateTime($currentTo->format('Y-m-01'));
            $previousTo = clone $currentFrom;
            $previousTo->modify('-1 day');
            $previousFrom = new DateTime($previousTo->format('Y-m-01'));
        }
        return array(
            array('from' => $currentFrom->format('Y-m-d'), 'to' => $currentTo->format('Y-m-d')),
            array('from' => $previousFrom->format('Y-m-d'), 'to' => $previousTo->format('Y-m-d')),
        );
    }

    private static function fetchRange($db, $from, $to)
    {
        $rows = $db->getAll('SELECT sex, age_bucket, city, SUM(orders_cnt) AS orders_cnt, SUM(revenue_sum) AS revenue_sum, SUM(repeat_rate_90d * orders_cnt) AS repeat_weight
            FROM summary_segments_daily WHERE stat_date BETWEEN ?s AND ?s GROUP BY sex, age_bucket, city', $from, $to);
        $data = array();
        foreach ($rows as $row) {
            $key = self::segmentKey($row['sex'], $row['age_bucket'], $row['city']);
            $orders = (int)$row['orders_cnt'];
            $revenue = (float)$row['revenue_sum'];
            $avgCheck = $orders > 0 ? round($revenue / $orders, 2) : 0.0;
            $repeatRate = $orders > 0 ? round(((float)$row['repeat_weight']) / $orders, 3) : 0.0;
            $data[$key] = array(
                'segment_key' => $key,
                'sex' => $row['sex'],
                'age_bucket' => $row['age_bucket'],
                'city' => $row['city'],
                'orders' => $orders,
                'revenue' => round($revenue, 2),
                'avg_check' => $avgCheck,
                'repeat_rate' => $repeatRate,
            );
        }
        return $data;
    }

    private static function combineSegments($current, $previous)
    {
        $segments = array();
        $keys = array_unique(array_merge(array_keys($current), array_keys($previous)));
        foreach ($keys as $key) {
            $cur = isset($current[$key]) ? $current[$key] : null;
            $prev = isset($previous[$key]) ? $previous[$key] : null;
            $ordersPrev = $prev ? $prev['orders'] : 0;
            $revenuePrev = $prev ? $prev['revenue'] : 0.0;
            $segment = array(
                'segment_key' => $key,
                'sex' => $cur ? $cur['sex'] : ($prev ? $prev['sex'] : null),
                'age_bucket' => $cur ? $cur['age_bucket'] : ($prev ? $prev['age_bucket'] : null),
                'city' => $cur ? $cur['city'] : ($prev ? $prev['city'] : null),
                'orders_this' => $cur ? $cur['orders'] : 0,
                'orders_prev' => $ordersPrev,
                'orders_diff' => ($cur ? $cur['orders'] : 0) - $ordersPrev,
                'orders_pct' => $ordersPrev > 0 ? round((($cur ? $cur['orders'] : 0) - $ordersPrev) / $ordersPrev, 3) : null,
                'revenue_this' => $cur ? $cur['revenue'] : 0.0,
                'revenue_prev' => $revenuePrev,
                'revenue_diff' => ($cur ? $cur['revenue'] : 0.0) - $revenuePrev,
                'revenue_pct' => $revenuePrev > 0 ? round((($cur ? $cur['revenue'] : 0.0) - $revenuePrev) / $revenuePrev, 3) : null,
                'avg_check_this' => $cur ? $cur['avg_check'] : 0.0,
                'avg_check_prev' => $prev ? $prev['avg_check'] : 0.0,
                'repeat_rate_this' => $cur ? $cur['repeat_rate'] : 0.0,
                'repeat_rate_prev' => $prev ? $prev['repeat_rate'] : 0.0,
            );
            $segments[$key] = $segment;
        }
        return array_values($segments);
    }

    private static function segmentKey($sex, $age, $city)
    {
        return implode('|', array($sex === null ? 'null' : $sex, $age === null ? 'null' : $age, $city === null ? 'null' : $city));
    }
}
