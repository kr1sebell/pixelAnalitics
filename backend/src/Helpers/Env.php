<?php
namespace PixelAnalytics\Helpers;

class Env
{
    /** @var array */
    private static $values = array();

    public static function bootstrap($file)
    {
        if (!empty(self::$values)) {
            return;
        }

        $path = $file;
        if (!file_exists($path)) {
            $path = dirname(__DIR__) . '/../.env';
            if (!file_exists($path)) {
                return;
            }
        }

        $lines = file($path);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");
            self::$values[$key] = $value;
            if (!getenv($key)) {
                putenv($key . '=' . $value);
            }
        }
    }

    public static function get($key, $default = null)
    {
        if (isset(self::$values[$key])) {
            return self::$values[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}
