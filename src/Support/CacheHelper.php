<?php

namespace OnaOnbir\OORolePermission\Support;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Check if caching is enabled and supported
     */
    public static function isEnabled(): bool
    {
        return config('oo-role-permission.cache.enabled', true);
    }

    /**
     * Check if cache tagging is supported
     */
    public static function supportsTagging(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        try {
            // Test if the current cache store supports tagging
            Cache::tags(['test'])->put('test_key', 'test_value', 1);
            Cache::tags(['test'])->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remember with fallback for stores without tagging
     */
    public static function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        if (!self::isEnabled()) {
            return $callback();
        }

        if (!empty($tags) && self::supportsTagging()) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Clear cache with tagging support check
     */
    public static function forget(string $key, array $tags = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        if (!empty($tags) && self::supportsTagging()) {
            Cache::tags($tags)->forget($key);
        } else {
            Cache::forget($key);
        }
    }

    /**
     * Flush cache with tagging support check
     */
    public static function flush(array $tags = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        if (!empty($tags) && self::supportsTagging()) {
            Cache::tags($tags)->flush();
        } else {
            // For stores without tagging, we have to flush all
            Cache::flush();
        }
    }

    /**
     * Get cache key with prefix
     */
    public static function key(string $key): string
    {
        $prefix = config('oo-role-permission.cache.key_prefix', 'oo_rp:');
        return $prefix . $key;
    }
}
