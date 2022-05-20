<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Provides a static interface for an underlying singleton
 *
 */
interface ISingular
{
    /**
     * Return true if the underlying instance has been initialised
     *
     * @return bool
     */
    public static function isLoaded(): bool;

    /**
     * Create, initialise and return the underlying instance
     *
     * @return object
     */
    public static function load();

    /**
     * Return the underlying instance
     *
     * @return object
     */
    public static function getInstance();

    /**
     * Pass static method calls to the underlying instance
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments);
}
