<?php

namespace Miladev\LaravelSettings;

use Illuminate\Support\ServiceProvider;
use Miladev\LaravelSettings\Contracts\SettingRepository;
use Miladev\LaravelSettings\Repositories\DatabaseRepository;
use Miladev\LaravelSettings\Repositories\FileRepository;
use Miladev\LaravelSettings\Repositories\RedisRepository;

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
        $this->mergeConfigFrom(__DIR__.'/../config/settings.php', 'settings');

        $this->app->singleton(SettingRepository::class, function ($app) {
            $config = $app['config']->get('settings', []);

            $driver = isset($config['driver']) ? $config['driver'] : 'database';
            $ttl = isset($config['ttl']) ? (int) $config['ttl'] : 60;

            switch ($driver) {
                case 'file':
                    $path = !empty($config['file_path']) ? $config['file_path'] : storage_path('framework/cache/lara-setting');
                    if (!is_dir($path)) {
                        @mkdir($path, 0775, true);
                    }
                    $storeName = 'lara_setting_file';
                    $app['config']->set('cache.stores.'.$storeName, [
                        'driver' => 'file',
                        'path' => $path,
                    ]);
                    $cache = $app['cache']->store($storeName);
                    return new FileRepository($cache, $ttl);

                case 'redis':
                    $cache = $app['cache']->store('redis');
                    return new RedisRepository($cache, $ttl);

                case 'database':
                default:
                    return new DatabaseRepository();
            }
        });

        $this->app->singleton('settings', function ($app) {
            return new Setting($app->make(SettingRepository::class));
        });
    }
}
