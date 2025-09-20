<?php
class Json
{
    public static function response($data, $status = 200, array $headers = array(), $cacheTtl = null)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            if ($cacheTtl !== null) {
                header('Cache-Control: public, max-age=' . (int)$cacheTtl);
            }
            foreach ($headers as $key => $value) {
                header($key . ': ' . $value);
            }
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
