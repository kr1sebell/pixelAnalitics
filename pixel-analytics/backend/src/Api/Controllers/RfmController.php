<?php
require_once __DIR__ . '/../../Config.php';
require_once __DIR__ . '/../../Db.php';
require_once __DIR__ . '/../../Helpers/Json.php';
require_once __DIR__ . '/../../Helpers/Cache.php';

class RfmController
{
    public static function handle($params)
    {
        Config::load(dirname(dirname(dirname(dirname(__DIR__)))));
        Cache::init(dirname(dirname(dirname(dirname(__DIR__)))));
        $limit = isset($params['limit']) ? (int)$params['limit'] : 100;
        if ($limit <= 0 || $limit > 1000) {
            $limit = 100;
        }
        $filters = isset($params['filter']) ? $params['filter'] : '';
        $cacheKey = 'rfm:' . md5($filters . ':' . $limit);
        $data = Cache::remember($cacheKey, 600, function () use ($filters, $limit) {
            $db = Db::analytics();
            $where = self::buildWhere($filters, $db);
            $sql = 'SELECT user_id, recency_days, frequency_90d, monetary_90d, r_class, f_class, m_class FROM summary_rfm';
            if ($where) {
                $sql .= ' WHERE ' . $where;
            }
            $sql .= ' ORDER BY monetary_90d DESC LIMIT ' . (int)$limit;
            $rows = $db->getAll($sql);
            foreach ($rows as &$row) {
                $row['monetary_90d'] = round((float)$row['monetary_90d'], 2);
            }
            $matrix = self::buildMatrix($db, $where);
            return array('items' => $rows, 'matrix' => $matrix);
        });
        Json::response($data, 200, array(), 600);
    }

    private static function buildWhere($filters, $db)
    {
        if (!$filters) {
            return '';
        }
        $parts = explode('&', $filters);
        $conditions = array();
        foreach ($parts as $part) {
            if (preg_match('~^([rfm])\s*(<=|>=|=|<|>)\s*(\d+)~i', $part, $m)) {
                $field = strtolower($m[1]);
                $operator = $m[2];
                $value = (int)$m[3];
                $column = null;
                if ($field === 'r') {
                    $column = 'r_class';
                } elseif ($field === 'f') {
                    $column = 'f_class';
                } elseif ($field === 'm') {
                    $column = 'm_class';
                }
                if ($column) {
                    $conditions[] = $column . ' ' . $operator . ' ' . (int)$value;
                }
            }
        }
        return implode(' AND ', $conditions);
    }

    private static function buildMatrix($db, $where)
    {
        $sql = 'SELECT r_class, f_class, SUM(monetary_90d) AS revenue FROM summary_rfm';
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' GROUP BY r_class, f_class';
        $rows = $db->getAll($sql);
        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float)$row['revenue'];
        }
        $cells = array();
        for ($r = 1; $r <= 5; $r++) {
            $cells[$r] = array();
            for ($f = 1; $f <= 5; $f++) {
                $cells[$r][$f] = array('revenue' => 0.0, 'share' => 0.0);
            }
        }
        foreach ($rows as $row) {
            $r = (int)$row['r_class'];
            $f = (int)$row['f_class'];
            $revenue = (float)$row['revenue'];
            $share = $total > 0 ? round($revenue / $total, 4) : 0.0;
            $cells[$r][$f] = array('revenue' => round($revenue, 2), 'share' => $share);
        }
        return array('total_revenue' => round($total, 2), 'cells' => $cells);
    }
}
