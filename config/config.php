<?php
return [
    'source_db' => [
        'host' => 'mysql56',
        'user' => 'bshop',
        'pass' => 'redalert7878',
        'db'   => 'bshop',
        'port' => 3306,
        'charset' => 'utf8',
    ],
    'analytics_db' => [
        'host' => 'mysql8',
        'user' => 'anal',
        'pass' => 'redalert7878',
        'db'   => 'anal',
        'port' => 3306,
        'charset' => 'utf8mb4',
    ],
    'vk' => [
        'access_token' => 'vk1.a.dg3KQfcSXIujscfHZf7ypzmobhrXjw2fahBQbwXpyHPAErAu_xZMQf29OJDyngCUfn6M9dtnPp6owPAv0tCgP9k51_R-fa861uvMASbcIQd9o9y50AfHrfO_mmuJFSIxAl65IZOnxY3MJs0bdCNqgnxql677wXvH-FC5mEuNjccz7wYsRV6F_D5nK3UktVJti4_XcuuP_yM7OoJ8w5QqVg',
        'api_version' => '5.199',
        'chunk_size' => 400,
        'sleep_between_requests' => 0.34
    ],
    'segmentation' => [
        'default_period_days' => 7,
        'lag_period_days' => 7
    ],
];
