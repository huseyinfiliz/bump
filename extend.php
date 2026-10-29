<?php

namespace HuseyinFiliz\Bump;

use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use HuseyinFiliz\Bump\Access\BumpPolicy;
use HuseyinFiliz\Bump\Api\Controllers\BumpStatsController;
use HuseyinFiliz\Bump\Api\Controllers\ClearCacheController;
use HuseyinFiliz\Bump\Api\Controllers\ManualBumpController;
use HuseyinFiliz\Bump\Api\Serializer\AddBumpAttributesSerializer;
use HuseyinFiliz\Bump\Listeners\HandlePostPosted;
use HuseyinFiliz\Bump\Providers\BumpServiceProvider;
use Illuminate\Support\Collection;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    // Register BumpSettingsResolver as a singleton service
    (new Extend\ServiceProvider())
        ->register(BumpServiceProvider::class),

    (new Extend\Model(Discussion::class))
        ->cast('last_bumped_at', 'datetime')
        ->relationship('bumpQuotas', function (Discussion $discussion) {
            return $discussion->hasMany(BumpQuota::class);
        })
        ->relationship('lastBumpQuota', function (Discussion $discussion) {
            return $discussion->hasOne(BumpQuota::class)->latest('bumped_at');
        }),

    (new Extend\Model(User::class))
        ->relationship('bumpQuotas', function (User $user) {
            return $user->hasMany(BumpQuota::class);
        }),

    (new Extend\Policy())
        ->modelPolicy(Discussion::class, BumpPolicy::class),

    // Listen to Posted event - handles bump absorber logic
    (new Extend\Event())
        ->listen(\Flarum\Post\Event\Posted::class, HandlePostPosted::class),

    (new Extend\Routes('api'))
        ->post('/manual-bump/{id}', 'huseyinfiliz-bump.manual', ManualBumpController::class)
        ->get('/bump/stats', 'bump.stats', BumpStatsController::class)
        ->post('/bump/clear-cache', 'bump.clear-cache', ClearCacheController::class),

    // Eager load bump quotas to prevent N+1 queries using Batch API Hook
    (new Extend\ApiController(ListDiscussionsController::class))
        ->prepareDataForSerialization(function ($controller, $data, $request, $document) {
            $actor = RequestUtil::getActor($request);

            if ($actor->isGuest()) {
                return;
            }

            // Flarum 1.x defansif Collection dönüşümü
            $discussions = collect(
                is_object($data) && method_exists($data, 'getResults') ? $data->getResults() : (is_iterable($data) ? $data : [])
            );

            $discussionIds = $discussions->pluck('id')->filter()->values()->all();

            if (empty($discussionIds)) {
                return;
            }

            $actorBumps = BumpQuota::whereIn('discussion_id', $discussionIds)
                ->where('user_id', $actor->id)
                ->selectRaw('discussion_id, MAX(bumped_at) as last_bumped_at')
                ->groupBy('discussion_id')
                ->get()
                ->keyBy('discussion_id');

            // N+1 Koruması: Kayıt yoksa bile 'null' olarak attribute'a yazılır.
            foreach ($discussions as $discussion) {
                $discussion->setAttribute(
                    'actor_last_bumped_at',
                    optional($actorBumps->get($discussion->id))->last_bumped_at
                );
            }
        }),

    (new Extend\Settings())
        // Absorber
        ->default('huseyinfiliz-bump.enable-absorber', true)
        ->default('huseyinfiliz-bump.threshold-hours', 72)
        ->default('huseyinfiliz-bump.absorber-tags', '[]')
        ->default('huseyinfiliz-bump.absorber-bypass-groups', '[]')

        // Manual Bump
        ->default('huseyinfiliz-bump.enable-manual-bump', true)
        ->default('huseyinfiliz-bump.manual-bump-tags', '[]')
        ->default('huseyinfiliz-bump.manual-cooldown-hours', 72)
        ->default('huseyinfiliz-bump.moderator-groups', '[]')

        // Quota
        ->default('huseyinfiliz-bump.owner-daily-quota', 0)
        ->default('huseyinfiliz-bump.owner-weekly-quota', 0)

        ->serializeToForum('huseyinfiliz-bump.enable-manual-bump', 'huseyinfiliz-bump.enable-manual-bump', 'boolval')
        ->serializeToForum('huseyinfiliz-bump.manual-cooldown-hours', 'huseyinfiliz-bump.manual-cooldown-hours', 'intval'),

    // Add bump attributes to discussion API responses
    (new Extend\ApiSerializer(\Flarum\Api\Serializer\DiscussionSerializer::class))
        ->attributes(AddBumpAttributesSerializer::class),
];
