<?php
namespace PixelAnalytics\Etl;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Logger;
use PixelAnalytics\Helpers\Date;

class Extract
{
    /** @var Logger */
    private $logger;

    /** @var WatermarkStore */
    private $watermarks;

    public function __construct(Logger $logger, WatermarkStore $watermarks)
    {
        $this->logger = $logger;
        $this->watermarks = $watermarks;
    }

    public function run()
    {
        $lastOrderId = $this->watermarks->get('orders_last_id', 0);
        $this->logger->info('Starting ETL extract', array('lastOrderId' => $lastOrderId));

        $prodDb = Db::production();
        $anDb = Db::analytics();

        $sqlOrders = 'SELECT * FROM orders WHERE id > ? ORDER BY id ASC LIMIT 1000';
        $orders = Db::query($prodDb, $sqlOrders, array((int) $lastOrderId));

        if (empty($orders)) {
            $this->logger->info('No new orders to process');
            return array('orders' => 0, 'order_items' => 0, 'users' => 0);
        }

        $maxId = $lastOrderId;
        $ordersInserted = 0;
        $itemsInserted = 0;
        $usersInserted = 0;

        foreach ($orders as $order) {
            $maxId = max($maxId, $order['id']);
            $this->stageOrder($anDb, $order);
            $ordersInserted += $this->upsertOrder($anDb, $order);
            $itemsInserted += $this->syncOrderItems($prodDb, $anDb, $order['id']);
            $usersInserted += $this->upsertUser($prodDb, $anDb, $order['user_id']);
        }

        $this->watermarks->set('orders_last_id', $maxId);

        $this->logger->info('ETL extract completed', array(
            'orders' => $ordersInserted,
            'order_items' => $itemsInserted,
            'users' => $usersInserted,
        ));

        return array('orders' => $ordersInserted, 'order_items' => $itemsInserted, 'users' => $usersInserted);
    }

    private function upsertOrder($anDb, $order)
    {
        $dateSql = 'SELECT date_sk FROM dim_date WHERE date = ? LIMIT 1';
        $date = substr($order['order_dt'], 0, 10);
        $dateSkRows = Db::query($anDb, $dateSql, array($date));
        $dateSk = !empty($dateSkRows) ? $dateSkRows[0]['date_sk'] : 0;

        $sql = 'REPLACE INTO fact_orders (order_id, user_id, order_dt, date_sk, city_id, channel, payment_type, promo_applied, items_qty, sum_goods, delivery_fee, discount_amount, sum_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        Db::query($anDb, $sql, array(
            $order['id'],
            $order['user_id'],
            $order['order_dt'],
            $dateSk,
            isset($order['city_id']) ? $order['city_id'] : null,
            isset($order['channel']) ? $order['channel'] : null,
            isset($order['payment_type']) ? $order['payment_type'] : null,
            isset($order['promo_applied']) ? (int) $order['promo_applied'] : 0,
            isset($order['items_qty']) ? $order['items_qty'] : 0,
            isset($order['sum_goods']) ? $order['sum_goods'] : 0,
            isset($order['delivery_fee']) ? $order['delivery_fee'] : 0,
            isset($order['discount_amount']) ? $order['discount_amount'] : 0,
            isset($order['sum_total']) ? $order['sum_total'] : 0,
        ));

        return 1;
    }

    private function stageOrder($anDb, $order)
    {
        $sql = 'REPLACE INTO stg_orders (id, user_id, order_dt, city_id, channel, payment_type, promo_applied, items_qty, sum_goods, delivery_fee, discount_amount, sum_total, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        Db::query($anDb, $sql, array(
            $order['id'],
            $order['user_id'],
            $order['order_dt'],
            isset($order['city_id']) ? $order['city_id'] : null,
            isset($order['channel']) ? $order['channel'] : null,
            isset($order['payment_type']) ? $order['payment_type'] : null,
            isset($order['promo_applied']) ? (int) $order['promo_applied'] : 0,
            isset($order['items_qty']) ? $order['items_qty'] : 0,
            isset($order['sum_goods']) ? $order['sum_goods'] : 0,
            isset($order['delivery_fee']) ? $order['delivery_fee'] : 0,
            isset($order['discount_amount']) ? $order['discount_amount'] : 0,
            isset($order['sum_total']) ? $order['sum_total'] : 0,
            isset($order['updated_at']) ? $order['updated_at'] : $order['order_dt'],
        ));
    }

    private function stageOrderItem($anDb, $item)
    {
        $sql = 'REPLACE INTO stg_order_items (order_id, product_id, qty, price, amount) VALUES (?, ?, ?, ?, ?)';
        Db::query($anDb, $sql, array(
            $item['order_id'],
            $item['product_id'],
            $item['qty'],
            $item['price'],
            $item['amount'],
        ));
    }

    private function stageUser($anDb, $user)
    {
        $sql = 'REPLACE INTO stg_users (id, phone, vk_id, created_at, updated_at, do_not_profile) VALUES (?, ?, ?, ?, ?, ?)';
        Db::query($anDb, $sql, array(
            $user['id'],
            isset($user['phone']) ? $user['phone'] : null,
            isset($user['vk_id']) ? $user['vk_id'] : null,
            isset($user['created_at']) ? $user['created_at'] : null,
            isset($user['updated_at']) ? $user['updated_at'] : null,
            isset($user['do_not_profile']) ? (int) $user['do_not_profile'] : 0,
        ));
    }

    private function syncOrderItems($prodDb, $anDb, $orderId)
    {
        $itemsSql = 'SELECT * FROM order_items WHERE order_id = ?';
        $items = Db::query($prodDb, $itemsSql, array($orderId));
        if (empty($items)) {
            return 0;
        }

        foreach ($items as $item) {
            $this->stageOrderItem($anDb, $item);
            $sql = 'REPLACE INTO fact_order_items (order_id, product_id, qty, price, amount) VALUES (?, ?, ?, ?, ?)';
            Db::query($anDb, $sql, array(
                $item['order_id'],
                $item['product_id'],
                $item['qty'],
                $item['price'],
                $item['amount'],
            ));

            $this->upsertProduct($anDb, $item);
        }

        return count($items);
    }

    private function upsertProduct($anDb, $item)
    {
        $sql = 'INSERT INTO dim_product (product_id, name, category, price_current, updated_at) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), price_current = VALUES(price_current), updated_at = VALUES(updated_at)';
        $name = isset($item['product_name']) ? $item['product_name'] : 'Unknown';
        $category = isset($item['category']) ? $item['category'] : null;
        $price = isset($item['price']) ? $item['price'] : 0;
        $now = Date::formatSql(Date::now());
        Db::query($anDb, $sql, array(
            $item['product_id'],
            $name,
            $category,
            $price,
            $now,
        ));
    }

    private function upsertUser($prodDb, $anDb, $userId)
    {
        $userSql = 'SELECT * FROM users WHERE id = ? LIMIT 1';
        $rows = Db::query($prodDb, $userSql, array($userId));
        if (empty($rows)) {
            return 0;
        }
        $user = $rows[0];
        $this->stageUser($anDb, $user);
        $hash = hash('sha256', isset($user['phone']) ? $user['phone'] : '');
        $binaryHash = pack('H*', $hash);
        $sql = 'INSERT INTO dim_user (user_id, phone_sha256, created_at, do_not_profile, first_order_dt, last_order_dt)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE phone_sha256 = VALUES(phone_sha256), do_not_profile = VALUES(do_not_profile), first_order_dt = LEAST(IFNULL(first_order_dt, VALUES(first_order_dt)), VALUES(first_order_dt)), last_order_dt = GREATEST(IFNULL(last_order_dt, VALUES(last_order_dt)), VALUES(last_order_dt))';
        $createdAt = isset($user['created_at']) ? $user['created_at'] : Date::formatSql(Date::now());
        $orderDt = isset($user['last_order_dt']) ? $user['last_order_dt'] : $createdAt;
        Db::query($anDb, $sql, array(
            $user['id'],
            $binaryHash,
            $createdAt,
            isset($user['do_not_profile']) ? (int) $user['do_not_profile'] : 0,
            $orderDt,
            $orderDt,
        ));

        return 1;
    }
}
