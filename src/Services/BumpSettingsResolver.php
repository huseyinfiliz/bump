<?php

namespace HuseyinFiliz\Bump\Services;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;

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

    const CACHE_TTL = 3600; // 1 hour

    /**
     * @param SettingsRepositoryInterface $settings
     * @param Cache $cache
     */
    public function __construct(SettingsRepositoryInterface $settings, Cache $cache)
    {
        $this->settings = $settings;
        $this->cache = $cache;
    }

    protected function getCacheVersion(): string
    {
        return $this->cache->rememberForever('bump_cache_version', function () {
            return uniqid();
        });
    }

    public function clearCache(): void
    {
        $this->cache->forget('bump_cache_version');
        $this->cache->forget('bump:group-overrides:manual');
        $this->cache->forget('bump:group-overrides:absorber');
    }

    /**
     * Get manual bump cooldown hours for a user.
     *
     * @param User $user
     * @return int Cooldown in hours (0 = unlimited, -1 = disabled)
     */
    public function getCooldown(User $user): int 
    { 
        return $this->resolveForUser($user, 'manual', 'cooldown', 'manual-cooldown-hours'); 
    }

    /**
     * Get manual bump daily quota for a user.
     *
     * @param User $user
     * @return int Quota (0 = unlimited, -1 = disabled)
     */
    public function getDailyQuota(User $user): int 
    { 
        return $this->resolveForUser($user, 'manual', 'daily', 'owner-daily-quota'); 
    }

    /**
     * Get manual bump weekly quota for a user.
     *
     * @param User $user
     * @return int Quota (0 = unlimited, -1 = disabled)
     */
    public function getWeeklyQuota(User $user): int 
    { 
        return $this->resolveForUser($user, 'manual', 'weekly', 'owner-weekly-quota'); 
    }
    
    /**
     * Get absorber threshold hours for a user.
     *
     * @param User $user
     * @return int Threshold in hours (0 = bypass absorber)
     */
    public function getThreshold(User $user): int 
    { 
        return max(0, $this->resolveForUser($user, 'absorber', 'threshold', 'threshold-hours')); 
    }

    protected function resolveForUser(User $user, string $type, string $key, string $fallbackKey): int
    {
        $groups = $user->groups;
        
        if ($groups->isEmpty() && !$user->isGuest()) {
            return (int) $this->settings->get("huseyinfiliz-bump.{$fallbackKey}", 0);
        }
        
        $groupIds = $groups->pluck('id')->sort()->implode(',');
        $version = $this->getCacheVersion();
        $cacheKey = "bump:groups:{$version}:{$groupIds}:{$type}:{$key}";

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($groups, $type, $key, $fallbackKey) {
            $groupOverrides = $this->getGroupOverrides($type);
            $values = [];

            foreach ($groups as $group) {
                if (isset($groupOverrides[$group->id][$key])) {
                    $values[] = (int) $groupOverrides[$group->id][$key];
                }
            }

            if (empty($values)) {
                return (int) $this->settings->get("huseyinfiliz-bump.{$fallbackKey}", 0);
            }

            // GÜVENLİK: -1 (Disabled) kuralı SADECE 'manual' yetkilerde geçerli ve her şeyi ezer
            if ($type === 'manual' && in_array(-1, $values, true)) {
                return -1;
            }

            // Sınırsız / Bypass kuralı
            if (in_array(0, $values, true)) {
                return 0;
            }

            // Absorber için tehlike yaratan -1 değerlerini filtrele
            $validValues = array_filter($values, function($v) { return $v !== -1; });
            
            if (empty($validValues)) {
                 return (int) $this->settings->get("huseyinfiliz-bump.{$fallbackKey}", 0);
            }

            if ($key === 'cooldown' || $key === 'threshold') {
                return min($validValues);
            }
            return max($validValues);
        });
    }

    protected function getGroupOverrides(string $type): array
    {
        return $this->cache->remember("bump:group-overrides:{$type}", self::CACHE_TTL, function () use ($type) {
            return json_decode($this->settings->get("huseyinfiliz-bump.group-overrides-{$type}", '{}'), true) ?: [];
        });
    }

    /**
     * Check if a user has completely unlimited manual bumps.
     *
     * @param User $user
     * @return bool
     */
    public function hasUnlimitedBumps(User $user): bool 
    { 
        return $this->getDailyQuota($user) === 0 && $this->getWeeklyQuota($user) === 0; 
    }

    /**
     * Check if a user can bypass the absorber logic entirely.
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
     * @param User $user
     * @return bool
     */
    public function isBumpDisabled(User $user): bool 
    { 
        return $this->getCooldown($user) === -1 || $this->getDailyQuota($user) === -1 || $this->getWeeklyQuota($user) === -1; 
    }

    /**
     * Check if a user is a bump moderator.
     *
     * @param User $user
     * @return bool
     */
    public function canModerateBumps(User $user): bool
    {
        $moderatorGroups = json_decode($this->settings->get('huseyinfiliz-bump.moderator-groups', '[]'), true) ?: [];
        if (empty($moderatorGroups)) return false;

        $groups = $user->groups;
        if ($groups->isEmpty()) return false;

        return !empty(array_intersect($moderatorGroups, $groups->pluck('id')->toArray()));
    }

    /**
     * Check if a user can bypass absorber globally.
     *
     * @param User $user
     * @return bool
     */
    public function canBypassAbsorberGlobally(User $user): bool
    {
        $bypassGroups = json_decode($this->settings->get('huseyinfiliz-bump.absorber-bypass-groups', '[]'), true) ?: [];
        if (empty($bypassGroups)) return false;

        $groups = $user->groups;
        if ($groups->isEmpty()) return false;

        return !empty(array_intersect($bypassGroups, $groups->pluck('id')->toArray()));
    }
}