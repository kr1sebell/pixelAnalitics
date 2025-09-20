<?php
/**
 * SafeMySQL
 *
 * An improved version of SafeMySQL wrapper by colshrapnel
 *
 * @link https://github.com/colshrapnel/safemysql
 * @license MIT
 */
class SafeMySQL
{
    private $conn;
    private $stats;
    private $emode;
    private $exname;

    private $defaults = [
        'host'    => 'localhost',
        'user'    => '',
        'pass'    => '',
        'db'      => '',
        'port'    => null,
        'socket'  => null,
        'pconnect' => false,
        'charset' => 'utf8mb4',
    ];

    /** @var array */
    private $opt;

    private $allowed_types = ['i', 'd', 'f', 's', 'b', 'n', 'a', 'u', 'p'];

    public function __construct($opt = [])
    {
        $opt = array_merge($this->defaults, $opt);

        $this->opt = $opt;
        $this->stats = ['queries' => 0, 'total_time' => 0];
        $this->emode = false;
        $this->exname = 'SafeMySQLException';

        $this->connect();
        $this->setCharset($opt['charset']);
    }

    public function connect()
    {
        $host = $this->opt['host'];
        if ($this->opt['pconnect']) {
            $host = 'p:' . $host;
        }

        $this->conn = mysqli_init();
        if (!empty($this->opt['options'])) {
            foreach ($this->opt['options'] as $option => $value) {
                mysqli_options($this->conn, $option, $value);
            }
        }

        if (!mysqli_real_connect(
            $this->conn,
            $host,
            $this->opt['user'],
            $this->opt['pass'],
            $this->opt['db'],
            $this->opt['port'],
            $this->opt['socket']
        )) {
            $this->error(mysqli_connect_errno(), mysqli_connect_error());
        }
    }

    public function setCharset($charset)
    {
        if ($charset) {
            mysqli_set_charset($this->conn, $charset);
        }
    }

    public function query($query)
    {
        $start = microtime(true);
        $result = mysqli_query($this->conn, $query);
        $time = microtime(true) - $start;
        $this->stats['queries']++;
        $this->stats['total_time'] += $time;

        if (false === $result) {
            $this->error(mysqli_errno($this->conn), mysqli_error($this->conn), $query);
        }

        return $result;
    }

    public function getOne($query, $params = [])
    {
        $query = $this->prepare($query, $params);
        $result = $this->query($query);
        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);
        if (!$row) {
            return null;
        }
        return $row[0];
    }

    public function getRow($query, $params = [], $mode = MYSQLI_ASSOC)
    {
        $query = $this->prepare($query, $params);
        $result = $this->query($query);
        $row = mysqli_fetch_array($result, $mode);
        mysqli_free_result($result);
        return $row ?: null;
    }

    public function getAll($query, $params = [], $mode = MYSQLI_ASSOC)
    {
        $query = $this->prepare($query, $params);
        $result = $this->query($query);
        $rows = [];
        while ($row = mysqli_fetch_array($result, $mode)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }

    public function getCol($query, $params = [])
    {
        $query = $this->prepare($query, $params);
        $result = $this->query($query);
        $col = [];
        while ($row = mysqli_fetch_row($result)) {
            $col[] = $row[0];
        }
        mysqli_free_result($result);
        return $col;
    }

    public function insert($table, array $data)
    {
        $keys = array_map([$this, 'escapeIdentifier'], array_keys($data));
        $values = array_map([$this, 'escapeValue'], array_values($data));

        $sql = 'INSERT INTO ' . $this->escapeIdentifier($table)
            . ' (' . implode(',', $keys) . ')
            VALUES (' . implode(',', $values) . ')';

        $this->query($sql);
        return mysqli_insert_id($this->conn);
    }

    public function insertOrUpdate($table, array $data, array $update)
    {
        $keys = array_map([$this, 'escapeIdentifier'], array_keys($data));
        $values = array_map([$this, 'escapeValue'], array_values($data));

        $updates = [];
        foreach ($update as $key => $value) {
            $updates[] = $this->escapeIdentifier($key) . ' = ' . $this->escapeValue($value);
        }

        $sql = 'INSERT INTO ' . $this->escapeIdentifier($table)
            . ' (' . implode(',', $keys) . ')
            VALUES (' . implode(',', $values) . ')
            ON DUPLICATE KEY UPDATE ' . implode(',', $updates);

        $this->query($sql);
        return mysqli_insert_id($this->conn);
    }

    public function escape($value)
    {
        return mysqli_real_escape_string($this->conn, $value);
    }

    public function prepare($query, $params = [])
    {
        if (!$params) {
            return $query;
        }

        $this->validatePlaceholders($query, $params);

        if (!preg_match_all('~\?[a-z]?~', $query, $matches, PREG_OFFSET_CAPTURE)) {
            return $query;
        }

        $offset = 0;
        foreach ($matches[0] as $index => $match) {
            $placeholder = $match[0];
            $type = substr($placeholder, 1) ?: 's';
            $value = $this->formatValue($params[$index], $type);

            $start = $match[1] + $offset;
            $length = strlen($placeholder);
            $query = substr_replace($query, $value, $start, $length);
            $offset += strlen($value) - $length;
        }

        return $query;
    }

    private function validatePlaceholders($query, $params)
    {
        preg_match_all('~\?[a-z]?~', $query, $matches);
        $placeholders = $matches[0];
        if (count($placeholders) !== count($params)) {
            throw new SafeMySQLException('Number of placeholders does not match number of params');
        }

        foreach ($placeholders as $placeholder) {
            $type = substr($placeholder, 1);
            if ($type === '') {
                $type = 's';
            }
            if (!in_array($type, $this->allowed_types, true)) {
                throw new SafeMySQLException('Placeholder ' . $placeholder . ' is not allowed');
            }
        }
    }

    private function formatValue($value, $type)
    {
        switch ($type) {
            case 'i':
                return (int) $value;
            case 'd':
            case 'f':
                return (float) $value;
            case 'b':
                return $value ? '1' : '0';
            case 'n':
                return $value === null ? 'NULL' : $this->escapeValue($value);
            case 'a':
                if (!is_array($value)) {
                    throw new SafeMySQLException('Value for ?a placeholder must be array');
                }
                $escaped = [];
                foreach ($value as $item) {
                    $escaped[] = $this->escapeValue($item);
                }
                return '(' . implode(',', $escaped) . ')';
            case 'u':
                if (!is_array($value)) {
                    throw new SafeMySQLException('Value for ?u placeholder must be associative array');
                }
                $set = [];
                foreach ($value as $key => $val) {
                    $set[] = $this->escapeIdentifier($key) . ' = ' . $this->escapeValue($val);
                }
                return implode(', ', $set);
            case 'p':
                return $value;
            case 's':
            default:
                return $this->escapeValue($value);
        }
    }

    private function escapeIdentifier($identifier)
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function escapeValue($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . $this->escape((string) $value) . "'";
    }

    private function error($errno, $error, $query = null)
    {
        if (!$this->emode) {
            throw new SafeMySQLException($error . ($query ? "\nQuery: " . $query : '')); 
        }

        $errstr = "Error #:" . $errno . "\n" . $error . "\n";
        if ($query) {
            $errstr .= "Query:\n" . $query;
        }
        trigger_error($errstr, $this->emode);
    }

    public function getStats()
    {
        return $this->stats;
    }

    public function affectedRows()
    {
        return mysqli_affected_rows($this->conn);
    }

    public function insertId()
    {
        return mysqli_insert_id($this->conn);
    }

    public function beginTransaction()
    {
        mysqli_begin_transaction($this->conn);
    }

    public function commit()
    {
        mysqli_commit($this->conn);
    }

    public function rollback()
    {
        mysqli_rollback($this->conn);
    }
}

class SafeMySQLException extends Exception
{
}
