<?php

namespace Miladev\LaravelSettings\Tests\Unit;

use Miladev\LaravelSettings\Models\Setting;
use Miladev\LaravelSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettingModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function a_setting_has_a_key()
    {
        $setting = Setting::factory()->create(['key' => 'fake_key', 'value' => 'fake value']);
        $this->assertEquals('fake_key', $setting->key);
    }

    /** @test */
    function a_setting_has_a_value()
    {
        $setting = Setting::factory()->create(['key' => 'fake_key', 'value' => 'fake value']);
        $this->assertEquals('fake value', $setting->value);
    }

    /** @test */
    function a_setting_has_a_autoload()
    {
        $setting = Setting::factory()->create(['key' => 'fake_key', 'value' => 'fake value', 'autoload' => true ]);
        $this->assertEquals(true, $setting->autoload);
    }

}