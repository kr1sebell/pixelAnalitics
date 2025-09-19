<?php
namespace PixelAnalytics\Etl;

use PixelAnalytics\Config;
use PixelAnalytics\Db;
use PixelAnalytics\Helpers\Date;
use PixelAnalytics\Helpers\Logger;

class EnrichVk
{
    private $logger;
    private $client;
    private $cooldownDays;

    public function __construct(Logger $logger, VkClient $client)
    {
        $this->logger = $logger;
        $this->client = $client;
        $this->cooldownDays = (int) Config::get('vk.cooldown_days', 30);
    }

    public function findPendingUsers($limit = 500)
    {
        $db = Db::analytics();
        $sql = 'SELECT su.id AS user_id, su.vk_id, vp.fetched_at, du.do_not_profile
            FROM stg_users su
            INNER JOIN dim_user du ON du.user_id = su.id
            LEFT JOIN vk_profiles vp ON vp.user_id = su.id
            WHERE su.vk_id IS NOT NULL AND du.do_not_profile = 0
            AND (vp.user_id IS NULL OR vp.fetched_at <= DATE_SUB(NOW(), INTERVAL ? DAY))
            LIMIT ?';
        return Db::query($db, $sql, array($this->cooldownDays, $limit));
    }

    public function updateProfiles(array $candidates)
    {
        if (empty($candidates)) {
            return array('updated' => 0, 'missed' => 0);
        }

        $vkIds = array();
        $map = array();
        foreach ($candidates as $candidate) {
            $vkIds[] = $candidate['vk_id'];
            $map[$candidate['vk_id']] = $candidate['user_id'];
        }

        $profiles = $this->client->fetchProfiles($vkIds);
        $updated = 0;
        $now = Date::formatSql(Date::now());
        $db = Db::analytics();

        foreach ($profiles as $profile) {
            $vkId = $profile['vk_id'];
            if (!isset($map[$vkId])) {
                continue;
            }
            $userId = $map[$vkId];
            $sql = 'REPLACE INTO vk_profiles (user_id, vk_id, sex, age, city, occupation, is_closed, last_seen, fetched_at, raw_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            Db::query($db, $sql, array(
                $userId,
                $vkId,
                $profile['sex'],
                $profile['age'],
                $profile['city'],
                $profile['occupation'],
                $profile['is_closed'],
                $profile['last_seen'],
                $now,
                $profile['raw'],
            ));
            $updated++;
        }

        $missed = count($vkIds) - $updated;
        if ($missed > 0) {
            $this->logger->error('Some VK profiles were not returned', array('missed' => $missed));
        }

        return array('updated' => $updated, 'missed' => $missed);
    }
}
