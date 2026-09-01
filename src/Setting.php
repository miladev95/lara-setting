<?php

namespace Miladev\LaravelSettings;

use Miladev\LaravelSettings\Contracts\SettingRepository;

class Setting
{
    /**
     * Underlying storage driver (database, file, or redis).
     * @var SettingRepository
     */
    private $repository;

    public function __construct(SettingRepository $repository)
    {
        $this->repository = $repository;
    }

    public function has($key)
    {
        return $this->repository->has($key);
    }

    public function set($key, $value = null, $autoload = false)
    {
        return $this->repository->set($key, $value, $autoload);
    }

    public function get($key, $default = null)
    {
        return $this->repository->get($key, $default);
    }

    public function forget($key)
    {
        return $this->repository->forget($key);
    }

    public function clean()
    {
        return $this->repository->clean();
    }

    public function clearCache()
    {
        $this->repository->clearCache();
    }

    public function all()
    {
        return $this->repository->all();
    }

    public function autoload()
    {
        $this->repository->autoload();
    }
}
