<?php
namespace PixelAnalytics\Etl;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Date;
use PixelAnalytics\Helpers\Logger;

class BuildSummaries
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function run()
    {
        $db = Db::analytics();
        $this->rebuildRfm($db);
        $this->rebuildSegments($db, 7);
        $this->rebuildProducts($db, 30);
        $this->rebuildCohorts($db);
    }

    private function rebuildRfm($db)
    {
        $this->logger->info('Rebuilding summary_rfm');
        $sqlDelete = 'TRUNCATE TABLE summary_rfm';
        Db::query($db, $sqlDelete);

        $sqlInsert = "INSERT INTO summary_rfm (user_id, recency_days, frequency_90d, monetary_90d, r_class, f_class, m_class, segment_label)
            SELECT
                fo.user_id,
                DATEDIFF(CURDATE(), MAX(fo.order_dt)) AS recency_days,
                SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS frequency_90d,
                SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) AS monetary_90d,
                CASE
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 7 THEN 5
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 14 THEN 4
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 30 THEN 3
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 60 THEN 2
                    ELSE 1
                END AS r_class,
                CASE
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 10 THEN 5
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 6 THEN 4
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 3 THEN 3
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 2 THEN 2
                    ELSE 1
                END AS f_class,
                CASE
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) >= 50000 THEN 5
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) >= 20000 THEN 4
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) >= 10000 THEN 3
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) >= 5000 THEN 2
                    ELSE 1
                END AS m_class,
                CONCAT(
                    CASE
                        WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 14 THEN 'active'
                        ELSE 'churn_risk'
                    END,
                    '_',
                    CASE
                        WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 6 THEN 'loyal'
                        WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 3 THEN 'regular'
                        ELSE 'new'
                    END
                ) AS segment_label
            FROM fact_orders fo
            INNER JOIN dim_user du ON du.user_id = fo.user_id AND du.do_not_profile = 0
            GROUP BY fo.user_id";
        Db::query($db, $sqlInsert);
    }

    private function rebuildSegments($db, $days)
    {
        $this->logger->info('Rebuilding summary_segments_daily');
        $endDate = Date::now();
        $endDate->modify('-1 day');
        $startDate = clone $endDate;
        $startDate->modify('-' . ($days - 1) . ' day');

        $sqlDelete = 'DELETE FROM summary_segments_daily WHERE stat_date BETWEEN ? AND ?';
        Db::query($db, $sqlDelete, array($startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

        $sqlInsert = "INSERT INTO summary_segments_daily (stat_date, sex, age_bucket, city, users_cnt, orders_cnt, revenue_sum, avg_check, repeat_rate_90d)
            SELECT
                DATE(fo.order_dt) AS stat_date,
                vp.sex,
                CASE
                    WHEN vp.age IS NULL THEN 'unknown'
                    WHEN vp.age < 25 THEN '18-24'
                    WHEN vp.age < 35 THEN '25-34'
                    WHEN vp.age < 45 THEN '35-44'
                    WHEN vp.age < 60 THEN '45-59'
                    ELSE '60+'
                END AS age_bucket,
                COALESCE(vp.city, 'unknown') AS city,
                COUNT(DISTINCT fo.user_id) AS users_cnt,
                COUNT(*) AS orders_cnt,
                SUM(fo.sum_total) AS revenue_sum,
                IFNULL(AVG(fo.sum_total), 0) AS avg_check,
                SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) / GREATEST(COUNT(DISTINCT fo.user_id), 1) AS repeat_rate_90d
            FROM fact_orders fo
            INNER JOIN dim_user du ON du.user_id = fo.user_id AND du.do_not_profile = 0
            LEFT JOIN vk_profiles vp ON vp.user_id = fo.user_id
            WHERE DATE(fo.order_dt) BETWEEN ? AND ?
            GROUP BY stat_date, vp.sex, age_bucket, city";
        Db::query($db, $sqlInsert, array($startDate->format('Y-m-d'), $endDate->format('Y-m-d')));
    }

    private function rebuildProducts($db, $days)
    {
        $this->logger->info('Rebuilding summary_products_daily');
        $endDate = Date::now();
        $endDate->modify('today');
        $startDate = clone $endDate;
        $startDate->modify('-' . $days . ' day');
        $sqlDelete = 'DELETE FROM summary_products_daily WHERE date BETWEEN ? AND ?';
        Db::query($db, $sqlDelete, array($startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

        $sqlInsert = "INSERT INTO summary_products_daily (date, product_id, orders_cnt, qty_sum, revenue_sum, unique_buyers)
            SELECT
                DATE(fo.order_dt) AS date,
                foi.product_id,
                COUNT(DISTINCT fo.order_id) AS orders_cnt,
                SUM(foi.qty) AS qty_sum,
                SUM(foi.amount) AS revenue_sum,
                COUNT(DISTINCT fo.user_id) AS unique_buyers
            FROM fact_orders fo
            INNER JOIN fact_order_items foi ON foi.order_id = fo.order_id
            WHERE DATE(fo.order_dt) BETWEEN ? AND ?
            GROUP BY date, foi.product_id";
        Db::query($db, $sqlInsert, array($startDate->format('Y-m-d'), $endDate->format('Y-m-d')));
    }

    private function rebuildCohorts($db)
    {
        $this->logger->info('Rebuilding summary_cohorts');
        $sqlDelete = 'TRUNCATE TABLE summary_cohorts';
        Db::query($db, $sqlDelete);

        $sqlInsert = "INSERT INTO summary_cohorts (cohort_month, m0, m1, m2, m3, m4, m5, m6)
            SELECT
                DATE_FORMAT(du.first_order_dt, '%Y-%m-01') AS cohort_month,
                SUM(CASE WHEN fo.order_dt < DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) AS m0,
                SUM(CASE WHEN fo.order_dt >= DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 1 MONTH) AND fo.order_dt < DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 2 MONTH) THEN 1 ELSE 0 END) AS m1,
                SUM(CASE WHEN fo.order_dt >= DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 2 MONTH) AND fo.order_dt < DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 3 MONTH) THEN 1 ELSE 0 END) AS m2,
                SUM(CASE WHEN fo.order_dt >= DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 3 MONTH) AND fo.order_dt < DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 4 MONTH) THEN 1 ELSE 0 END) AS m3,
                SUM(CASE WHEN fo.order_dt >= DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 4 MONTH) AND fo.order_dt < DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 5 MONTH) THEN 1 ELSE 0 END) AS m4,
                SUM(CASE WHEN fo.order_dt >= DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 5 MONTH) AND fo.order_dt < DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS m5,
                SUM(CASE WHEN fo.order_dt >= DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 6 MONTH) AND fo.order_dt < DATE_ADD(DATE_FORMAT(du.first_order_dt, '%Y-%m-01'), INTERVAL 7 MONTH) THEN 1 ELSE 0 END) AS m6
            FROM dim_user du
            LEFT JOIN fact_orders fo ON fo.user_id = du.user_id
            WHERE du.first_order_dt IS NOT NULL
            GROUP BY cohort_month";
        Db::query($db, $sqlInsert);
    }
}
