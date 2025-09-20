<?php
namespace ETL;

use SafeMySQL;

class DataSyncService
{
    private SafeMySQL $source;
    private SafeMySQL $analytics;
    private array $productCache = [];
    private array $cityCache = [];

    public function __construct(SafeMySQL $source, SafeMySQL $analytics)
    {
        $this->source = $source;
        $this->analytics = $analytics;
    }

    public function sync(): void
    {
        $lastSync = $this->getLastSync('orders');
        $orders = $this->fetchOrders($lastSync);
        $maxUpdated = $lastSync ? strtotime($lastSync) : null;
        foreach ($orders as $order) {
            $this->upsertOrder($order);
            $this->syncOrderItems($order['id']);
            if (!empty($order['updated_at'])) {
                $orderUpdated = strtotime($order['updated_at']);
                if ($maxUpdated === null || $orderUpdated > $maxUpdated) {
                    $maxUpdated = $orderUpdated;
                }
            }
        }

        $userIds = array_unique(array_column($orders, 'id_user'));
        if ($userIds) {
            $users = $this->fetchUsers($userIds);
            foreach ($users as $user) {
                $this->upsertUser($user);
            }
        }

        if ($maxUpdated) {
            $this->setLastSync('orders', date('Y-m-d H:i:s', $maxUpdated));
        }
    }

    private function fetchOrders(?string $lastSync): array
    {
        $sql = "SELECT * FROM zakazy WHERE deleted = 0 AND status IN (1,6)";
        $params = [];
        if ($lastSync) {
            $sql .= " AND updated_at > ?s";
            $params[] = $lastSync;
        }

        return $this->source->getAll($sql, $params);
    }

    private function fetchUsers(array $userIds): array
    {
        $placeholders = implode(',', array_fill(0, count($userIds), '?i'));
        $sql = "SELECT * FROM users WHERE id IN ($placeholders)";
        return $this->source->getAll($sql, $userIds);
    }

    private function upsertOrder(array $order): void
    {
        $existing = $this->analytics->getRow(
            'SELECT id, order_number FROM analytics_orders WHERE source_order_id = ?i',
            [$order['id']]
        );

        $orderDate = $this->normalizeDate($order['date']);
        $orderDatetime = $orderDate . ' ' . ($order['time'] ?? '00:00:00');

        if ($existing) {
            $orderNumber = $existing['order_number'];
        } else {
            $orderNumber = $this->calculateOrderNumber($order['id_user'], $orderDatetime);
        }

        $data = [
            'source_order_id' => (int) $order['id'],
            'source_user_id' => (int) $order['id_user'],
            'order_date' => $orderDate,
            'order_datetime' => $orderDatetime,
            'status' => (int) $order['status'],
            'payment_type' => (int) $order['type'],
            'total_sum' => (float) $order['summa'],
            'total_sum_discounted' => isset($order['sales']) ? (float) $order['sales'] : null,
            'total_items' => $this->calculateTotalItems($order),
            'city_id' => $order['city_id'] !== null ? (int) $order['city_id'] : null,
            'city_name' => $this->getCityName($order['city_id'] ?? null),
            'repeat_order' => $orderNumber > 1 ? 1 : 0,
            'order_number' => $orderNumber,
            'weekday' => (int) date('N', strtotime($orderDate)),
        ];

        $this->analytics->insertOrUpdate('analytics_orders', $data, $data);
    }

    private function normalizeDate(string $rawDate): string
    {
        $rawDate = trim($rawDate);
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd.m.Y', 'd.m.Y H:i:s'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $rawDate);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }
        return date('Y-m-d', strtotime($rawDate));
    }

    private function calculateOrderNumber(int $userId, string $orderDatetime): int
    {
        $count = $this->analytics->getOne(
            'SELECT COUNT(*) FROM analytics_orders WHERE source_user_id = ?i AND order_datetime < ?s',
            [$userId, $orderDatetime]
        );
        return (int) $count + 1;
    }

    private function calculateTotalItems(array $order): int
    {
        $ids = array_filter(array_map('trim', explode(',', $order['kol_tovar'] ?? '')));
        $total = 0;
        foreach ($ids as $value) {
            $total += (int) $value;
        }
        if (!empty($order['dop_tovar_count'])) {
            $dopCounts = array_filter(array_map('trim', explode(',', $order['dop_tovar_count'])));
            foreach ($dopCounts as $count) {
                $total += (int) $count;
            }
        }
        return $total;
    }

    private function syncOrderItems(int $orderId): void
    {
        $items = $this->source->getAll(
            'SELECT * FROM orders_list_full WHERE id_zakaz = ?i AND delete_tovar = 0',
            [$orderId]
        );

        $analyticsOrderId = $this->analytics->getOne(
            'SELECT id FROM analytics_orders WHERE source_order_id = ?i',
            [$orderId]
        );

        if (!$analyticsOrderId) {
            return;
        }

        foreach ($items as $item) {
            $meta = $this->getProductMeta((int) $item['id_tovar']);

            $data = [
                'analytics_order_id' => (int) $analyticsOrderId,
                'source_order_id' => (int) $orderId,
                'product_id' => (int) $item['id_tovar'],
                'product_title' => $item['title_tovar'],
                'category_id' => $meta['category_id'],
                'category_name' => $meta['category_name'],
                'quantity' => (int) $item['count'],
                'price' => (float) $item['price_tovar'],
                'revenue' => (float) $item['summa'],
                'is_gift' => 0,
            ];

            $this->analytics->insertOrUpdate('analytics_order_items', $data, $data);
        }

        $gifts = $this->source->getAll(
            'SELECT * FROM order_list_gift WHERE id_zakaz = ?i AND delete_gift = 0',
            [$orderId]
        );
        foreach ($gifts as $gift) {
            $meta = $this->getProductMeta((int) $gift['id_tovar']);

            $data = [
                'analytics_order_id' => (int) $analyticsOrderId,
                'source_order_id' => (int) $orderId,
                'product_id' => (int) $gift['id_tovar'],
                'product_title' => $gift['title'],
                'category_id' => $meta['category_id'],
                'category_name' => $meta['category_name'],
                'quantity' => (int) $gift['count'],
                'price' => 0,
                'revenue' => 0,
                'is_gift' => 1,
            ];
            $this->analytics->insertOrUpdate('analytics_order_items', $data, $data);
        }
    }

    private function getProductMeta(int $productId): array
    {
        if (isset($this->productCache[$productId])) {
            return $this->productCache[$productId];
        }

        $row = $this->source->getRow(
            'SELECT c.id as category_id, cat.name as category_name
             FROM catalog c
             LEFT JOIN category cat ON cat.id = c.category
             WHERE c.id = ?i',
            [$productId]
        );

        $meta = [
            'category_id' => $row['category_id'] ?? null,
            'category_name' => $row['category_name'] ?? null,
        ];

        $this->productCache[$productId] = $meta;

        return $meta;
    }

    private function getCityName($cityId): ?string
    {
        if ($cityId === null || $cityId === '' || $cityId === '0') {
            return null;
        }

        $cityId = (int) $cityId;
        if (isset($this->cityCache[$cityId])) {
            return $this->cityCache[$cityId];
        }

        $row = $this->source->getRow(
            'SELECT title FROM city_bind WHERE id = ?i',
            [$cityId]
        );

        $name = $row['title'] ?? null;
        $this->cityCache[$cityId] = $name;

        return $name;
    }

    private function upsertUser(array $user): void
    {
        $existing = $this->analytics->getRow(
            'SELECT id, total_orders, total_revenue, total_items FROM analytics_users WHERE source_user_id = ?i',
            [$user['id']]
        );

        $ordersStats = $this->analytics->getRow(
            'SELECT COUNT(*) AS total_orders, SUM(total_sum) AS total_revenue, SUM(total_items) AS total_items, MIN(order_datetime) AS first_order, MAX(order_datetime) AS last_order
             FROM analytics_orders WHERE source_user_id = ?i',
            [$user['id']]
        );

        $birthDate = !empty($user['birthday']) ? date('Y-m-d', strtotime($user['birthday'])) : null;
        $age = $birthDate ? $this->calculateAge($birthDate) : null;
        $ageGroup = $age ? $this->resolveAgeGroup($age) : null;

        $data = [
            'source_user_id' => (int) $user['id'],
            'vk_id' => (int) $user['id_UserVK'] ?: null,
            'phone' => $user['phone'] ?: null,
            'email' => $user['email'] ?: null,
            'first_name' => $user['name'] ?: null,
            'last_name' => $user['last_name'] ?: null,
            'birthday' => $birthDate,
            'gender' => 'unknown',
            'city' => null,
            'occupation' => null,
            'age' => $age,
            'age_group' => $ageGroup,
            'created_at' => date('Y-m-d H:i:s', $user['date_reg']),
            'updated_at' => date('Y-m-d H:i:s'),
            'first_order_at' => $ordersStats['first_order'] ?? null,
            'last_order_at' => $ordersStats['last_order'] ?? null,
            'total_orders' => (int) ($ordersStats['total_orders'] ?? 0),
            'total_revenue' => (float) ($ordersStats['total_revenue'] ?? 0),
            'avg_receipt' => $this->calculateAverageReceipt($ordersStats),
            'total_items' => (int) ($ordersStats['total_items'] ?? 0),
        ];

        $this->analytics->insertOrUpdate('analytics_users', $data, $data);
    }

    private function calculateAverageReceipt(?array $stats): float
    {
        if (!$stats) {
            return 0.0;
        }
        $orders = (int) ($stats['total_orders'] ?? 0);
        $revenue = (float) ($stats['total_revenue'] ?? 0);
        if ($orders === 0) {
            return 0.0;
        }
        return round($revenue / $orders, 2);
    }

    private function calculateAge(string $birthday): ?int
    {
        $birth = date_create($birthday);
        $now = new \DateTime();
        if (!$birth) {
            return null;
        }
        $diff = $birth->diff($now);
        return $diff->y;
    }

    private function resolveAgeGroup(int $age): string
    {
        if ($age < 18) {
            return 'under_18';
        }
        if ($age <= 24) {
            return '18-24';
        }
        if ($age <= 34) {
            return '25-34';
        }
        if ($age <= 44) {
            return '35-44';
        }
        if ($age <= 54) {
            return '45-54';
        }
        if ($age <= 64) {
            return '55-64';
        }
        return '65_plus';
    }

    private function getLastSync(string $key): ?string
    {
        $value = $this->analytics->getOne(
            'SELECT value FROM analytics_metadata WHERE name = ?s',
            [$key]
        );
        return $value ?: null;
    }

    private function setLastSync(string $key, string $value): void
    {
        $this->analytics->insertOrUpdate(
            'analytics_metadata',
            ['name' => $key, 'value' => $value],
            ['value' => $value]
        );
    }
}
