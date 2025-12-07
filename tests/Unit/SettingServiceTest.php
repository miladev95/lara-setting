<?php

namespace Miladev\LaravelSettings\Tests\Unit;

use Miladev\LaravelSettings\Models\Setting as SettingModel;
use Miladev\LaravelSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_set_get_and_check_a_setting()
    {
        $settings = app('settings');

        $this->assertFalse($settings->has('foo'));
        $this->assertNull($settings->get('foo'));
        $this->assertEquals('def', $settings->get('nope', 'def'));

        $settings->set('foo', 'bar', true);

        $this->assertTrue($settings->has('foo'));
        $this->assertEquals('bar', $settings->get('foo'));

        $dbSetting = SettingModel::where('key', 'foo')->first();
        $this->assertNotNull($dbSetting);
        $this->assertEquals('bar', $dbSetting->value);
    }

    /** @test */
    public function it_can_forget_and_clean_settings()
    {
        $settings = app('settings');

        $settings->set('a', '1');
        $settings->set('b', '2');

        $this->assertTrue($settings->has('a'));
        $this->assertTrue($settings->has('b'));

        $settings->forget('a');

        $this->assertFalse($settings->has('a'));
        $this->assertTrue($settings->has('b'));

        $settings->clean();

        $this->assertFalse($settings->has('b'));
        $this->assertEmpty($settings->all());
    }

    /** @test */
    public function it_can_load_all_and_autoload_settings()
    {
        $settings = app('settings');

        SettingModel::create(['key' => 'one', 'value' => '1', 'autoload' => false]);
        SettingModel::create(['key' => 'two', 'value' => '2', 'autoload' => true]);

        $all = $settings->all();
        $this->assertArrayHasKey('one', $all);
        $this->assertArrayHasKey('two', $all);

        // clearing internal cache to test autoload method
        $settings->clearCache();

        // ensure autoload will pull only autoload=true
        SettingModel::create(['key' => 'three', 'value' => '3', 'autoload' => true]);

        $settings->autoload();
        $this->assertEquals('2', $settings->get('two'));
        $this->assertEquals('3', $settings->get('three'));
    }
}
