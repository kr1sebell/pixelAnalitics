<?php
/**
 * SafeMySQL
 * @version 0.9.7
 *
 * A simple wrapper for mysqli with prepared statements.
 * Original author: colshrapnel at phpfaq.ru
 *
 * This version is bundled for PixelAnalytics and kept compatible with PHP 5.6.
 */

class SafeMySQL
{
    protected $conn;
    protected $stats = array();
    protected $emode;
    protected $exname;
    protected $defaults = array(
        'host'      => 'localhost',
        'user'      => 'root',
        'pass'      => '',
        'db'        => '',
        'port'      => NULL,
        'socket'    => NULL,
        'pconnect'  => FALSE,
        'charset'   => 'utf8',
        'errmode'   => 'error',
        'exception' => 'Exception',
    );

    protected $mb;

    public function __construct($opt = array())
    {
        $opt = array_replace($this->defaults, $opt);

        if ($opt['pconnect']) {
            $opt['host'] = "p:" . $opt['host'];
        }

        $mysqli = @new mysqli($opt['host'], $opt['user'], $opt['pass'], $opt['db'], $opt['port'], $opt['socket']);

        if ($mysqli->connect_errno) {
            $this->error("Connection error: " . $mysqli->connect_error, false, $opt['errmode'], $opt['exception']);
        }

        $mysqli->set_charset($opt['charset']);

        $this->conn = $mysqli;
        $this->emode = $opt['errmode'];
        $this->exname = $opt['exception'];
        $this->mb = extension_loaded('mbstring');
    }

    public function __destruct()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    public function query()
    {
        $args = func_get_args();
        $q = $this->prepareQuery($args);
        return $this->rawQuery($q);
    }

    public function rawQuery($query)
    {
        $start = microtime(true);
        $res = $this->conn->query($query);
        $timer = microtime(true) - $start;
        $this->stats[] = array('query' => $query, 'start' => $start, 'timer' => $timer);

        if (!$res) {
            $this->error("Query error: " . $this->conn->error . " [" . $query . "]");
        }

        return $res;
    }

    public function getOne()
    {
        $args = func_get_args();
        $q = $this->prepareQuery($args);
        $res = $this->rawQuery($q);
        if ($row = $res->fetch_row()) {
            return $row[0];
        }
        return NULL;
    }

    public function getRow()
    {
        $args = func_get_args();
        $q = $this->prepareQuery($args);
        $res = $this->rawQuery($q);
        return $res->fetch_assoc();
    }

    public function getAll()
    {
        $args = func_get_args();
        $q = $this->prepareQuery($args);
        $res = $this->rawQuery($q);
        $data = array();
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }

    public function getCol()
    {
        $args = func_get_args();
        $q = $this->prepareQuery($args);
        $res = $this->rawQuery($q);
        $data = array();
        while ($row = $res->fetch_row()) {
            $data[] = $row[0];
        }
        return $data;
    }

    public function getInd()
    {
        $args = func_get_args();
        $index = array_shift($args);
        $q = $this->prepareQuery($args);
        $res = $this->rawQuery($q);
        $data = array();
        while ($row = $res->fetch_assoc()) {
            $data[$row[$index]] = $row;
        }
        return $data;
    }

    public function getIndCol()
    {
        $args = func_get_args();
        $index = array_shift($args);
        $q = $this->prepareQuery($args);
        $res = $this->rawQuery($q);
        $data = array();
        while ($row = $res->fetch_row()) {
            $data[$row[0]] = $row[1];
        }
        return $data;
    }

    public function parse()
    {
        $args = func_get_args();
        return $this->prepareQuery($args);
    }

    public function lastQuery()
    {
        $stat = end($this->stats);
        return $stat['query'];
    }

    public function affectedRows()
    {
        return $this->conn->affected_rows;
    }

    public function insertId()
    {
        return $this->conn->insert_id;
    }

    public function escape($value)
    {
        if ($this->mb && is_string($value)) {
            return $this->conn->real_escape_string($value);
        }
        return $this->conn->real_escape_string($value);
    }

    public function prepareQuery($args)
    {
        $query = array_shift($args);

        if (is_array($query)) {
            $query = implode(' ', $query);
        }

        $array = preg_split('~(\?i|\?n|\?s|\?u|\?a|\?p|\?l|\?e)~u', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
        $query = '';
        $argnum = 0;
        foreach ($array as $fragment) {
            if (strlen($fragment) && $fragment[0] == '?') {
                $value = isset($args[$argnum]) ? $args[$argnum] : NULL;
                $fragment = $this->prepareIdent($fragment, $value);
                $argnum++;
            }
            $query .= $fragment;
        }

        return $query;
    }

    protected function prepareIdent($ident, $value)
    {
        switch ($ident) {
            case '?n':
                if (!is_array($value)) {
                    return '`' . str_replace('`', '``', $value) . '`';
                }
                foreach ($value as $k => $v) {
                    $value[$k] = '`' . str_replace('`', '``', $v) . '`';
                }
                return implode(', ', $value);
            case '?s':
                if (is_null($value)) {
                    return 'NULL';
                }
                return "'" . $this->escape($value) . "'";
            case '?i':
                if (is_null($value)) {
                    return 'NULL';
                }
                return (int)$value;
            case '?a':
                if (!is_array($value)) {
                    $this->error('Array expected for ?a placeholder');
                }
                $values = array();
                foreach ($value as $v) {
                    $values[] = $this->prepareIdent('?s', $v);
                }
                return implode(', ', $values);
            case '?u':
                if (!is_array($value)) {
                    $this->error('Array expected for ?u placeholder');
                }
                $set = array();
                foreach ($value as $k => $v) {
                    $set[] = '`' . str_replace('`', '``', $k) . '` = ' . $this->prepareIdent('?s', $v);
                }
                return implode(', ', $set);
            case '?p':
                return $value;
            case '?l':
                if (!is_array($value)) {
                    $this->error('Array expected for ?l placeholder');
                }
                $list = array();
                foreach ($value as $v) {
                    $list[] = $this->prepareIdent('?i', $v);
                }
                return implode(', ', $list);
            case '?e':
                if (!is_array($value)) {
                    $this->error('Array expected for ?e placeholder');
                }
                $set = array();
                foreach ($value as $k => $v) {
                    $set[] = '`' . str_replace('`', '``', $k) . '` = ' . $this->prepareIdent('?s', $v);
                }
                return implode(' AND ', $set);
        }
        $this->error('Unknown placeholder: ' . $ident);
        return '';
    }

    protected function error($err, $is_error = true, $mode = null, $exname = null)
    {
        $mode = $mode ? $mode : $this->emode;
        $exname = $exname ? $exname : $this->exname;

        if (!$is_error && $mode == 'error') {
            throw new $exname($err);
        }

        switch ($mode) {
            case 'error':
                throw new $exname($err);
            case 'exception':
                throw new $exname($err);
            case 'silent':
                return false;
            default:
                throw new Exception($err);
        }
    }
}
