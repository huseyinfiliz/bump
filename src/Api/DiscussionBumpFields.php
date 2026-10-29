<?php

namespace HuseyinFiliz\Bump\Api;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;

class DiscussionBumpFields
{
    public function __construct(
        protected BumpQuotaRepository $repository,
        protected BumpSettingsResolver $resolver
    ) {
    }

    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canBump')
                ->get(fn (Discussion $discussion, Context $context) => $context->getActor()->can('bump', $discussion)),
            Schema\DateTime::make('lastBumpedAt')->nullable(),
            Schema\DateTime::make('lastManualBumpedAt')->nullable()
                ->get(function (Discussion $discussion, Context $context) {
                    $actor = $context->getActor();

                    if ($actor->isGuest()) {
                        return null;
                    }

                    if (array_key_exists('actor_last_bumped_at', $discussion->getAttributes())) {
                        $date = $discussion->getAttribute('actor_last_bumped_at');

                        return $date ? Carbon::parse($date) : null;
                    }

                    return $this->repository->getLastManualBump($actor->id, $discussion->id)?->bumped_at;
                }),
            Schema\Integer::make('bumpCooldownHours')
                ->get(fn (Discussion $discussion, Context $context) => $context->getActor()->isGuest() ? 0 : $this->resolver->getCooldown($context->getActor())),
            Schema\Boolean::make('canModerateBumps')
                ->get(fn (Discussion $discussion, Context $context) => ! $context->getActor()->isGuest() && $this->resolver->canModerateBumps($context->getActor())),
            Schema\Boolean::make('isBumpDisabled')
                ->get(fn (Discussion $discussion, Context $context) => ! $context->getActor()->isGuest() && $this->resolver->isBumpDisabled($context->getActor())),
            Schema\Arr::make('dailyBumpQuota')->nullable()
                ->get(fn (Discussion $discussion, Context $context) => $this->quota($context->getActor(), 'daily')),
            Schema\Arr::make('weeklyBumpQuota')->nullable()
                ->get(fn (Discussion $discussion, Context $context) => $this->quota($context->getActor(), 'weekly')),
        ];
    }

    protected function quota(User $actor, string $period): ?array
    {
        if ($actor->isGuest() || $this->resolver->isBumpDisabled($actor) || $this->resolver->canModerateBumps($actor)) {
            return null;
        }

        $limit = $period === 'daily' ? $this->resolver->getDailyQuota($actor) : $this->resolver->getWeeklyQuota($actor);

        if ($limit <= 0) {
            return null;
        }

        $used = $this->repository->getQuotaCounts($actor->id)->{$period.'_count'};

        return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used)];
    }
}
