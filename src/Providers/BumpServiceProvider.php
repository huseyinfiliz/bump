<?php

namespace HuseyinFiliz\Bump\Providers;

use Flarum\Foundation\AbstractServiceProvider;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;

class BumpServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->singleton(BumpSettingsResolver::class, function ($container) {
            return new BumpSettingsResolver(
                $container->make('flarum.settings'),
                $container->make('cache.store')
            );
        });
    }
}
