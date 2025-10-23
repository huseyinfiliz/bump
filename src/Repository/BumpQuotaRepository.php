<?php

namespace HuseyinFiliz\Bump\Repository;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Bump\BumpQuota;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Repository for BumpQuota operations with caching support.
 *
 * This repository provides a clean abstraction layer for BumpQuota operations
 * and implements caching strategies to improve performance.
 */
class BumpQuotaRepository
{
    /**
     * Cache TTL for quota counts (60 seconds).
     * User quotas are checked frequently, so we use a shorter TTL.
     */
    const QUOTA_CACHE_TTL = 60;

    /**
     * Cache TTL for admin statistics (5 minutes).
     * Admin stats are less critical, so we can cache longer.
     */
    const STATS_CACHE_TTL = 300;

    /**
     * @var CacheRepository
     */
    protected $cache;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @param CacheRepository $cache
     * @param SettingsRepositoryInterface $settings
     */
    public function __construct(CacheRepository $cache, SettingsRepositoryInterface $settings)
    {
        $this->cache = $cache;
        $this->settings = $settings;
    }

    /**
     * Get quota counts for a user (with caching).
     *
     * Returns daily and weekly bump counts for the specified user.
     * Results are cached for 60 seconds to reduce database load.
     *
     * @param int $userId
     * @return object Object with daily_count and weekly_count properties
     */
    public function getQuotaCounts(int $userId): object
    {
        $cacheKey = "bump_quota_counts_{$userId}";

        return $this->cache->remember($cacheKey, self::QUOTA_CACHE_TTL, function () use ($userId) {
            return BumpQuota::getQuotaCounts($userId);
        });
    }

    /**
     * Get the last manual bump for a user and discussion.
     *
     * This is NOT cached as it needs to be real-time for cooldown calculations.
     *
     * @param int $userId
     * @param int $discussionId
     * @return BumpQuota|null
     */
    public function getLastManualBump(int $userId, int $discussionId): ?BumpQuota
    {
        return BumpQuota::getLastManualBump($userId, $discussionId);
    }

    /**
     * Create a new bump record.
     *
     * Invalidates the user's quota cache after creating the bump.
     *
     * @param int $userId
     * @param int $discussionId
     * @param Carbon $bumpedAt
     * @return BumpQuota
     */
    public function createBump(int $userId, int $discussionId, Carbon $bumpedAt): BumpQuota
    {
        $bump = BumpQuota::create([
            'user_id' => $userId,
            'discussion_id' => $discussionId,
            'bumped_at' => $bumpedAt,
        ]);

        // Invalidate user's quota cache
        $this->invalidateQuotaCache($userId);

        return $bump;
    }

    /**
     * Get recent bumps with relationships (for admin panel).
     *
     * Cached for 5 minutes as admin stats are less critical.
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentBumps(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "bump_recent_bumps_{$limit}";

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () use ($limit) {
            return BumpQuota::with(['discussion', 'user'])
                ->recent($limit)
                ->get();
        });
    }

    /**
     * Get total bump count (for admin panel).
     *
     * Cached for 5 minutes.
     *
     * @return int
     */
    public function getTotalBumpCount(): int
    {
        $cacheKey = 'bump_total_count';

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () {
            return BumpQuota::count();
        });
    }

    /**
     * Get daily bump count (for admin panel).
     *
     * Cached for 5 minutes.
     *
     * @return int
     */
    public function getDailyBumpCount(): int
    {
        $cacheKey = 'bump_daily_count';

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () {
            return BumpQuota::since(Carbon::now()->subDay())->count();
        });
    }

    /**
     * Get weekly bump count (for admin panel).
     *
     * Cached for 5 minutes.
     *
     * @return int
     */
    public function getWeeklyBumpCount(): int
    {
        $cacheKey = 'bump_weekly_count';

        return $this->cache->remember($cacheKey, self::STATS_CACHE_TTL, function () {
            return BumpQuota::since(Carbon::now()->subWeek())->count();
        });
    }

    /**
     * Invalidate quota cache for a specific user.
     *
     * Called after creating a new bump to ensure fresh data.
     *
     * @param int $userId
     * @return void
     */
    public function invalidateQuotaCache(int $userId): void
    {
        $cacheKey = "bump_quota_counts_{$userId}";
        $this->cache->forget($cacheKey);
    }

    /**
     * Invalidate all admin statistics cache.
     *
     * Called when admin stats need to be refreshed.
     *
     * @return void
     */
    public function invalidateStatsCache(): void
    {
        $this->cache->forget('bump_total_count');
        $this->cache->forget('bump_daily_count');
        $this->cache->forget('bump_weekly_count');

        // Invalidate recent bumps for common limits
        foreach ([10, 20, 50] as $limit) {
            $this->cache->forget("bump_recent_bumps_{$limit}");
        }
    }

    /**
     * Get quota settings from extension settings.
     *
     * @return object Object with dailyLimit, weeklyLimit, and cooldown properties
     */
    public function getQuotaSettings(): object
    {
        return (object) [
            'dailyLimit' => (int) $this->settings->get('huseyinfiliz-bump.daily_bump_limit', 3),
            'weeklyLimit' => (int) $this->settings->get('huseyinfiliz-bump.weekly_bump_limit', 10),
            'cooldown' => (int) $this->settings->get('huseyinfiliz-bump.bump_cooldown', 24),
        ];
    }
}
