<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use LogicException;

/**
 * Provides a static interface to an underlying instance
 *
 * Underlying instances that implement {@see FacadeAwareInterface} are replaced
 * with the object returned by {@see FacadeAwareInterface::withFacade()}, and
 * its {@see FacadeAwareInterface::withoutFacade()} method is used to service
 * the facade's {@see swap()}, {@see unload()} and {@see getInstance()} methods.
 *
 * @api
 *
 * @template TService of object
 */
interface FacadeInterface
{
    /**
     * True if the facade's underlying instance is loaded
     */
    public static function isLoaded(): bool;

    /**
     * Load the facade's underlying instance
     *
     * If `$instance` is `null`, the facade creates a new underlying instance.
     *
     * @param TService|null $instance
     * @throws LogicException if the facade's underlying instance is already
     * loaded.
     */
    public static function load(?object $instance = null): void;

    /**
     * Replace the facade's underlying instance
     *
     * @param TService $instance
     */
    public static function swap(object $instance): void;

    /**
     * Remove the facade's underlying instance if loaded
     */
    public static function unload(): void;

    /**
     * Get the facade's underlying instance, loading it if necessary
     *
     * @return TService
     */
    public static function getInstance(): object;

    /**
     * Forward a static method to the facade's underlying instance, loading it
     * if necessary
     *
     * @param mixed[] $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments);
}
