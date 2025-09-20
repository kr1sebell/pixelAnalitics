<?php
require_once __DIR__ . '/../lib/SafeMySQL.php';
require_once __DIR__ . '/Config.php';

class Db
{
    private static $prod;
    private static $an;

    public static function prod()
    {
        if (!self::$prod) {
            self::$prod = self::createConnection('PROD');
        }
        return self::$prod;
    }

    public static function analytics()
    {
        if (!self::$an) {
            self::$an = self::createConnection('AN');
        }
        return self::$an;
    }

    private static function createConnection($prefix)
    {
        $options = array(
            'host' => Config::get($prefix . '_DB_HOST', '127.0.0.1'),
            'user' => Config::get($prefix . '_DB_USER', 'root'),
            'pass' => Config::get($prefix . '_DB_PASS', ''),
            'db' => Config::get($prefix . '_DB_NAME'),
            'charset' => Config::get($prefix . '_DB_CHARSET', 'utf8'),
            'errmode' => 'error',
        );
        return new SafeMySQL($options);
    }
}
