<?php
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Helpers/Json.php';
require_once __DIR__ . '/../Helpers/Cache.php';
require_once __DIR__ . '/../Helpers/Logger.php';

class Router
{
    private $routes = array();

    public function add($method, $path, $handler)
    {
        $method = strtoupper($method);
        $this->routes[$method . ':' . $path] = $handler;
    }

    public function dispatch()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $key = strtoupper($method) . ':' . rtrim($path, '/') ;
        if ($key === 'GET:') {
            $key = 'GET:/';
        }
        if (isset($this->routes[$key])) {
            $handler = $this->routes[$key];
            call_user_func($handler, $_REQUEST);
            return;
        }
        Json::response(array('error' => 'Not found'), 404);
    }
}
