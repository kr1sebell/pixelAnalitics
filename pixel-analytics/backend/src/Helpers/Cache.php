<?php
class Cache
{
    private static $dir;

    public static function init($rootDir)
    {
        if (self::$dir) {
            return;
        }
        self::$dir = rtrim($rootDir, '/') . '/backend/cache';
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0775, true);
        }
    }

    public static function remember($key, $ttl, $callback)
    {
        if (!self::$dir) {
            self::init(dirname(dirname(__DIR__)));
        }
        $file = self::$dir . '/' . sha1($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            $data = file_get_contents($file);
            return unserialize($data);
        }
        $value = call_user_func($callback);
        file_put_contents($file, serialize($value));
        return $value;
    }

    public static function clear($key)
    {
        if (!self::$dir) {
            self::init(dirname(dirname(__DIR__)));
        }
        $file = self::$dir . '/' . sha1($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
