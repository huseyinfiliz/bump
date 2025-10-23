<?php

namespace HuseyinFiliz\Bump\Api\Serializer;

use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Bump\BumpQuota;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;

/**
 * Adds bump-related attributes to discussion API responses.
 *
 * This serializer adds bump-specific data to discussion API responses,
 * including permissions, last bump times, and quota information.
 * It uses context-aware serialization to prevent N+1 queries in list views.
 * Uses BumpSettingsResolver to apply group-based settings.
 */
class AddBumpAttributesSerializer
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var BumpQuotaRepository
     */
    protected $repository;

    /**
     * @var BumpSettingsResolver
     */
    protected $resolver;

    /**
     * @param SettingsRepositoryInterface $settings
     * @param BumpQuotaRepository $repository
     * @param BumpSettingsResolver $resolver
     */
    public function __construct(
        SettingsRepositoryInterface $settings,
        BumpQuotaRepository $repository,
        BumpSettingsResolver $resolver
    ) {
        $this->settings = $settings;
        $this->repository = $repository;
        $this->resolver = $resolver;
    }

    /**
     * Add bump attributes to discussion serialization.
     *
     * Invoked by Flarum's API serialization system to add bump-specific
     * attributes to discussion API responses.
     *
     * @param DiscussionSerializer $serializer The discussion serializer
     * @param mixed $discussion The discussion model
     * @param array $attributes Current attributes array
     * @return array Modified attributes array with bump data
     */
    public function __invoke(DiscussionSerializer $serializer, $discussion, array $attributes): array
    {
        $actor = $serializer->getActor();

        // Basic bump attributes
        $attributes['canBump'] = $actor->can('bump', $discussion);
        $attributes['lastBumpedAt'] = $discussion->last_bumped_at
            ? $serializer->formatDate($discussion->last_bumped_at)
            : null;

        // User-specific bump information
        if (!$actor->isGuest()) {
            $this->addUserBumpInfo($serializer, $discussion, $actor, $attributes);
        } else {
            $this->addGuestBumpInfo($attributes);
        }

        return $attributes;
    }

    /**
     * Add bump information for authenticated users.
     *
     * Includes last manual bump time and quota information.
     * Quota data is needed for bump button text display.
     *
     * @param DiscussionSerializer $serializer The serializer instance
     * @param mixed $discussion The discussion model
     * @param mixed $actor The current user
     * @param array $attributes Attributes array to modify
     * @return void
     */
    protected function addUserBumpInfo(DiscussionSerializer $serializer, $discussion, $actor, array &$attributes): void
    {
        try {
            // Last manual bump time for this discussion
            $lastManualBump = $this->getLastManualBump($discussion, $actor);

            $attributes['lastManualBumpedAt'] = $lastManualBump
                ? $serializer->formatDate($lastManualBump->bumped_at)
                : null;

            // Always add quota info (needed for bump button text)
            $this->addQuotaInfo($actor, $attributes);
        } catch (\Exception $e) {
            // Table doesn't exist yet or other error
            $this->addGuestBumpInfo($attributes);
        }
    }

    /**
     * Add quota and settings information for the user.
     *
     * Uses BumpSettingsResolver to get user-specific settings based on group overrides.
     * Fetches quota counts from repository (cached for 60 seconds)
     * to provide current usage statistics.
     *
     * @param mixed $actor The current user
     * @param array $attributes Attributes array to modify
     * @return void
     */
    protected function addQuotaInfo($actor, array &$attributes): void
    {
        // Get user-specific settings using resolver (respects group overrides)
        $cooldownHours = $this->resolver->getCooldown($actor);
        $dailyQuota = $this->resolver->getDailyQuota($actor);
        $weeklyQuota = $this->resolver->getWeeklyQuota($actor);
        $isModerator = $this->resolver->canModerateBumps($actor);
        $isBumpDisabled = $this->resolver->isBumpDisabled($actor);

        // Add user-specific settings to attributes
        $attributes['bumpCooldownHours'] = $cooldownHours;
        $attributes['canModerateBumps'] = $isModerator;
        $attributes['isBumpDisabled'] = $isBumpDisabled;

        // If bump is disabled or user is moderator, quotas don't apply
        if ($isBumpDisabled || $isModerator) {
            $attributes['dailyBumpQuota'] = null;
            $attributes['weeklyBumpQuota'] = null;
            return;
        }

        // Add quota information (0 = unlimited, positive = limited)
        if ($dailyQuota > 0 || $weeklyQuota > 0) {
            // Use repository to get cached quota counts (60 second cache)
            $quotaCounts = $this->repository->getQuotaCounts($actor->id);

            $attributes['dailyBumpQuota'] = $dailyQuota > 0 ? [
                'used' => $quotaCounts->daily_count,
                'limit' => $dailyQuota,
                'remaining' => max(0, $dailyQuota - $quotaCounts->daily_count)
            ] : null;

            $attributes['weeklyBumpQuota'] = $weeklyQuota > 0 ? [
                'used' => $quotaCounts->weekly_count,
                'limit' => $weeklyQuota,
                'remaining' => max(0, $weeklyQuota - $quotaCounts->weekly_count)
            ] : null;
        } else {
            $attributes['dailyBumpQuota'] = null;
            $attributes['weeklyBumpQuota'] = null;
        }
    }

    /**
     * Add null bump info for guest users.
     *
     * Sets all bump-related attributes to null for unauthenticated users.
     *
     * @param array $attributes Attributes array to modify
     * @return void
     */
    protected function addGuestBumpInfo(array &$attributes): void
    {
        $attributes['lastManualBumpedAt'] = null;
        $attributes['dailyBumpQuota'] = null;
        $attributes['weeklyBumpQuota'] = null;
        $attributes['bumpCooldownHours'] = 0;
        $attributes['canModerateBumps'] = false;
        $attributes['isBumpDisabled'] = false;
    }

    /**
     * Get last manual bump for the discussion and user.
     *
     * Uses eager-loaded relationship if available to prevent additional queries,
     * otherwise falls back to repository method.
     *
     * @param mixed $discussion The discussion model
     * @param mixed $actor The current user
     * @return BumpQuota|null The last bump record or null
     */
    protected function getLastManualBump($discussion, $actor): ?BumpQuota
    {
        // Use eager-loaded relationship if available to prevent N+1 queries
        if ($discussion->relationLoaded('lastBumpQuota')) {
            return $discussion->lastBumpQuota()
                ->where('user_id', $actor->id)
                ->first();
        }

        // Fallback to repository method (not cached as it's user+discussion specific)
        return $this->repository->getLastManualBump($actor->id, $discussion->id);
    }
}
