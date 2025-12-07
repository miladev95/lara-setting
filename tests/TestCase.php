<?php

namespace Miladev\LaravelSettings\Tests;

use Miladev\LaravelSettings\SettingServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // load package migrations so RefreshDatabase can run
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            SettingServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Use in-memory SQLite for fast tests
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}