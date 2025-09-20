<?php
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Helpers/Logger.php';

class BuildSummaries
{
    private $db;

    public function __construct()
    {
        Config::load(dirname(dirname(__DIR__)));
        Logger::init(dirname(dirname(__DIR__)));
        $this->db = Db::analytics();
    }

    public function run()
    {
        $this->buildRfm();
        $this->buildSegments();
    }

    private function buildRfm()
    {
        $this->db->query('TRUNCATE TABLE summary_rfm');
        $sql = "INSERT INTO summary_rfm (user_id, recency_days, frequency_90d, monetary_90d, r_class, f_class, m_class, segment_label)
            SELECT
                fo.user_id,
                DATEDIFF(CURDATE(), MAX(fo.order_dt)) AS recency_days,
                SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS frequency_90d,
                SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) AS monetary_90d,
                CASE
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 14 THEN 5
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 30 THEN 4
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 60 THEN 3
                    WHEN DATEDIFF(CURDATE(), MAX(fo.order_dt)) <= 120 THEN 2
                    ELSE 1
                END AS r_class,
                CASE
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 6 THEN 5
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 4 THEN 4
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 2 THEN 3
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) >= 1 THEN 2
                    ELSE 1
                END AS f_class,
                CASE
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) >= 8000 THEN 5
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) >= 5000 THEN 4
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) >= 3000 THEN 3
                    WHEN SUM(CASE WHEN fo.order_dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN fo.sum_total ELSE 0 END) > 0 THEN 2
                    ELSE 1
                END AS m_class,
                NULL AS segment_label
            FROM fact_orders fo
            GROUP BY fo.user_id";
        $this->db->query($sql);
        echo "summary_rfm rebuilt\n";
    }

    private function buildSegments()
    {
        $yesterday = new DateTime('yesterday');
        $start = clone $yesterday;
        $start->modify('-6 days');
        $period = new DatePeriod($start, new DateInterval('P1D'), (clone $yesterday)->modify('+1 day'));

        foreach ($period as $date) {
            $day = $date->format('Y-m-d');
            $this->db->query('DELETE FROM summary_segments_daily WHERE stat_date = ?s', $day);
            $sql = "INSERT INTO summary_segments_daily (stat_date, sex, age_bucket, city, users_cnt, orders_cnt, revenue_sum, avg_check, repeat_rate_90d)
                SELECT
                    ?s AS stat_date,
                    vp.sex,
                    CASE
                        WHEN vp.age BETWEEN 18 AND 24 THEN '18-24'
                        WHEN vp.age BETWEEN 25 AND 34 THEN '25-34'
                        WHEN vp.age BETWEEN 35 AND 44 THEN '35-44'
                        WHEN vp.age BETWEEN 45 AND 54 THEN '45-54'
                        WHEN vp.age BETWEEN 55 AND 64 THEN '55-64'
                        WHEN vp.age >= 65 THEN '65+'
                        ELSE NULL
                    END AS age_bucket,
                    vp.city,
                    COUNT(DISTINCT fo.user_id) AS users_cnt,
                    COUNT(*) AS orders_cnt,
                    IFNULL(SUM(fo.sum_total), 0) AS revenue_sum,
                    IFNULL(SUM(fo.sum_total) / NULLIF(COUNT(*), 0), 0) AS avg_check,
                    ROUND(IFNULL(SUM(CASE WHEN (
                        SELECT COUNT(*) FROM fact_orders fo2
                        WHERE fo2.user_id = fo.user_id
                          AND fo2.order_dt >= DATE_SUB(?s, INTERVAL 90 DAY)
                          AND fo2.order_dt < DATE_ADD(?s, INTERVAL 1 DAY)
                    ) >= 2 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 0), 3) AS repeat_rate_90d
                FROM fact_orders fo
                LEFT JOIN vk_profiles vp ON vp.user_id = fo.user_id
                WHERE DATE(fo.order_dt) = ?s
                GROUP BY vp.sex, age_bucket, vp.city";
            $this->db->query($sql, $day, $day, $day, $day);
        }
        echo "summary_segments_daily rebuilt\n";
    }
}
