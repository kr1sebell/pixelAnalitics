<?php
namespace PixelAnalytics\Api\Controllers;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Json;

class CohortsController
{
    public function index()
    {
        $db = Db::analytics();
        $sql = 'SELECT * FROM summary_cohorts ORDER BY cohort_month DESC LIMIT 12';
        $rows = Db::query($db, $sql);
        return Json::success(array('items' => $rows));
    }
}
