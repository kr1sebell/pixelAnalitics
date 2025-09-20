<?php
require_once __DIR__ . '/../../Config.php';
require_once __DIR__ . '/../../Db.php';
require_once __DIR__ . '/../../Helpers/Json.php';
require_once __DIR__ . '/../../Helpers/Cache.php';

class HealthController
{
    public static function handle()
    {
        Config::load(dirname(dirname(dirname(dirname(__DIR__)))));
        $db = Db::analytics();
        $lastWatermark = $db->getOne('SELECT value_str FROM etl_watermarks WHERE name = ?s', 'orders.updated_at');
        $ordersCount = $db->getOne('SELECT COUNT(*) FROM fact_orders');
        $itemsCount = $db->getOne('SELECT COUNT(*) FROM fact_order_items');

        $data = array(
            'status' => 'ok',
            'time' => date('c'),
            'last_watermark' => $lastWatermark,
            'facts_count' => array(
                'orders' => (int)$ordersCount,
                'items' => (int)$itemsCount,
            ),
        );
        Json::response($data, 200, array(), 60);
    }
}
