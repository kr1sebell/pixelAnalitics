<?php
namespace PixelAnalytics\Api\Controllers;

use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Json;

class RfmController
{
    private $fieldMap = array(
        'r' => 'r_class',
        'f' => 'f_class',
        'm' => 'm_class',
    );

    public function index($query)
    {
        $filter = isset($query['filter']) ? $query['filter'] : '';
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;
        $limit = max(1, min($limit, 500));

        list($where, $params) = $this->buildWhere($filter);
        $sql = 'SELECT user_id, recency_days, frequency_90d, monetary_90d, r_class, f_class, m_class, segment_label
            FROM summary_rfm';
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY monetary_90d DESC LIMIT ' . $limit;

        $db = Db::analytics();
        $rows = Db::query($db, $sql, $params);

        return Json::success(array(
            'total' => count($rows),
            'items' => $rows,
        ));
    }

    private function buildWhere($filter)
    {
        if (empty($filter)) {
            return array('', array());
        }
        $parts = explode('&', $filter);
        $clauses = array();
        $params = array();
        foreach ($parts as $part) {
            if (!preg_match('/^(r|f|m)([<>]=?)(\d)$/', $part, $matches)) {
                continue;
            }
            $column = $this->fieldMap[$matches[1]];
            $operator = $matches[2];
            $value = (int) $matches[3];
            $clauses[] = $column . ' ' . $operator . ' ?';
            $params[] = $value;
        }
        $where = implode(' AND ', $clauses);
        return array($where, $params);
    }
}
