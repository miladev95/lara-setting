<?php

namespace Miladev\LaravelSettings\Contracts;

interface SettingRepository
{
    public function has($key);

    public function get($key, $default = null);

    public function set($key, $value = null, $autoload = false);

    public function forget($key);

    public function clean();

    public function clearCache();

    public function all();

    public function autoload();
}
