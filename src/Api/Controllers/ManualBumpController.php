<?php

namespace HuseyinFiliz\Bump\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Locale\Translator;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use Carbon\Carbon;

class ManualBumpController extends AbstractShowController
{
    public $serializer = \Flarum\Api\Serializer\DiscussionSerializer::class;

    protected $settings;
    protected $translator;
    protected $repository;
    protected $resolver;

    public function __construct(
        SettingsRepositoryInterface $settings,
        Translator $translator,
        BumpQuotaRepository $repository,
        \HuseyinFiliz\Bump\Services\BumpSettingsResolver $resolver
    ) {
        $this->settings = $settings;
        $this->translator = $translator;
        $this->repository = $repository;
        $this->resolver = $resolver;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $discussionId = Arr::get($request->getQueryParams(), 'id');
        $discussion = Discussion::findOrFail($discussionId);

        // Permission check (BumpPolicy handles everything)
        $actor->assertCan('bump', $discussion);

        $now = Carbon::now();

        // CHECK: Can user moderate bumps? (group-based)
        $isModerator = $this->resolver->canModerateBumps($actor);

        // If not moderator, check quota and cooldown using resolver
        if (!$isModerator) {
            // CHECK: Is bump disabled for this user's group?
            if ($this->resolver->isBumpDisabled($actor)) {
                throw new \Flarum\Foundation\ValidationException([
                    'message' => $this->translator->trans('huseyinfiliz-bump.api.bump_disabled')
                ]);
            }

            // Get user's group-aware settings
            $cooldownHours = $this->resolver->getCooldown($actor);
            $dailyQuota = $this->resolver->getDailyQuota($actor);
            $weeklyQuota = $this->resolver->getWeeklyQuota($actor);

            // Cooldown check using repository
            $lastManualBump = $this->repository->getLastManualBump($actor->id, $discussion->id);

            if ($lastManualBump && $cooldownHours > 0) {
                $hoursSinceLastBump = $now->diffInHours($lastManualBump->bumped_at);

                if ($hoursSinceLastBump < $cooldownHours) {
                    $hoursRemaining = ceil($cooldownHours - $hoursSinceLastBump);

                    throw new \Flarum\Foundation\ValidationException([
                        'message' => $this->translator->trans('huseyinfiliz-bump.api.cooldown_error', [
                            'hours' => $hoursRemaining
                        ])
                    ]);
                }
            }

            // Quota check (for owners only)
            if ($dailyQuota > 0 || $weeklyQuota > 0) {
                // Get quota counts using repository (cached for 60 seconds)
                $quotaCounts = $this->repository->getQuotaCounts($actor->id);

                // Daily quota check
                if ($dailyQuota > 0 && $quotaCounts->daily_count >= $dailyQuota) {
                    throw new \Flarum\Foundation\ValidationException([
                        'message' => $this->translator->trans('huseyinfiliz-bump.api.daily_quota_error', [
                            'limit' => $dailyQuota
                        ])
                    ]);
                }

                // Weekly quota check
                if ($weeklyQuota > 0 && $quotaCounts->weekly_count >= $weeklyQuota) {
                    throw new \Flarum\Foundation\ValidationException([
                        'message' => $this->translator->trans('huseyinfiliz-bump.api.weekly_quota_error', [
                            'limit' => $weeklyQuota
                        ])
                    ]);
                }
            }
        }

        // Perform bump
        $discussion->last_posted_at = $now;
        $discussion->last_bumped_at = $now;
        $discussion->save();

        // Record quota using repository (if not moderator)
        // This automatically invalidates the user's quota cache
        if (!$isModerator) {
            $this->repository->createBump($actor->id, $discussion->id, $now);
        }

        return $discussion;
    }
}
