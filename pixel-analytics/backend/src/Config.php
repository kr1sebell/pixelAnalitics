<?php
require_once __DIR__ . '/Helpers/Env.php';

class Config
{
    private static $env = array();
    private static $loaded = false;

    public static function load($rootDir)
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $rootDir . '/.env';
        if (!file_exists($envFile)) {
            $envFile = $rootDir . '/.env.example';
        }

        self::$env = Env::parseFile($envFile);
        self::$loaded = true;

        if (!empty(self::$env['TIMEZONE'])) {
            date_default_timezone_set(self::$env['TIMEZONE']);
        }
    }

    public static function get($key, $default = null)
    {
        return isset(self::$env[$key]) ? self::$env[$key] : $default;
    }

    public static function requireEnv($key)
    {
        if (!isset(self::$env[$key])) {
            throw new Exception('Missing env key: ' . $key);
        }
        return self::$env[$key];
    }

    public static function getAll()
    {
        return self::$env;
    }
}
