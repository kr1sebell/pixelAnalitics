<?php
namespace Vk;

class VkApiClient
{
    private string $accessToken;
    private string $version;
    private int $chunkSize;
    private float $sleep;

    public function __construct(array $config)
    {
        $this->accessToken = $config['access_token'];
        $this->version = $config['api_version'] ?? '5.199';
        $this->chunkSize = $config['chunk_size'] ?? 400;
        $this->sleep = $config['sleep_between_requests'] ?? 0.34;
    }

    public function fetchProfiles(array $vkIds): array
    {
        $vkIds = array_filter(array_unique($vkIds));
        if (!$vkIds) {
            return [];
        }

        $result = [];
        foreach (array_chunk($vkIds, $this->chunkSize) as $chunk) {
            $response = $this->call('users.get', [
                'user_ids' => implode(',', $chunk),
                'fields' => 'bdate,city,domain,sex,photo_max,occupation,counters',
            ]);
            if (!empty($response['response'])) {
                $result = array_merge($result, $response['response']);
            }
            usleep((int) ($this->sleep * 1_000_000));
        }

        return $result;
    }

    private function call(string $method, array $params)
    {
        $params['access_token'] = $this->accessToken;
        $params['v'] = $this->version;

        $query = http_build_query($params);
        $url = "https://api.vk.com/method/{$method}?{$query}";

        $response = file_get_contents($url);
        if (false === $response) {
            throw new \RuntimeException('VK API request failed: ' . $method);
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            throw new \RuntimeException('VK API error: ' . json_encode($decoded['error'], JSON_UNESCAPED_UNICODE));
        }

        return $decoded;
    }
}
