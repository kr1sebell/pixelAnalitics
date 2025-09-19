<?php
namespace PixelAnalytics\Helpers;

class Logger
{
    private $file;

    public function __construct($file)
    {
        $this->file = $file;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    public function info($message, $context = array())
    {
        $this->write('INFO', $message, $context);
    }

    public function error($message, $context = array())
    {
        $this->write('ERROR', $message, $context);
    }

    private function write($level, $message, $context)
    {
        $entry = sprintf(
            "[%s] %s %s %s\n",
            date('c'),
            $level,
            $message,
            json_encode($context)
        );
        file_put_contents($this->file, $entry, FILE_APPEND);
    }
}
