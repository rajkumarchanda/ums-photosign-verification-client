<?php

namespace PhotoSign;

use Illuminate\Support\ServiceProvider;

class PhotoSignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/photosign.php', 'photosign');
        $this->app->singleton(Client::class, function ($app) {
            return Client::fromConfig($app['config']->get('photosign', []), $app->make('log'));
        });
        $this->app->singleton(PhotoSign::class, fn ($app) => new PhotoSign($app->make(Client::class)));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/photosign.php' => config_path('photosign.php'),
            ], 'photosign-config');
        }
    }
}
