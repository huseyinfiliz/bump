<?php

namespace HuseyinFiliz\Bump\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;

class BumpStatsSerializer extends AbstractSerializer
{
    protected $type = 'bump-stats';

    protected function getDefaultAttributes($stats): array
    {
        return [
            'totalBumps' => (int) ($stats['total_bumps'] ?? 0),
            'todayBumps' => (int) ($stats['today_bumps'] ?? 0),
            'weekBumps' => (int) ($stats['week_bumps'] ?? 0),
            'absorberActive' => (bool) ($stats['absorber_active'] ?? false),
            'lastBumpDate' => $stats['last_bump_date'] ?? null,
            'recentBumps' => $stats['recent_bumps'] ?? [],
        ];
    }

    public function getId($stats): string
    {
        return 'bump-stats';
    }
}
