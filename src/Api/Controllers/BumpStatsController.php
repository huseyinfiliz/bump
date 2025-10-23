<?php

namespace HuseyinFiliz\Bump\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class BumpStatsController extends AbstractShowController
{
    public $serializer = 'HuseyinFiliz\Bump\Api\Serializer\BumpStatsSerializer';

    protected $settings;
    protected $repository;

    public function __construct(
        SettingsRepositoryInterface $settings,
        BumpQuotaRepository $repository
    ) {
        $this->settings = $settings;
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document): array
    {
        $actor = RequestUtil::getActor($request);

        // Admin permission check
        $actor->assertAdmin();

        // Absorber status
        $absorberEnabled = (bool) $this->settings->get('huseyinfiliz-bump.enable-absorber', false);

        // Prepare statistics using repository (all cached for 5 minutes)
        $stats = [
            'total_bumps' => $this->repository->getTotalBumpCount(),
            'today_bumps' => $this->repository->getDailyBumpCount(),
            'week_bumps' => $this->repository->getWeeklyBumpCount(),
            'absorber_active' => $absorberEnabled,
            'last_bump_date' => $this->getLastBumpDate(),
            'recent_bumps' => $this->getRecentBumps(10),
        ];

        return $stats;
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
                'discussion_slug' => $bump->discussion?->slug,
                'user_id' => $bump->user_id,
                'username' => $bump->user?->username,
                'type' => 'manual', // user_bump_quota only stores manual bumps
                'created_at' => $bump->bumped_at->toDateTimeString(),
            ];
        })->toArray();
    }
}
