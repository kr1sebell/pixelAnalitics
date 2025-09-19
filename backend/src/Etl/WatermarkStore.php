<?php
namespace PixelAnalytics\Etl;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Date;

class WatermarkStore
{
    public function get($name, $default = null)
    {
        $db = Db::analytics();
        $sql = 'SELECT value_str FROM etl_watermarks WHERE name = ?';
        $rows = Db::query($db, $sql, array($name));
        if (is_array($rows) && !empty($rows)) {
            return $rows[0]['value_str'];
        }
        return $default;
    }

    public function set($name, $value)
    {
        $db = Db::analytics();
        $sql = 'REPLACE INTO etl_watermarks (name, value_str, updated_at) VALUES (?, ?, ?)';
        $now = Date::formatSql(Date::now());
        Db::query($db, $sql, array($name, $value, $now));
    }
}
