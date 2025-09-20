<?php
return [
    'source_db' => [
        'host' => '127.0.0.1',
        'user' => 'source_user',
        'pass' => 'source_password',
        'db'   => 'source_database',
        'port' => 3306,
        'charset' => 'utf8',
    ],
    'analytics_db' => [
        'host' => '127.0.0.1',
        'user' => 'analytics_user',
        'pass' => 'analytics_password',
        'db'   => 'analytics_database',
        'port' => 3306,
        'charset' => 'utf8mb4',
    ],
    'vk' => [
        'access_token' => 'CHANGE_ME',
        'api_version' => '5.199',
        'chunk_size' => 400,
        'sleep_between_requests' => 0.34
    ],
    'segmentation' => [
        'default_period_days' => 7,
        'lag_period_days' => 7
    ],
];
