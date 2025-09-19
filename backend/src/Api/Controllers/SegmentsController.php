<?php
namespace PixelAnalytics\Api\Controllers;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Json;

class SegmentsController
{
    public function compare($query)
    {
        $periods = isset($query['periods']) ? (int) $query['periods'] : 2;
        $periods = max(1, min($periods, 4));
        $daysBack = $periods * 7;
        $endDate = date('Y-m-d', strtotime('sunday last week'));
        $startDate = date('Y-m-d', strtotime('-' . ($daysBack - 1) . ' days', strtotime($endDate)));

        $db = Db::analytics();
        $sql = 'SELECT YEARWEEK(stat_date, 1) AS wk, sex, age_bucket, city,
                SUM(revenue_sum) AS revenue_sum,
                SUM(orders_cnt) AS orders_cnt,
                SUM(users_cnt) AS users_cnt
            FROM summary_segments_daily
            WHERE stat_date BETWEEN ? AND ?
            GROUP BY wk, sex, age_bucket, city';
        $rows = Db::query($db, $sql, array($startDate, $endDate));

        $weeks = array();
        foreach ($rows as $row) {
            $week = $row['wk'];
            if (!isset($weeks[$week])) {
                $weeks[$week] = array();
            }
            $key = $this->buildKey($row['sex'], $row['age_bucket'], $row['city']);
            $weeks[$week][$key] = array(
                'key' => $key,
                'sex' => $row['sex'],
                'age_bucket' => $row['age_bucket'],
                'city' => $row['city'],
                'revenue' => (float) $row['revenue_sum'],
                'orders' => (int) $row['orders_cnt'],
                'users' => (int) $row['users_cnt'],
            );
        }

        ksort($weeks);
        $weekKeys = array_keys($weeks);
        $countWeeks = count($weekKeys);
        if ($countWeeks < 2) {
            return Json::success(array('groups' => array(), 'weeks' => $weekKeys));
        }

        $currentWeek = $weekKeys[$countWeeks - 1];
        $previousWeek = $weekKeys[$countWeeks - 2];

        $currentData = isset($weeks[$currentWeek]) ? $weeks[$currentWeek] : array();
        $previousData = isset($weeks[$previousWeek]) ? $weeks[$previousWeek] : array();

        $allKeys = array_unique(array_merge(array_keys($currentData), array_keys($previousData)));
        $groups = array();
        foreach ($allKeys as $key) {
            $current = isset($currentData[$key]) ? $currentData[$key] : $this->emptyGroup($key);
            $previous = isset($previousData[$key]) ? $previousData[$key] : $this->emptyGroup($key);
            $meta = $current['sex'] !== null ? $current : $previous;
            $groups[] = array(
                'key' => $key,
                'sex' => $meta['sex'],
                'age_bucket' => $meta['age_bucket'],
                'city' => $meta['city'],
                'current' => $current,
                'previous' => $previous,
                'wow_revenue' => $this->deltaPercent($previous['revenue'], $current['revenue']),
                'wow_orders' => $this->deltaPercent($previous['orders'], $current['orders']),
                'wow_users' => $this->deltaPercent($previous['users'], $current['users']),
            );
        }

        return Json::success(array(
            'weeks' => array('previous' => $previousWeek, 'current' => $currentWeek),
            'groups' => $groups,
        ));
    }

    private function buildKey($sex, $age, $city)
    {
        return implode(':', array($sex, $age, $city));
    }

    private function emptyGroup($key)
    {
        $parts = explode(':', $key);
        return array(
            'key' => $key,
            'sex' => isset($parts[0]) ? $parts[0] : null,
            'age_bucket' => isset($parts[1]) ? $parts[1] : 'unknown',
            'city' => isset($parts[2]) ? $parts[2] : 'unknown',
            'revenue' => 0.0,
            'orders' => 0,
            'users' => 0,
        );
    }

    private function deltaPercent($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return ($current - $previous) / $previous * 100.0;
    }
}
