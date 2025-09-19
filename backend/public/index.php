<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PixelAnalytics\Api\Router;
use PixelAnalytics\Config;
use PixelAnalytics\Helpers\Env;

if (!class_exists('Dotenv')) {
    // basic .env loader to keep php -S usable without composer dumps
    Env::bootstrap(dirname(__DIR__) . '/../.env');
}

Config::init();

$router = new Router();
$router->registerDefaults();

$response = $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

http_response_code($response['status']);
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode($response['body']);
