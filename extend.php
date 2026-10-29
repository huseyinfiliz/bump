<?php

namespace HuseyinFiliz\Bump;

use Flarum\Extend;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use HuseyinFiliz\Bump\Access\BumpPolicy;
use HuseyinFiliz\Bump\BumpQuota;
use HuseyinFiliz\Bump\Listeners\HandlePostPosted;
use HuseyinFiliz\Bump\Api\Controllers\ManualBumpController;
use HuseyinFiliz\Bump\Api\Controllers\BumpStatsController;
use HuseyinFiliz\Bump\Api\Controllers\ClearCacheController;
use HuseyinFiliz\Bump\Api\DiscussionBumpFields;
use HuseyinFiliz\Bump\Providers\BumpServiceProvider;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\DiscussionResource;

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
        ->get('/bump/stats', 'bump.stats', BumpStatsController::class)
        ->post('/bump/clear-cache', 'bump.clear-cache', ClearCacheController::class),

    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(DiscussionBumpFields::class)
        ->endpoints(fn () => [
            Endpoint\Endpoint::make('bump')
                ->route('POST', '/{id}/bump')
                ->authenticated()
                ->action(fn (Context $context) => resolve(ManualBumpController::class)($context)),
        ])
        ->endpoint('index', fn (Endpoint\Index $endpoint) => $endpoint->beforeSerialization(function (Context $context, $data) {
            $actor = $context->getActor();

            if ($actor->isGuest()) {
                return;
            }

            $discussions = collect($data['models']);

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
        })),

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
];
