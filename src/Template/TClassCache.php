<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Implements IClassCache to provide an in-memory cache shared between instances
 * of the same class
 *
 * @package Lkrms
 * @see IClassCache
 */
trait TClassCache
{
    private static $ClassCache = [];

    private static function & getClassCacheArray(string $itemType): array
    {
        if (!isset(self::$ClassCache[ static::class]))
        {
            self::$ClassCache[ static::class] = [];
        }

        if (!isset(self::$ClassCache[ static::class][$itemType]))
        {
            self::$ClassCache[ static::class][$itemType] = [];
        }

        return self::$ClassCache[ static::class][$itemType];
    }

    /**
     * Return an item from the class cache
     *
     * Returns `null` if nothing was previously stored at `$itemPath` in the
     * `$itemType` cache shared between instances of this class.
     *
     * @param string $itemType
     * @param int|string $itemPath
     * @return mixed
     */
    final public static function getClassCache(string $itemType, ...$itemPath)
    {
        $cache = self::getClassCacheArray($itemType);

        while (is_array($cache) && !empty($itemPath))
        {
            $cache = $cache[array_shift($itemPath)] ?? null;
        }

        return $cache;
    }

    /**
     * Store an item in the class cache
     *
     * Stores `$item` at `$itemPath` in the `$itemType` cache shared between
     * instances of this class.
     *
     * @param string $itemType
     * @param mixed $item
     * @param int|string $itemPath
     */
    final public static function setClassCache(string $itemType, $item, ...$itemPath)
    {
        $cache = & self::getClassCacheArray($itemType);

        while (!empty($itemPath))
        {
            $key = array_shift($itemPath);

            if (!isset($cache[$key]))
            {
                $cache[$key] = [];
            }

            $cache = & $cache[$key];
        }

        $cache = $item;
    }

    /**
     * Return an item from the class cache, or use a callback to generate it
     *
     * Runs `$callback` and caches its return value if nothing was previously
     * stored at `$itemPath` in the `$itemType` cache shared between instances
     * of this class. The cached item is always returned.
     *
     * @param string $itemType
     * @param callable $callback
     * @param int|string $itemPath
     * @return mixed
     */
    final public static function getOrSetClassCache(string $itemType, callable $callback, ...$itemPath)
    {
        if (is_null($item = self::getClassCache($itemType, ...$itemPath)))
        {
            $item = $callback();
            self::setClassCache($itemType, $item, ...$itemPath);
        }

        return $item;
    }
}

