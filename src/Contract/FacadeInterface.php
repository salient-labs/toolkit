<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Facade;
use LogicException;

/**
 * Provides a static interface to an instance of an underlying class
 *
 * @template TClass of object
 *
 * @see Facade
 * @see FacadeAwareInterface
 */
interface FacadeInterface
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
     * If the underlying class implements {@see FacadeAwareInterface}, the name
     * of the facade is passed to {@see FacadeAwareInterface::withFacade()}.
     *
     * @return TClass
     * @throws LogicException if an underlying instance has already been loaded.
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
     * If an underlying instance has not been loaded, the facade should return
     * an instance from {@see FacadeInterface::load()}.
     *
     * @return TClass
     */
    public static function getInstance();
}
