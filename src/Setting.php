<?php

namespace Miladev\LaravelSettings;

use Miladev\LaravelSettings\Models\Setting as SettingModel;

class Setting
{
    /**
     * Store already retrieved key-value
     * @var array
     */
    private $data = [];

    /**
     * Check whether the key exists or not
     *
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        return isset($this->data[$key]) || (bool) SettingModel::where('key', $key)->count();
    }

    /**
     * Set a specific setting with key.
     *
     * @param string $key
     * @param string|null $value
     * @param bool $autoload
     * @return mixed
     */
    public function set($key, $value = null, $autoload = false)
    {
        $this->data[$key] = $value;

        return SettingModel::updateOrCreate(
            [
                'key' => $key
            ],
            [
                'value' => $value,
                'autoload' => $autoload,
            ]
        );
    }

    /**
     * Get a single setting
     *
     * @param string $key
     * @return string|null
     */
    public function get($key, $default = null)
    {
        if(isset($this->data[$key])) {
            return $this->data[$key];
        }

        $setting = SettingModel::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Remove single setting
     *
     * @param string $key
     * @return boolean
     */
    public function forget($key)
    {
        unset($this->data[$key]);

        return SettingModel::where('key', $key)->delete();
    }

    /**
     * Remove all settings
     *
     * @return boolean
     */
    public function clean()
    {
        $this->data = [];

        return SettingModel::query()->delete();
    }

    /**
     * Clear only in-memory cache of settings (do not delete DB records)
     *
     * @return void
     */
    public function clearCache()
    {
        $this->data = [];
    }

    /**
     * Get All Settings
     *
     * @return array
     */
    public function all()
    {
        $this->data = SettingModel::get(['key', 'value'])->mapWithKeys(function($item) {
            return [ $item->key => $item->value ];
        })->toArray();

        return $this->data;
    }

    /**
     * Run Autoloader
     */
    public function autoload()
    {
        // ensure we retrieve a collection before using collection helpers
        $settings = SettingModel::where('autoload', true)->pluck('value', 'key')->toArray();

        $this->data = array_merge($this->data, $settings);
    }
}