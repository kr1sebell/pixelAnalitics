<?php
namespace ETL;

use SafeMySQL;
use Vk\VkApiClient;

class VkProfileSyncService
{
    private SafeMySQL $analytics;
    private VkApiClient $client;

    public function __construct(SafeMySQL $analytics, VkApiClient $client)
    {
        $this->analytics = $analytics;
        $this->client = $client;
    }

    public function sync(array $vkIds): void
    {
        $vkIds = array_filter(array_unique($vkIds));
        if (!$vkIds) {
            return;
        }

        $chunks = array_chunk($vkIds, 990);
        foreach ($chunks as $chunk) {
            // чтобы не словить rate limit, ставим небольшую паузу
            usleep(480000); // 0.38 сек, даёт ~3 запроса/сек

            $profiles = $this->client->fetchProfiles($chunk);
            foreach ($profiles as $profile) {
                $this->processProfile($profile);
                usleep(180000);
            }
        }
    }

    private function processProfile(array $profile): void
    {
            $vkId = (int) $profile['id'];
        $city = $profile['city']['title'] ?? null;
        $occupation = $profile['occupation']['name'] ?? null;
        $sourceUserId = $this->getSourceUserIdByVk($vkId);

            $data = [
                'vk_id' => $vkId,
                'first_name' => $profile['first_name'] ?? null,
                'last_name' => $profile['last_name'] ?? null,
                'sex' => $profile['sex'] ?? null,
                'bdate' => $profile['bdate'] ?? null,
                'city' => $city,
        'country' => $profile['country']['title'] ?? null,
                'occupation' => $occupation,
                'domain' => $profile['domain'] ?? null,
                'photo_url' => $profile['photo_max'] ?? null,
                'data' => json_encode($profile, JSON_UNESCAPED_UNICODE),
            ];

            $this->analytics->insertOrUpdate('analytics_vk_profiles', $data, $data);

            if (null !== $sourceUserId) {
                $userPayload = [
                    'vk_id' => $vkId,
                    'first_name' => $profile['first_name'] ?? null,
                    'last_name' => $profile['last_name'] ?? null,
                    'gender' => $this->translateGender($profile['sex'] ?? null),
                    'city' => $city,
                    'occupation' => $occupation,
                    'age' => $this->resolveAgeFromBirthDate($profile['bdate'] ?? null),
                    'age_group' => $this->resolveAgeGroup($profile['bdate'] ?? null),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $this->analytics->insertOrUpdate(
                    'analytics_users',
                    array_merge(['source_user_id' => $sourceUserId], $userPayload),
                    $userPayload
                );

                $this->analytics->query(
                    "UPDATE analytics_users SET vk_synced = 1 WHERE vk_id = " . (int)$vkId
                );
//                echo "VK юзер ".$vkId;
            }
    }

    private function getSourceUserIdByVk(int $vkId): ?int
    {
        $userId = $this->analytics->getOne(
            'SELECT source_user_id FROM analytics_users WHERE vk_id = ?i',
            [$vkId]
        );
        return $userId ? (int) $userId : null;
    }

    private function translateGender(?int $sex): string
    {
        return match ($sex) {
            1 => 'female',
            2 => 'male',
            default => 'unknown',
        };
    }

    private function resolveAgeFromBirthDate(?string $bdate): ?int
    {
        if (!$bdate) {
            return null;
        }
        $parts = explode('.', $bdate);
        if (count($parts) !== 3) {
            return null;
        }
        $date = \DateTime::createFromFormat('d.m.Y', $bdate);
        if (!$date) {
            return null;
        }
        $now = new \DateTime();
        return $now->diff($date)->y;
    }

    private function resolveAgeGroup(?string $bdate): ?string
    {
        $age = $this->resolveAgeFromBirthDate($bdate);
        if (null === $age) {
            return null;
        }
        if ($age < 18) {
            return 'under_18';
        }
        if ($age <= 24) {
            return '18-24';
        }
        if ($age <= 34) {
            return '25-34';
        }
        if ($age <= 44) {
            return '35-44';
        }
        if ($age <= 54) {
            return '45-54';
        }
        if ($age <= 64) {
            return '55-64';
        }
        return '65_plus';
    }
}
