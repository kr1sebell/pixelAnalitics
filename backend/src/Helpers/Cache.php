<?php
namespace PixelAnalytics\Helpers;

class Cache
{
    private $path;

    public function __construct($path)
    {
        $this->path = rtrim($path, '/');
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function remember($key, $ttl, $callback)
    {
        $file = $this->path . '/' . md5($key) . '.json';
        $now = time();
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && isset($data['expires_at']) && $data['expires_at'] > $now) {
                return $data['payload'];
            }
        }

        $payload = call_user_func($callback);
        $data = array(
            'expires_at' => $now + $ttl,
            'payload' => $payload,
        );
        file_put_contents($file, json_encode($data));
        return $payload;
    }
}
