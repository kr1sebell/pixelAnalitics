<?php
require_once __DIR__ . '/../../Config.php';
require_once __DIR__ . '/../../Db.php';
require_once __DIR__ . '/../../Helpers/Json.php';
require_once __DIR__ . '/../../Helpers/Cache.php';

class CohortsController
{
    public static function handle($params)
    {
        Config::load(dirname(dirname(dirname(dirname(__DIR__)))));
        Cache::init(dirname(dirname(dirname(dirname(__DIR__)))));
        $limit = isset($params['limit']) ? (int)$params['limit'] : 6;
        if ($limit <= 0 || $limit > 24) {
            $limit = 6;
        }
        $cacheKey = 'cohorts:' . $limit;
        $data = Cache::remember($cacheKey, 900, function () use ($limit) {
            $db = Db::analytics();
            $cohortsRaw = $db->getAll('SELECT DATE_FORMAT(first_order_dt, "%Y-%m-01") AS cohort_month, COUNT(*) AS users_cnt
                FROM dim_user WHERE first_order_dt IS NOT NULL GROUP BY cohort_month ORDER BY cohort_month DESC LIMIT ?i', $limit);
            if (!$cohortsRaw) {
                return array();
            }
            $cohortMonths = array();
            foreach ($cohortsRaw as $row) {
                $cohortMonths[] = $row['cohort_month'];
            }
            $placeholders = "'" . implode("','", $cohortMonths) . "'";
            $rows = $db->getAll('SELECT DATE_FORMAT(du.first_order_dt, "%Y-%m-01") AS cohort_month,
                    DATE_FORMAT(fo.order_dt, "%Y-%m-01") AS period_month,
                    COUNT(DISTINCT fo.user_id) AS users_cnt
                FROM fact_orders fo
                JOIN dim_user du ON du.user_id = fo.user_id
                WHERE du.first_order_dt IS NOT NULL AND DATE_FORMAT(du.first_order_dt, "%Y-%m-01") IN (?p)
                GROUP BY cohort_month, period_month
                ORDER BY cohort_month, period_month', $placeholders);
            $cohortSizes = array();
            foreach ($cohortsRaw as $row) {
                $cohortSizes[$row['cohort_month']] = (int)$row['users_cnt'];
            }
            $result = array();
            $periodData = array();
            foreach ($rows as $row) {
                $cohort = $row['cohort_month'];
                $period = $row['period_month'];
                if (!isset($periodData[$cohort])) {
                    $periodData[$cohort] = array();
                }
                $periodData[$cohort][$period] = (int)$row['users_cnt'];
            }
            foreach ($cohortMonths as $cohort) {
                $size = isset($cohortSizes[$cohort]) ? $cohortSizes[$cohort] : 0;
                $cohortEntry = array(
                    'cohort' => substr($cohort, 0, 7),
                    'size' => $size,
                    'periods' => array(),
                );
                if ($size > 0 && isset($periodData[$cohort])) {
                    foreach ($periodData[$cohort] as $period => $usersCnt) {
                        $offset = self::monthDiff($cohort, $period);
                        if ($offset < 0) {
                            continue;
                        }
                        $cohortEntry['periods'][] = array(
                            'month_offset' => $offset,
                            'users' => $usersCnt,
                            'retention' => round($usersCnt / $size, 3),
                            'period_label' => substr($period, 0, 7),
                        );
                    }
                }
                usort($cohortEntry['periods'], function ($a, $b) {
                    return $a['month_offset'] - $b['month_offset'];
                });
                $result[] = $cohortEntry;
            }
            return $result;
        });
        Json::response(array('cohorts' => $data), 200, array(), 900);
    }

    private static function monthDiff($start, $end)
    {
        $startDate = DateTime::createFromFormat('Y-m-d', $start);
        $endDate = DateTime::createFromFormat('Y-m-d', $end);
        if (!$startDate || !$endDate) {
            return 0;
        }
        $years = (int)$endDate->format('Y') - (int)$startDate->format('Y');
        $months = (int)$endDate->format('m') - (int)$startDate->format('m');
        return $years * 12 + $months;
    }
}
