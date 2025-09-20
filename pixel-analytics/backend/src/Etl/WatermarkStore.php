<?php
require_once __DIR__ . '/../Db.php';

class WatermarkStore
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function get($name, $default = null)
    {
        $value = $this->db->getOne('SELECT value_str FROM etl_watermarks WHERE name = ?s', $name);
        if ($value === null) {
            return $default;
        }
        return $value;
    }

    public function set($name, $value)
    {
        $this->db->query('REPLACE INTO etl_watermarks SET name = ?s, value_str = ?s, updated_at = NOW()', $name, $value);
    }
}
