<?php

/**
 * Plugin Name: Hobo Plugin Framework - Request Cache.
 * Description: Abstract class for request cache operations. Cache is only active for the duration of the request.
 * Version: 1.0
 * Author: Hobo Digital Ltd.
 */

namespace Hobo\Framework;

class Request_Cache
{
    private $_use_cache = TRUE;
    private $_cache = array();

    /**
     * @param string $function
     * @param        $attributes
     *
     * @return string
     */
    protected function get_cache_key(string $function, $attributes): string
    {
        if (!$this->_use_cache || empty($func)) {
            return NULL;
        }
        return md5($function . serialize(func_get_args()));
    }

    /**
     * @param string $cache_key
     *
     * @return bool
     */
    protected function is_in_cache(string $cache_key): bool
    {
        return array_key_exists($cache_key, $this->_cache);
    }

    /**
     * @param string $cache_key
     *
     * @return mixed
     */
    protected function get_from_cache(string $cache_key)
    {
        if ($this->_use_cache && !empty($cache_key)) {
            if ($this->is_in_cache($cache_key)) {
                return $this->_cache [$cache_key];
            }
        }
        return NULL;
    }

    /**
     * @param string $cache_key
     * @param        $value
     */
    protected function put_in_cache(string $cache_key, $value): void
    {
        if ($this->_use_cache) {
            $this->_cache [$cache_key] = $value;
        }
    }

    /**
     * @return array
     */
    protected function get_cache(): array
    {
        return $this->_cache;
    }

    /**
     * @return bool
     */
    public function is_use_cache(): bool
    {
        return $this->_use_cache;
    }

    /**
     * @param bool $use_cache
     */
    public function set_use_cache(bool $use_cache): void
    {
        $this->_use_cache = $use_cache;
    }

}
