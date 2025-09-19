<?php
namespace PixelAnalytics\Api\Controllers;

use PixelAnalytics\Helpers\Json;

class HealthController
{
    public function index()
    {
        return Json::success(array(
            'status' => 'ok',
            'time' => date('c'),
        ));
    }
}
