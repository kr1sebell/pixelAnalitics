<?php
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Api/Router.php';
require_once __DIR__ . '/../src/Api/Controllers/HealthController.php';
require_once __DIR__ . '/../src/Api/Controllers/KpiController.php';
require_once __DIR__ . '/../src/Api/Controllers/SegmentsController.php';
require_once __DIR__ . '/../src/Api/Controllers/RfmController.php';
require_once __DIR__ . '/../src/Api/Controllers/ProductsController.php';
require_once __DIR__ . '/../src/Api/Controllers/CohortsController.php';

Config::load(dirname(dirname(__DIR__)));
$router = new Router();

$router->add('GET', '/', function ($params) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "PixelAnalytics API\n";
    echo "GET /api/health\n";
    echo "GET /api/kpi?period=30d\n";
    echo "GET /api/segments/compare?group=sex_age_city&granularity=week&periods=2\n";
    echo "GET /api/rfm?filter=r>=4&f>=3&m>=3&limit=100\n";
    echo "GET /api/products/daily?from=YYYY-MM-DD&to=YYYY-MM-DD\n";
    echo "GET /api/cohorts?limit=6\n";
});

$router->add('GET', '/api/health', function ($params) {
    HealthController::handle();
});

$router->add('GET', '/api/kpi', function ($params) {
    KpiController::handle($params);
});

$router->add('GET', '/api/segments/compare', function ($params) {
    SegmentsController::handle($params);
});

$router->add('GET', '/api/rfm', function ($params) {
    RfmController::handle($params);
});

$router->add('GET', '/api/products/daily', function ($params) {
    ProductsController::handle($params);
});

$router->add('GET', '/api/cohorts', function ($params) {
    CohortsController::handle($params);
});

$router->dispatch();
