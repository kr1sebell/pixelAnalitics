<?php
class Logger
{
    private static $logDir;

    public static function init($rootDir)
    {
        if (self::$logDir) {
            return;
        }
        self::$logDir = rtrim($rootDir, '/').'/backend/logs';
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0775, true);
        }
    }

    public static function info($channel, $message, array $context = array())
    {
        self::write('INFO', $channel, $message, $context);
    }

    public static function error($channel, $message, array $context = array())
    {
        self::write('ERROR', $channel, $message, $context);
    }

    private static function write($level, $channel, $message, array $context)
    {
        if (!self::$logDir) {
            self::init(dirname(dirname(__DIR__)));
        }
        $file = self::$logDir . '/' . $channel . '.log';
        $date = date('Y-m-d H:i:s');
        if (!empty($context)) {
            $message .= ' ' . json_encode($context);
        }
        file_put_contents($file, "[$date] [$level] $message" . PHP_EOL, FILE_APPEND);
    }
}
