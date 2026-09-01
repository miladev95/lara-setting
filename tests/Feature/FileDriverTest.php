<?php

namespace Miladev\LaravelSettings\Tests\Feature;

use Miladev\LaravelSettings\Tests\TestCase;

class FileDriverTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $path = sys_get_temp_dir().'/lara-setting-test-'.uniqid();
        $app['config']->set('settings.driver', 'file');
        $app['config']->set('settings.file_path', $path);
        $app['config']->set('settings.ttl', 0);
    }

    public function tearDown(): void
    {
        $path = config('settings.file_path');
        if (is_dir($path)) {
            foreach (glob($path.'/*') as $f) {
                @unlink($f);
            }
            @rmdir($path);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_stores_and_reads_settings_through_file_driver()
    {
        $settings = app('settings');

        $this->assertFalse($settings->has('site_name'));
        $this->assertEquals('default', $settings->get('site_name', 'default'));

        $settings->set('site_name', 'Acme', true);
        $this->assertTrue($settings->has('site_name'));
        $this->assertEquals('Acme', $settings->get('site_name'));

        $settings->clearCache();
        $this->assertEquals('Acme', $settings->get('site_name'));
    }

    /** @test */
    public function it_supports_forget_and_clean_via_file_driver()
    {
        $settings = app('settings');

        $settings->set('a', '1');
        $settings->set('b', '2');

        $settings->forget('a');
        $this->assertFalse($settings->has('a'));
        $this->assertTrue($settings->has('b'));

        $settings->clean();
        $this->assertFalse($settings->has('b'));
        $this->assertEmpty($settings->all());
    }

    /** @test */
    public function file_driver_all_returns_every_persisted_setting()
    {
        $settings = app('settings');
        $settings->set('one', '1', false);
        $settings->set('two', '2', true);

        $all = $settings->all();
        $this->assertEquals('1', $all['one']);
        $this->assertEquals('2', $all['two']);

        $settings->clearCache();
        $all2 = $settings->all();
        $this->assertEquals('1', $all2['one']);
        $this->assertEquals('2', $all2['two']);
    }
}
