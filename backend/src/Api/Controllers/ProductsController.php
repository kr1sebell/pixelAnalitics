<?php
namespace PixelAnalytics\Api\Controllers;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Json;

class ProductsController
{
    public function daily($query)
    {
        $from = isset($query['from']) ? $query['from'] : date('Y-m-d', strtotime('-30 days'));
        $to = isset($query['to']) ? $query['to'] : date('Y-m-d');

        $db = Db::analytics();
        $sql = 'SELECT date, SUM(orders_cnt) AS orders_cnt, SUM(qty_sum) AS qty_sum, SUM(revenue_sum) AS revenue_sum
            FROM summary_products_daily
            WHERE date BETWEEN ? AND ?
            GROUP BY date
            ORDER BY date ASC';
        $rows = Db::query($db, $sql, array($from, $to));

        return Json::success(array(
            'from' => $from,
            'to' => $to,
            'series' => $rows,
        ));
    }
}
