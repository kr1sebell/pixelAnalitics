<?php
// Загружаем .env в окружение
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

return [
    'source_db' => [
        'host' => getenv('SOURCE_DB_HOST') ?: 'localhost',
        'user' => getenv('SOURCE_DB_USER') ?: 'root',
        'pass' => getenv('SOURCE_DB_PASS') ?: '',
        'db'   => getenv('SOURCE_DB_NAME') ?: '',
        'port' => getenv('SOURCE_DB_PORT') ?: 3306,
        'charset' => getenv('SOURCE_DB_CHARSET') ?: 'utf8',
    ],
    'analytics_db' => [
        'host' => getenv('ANALYTICS_DB_HOST') ?: 'localhost',
        'user' => getenv('ANALYTICS_DB_USER') ?: 'root',
        'pass' => getenv('ANALYTICS_DB_PASS') ?: '',
        'db'   => getenv('ANALYTICS_DB_NAME') ?: '',
        'port' => getenv('ANALYTICS_DB_PORT') ?: 3306,
        'charset' => getenv('ANALYTICS_DB_CHARSET') ?: 'utf8mb4',
    ],
    'vk' => [
        'access_token' => getenv('VK_TOKEN') ?: '',
        'api_version'  => getenv('VK_API_VERSION') ?: '5.199',
        'chunk_size'   => getenv('VK_CHUNK_SIZE') ?: 400,
        'sleep_between_requests' => getenv('VK_SLEEP_BETWEEN_REQUESTS') ?: 0.34,
    ],
    'segmentation' => [
        'default_period_days' => getenv('SEGMENTATION_DEFAULT_PERIOD_DAYS') ?: 7,
        'lag_period_days'     => getenv('SEGMENTATION_LAG_PERIOD_DAYS') ?: 7,
    ],
    'yandex_api_key' => getenv('yandex_api_key') ?: '0'
];
