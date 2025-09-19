<?php
namespace PixelAnalytics;

use PixelAnalytics\Helpers\Env;

class Config
{
    /** @var array */
    private static $config = array();

    public static function init()
    {
        if (!empty(self::$config)) {
            return;
        }

        Env::bootstrap(dirname(__DIR__) . '/../.env');

        self::$config = array(
            'app_env' => Env::get('APP_ENV', 'prod'),
            'timezone' => Env::get('TIMEZONE', 'Europe/Moscow'),
            'prod_db' => array(
                'host' => Env::get('PROD_DB_HOST'),
                'name' => Env::get('PROD_DB_NAME'),
                'user' => Env::get('PROD_DB_USER'),
                'pass' => Env::get('PROD_DB_PASS'),
                'charset' => Env::get('PROD_DB_CHARSET', 'utf8'),
            ),
            'analytics_db' => array(
                'host' => Env::get('AN_DB_HOST'),
                'name' => Env::get('AN_DB_NAME'),
                'user' => Env::get('AN_DB_USER'),
                'pass' => Env::get('AN_DB_PASS'),
                'charset' => Env::get('AN_DB_CHARSET', 'utf8'),
            ),
            'vk' => array(
                'api_version' => Env::get('VK_API_VERSION'),
                'access_token' => Env::get('VK_ACCESS_TOKEN'),
                'batch_size' => (int) Env::get('VK_BATCH_SIZE', 100),
                'cooldown_days' => (int) Env::get('VK_FETCH_COOLDOWN_DAYS', 30),
            ),
            'http' => array(
                'port' => (int) Env::get('HTTP_PORT', 8080),
            ),
        );

        date_default_timezone_set(self::$config['timezone']);
    }

    public static function get($path, $default = null)
    {
        if (empty(self::$config)) {
            self::init();
        }

        $parts = explode('.', $path);
        $value = self::$config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
