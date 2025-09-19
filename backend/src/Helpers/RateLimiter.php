<?php
namespace PixelAnalytics\Helpers;

class RateLimiter
{
    private $key;
    private $limit;
    private $window;
    private $storagePath;

    public function __construct($key, $limit, $windowSeconds, $storagePath)
    {
        $this->key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $this->limit = (int) $limit;
        $this->window = (int) $windowSeconds;
        $this->storagePath = rtrim($storagePath, '/');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
    }

    public function allow()
    {
        $file = $this->storagePath . '/' . $this->key . '.json';
        $now = time();
        $counter = array('count' => 0, 'expires_at' => $now + $this->window);

        if (file_exists($file)) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['expires_at']) && $decoded['expires_at'] > $now) {
                $counter = $decoded;
            }
        }

        if ($counter['expires_at'] <= $now) {
            $counter['count'] = 0;
            $counter['expires_at'] = $now + $this->window;
        }

        if ($counter['count'] >= $this->limit) {
            return false;
        }

        $counter['count']++;
        file_put_contents($file, json_encode($counter));
        return true;
    }
}
