<?php

namespace Miladev\LaravelSettings\Repositories;

use Miladev\LaravelSettings\Contracts\SettingRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

abstract class CacheStoreRepository implements SettingRepository
{
    /**
     * In-process cache of already retrieved key-value pairs
     * @var array
     */
    protected $data = [];

    /**
     * Underlying cache repository (file or redis)
     * @var CacheRepository
     */
    protected $cache;

    /**
     * TTL in minutes, 0 = forever
     * @var int
     */
    protected $ttl;

    public function __construct(CacheRepository $cache, $ttl = 60)
    {
        $this->cache = $cache;
        $this->ttl = (int) $ttl;
    }

    /**
     * Cache key used to store the value for a setting.
     */
    protected function valueKey($key)
    {
        return 'lara_setting:'.$key;
    }

    /**
     * Cache key used to store the index of all known setting keys.
     */
    protected function indexKey()
    {
        return 'lara_setting:__index__';
    }

    /**
     * TTL in seconds, 0 = forever
     */
    protected function ttlSeconds()
    {
        return $this->ttl > 0 ? $this->ttl * 60 : 0;
    }

    /**
     * Read a stored value, returning null on miss.
     */
    protected function readValue($key)
    {
        $entry = $this->cache->get($this->valueKey($key));

        if (!is_array($entry) || !array_key_exists('value', $entry)) {
            return null;
        }

        return $entry;
    }

    /**
     * Write a stored value, including autoload flag, and update the key index.
     */
    protected function writeValue($key, $value, $autoload)
    {
        $payload = [
            'value' => $value,
            'autoload' => (bool) $autoload,
        ];

        if ($this->ttlSeconds() > 0) {
            $this->cache->put($this->valueKey($key), $payload, $this->ttlSeconds());
        } else {
            $this->cache->forever($this->valueKey($key), $payload);
        }

        $this->addToIndex($key);
    }

    /**
     * Remove a stored value and update the key index.
     */
    protected function dropValue($key)
    {
        $this->cache->forget($this->valueKey($key));
        $this->removeFromIndex($key);
    }

    /**
     * Read the index of all known setting keys.
     */
    protected function readIndex()
    {
        $index = $this->cache->get($this->indexKey(), []);

        return is_array($index) ? $index : [];
    }

    /**
     * Persist the index of all known setting keys.
     */
    protected function writeIndex(array $index)
    {
        if ($this->ttlSeconds() > 0) {
            $this->cache->put($this->indexKey(), $index, $this->ttlSeconds());
        } else {
            $this->cache->forever($this->indexKey(), $index);
        }
    }

    /**
     * Add a key to the index if it is not already present.
     */
    protected function addToIndex($key)
    {
        $index = $this->readIndex();

        if (!in_array($key, $index, true)) {
            $index[] = $key;
            $this->writeIndex($index);
        }
    }

    /**
     * Remove a key from the index.
     */
    protected function removeFromIndex($key)
    {
        $index = $this->readIndex();

        $filtered = array_values(array_filter($index, function ($k) use ($key) {
            return $k !== $key;
        }));

        if (count($filtered) !== count($index)) {
            $this->writeIndex($filtered);
        }
    }

    public function has($key)
    {
        if (array_key_exists($key, $this->data)) {
            return true;
        }

        return $this->readValue($key) !== null;
    }

    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $entry = $this->readValue($key);

        if ($entry === null) {
            return $default;
        }

        $this->data[$key] = $entry['value'];

        return $entry['value'];
    }

    public function set($key, $value = null, $autoload = false)
    {
        $this->data[$key] = $value;

        $this->writeValue($key, $value, $autoload);

        return $value;
    }

    public function forget($key)
    {
        unset($this->data[$key]);

        $this->dropValue($key);

        return 1;
    }

    public function clean()
    {
        $this->data = [];

        foreach ($this->readIndex() as $key) {
            $this->cache->forget($this->valueKey($key));
        }

        $this->writeIndex([]);

        return 1;
    }

    public function clearCache()
    {
        $this->data = [];
    }

    public function all()
    {
        $this->data = [];

        foreach ($this->readIndex() as $key) {
            $entry = $this->readValue($key);
            if ($entry !== null) {
                $this->data[$key] = $entry['value'];
            }
        }

        return $this->data;
    }

    public function autoload()
    {
        foreach ($this->readIndex() as $key) {
            $entry = $this->readValue($key);
            if ($entry !== null && !empty($entry['autoload']) && !array_key_exists($key, $this->data)) {
                $this->data[$key] = $entry['value'];
            }
        }
    }
}
