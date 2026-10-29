<?php

namespace HuseyinFiliz\Bump\Api\Serializer;

use Carbon\Carbon;
use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;

/**
 * Adds bump-related attributes to discussion API responses.
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param SettingsRepositoryInterface $settings
     * @param BumpQuotaRepository $repository
     * @param BumpSettingsResolver $resolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        SettingsRepositoryInterface $settings,
        BumpQuotaRepository $repository,
        BumpSettingsResolver $resolver,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->repository = $repository;
        $this->resolver = $resolver;
        $this->logger = $logger;
    }

    public function __invoke(DiscussionSerializer $serializer, $discussion, array $attributes): array
    {
        $actor = $serializer->getActor();

        $attributes['canBump'] = $actor->can('bump', $discussion);
        $attributes['lastBumpedAt'] = $discussion->last_bumped_at
            ? $serializer->formatDate($discussion->last_bumped_at)
            : null;

        if (! $actor->isGuest()) {
            $this->addUserBumpInfo($serializer, $discussion, $actor, $attributes);
        } else {
            $this->addGuestBumpInfo($attributes);
        }

        return $attributes;
    }

    protected function addUserBumpInfo(DiscussionSerializer $serializer, $discussion, $actor, array &$attributes): void
    {
        try {
            $lastManualBump = $this->getLastManualBump($discussion, $actor);

            $attributes['lastManualBumpedAt'] = $lastManualBump
                ? $serializer->formatDate($lastManualBump)
                : null;

            $this->addQuotaInfo($actor, $attributes);
        } catch (QueryException $e) {
            $code = $e->getCode();
            // Fallback for migration window (table or column doesn't exist yet)
            if ($code === '42S02' || $code === '42S22' || str_contains($e->getMessage(), 'Base table or view not found') || str_contains($e->getMessage(), 'Unknown column')) {
                $this->addGuestBumpInfo($attributes);
            } else {
                $this->logger->error('Bump Extension DB Error: '.$e->getMessage());

                throw $e;
            }
        }
    }

    protected function addQuotaInfo($actor, array &$attributes): void
    {
        $cooldownHours = $this->resolver->getCooldown($actor);
        $dailyQuota = $this->resolver->getDailyQuota($actor);
        $weeklyQuota = $this->resolver->getWeeklyQuota($actor);
        $isModerator = $this->resolver->canModerateBumps($actor);
        $isBumpDisabled = $this->resolver->isBumpDisabled($actor);

        $attributes['bumpCooldownHours'] = $cooldownHours;
        $attributes['canModerateBumps'] = $isModerator;
        $attributes['isBumpDisabled'] = $isBumpDisabled;

        if ($isBumpDisabled || $isModerator) {
            $attributes['dailyBumpQuota'] = null;
            $attributes['weeklyBumpQuota'] = null;

            return;
        }

        if ($dailyQuota > 0 || $weeklyQuota > 0) {
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
     * @return \Carbon\Carbon|null
     */
    protected function getLastManualBump($discussion, $actor): ?Carbon
    {
        if (array_key_exists('actor_last_bumped_at', $discussion->getAttributes())) {
            $lastBumpedAt = $discussion->getAttribute('actor_last_bumped_at');

            return $lastBumpedAt ? Carbon::parse($lastBumpedAt) : null;
        }

        $bump = $this->repository->getLastManualBump($actor->id, $discussion->id);

        return $bump ? Carbon::parse($bump->bumped_at) : null;
    }
}
