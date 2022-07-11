<?php

namespace Miladev\LaravelSettings;

use Illuminate\Support\ServiceProvider;


class SettingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__.'/../config/settings.php' => config_path('settings.php'),
            ], 'config');

        }
    }

    public function register()
    {
        $this->app->bind('settings', function () {
            return new Setting();
        });

        $this->mergeConfigFrom(__DIR__.'/../config/settings.php', 'settings');
    }
}