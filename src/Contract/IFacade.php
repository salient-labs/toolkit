<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Provides a static interface for an underlying singleton
 *
 * @see \Lkrms\Concept\Facade
 * @see \Lkrms\Contract\HasFacade
 */
interface IFacade
{
    /**
     * Return true if the underlying instance has been created
     *
     */
    public static function isLoaded(): bool;

    /**
     * Create and return the underlying instance
     *
     * If called with arguments, they are passed to the constructor of the
     * underlying class.
     *
     * If the underlying class implements {@see HasFacade}, the name of the
     * facade is passed to its {@see HasFacade::setFacade()} method.
     *
     * @throws \RuntimeException if the underlying instance already exists
     */
    public static function load();

    /**
     * Return the underlying instance
     *
     * If the underlying instance has not been created, the implementing class
     * may either:
     * 1. throw a `RuntimeException`, or
     * 2. create the underlying instance and return it
     */
    public static function getInstance();
}
