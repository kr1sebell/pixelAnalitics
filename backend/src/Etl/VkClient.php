<?php
namespace PixelAnalytics\Etl;

use Exception;
use PixelAnalytics\Config;
use PixelAnalytics\Helpers\Logger;

class VkClient
{
    private $logger;
    private $accessToken;
    private $version;
    private $batchSize;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $vkConfig = Config::get('vk');
        $this->accessToken = $vkConfig['access_token'];
        $this->version = $vkConfig['api_version'];
        $this->batchSize = $vkConfig['batch_size'];
    }

    public function fetchProfiles(array $vkIds)
    {
        $result = array();
        $chunks = array_chunk($vkIds, $this->batchSize);
        foreach ($chunks as $chunk) {
            $profiles = $this->fetchChunk($chunk);
            foreach ($profiles as $profile) {
                $result[] = $profile;
            }
            usleep(rand(350000, 500000));
        }
        return $result;
    }

    private function fetchChunk(array $chunk)
    {
        $attempts = 0;
        while ($attempts < 5) {
            $attempts++;
            $query = http_build_query(array(
                'user_ids' => implode(',', $chunk),
                'fields' => implode(',', array(
                    'sex', 'bdate', 'city', 'country', 'occupation', 'education', 'relation', 'last_seen', 'is_closed'
                )),
                'access_token' => $this->accessToken,
                'v' => $this->version,
            ));
            $url = 'https://api.vk.com/method/users.get?' . $query;
            $response = $this->httpGet($url);
            if (!isset($response['response'])) {
                if (isset($response['error'])) {
                    $error = $response['error'];
                    $this->logger->error('VK API error', $error);
                    if ((int) $error['error_code'] === 6) {
                        sleep(1);
                        continue;
                    }
                    throw new Exception('VK API error: ' . $error['error_msg']);
                }
                throw new Exception('VK API unexpected response');
            }

            $profiles = array();
            foreach ($response['response'] as $row) {
                $profiles[] = array(
                    'vk_id' => $row['id'],
                    'sex' => isset($row['sex']) ? (int) $row['sex'] : null,
                    'age' => $this->extractAge($row),
                    'city' => isset($row['city']['title']) ? $row['city']['title'] : null,
                    'occupation' => isset($row['occupation']['name']) ? $row['occupation']['name'] : null,
                    'is_closed' => isset($row['is_closed']) ? (int) $row['is_closed'] : null,
                    'last_seen' => isset($row['last_seen']['time']) ? date('Y-m-d H:i:s', $row['last_seen']['time']) : null,
                    'raw' => json_encode($row),
                );
            }
            return $profiles;
        }

        throw new Exception('Unable to fetch VK profiles after retries');
    }

    private function httpGet($url)
    {
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 10,
            ),
        ));
        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new Exception('Failed to call VK API');
        }
        return json_decode($body, true);
    }

    private function extractAge($row)
    {
        if (empty($row['bdate'])) {
            return null;
        }
        $parts = explode('.', $row['bdate']);
        if (count($parts) !== 3) {
            return null;
        }
        $year = (int) $parts[2];
        if ($year <= 0) {
            return null;
        }
        $age = (int) date('Y') - $year;
        return $age > 0 ? $age : null;
    }
}
