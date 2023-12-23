<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Facade;

/**
 * Provides a static interface to an instance of an underlying class
 *
 * @template TClass of object
 *
 * @see Facade
 * @see ReceivesFacade
 */
interface IFacade
{
    /**
     * True if an underlying instance has been loaded
     */
    public static function isLoaded(): bool;

    /**
     * Load and return an instance of the underlying class
     *
     * If called with arguments, they are passed to the constructor of the
     * underlying class.
     *
     * If the underlying class implements {@see ReceivesFacade}, the name of the
     * facade is passed to {@see ReceivesFacade::setFacade()}.
     *
     * @return TClass
     * @throws \RuntimeException if an underlying instance has already been
     * loaded.
     */
    public static function load();

    /**
     * Clear the underlying instance
     *
     * If an underlying instance has not been loaded, no action is taken.
     */
    public static function unload(): void;

    /**
     * Get the underlying instance
     *
     * If an underlying instance has not been loaded, the facade may either:
     *
     * 1. call {@see IFacade::load()} and pass the instance it returns to the
     *    caller (preferred), or
     * 2. throw a `RuntimeException`.
     *
     * @return TClass
     */
    public static function getInstance();
}
