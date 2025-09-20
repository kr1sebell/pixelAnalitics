<?php
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Helpers/Money.php';
require_once __DIR__ . '/../Helpers/Date.php';
require_once __DIR__ . '/../Helpers/Logger.php';
require_once __DIR__ . '/../Helpers/Cache.php';
require_once __DIR__ . '/WatermarkStore.php';

class Extract
{
    const BATCH_LIMIT = 5000;

    private $prod;
    private $an;
    private $watermarks;
    private $cityCache = array();
    private $productCache = array();

    public function __construct()
    {
        Config::load(dirname(dirname(__DIR__)));
        Logger::init(dirname(dirname(__DIR__)));
        Cache::init(dirname(dirname(__DIR__)));
        $this->prod = Db::prod();
        $this->an = Db::analytics();
        $this->watermarks = new WatermarkStore($this->an);
    }

    public function run()
    {
        $processed = 0;
        $skipped = 0;
        $skippedReasons = array();
        $insertedItems = 0;
        $userIds = array();
        $lastUpdatedAt = null;
        $lastId = 0;

        list($wmUpdatedAt, $wmId) = $this->getWatermarkValues();

        while (true) {
            $orders = $this->fetchOrders($wmUpdatedAt, $wmId);
            if (!$orders) {
                break;
            }

            foreach ($orders as $order) {
                $lastUpdatedAt = $order['updated_at'] ? $order['updated_at'] : '1970-01-01 00:00:00';
                $lastId = $order['id'];

                $sum = Money::parse($order['summa']);
                if ($sum === null || $sum < 1) {
                    $skipped++;
                    $skippedReasons['invalid_sum'] = isset($skippedReasons['invalid_sum']) ? $skippedReasons['invalid_sum'] + 1 : 1;
                    Logger::error('etl_extract', 'Skip order: invalid sum', array('order_id' => $order['id'], 'summa' => $order['summa']));
                    continue;
                }

                $orderDt = Date::parseOrderDT($order['date'], $order['time']);
                if ($orderDt === null) {
                    $skipped++;
                    $skippedReasons['invalid_date'] = isset($skippedReasons['invalid_date']) ? $skippedReasons['invalid_date'] + 1 : 1;
                    Logger::error('etl_extract', 'Skip order: invalid date', array('order_id' => $order['id'], 'date' => $order['date'], 'time' => $order['time']));
                    continue;
                }

                $dateSk = Date::toDateSk($orderDt);
                if ($dateSk === null) {
                    $skipped++;
                    $skippedReasons['date_sk'] = isset($skippedReasons['date_sk']) ? $skippedReasons['date_sk'] + 1 : 1;
                    Logger::error('etl_extract', 'Skip order: failed to derive date_sk', array('order_id' => $order['id'], 'order_dt' => $orderDt));
                    continue;
                }

                $cityName = $this->getCityName($order['city_id']);
                $itemsResult = $this->loadOrderItems($order['id']);
                $itemsQty = $itemsResult['qty'];
                $insertedItems += $itemsResult['count'];

                $promo = $order['promokod'] ? trim($order['promokod']) : null;
                $channel = $order['in_zakaz_type'] !== null ? (string)$order['in_zakaz_type'] : null;
                $paymentType = $order['type'] !== null ? (string)$order['type'] : null;

                $this->an->query('REPLACE INTO fact_orders SET order_id = ?i, user_id = ?i, order_dt = ?s, date_sk = ?i, city_id = ?i, city_name = ?s, channel = ?s, payment_type = ?s, promo_applied = ?s, items_qty = ?i, sum_goods = ?s, delivery_fee = 0, discount_amount = 0, sum_total = ?s',
                    $order['id'],
                    $order['id_user'],
                    $orderDt,
                    $dateSk,
                    $order['city_id'] !== null ? (int)$order['city_id'] : null,
                    $cityName,
                    $channel,
                    $paymentType,
                    $promo,
                    $itemsQty,
                    number_format($sum, 2, '.', ''),
                    number_format($sum, 2, '.', '')
                );

                $this->upsertUser($order);
                $userIds[$order['id_user']] = true;

                $processed++;
            }

            $wmUpdatedAt = $lastUpdatedAt;
            $wmId = $lastId;
            $this->saveWatermark($wmUpdatedAt, $wmId);
        }

        if (!empty($userIds)) {
            $this->refreshUserStats(array_keys($userIds));
        }

        echo "Orders processed: {$processed}\n";
        echo "Items inserted: {$insertedItems}\n";
        echo "Skipped: {$skipped}\n";
        foreach ($skippedReasons as $reason => $count) {
            echo "  - {$reason}: {$count}\n";
        }
    }

    private function fetchOrders($wmUpdatedAt, $wmId)
    {
        $sql = 'SELECT z.*, u.phone AS user_phone, u.date_reg AS user_date_reg, u.id_UserVK AS user_vk_id, cb.title AS city_title
            FROM zakazy z
            LEFT JOIN users u ON u.id = z.id_user
            LEFT JOIN city_bind cb ON cb.id = z.city_id
            WHERE z.status = 1
              AND (z.deleted IS NULL OR z.deleted = 0)
              AND (
                COALESCE(z.updated_at, \"1970-01-01 00:00:00\") > ?s
                OR (COALESCE(z.updated_at, \"1970-01-01 00:00:00\") = ?s AND z.id > ?i)
              )
            ORDER BY COALESCE(z.updated_at, \"1970-01-01 00:00:00\") ASC, z.id ASC
            LIMIT ?i';
        return $this->prod->getAll($sql, $wmUpdatedAt, $wmUpdatedAt, $wmId, self::BATCH_LIMIT);
    }

    private function loadOrderItems($orderId)
    {
        $rows = $this->prod->getAll('SELECT * FROM orders_list_full WHERE id_zakaz = ?i AND (delete_tovar IS NULL OR delete_tovar = 0)', $orderId);
        $qtyTotal = 0;
        $inserted = 0;
        foreach ($rows as $row) {
            $qty = isset($row['count']) ? (int)$row['count'] : 0;
            if ($qty < 0) {
                $qty = 0;
            }
            $priceRaw = $row['price_sales'];
            $price = Money::parse($priceRaw);
            if ($price === null || $price <= 0) {
                $price = Money::parse($row['price_tovar']);
            }
            if ($price === null) {
                $price = 0;
            }
            $amount = $price * $qty;
            $amount = round($amount, 2);

            $this->an->query('REPLACE INTO fact_order_items SET order_id = ?i, product_id = ?i, qty = ?i, price = ?s, amount = ?s',
                $orderId,
                $row['id_tovar'],
                $qty,
                number_format($price, 2, '.', ''),
                number_format($amount, 2, '.', '')
            );

            $qtyTotal += $qty;
            $inserted++;

            $this->upsertProduct($row['id_tovar']);
        }
        return array('qty' => $qtyTotal, 'count' => $inserted);
    }

    private function upsertUser($order)
    {
        $userId = (int)$order['id_user'];
        $phone = $order['user_phone'];
        $dateReg = $order['user_date_reg'];
        $phoneHash = null;
        if ($phone) {
            $normalized = preg_replace('~[^0-9]+~', '', $phone);
            if ($normalized !== '') {
                $phoneHash = hash('sha256', $normalized);
            }
        }
        $phoneBinary = $phoneHash ? pack('H*', $phoneHash) : null;
        $createdAt = null;
        if ($dateReg !== null && (int)$dateReg > 0) {
            $createdAt = date('Y-m-d H:i:s', (int)$dateReg);
        }

        $this->an->query('INSERT INTO dim_user (user_id, phone_sha256, created_at) VALUES (?i, ?s, ?s)
            ON DUPLICATE KEY UPDATE phone_sha256 = VALUES(phone_sha256), created_at = COALESCE(VALUES(created_at), created_at)',
            $userId,
            $phoneBinary,
            $createdAt
        );
    }

    private function upsertProduct($productId)
    {
        if (isset($this->productCache[$productId])) {
            return;
        }
        $row = $this->prod->getRow('SELECT c.id, c.title, c.category, c.price, c.active, c.city_bind, cat.name AS category_name
            FROM catalog c
            LEFT JOIN category cat ON cat.id = c.category
            WHERE c.id = ?i', $productId);
        if (!$row) {
            return;
        }
        $price = Money::parse($row['price']);
        $this->an->query('REPLACE INTO dim_product SET product_id = ?i, name = ?s, category_id = ?i, category_name = ?s, price_current = ?s, is_active = ?i, city_bind = ?i',
            $row['id'],
            $row['title'],
            $row['category'] !== null ? (int)$row['category'] : null,
            $row['category_name'],
            $price !== null ? number_format($price, 2, '.', '') : null,
            $row['active'] !== null ? (int)$row['active'] : 0,
            $row['city_bind'] !== null ? (int)$row['city_bind'] : null
        );
        $this->productCache[$productId] = true;
    }

    private function getCityName($cityId)
    {
        if ($cityId === null) {
            return null;
        }
        if (isset($this->cityCache[$cityId])) {
            return $this->cityCache[$cityId];
        }
        $name = $this->prod->getOne('SELECT title FROM city_bind WHERE id = ?i', $cityId);
        $this->cityCache[$cityId] = $name;
        return $name;
    }

    private function refreshUserStats($userIds)
    {
        $ids = array();
        foreach ($userIds as $id) {
            $ids[] = (int)$id;
        }
        if (empty($ids)) {
            return;
        }
        $in = '(' . implode(',', $ids) . ')';
        $sql = "UPDATE dim_user du
            JOIN (
                SELECT user_id, MIN(order_dt) AS first_dt, MAX(order_dt) AS last_dt
                FROM fact_orders
                WHERE user_id IN $in
                GROUP BY user_id
            ) agg ON agg.user_id = du.user_id
            SET du.first_order_dt = agg.first_dt, du.last_order_dt = agg.last_dt
            WHERE du.user_id IN $in";
        $this->an->query($sql);
    }

    private function getWatermarkValues()
    {
        $raw = $this->watermarks->get('orders.updated_at', '1970-01-01 00:00:00|0');
        $parts = explode('|', $raw);
        $ts = isset($parts[0]) ? $parts[0] : '1970-01-01 00:00:00';
        $id = isset($parts[1]) ? (int)$parts[1] : 0;
        return array($ts, $id);
    }

    private function saveWatermark($updatedAt, $id)
    {
        if ($updatedAt === null) {
            $updatedAt = '1970-01-01 00:00:00';
        }
        $value = $updatedAt . '|' . (int)$id;
        $this->watermarks->set('orders.updated_at', $value);
    }
}
