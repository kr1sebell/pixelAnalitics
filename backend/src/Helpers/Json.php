<?php
namespace PixelAnalytics\Helpers;

class Json
{
    public static function success($data = array(), $status = 200)
    {
        return array(
            'status' => $status,
            'body' => array(
                'status' => 'ok',
                'data' => $data,
            ),
        );
    }

    public static function error($message, $status = 500)
    {
        return array(
            'status' => $status,
            'body' => array(
                'status' => 'error',
                'message' => $message,
            ),
        );
    }
}
