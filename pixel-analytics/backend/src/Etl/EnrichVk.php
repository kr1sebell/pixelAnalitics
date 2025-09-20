<?php
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Helpers/Logger.php';
require_once __DIR__ . '/VkClient.php';

class EnrichVk
{
    private $prod;
    private $an;
    private $client;
    private $batchSize;
    private $cooldownDays;

    public function __construct()
    {
        Config::load(dirname(dirname(__DIR__)));
        Logger::init(dirname(dirname(__DIR__)));
        $this->prod = Db::prod();
        $this->an = Db::analytics();

        $token = Config::get('VK_ACCESS_TOKEN');
        $version = Config::get('VK_API_VERSION', '5.199');
        $this->batchSize = (int)Config::get('VK_BATCH_SIZE', 100);
        if ($this->batchSize <= 0) {
            $this->batchSize = 100;
        }
        $this->cooldownDays = (int)Config::get('VK_FETCH_COOLDOWN_DAYS', 30);
        if ($this->cooldownDays < 1) {
            $this->cooldownDays = 30;
        }
        $this->client = new VkClient($token, $version);
    }

    public function run()
    {
        if (!$this->client->isEnabled()) {
            echo "VK enrichment disabled (no token)\n";
            return;
        }

        $analyticsDb = Config::get('AN_DB_NAME', 'analytics');
        $candidates = $this->prod->getAll('SELECT u.id, u.id_UserVK FROM users u
            LEFT JOIN ' . $analyticsDb . '.vk_profiles vp ON vp.user_id = u.id
            WHERE u.id_UserVK > 0 AND (vp.user_id IS NULL OR vp.fetched_at <= DATE_SUB(NOW(), INTERVAL ?i DAY))
            LIMIT ?i', $this->cooldownDays, $this->batchSize);
        if (!$candidates) {
            echo "No VK profiles to update\n";
            return;
        }
        $vkIds = array();
        $map = array();
        foreach ($candidates as $row) {
            $vkIds[] = $row['id_UserVK'];
            $map[$row['id_UserVK']] = $row['id'];
        }

        $chunks = array_chunk($vkIds, min($this->batchSize, 100));
        $updated = 0;
        foreach ($chunks as $chunk) {
            $profiles = $this->client->fetchUsers($chunk);
            foreach ($profiles as $profile) {
                $vkId = isset($profile['id']) ? $profile['id'] : null;
                if (!$vkId || !isset($map[$vkId])) {
                    continue;
                }
                $userId = $map[$vkId];
                $age = $this->extractAge($profile);
                $city = isset($profile['city']['title']) ? $profile['city']['title'] : null;
                $occupation = isset($profile['occupation']['name']) ? $profile['occupation']['name'] : null;
                $lastSeen = null;
                if (isset($profile['last_seen']['time'])) {
                    $lastSeen = date('Y-m-d H:i:s', (int)$profile['last_seen']['time']);
                }
                $rawJson = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->an->query('REPLACE INTO vk_profiles SET user_id = ?i, vk_id = ?i, sex = ?i, age = ?i, city = ?s, occupation = ?s, is_closed = ?i, last_seen = ?s, fetched_at = NOW(), raw_json = ?s',
                    $userId,
                    $vkId,
                    isset($profile['sex']) ? (int)$profile['sex'] : null,
                    $age,
                    $city,
                    $occupation,
                    isset($profile['is_closed']) ? (int)$profile['is_closed'] : null,
                    $lastSeen,
                    $rawJson
                );
                $updated++;
            }
            usleep(400000);
        }
        echo "VK profiles updated: {$updated}\n";
    }

    private function extractAge($profile)
    {
        if (!isset($profile['bdate'])) {
            return null;
        }
        $parts = explode('.', $profile['bdate']);
        if (count($parts) !== 3) {
            return null;
        }
        $birth = DateTime::createFromFormat('d.m.Y', $profile['bdate']);
        if (!$birth) {
            return null;
        }
        $today = new DateTime('today');
        $age = $today->diff($birth)->y;
        return $age;
    }
}
