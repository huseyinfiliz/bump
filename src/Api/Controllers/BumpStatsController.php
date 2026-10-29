<?php

namespace HuseyinFiliz\Bump\Api\Controllers;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BumpStatsController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected BumpQuotaRepository $repository,
        protected SlugManager $slugManager
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // Admin permission check
        $actor->assertAdmin();

        // Absorber status
        $absorberEnabled = (bool) $this->settings->get('huseyinfiliz-bump.enable-absorber', false);

        // Prepare statistics using repository (all cached for 5 minutes)
        $stats = [
            'totalBumps' => $this->repository->getTotalBumpCount(),
            'todayBumps' => $this->repository->getDailyBumpCount(),
            'weekBumps' => $this->repository->getWeeklyBumpCount(),
            'absorberActive' => $absorberEnabled,
            'lastBumpDate' => $this->getLastBumpDate(),
            'recentBumps' => $this->getRecentBumps(10),
        ];

        return new JsonResponse($stats);
    }

    protected function getLastBumpDate(): ?string
    {
        // Get from repository (utilizes same cache as recent bumps)
        $recentBumps = $this->repository->getRecentBumps(1);
        $lastBump = $recentBumps->first();

        return $lastBump ? $lastBump->bumped_at->toDateTimeString() : null;
    }

    protected function getRecentBumps(int $limit = 10): array
    {
        // Get recent bumps from repository (cached for 5 minutes)
        $bumps = $this->repository->getRecentBumps($limit);

        return $bumps->map(function ($bump) {
            return [
                'id' => $bump->id,
                'discussion_id' => $bump->discussion_id,
                'discussion_title' => $bump->discussion?->title,
                'discussion_slug' => $bump->discussion ? $this->slugManager->forResource(Discussion::class)->toSlug($bump->discussion) : null,
                'user_id' => $bump->user_id,
                'username' => $bump->user?->username,
                'type' => 'manual', // user_bump_quota only stores manual bumps
                'created_at' => $bump->bumped_at->toDateTimeString(),
            ];
        })->toArray();
    }
}
