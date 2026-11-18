<?php

namespace HuseyinFiliz\Bump\Api\Controllers;

use Carbon\Carbon;
use Flarum\Api\Context;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\User\User;
use HuseyinFiliz\Bump\Event\ManuallyBumped;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class ManualBumpController
{
    public function __construct(
        protected Translator $translator,
        protected BumpQuotaRepository $repository,
        protected BumpSettingsResolver $resolver,
        protected ConnectionInterface $database,
        protected Dispatcher $events
    ) {
    }

    public function __invoke(Context $context): Discussion
    {
        $actor = $context->getActor();
        $discussion = $context->model;

        $actor->assertCan('bump', $discussion);
        $isModerator = $this->resolver->canModerateBumps($actor);

        $this->database->transaction(function () use ($actor, $discussion, $isModerator) {
            if (! $isModerator) {
                // All discussions share the actor's budget, including an empty history.
                $query = $this->database->table('users')->where('id', $actor->id);

                if ($this->database->getDriverName() === 'sqlite') {
                    // SQLite has no row locks. Reserve its writer before any budget reads.
                    $query->update(['id' => $this->database->raw('id')]);
                } else {
                    $query->lockForUpdate()->first(['id']);
                }
            }

            $now = Carbon::now();

            if (! $isModerator) {
                $this->assertBudgetAvailable($actor, $discussion, $now);
            }

            $discussion->last_posted_at = $now;
            $discussion->last_bumped_at = $now;
            $discussion->save();

            if (! $isModerator) {
                $this->repository->createBump($actor->id, $discussion->id, $now);
            }

            // Audit listeners participate in the transaction, so failed writes leave no log.
            $this->events->dispatch(new ManuallyBumped($discussion, $actor, $isModerator));
        }, 3);

        // Publish cache invalidation only after ordering and accounting have committed.
        if (! $isModerator) {
            $this->repository->invalidateQuotaCache($actor->id);
            $this->repository->invalidateStatsCache();
        }

        return $discussion;
    }

    private function assertBudgetAvailable(User $actor, Discussion $discussion, Carbon $now): void
    {
        if ($this->resolver->isBumpDisabled($actor)) {
            throw new ValidationException([
                'message' => $this->translator->trans('huseyinfiliz-bump.api.bump_disabled'),
            ]);
        }

        $cooldownHours = $this->resolver->getCooldown($actor);
        $dailyQuota = $this->resolver->getDailyQuota($actor);
        $weeklyQuota = $this->resolver->getWeeklyQuota($actor);
        $lastManualBump = $this->repository->getLastManualBump($actor->id, $discussion->id);

        if ($lastManualBump && $cooldownHours > 0) {
            $hoursSinceLastBump = $now->diffInHours($lastManualBump->bumped_at, true);

            if ($hoursSinceLastBump < $cooldownHours) {
                throw new ValidationException([
                    'message' => $this->translator->trans('huseyinfiliz-bump.api.cooldown_error', [
                        'hours' => ceil($cooldownHours - $hoursSinceLastBump),
                    ]),
                ]);
            }
        }

        if ($dailyQuota > 0 || $weeklyQuota > 0) {
            $counts = $this->repository->getFreshQuotaCounts($actor->id);

            foreach (['daily' => $dailyQuota, 'weekly' => $weeklyQuota] as $period => $limit) {
                if ($limit > 0 && $counts->{$period.'_count'} >= $limit) {
                    throw new ValidationException([
                        'message' => $this->translator->trans("huseyinfiliz-bump.api.{$period}_quota_error", [
                            'limit' => $limit,
                        ]),
                    ]);
                }
            }
        }
    }
}
