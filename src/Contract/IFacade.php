<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Provides a static interface for an underlying singleton
 *
 * @see \Lkrms\Concept\Facade
 */
interface IFacade
{
    /**
     * Return true if the underlying instance has been created
     *
     * @return bool
     */
    public static function isLoaded(): bool;

    /**
     * Create and return the underlying instance
     *
     */
    public static function load();

    /**
     * Return the underlying instance
     *
     * If the underlying instance has not been created, the implementing class
     * may either:
     * 1. throw a {@see \RuntimeException}, or
     * 2. create the underlying instance and return it
     *
     * @throws \RuntimeException
     */
    public static function getInstance();
}
