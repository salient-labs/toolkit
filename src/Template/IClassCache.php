<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Implements an in-memory cache shared between instances of the same class
 *
 * @package Lkrms
 * @see TClassCache
 */
interface IClassCache
{
    /**
     * Return an item from the class cache
     *
     * @param string $itemType
     * @param int|string $itemPath
     * @return mixed
     */
    public static function getClassCache(string $itemType, ...$itemPath);

    /**
     * Store an item in the class cache
     *
     * @param string $itemType
     * @param mixed $item
     * @param int|string $itemPath
     */
    public static function setClassCache(string $itemType, $item, ...$itemPath);

    /**
     * Return an item from the class cache, or use a callback to generate it
     *
     * @param string $itemType
     * @param callable $callback
     * @param int|string $itemPath
     * @return mixed
     */
    public static function getOrSetClassCache(string $itemType, callable $callback, ...$itemPath);
}
