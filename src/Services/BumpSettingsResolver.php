<?php

namespace HuseyinFiliz\Bump\Services;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Resolves bump settings for users based on their group membership.
 *
 * This service implements a runtime + cache pattern for resolving bump settings.
 * Settings are resolved based on group overrides with fallback to global defaults.
 *
 * Cache Strategy:
 * - Cache key is based on group ID combination (e.g., "bump:groups:1,4,5:cooldown")
 * - Users in the same groups share the same cache
 * - Cache expires after 1 hour or when settings are saved
 *
 * Priority:
 * - Group overrides are applied based on Flarum's group priority (highest priority wins)
 * - If no override exists, global default is used
 * - Setting value of 0 can mean "unlimited" depending on context
 */
class BumpSettingsResolver
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * Cache TTL in seconds (1 hour)
     */
    const CACHE_TTL = 3600;

    /**
     * @param SettingsRepositoryInterface $settings
     * @param Cache $cache
     */
    public function __construct(
        SettingsRepositoryInterface $settings,
        Cache $cache
    ) {
        $this->settings = $settings;
        $this->cache = $cache;
    }

    /**
     * Get manual bump cooldown hours for a user.
     *
     * @param User $user
     * @return int Cooldown in hours (0 = unlimited)
     */
    public function getCooldown(User $user): int
    {
        return $this->resolveForUser($user, 'manual', 'cooldown', 'manual-cooldown-hours');
    }

    /**
     * Get daily bump quota for a user.
     *
     * @param User $user
     * @return int Daily quota (0 = unlimited)
     */
    public function getDailyQuota(User $user): int
    {
        return $this->resolveForUser($user, 'manual', 'daily', 'owner-daily-quota');
    }

    /**
     * Get weekly bump quota for a user.
     *
     * @param User $user
     * @return int Weekly quota (0 = unlimited)
     */
    public function getWeeklyQuota(User $user): int
    {
        return $this->resolveForUser($user, 'manual', 'weekly', 'owner-weekly-quota');
    }

    /**
     * Get absorber threshold hours for a user.
     *
     * @param User $user
     * @return int Threshold in hours (0 = bypass absorber, -1 values are converted to 0)
     */
    public function getThreshold(User $user): int
    {
        $value = $this->resolveForUser($user, 'absorber', 'threshold', 'threshold-hours');

        // Safety check: -1 is not valid for absorber, convert to 0 (bypass)
        return max(0, $value);
    }

    /**
     * Resolve a setting value for a user based on group overrides.
     *
     * Resolution order:
     * 1. Check cache for this group combination
     * 2. If cache miss, load group overrides
     * 3. Find highest priority group with override
     * 4. If no override, use global default
     * 5. Cache result
     *
     * @param User $user
     * @param string $type 'manual' or 'absorber'
     * @param string $key Setting key in JSON (e.g., 'cooldown', 'daily', 'threshold')
     * @param string $fallbackKey Global setting key
     * @return int
     */
    protected function resolveForUser(User $user, string $type, string $key, string $fallbackKey): int
    {
        // Build cache key based on group combination
        $groupIds = $user->groups->pluck('id')->sort()->implode(',');
        $cacheKey = "bump:groups:{$groupIds}:{$type}:{$key}";

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($user, $type, $key, $fallbackKey) {
            // Load group overrides (this is also cached separately)
            $groupOverrides = $this->getGroupOverrides($type);

            // Find highest priority group with override
            foreach ($user->groups->sortByDesc('id') as $group) {
                if (isset($groupOverrides[$group->id][$key])) {
                    return (int) $groupOverrides[$group->id][$key];
                }
            }

            // Fallback to global default
            return (int) $this->settings->get("huseyinfiliz-bump.{$fallbackKey}", 0);
        });
    }

    /**
     * Get all group overrides for a type.
     *
     * This method is cached separately to avoid parsing JSON repeatedly.
     *
     * @param string $type 'manual' or 'absorber'
     * @return array Group overrides array
     */
    protected function getGroupOverrides(string $type): array
    {
        $cacheKey = "bump:group-overrides:{$type}";

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($type) {
            $json = $this->settings->get("huseyinfiliz-bump.group-overrides-{$type}", '{}');
            return json_decode($json, true) ?: [];
        });
    }

    /**
     * Clear all bump settings cache.
     *
     * This should be called when settings are saved.
     *
     * @return void
     */
    public function clearCache(): void
    {
        // Clear all bump-related cache
        // Note: This is a simple implementation. In production, you might want
        // to use cache tags for more efficient clearing.
        $this->cache->forget('bump:group-overrides:manual');
        $this->cache->forget('bump:group-overrides:absorber');

        // Individual group combination caches will expire naturally or we can
        // implement a more sophisticated tagging system if needed
    }

    /**
     * Check if a user has unlimited bumps (both daily and weekly are 0).
     *
     * @param User $user
     * @return bool
     */
    public function hasUnlimitedBumps(User $user): bool
    {
        return $this->getDailyQuota($user) === 0 && $this->getWeeklyQuota($user) === 0;
    }

    /**
     * Check if a user can bypass absorber (threshold is 0).
     *
     * @param User $user
     * @return bool
     */
    public function canBypassAbsorber(User $user): bool
    {
        return $this->getThreshold($user) === 0;
    }

    /**
     * Check if manual bump is completely disabled for a user.
     *
     * Returns true if ANY of the manual bump settings (cooldown, daily, weekly)
     * is set to -1, indicating the feature is disabled for this user's group.
     *
     * @param User $user
     * @return bool
     */
    public function isBumpDisabled(User $user): bool
    {
        return $this->getCooldown($user) === -1
            || $this->getDailyQuota($user) === -1
            || $this->getWeeklyQuota($user) === -1;
    }

    /**
     * Check if user can moderate bumps (bump others' discussions).
     *
     * Users in moderator groups can:
     * - Bump ANY discussion (not just their own)
     * - Bypass cooldown and quota restrictions
     *
     * @param User $user
     * @return bool
     */
    public function canModerateBumps(User $user): bool
    {
        $moderatorGroups = json_decode(
            $this->settings->get('huseyinfiliz-bump.moderator-groups', '[]'),
            true
        ) ?: [];

        // If no groups configured, nobody can moderate
        if (empty($moderatorGroups)) {
            return false;
        }

        $userGroupIds = $user->groups->pluck('id')->toArray();
        return !empty(array_intersect($moderatorGroups, $userGroupIds));
    }

    /**
     * Check if user can bypass absorber globally.
     *
     * This is separate from group overrides (threshold: 0).
     * Users in bypass groups will ALWAYS bypass absorber.
     *
     * @param User $user
     * @return bool
     */
    public function canBypassAbsorberGlobally(User $user): bool
    {
        $bypassGroups = json_decode(
            $this->settings->get('huseyinfiliz-bump.absorber-bypass-groups', '[]'),
            true
        ) ?: [];

        // If no groups configured, nobody can bypass
        if (empty($bypassGroups)) {
            return false;
        }

        $userGroupIds = $user->groups->pluck('id')->toArray();
        return !empty(array_intersect($bypassGroups, $userGroupIds));
    }
}
