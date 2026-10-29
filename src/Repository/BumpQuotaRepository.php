<?php

namespace HuseyinFiliz\Bump\Repository;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Bump\BumpQuota;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class BumpQuotaRepository
{
    const QUOTA_CACHE_TTL = 60;
    const STATS_CACHE_TTL = 300;

    /**
     * @var CacheRepository
     */
    protected $cache;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    public function __construct(CacheRepository $cache, SettingsRepositoryInterface $settings)
    {
        $this->cache = $cache;
        $this->settings = $settings;
    }

    public function getQuotaCounts(int $userId): object
    {
        $cacheKey = "bump_quota_counts_{$userId}";

        return $this->cache->remember($cacheKey, self::QUOTA_CACHE_TTL, function () use ($userId) {
            return BumpQuota::getQuotaCounts($userId);
        });
    }

    public function getLastManualBump(int $userId, int $discussionId): ?BumpQuota
    {
        return BumpQuota::getLastManualBump($userId, $discussionId);
    }

    public function createBump(int $userId, int $discussionId, Carbon $bumpedAt): BumpQuota
    {
        $bump = BumpQuota::create([
            'user_id' => $userId,
            'discussion_id' => $discussionId,
            'bumped_at' => $bumpedAt,
        ]);

        // Kullanıcının kişisel kota önbelleğini temizle
        $this->invalidateQuotaCache($userId);

        // DÜZELTME: Yeni bump atıldığında Admin panelindeki global istatistikleri de anında yenile
        $this->invalidateStatsCache();

        return $bump;
    }

    public function getRecentBumps(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "bump_recent_bumps_{$limit}";

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () use ($limit) {
            return BumpQuota::with(['discussion', 'user'])
                ->recent($limit)
                ->get();
        });
    }

    public function getTotalBumpCount(): int
    {
        $cacheKey = 'bump_total_count';

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () {
            return BumpQuota::count();
        });
    }

    public function getDailyBumpCount(): int
    {
        $cacheKey = 'bump_daily_count';

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () {
            return BumpQuota::since(Carbon::now()->subDay())->count();
        });
    }

    public function getWeeklyBumpCount(): int
    {
        $cacheKey = 'bump_weekly_count';

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () {
            return BumpQuota::since(Carbon::now()->subWeek())->count();
        });
    }

    public function invalidateQuotaCache(int $userId): void
    {
        $cacheKey = "bump_quota_counts_{$userId}";
        $this->cache->forget($cacheKey);
    }

    public function invalidateStatsCache(): void
    {
        $this->cache->forget('bump_total_count');
        $this->cache->forget('bump_daily_count');
        $this->cache->forget('bump_weekly_count');

        foreach ([10, 20, 50] as $limit) {
            $this->cache->forget("bump_recent_bumps_{$limit}");
        }
    }
}
