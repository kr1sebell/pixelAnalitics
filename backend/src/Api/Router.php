<?php
namespace PixelAnalytics\Api;

use PixelAnalytics\Helpers\Cache;
use PixelAnalytics\Helpers\Json;
use PixelAnalytics\Api\Controllers\HealthController;
use PixelAnalytics\Api\Controllers\KpiController;
use PixelAnalytics\Api\Controllers\SegmentsController;
use PixelAnalytics\Api\Controllers\RfmController;
use PixelAnalytics\Api\Controllers\ProductsController;
use PixelAnalytics\Api\Controllers\CohortsController;

class Router
{
    private $routes = array();
    private $cache;

    public function __construct()
    {
        $this->cache = new Cache(__DIR__ . '/../../cache');
    }

    public function registerDefaults()
    {
        $this->get('/api/health', array(new HealthController(), 'index'), 60);
        $this->get('/api/kpi', array(new KpiController(), 'index'), 300);
        $this->get('/api/segments/compare', array(new SegmentsController(), 'compare'), 600);
        $this->get('/api/rfm', array(new RfmController(), 'index'), 600);
        $this->get('/api/products/daily', array(new ProductsController(), 'daily'), 600);
        $this->get('/api/cohorts', array(new CohortsController(), 'index'), 900);
    }

    public function get($path, $handler, $ttl = 0)
    {
        $this->routes['GET'][$path] = array('handler' => $handler, 'ttl' => $ttl);
    }

    public function dispatch($method, $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!isset($this->routes[$method][$path])) {
            return Json::error('Not Found', 404);
        }

        $route = $this->routes[$method][$path];
        $handler = $route['handler'];
        $ttl = isset($route['ttl']) ? (int) $route['ttl'] : 0;

        if ($ttl > 0) {
            $cacheKey = $path . '?' . http_build_query($_GET);
            $payload = $this->cache->remember($cacheKey, $ttl, function () use ($handler) {
                return call_user_func($handler, $_GET);
            });
            if (!headers_sent()) {
                header('Cache-Control: public, max-age=' . $ttl);
            }
        } else {
            $payload = call_user_func($handler, $_GET);
        }

        if (!is_array($payload) || !isset($payload['status'])) {
            return Json::success($payload);
        }

        return $payload;
    }
}
