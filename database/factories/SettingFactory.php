<?php

namespace Miladev\LaravelSettings\Database\Factories;

use Miladev\LaravelSettings\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition()
    {
        return [
            'key' => $this->faker->unique()->word,
            'value' => $this->faker->sentence(3),
            'autoload' => false,
        ];
    }
}