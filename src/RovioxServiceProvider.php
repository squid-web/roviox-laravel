<?php

namespace Roviox;

use Illuminate\Support\ServiceProvider;

class RovioxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/roviox.php', 'roviox');

        $this->app->singleton(RovioxClient::class, function ($app) {
            return new RovioxClient(
                apiKey: $app['config']->get('roviox.key'),
                timeout: (int) $app['config']->get('roviox.timeout', 15),
            );
        });

        $this->app->alias(RovioxClient::class, 'roviox');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/roviox.php' => config_path('roviox.php'),
        ], 'roviox-config');
    }
}
