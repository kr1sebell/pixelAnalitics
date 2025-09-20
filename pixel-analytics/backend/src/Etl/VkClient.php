<?php
require_once __DIR__ . '/../Helpers/Logger.php';

class VkClient
{
    private $token;
    private $version;
    private $lastRequest = 0.0;

    public function __construct($token, $version)
    {
        $this->token = $token;
        $this->version = $version;
    }

    public function isEnabled()
    {
        return !empty($this->token);
    }

    public function fetchUsers(array $ids)
    {
        if (!$this->isEnabled()) {
            return array();
        }
        $params = array(
            'user_ids' => implode(',', $ids),
            'fields' => 'sex,bdate,city,country,occupation,education,relation,last_seen,is_closed'
        );
        $result = $this->call('users.get', $params);
        if (!isset($result['response'])) {
            return array();
        }
        return $result['response'];
    }

    private function call($method, array $params)
    {
        $params['access_token'] = $this->token;
        $params['v'] = $this->version;
        $url = 'https://api.vk.com/method/' . $method;

        $attempt = 0;
        do {
            $this->throttle();
            $attempt++;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $body = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                Logger::error('vk_client', 'VK request failed', array('error' => $error));
                usleep($attempt * 200000);
                continue;
            }
            $decoded = json_decode($body, true);
            if (isset($decoded['error'])) {
                $errCode = isset($decoded['error']['error_code']) ? $decoded['error']['error_code'] : 'unknown';
                Logger::error('vk_client', 'VK API error', array('code' => $errCode, 'body' => $decoded));
                if ($errCode == 6) {
                    usleep($attempt * 300000);
                    continue;
                }
            }
            if ($status >= 200 && $status < 300) {
                return $decoded;
            }
            usleep($attempt * 200000);
        } while ($attempt < 5);

        return array();
    }

    private function throttle()
    {
        $minInterval = 0.35;
        $now = microtime(true);
        $elapsed = $now - $this->lastRequest;
        if ($elapsed < $minInterval) {
            usleep((int)(($minInterval - $elapsed) * 1000000));
        }
        $this->lastRequest = microtime(true);
    }
}
