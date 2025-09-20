<?php
$config = require __DIR__ . '/config/config.php';

spl_autoload_register(function ($class) {
    $prefixes = [
        'Analytics\\' => __DIR__ . '/src/Analytics/',
        'Database\\' => __DIR__ . '/src/Database/',
        'ETL\\' => __DIR__ . '/src/ETL/',
        'Segmentation\\' => __DIR__ . '/src/Segmentation/',
        'Vk\\' => __DIR__ . '/src/Vk/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    $basePath = __DIR__ . '/src/';
    $file = $basePath . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/lib/SafeMySQL.php';
