<?php

namespace Miladev\LaravelSettings\Repositories;

use Miladev\LaravelSettings\Contracts\SettingRepository;
use Miladev\LaravelSettings\Models\Setting as SettingModel;

class DatabaseRepository implements SettingRepository
{
    /**
     * Store already retrieved key-value
     * @var array
     */
    private $data = [];

    public function has($key)
    {
        return array_key_exists($key, $this->data) || SettingModel::where('key', $key)->exists();
    }

    public function set($key, $value = null, $autoload = false)
    {
        $this->data[$key] = $value;

        return SettingModel::updateOrCreate(
            [
                'key' => $key,
            ],
            [
                'value' => $value,
                'autoload' => $autoload,
            ]
        );
    }

    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $setting = SettingModel::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public function forget($key)
    {
        unset($this->data[$key]);

        return SettingModel::where('key', $key)->delete();
    }

    public function clean()
    {
        $this->data = [];

        return SettingModel::query()->delete();
    }

    public function clearCache()
    {
        $this->data = [];
    }

    public function all()
    {
        $this->data = SettingModel::get(['key', 'value'])->mapWithKeys(function ($item) {
            return [$item->key => $item->value];
        })->toArray();

        return $this->data;
    }

    public function autoload()
    {
        $settings = SettingModel::where('autoload', true)->pluck('value', 'key')->toArray();

        $this->data = array_merge($this->data, $settings);
    }
}
