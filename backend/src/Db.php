<?php
namespace PixelAnalytics;

use Exception;

class Db
{
    /** @var array */
    private static $connections = array();

    public static function analytics()
    {
        return self::connect('analytics_db');
    }

    public static function production()
    {
        return self::connect('prod_db');
    }

    private static function connect($configKey)
    {
        if (isset(self::$connections[$configKey])) {
            return self::$connections[$configKey];
        }

        $config = Config::get($configKey);
        if (!$config) {
            throw new Exception('Database configuration missing for ' . $configKey);
        }

        if (class_exists('\\SafeMySQL')) {
            $safeMysqlConfig = array(
                'user' => $config['user'],
                'pass' => $config['pass'],
                'db' => $config['name'],
                'host' => $config['host'],
                'charset' => $config['charset'],
            );
            self::$connections[$configKey] = new \SafeMySQL($safeMysqlConfig);
        } else {
            $mysqli = new \mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
            if ($mysqli->connect_errno) {
                throw new Exception('Failed to connect to MySQL: ' . $mysqli->connect_error);
            }
            if (!empty($config['charset'])) {
                $mysqli->set_charset($config['charset']);
            }
            self::$connections[$configKey] = $mysqli;
        }

        return self::$connections[$configKey];
    }

    public static function query($connection, $sql, $params = array())
    {
        if ($connection instanceof \SafeMySQL) {
            return $connection->query($sql, $params);
        }

        if ($connection instanceof \mysqli) {
            if (!empty($params)) {
                $sql = self::prepareSql($connection, $sql, $params);
            }

            $result = $connection->query($sql);
            if ($result === true) {
                return true;
            }
            if ($result === false) {
                throw new Exception('MySQL error: ' . $connection->error);
            }
            $rows = array();
            if ($result instanceof \mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $result->free();
            }
            return $rows;
        }

        throw new Exception('Unsupported connection type');
    }

    private static function prepareSql($connection, $sql, $params)
    {
        foreach ($params as $param) {
            if ($param === null) {
                $replacement = 'NULL';
            } elseif (is_int($param) || is_float($param)) {
                $replacement = (string) $param;
            } else {
                $replacement = "'" . $connection->real_escape_string($param) . "'";
            }
            $pos = strpos($sql, '?');
            if ($pos === false) {
                break;
            }
            $sql = substr_replace($sql, $replacement, $pos, 1);
        }
        return $sql;
    }
}
