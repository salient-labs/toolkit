<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Implements the singleton pattern
 *
 * @package Lkrms
 * @see Singleton
 */
trait TSingleton
{
    private static $Singletons = [];

    final protected function __construct()
    {
        self::$Singletons[static::class] = $this;
    }

    final protected static function hasInstance(): bool
    {
        return !is_null(self::$Singletons[static::class] ?? null);
    }

    /**
     *
     * @return static
     */
    public static function getInstance()
    {
        return (self::$Singletons[static::class] ?? null) ?: new static();
    }
}

