<?php declare(strict_types=1);

namespace Salient\Contract\Core\Facade;

use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;
use LogicException;

/**
 * @api
 *
 * @template TService of Instantiable
 */
interface FacadeInterface
{
    /**
     * Check if the facade's underlying instance is loaded
     */
    public static function isLoaded(): bool;

    /**
     * Load the facade's underlying instance
     *
     * If `$instance` is `null`, the facade creates a new underlying instance.
     *
     * Then, if the instance implements {@see FacadeAwareInterface}, it is
     * replaced with the return value of
     * {@see FacadeAwareInterface::withFacade()}.
     *
     * @param TService|null $instance
     * @throws LogicException if the facade's underlying instance is already
     * loaded.
     */
    public static function load(?object $instance = null): void;

    /**
     * Replace the facade's underlying instance
     *
     * Equivalent to calling {@see unload()} before passing `$instance` to
     * {@see load()}.
     *
     * @param TService $instance
     */
    public static function swap(object $instance): void;

    /**
     * Remove the facade's underlying instance if loaded
     *
     * If the underlying instance implements {@see FacadeAwareInterface}, it is
     * replaced with the return value of
     * {@see FacadeAwareInterface::withoutFacade()}.
     *
     * Then, if the instance implements {@see Unloadable}, its
     * {@see Unloadable::unload()} method is called.
     */
    public static function unload(): void;

    /**
     * Get the facade's underlying instance, loading it if necessary
     *
     * Returns the instance returned by
     * {@see FacadeAwareInterface::withoutFacade()} if the underlying instance
     * implements {@see FacadeAwareInterface}.
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
