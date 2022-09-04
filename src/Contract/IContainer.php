<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Psr\Container\ContainerInterface;

/**
 * A service container with support for contextual bindings
 *
 */
interface IContainer extends ContainerInterface
{
    public function __construct();

    /**
     * Return true if a global container has been loaded
     *
     */
    public static function hasGlobalContainer(): bool;

    /**
     * Get the current global container, loading it if necessary
     *
     */
    public static function getGlobalContainer(): IContainer;

    /**
     * Set (or unset) the global container
     *
     */
    public static function setGlobalContainer(?IContainer $container): ?IContainer;

    /**
     * Get a copy of the container where the contextual bindings of the given
     * class or interface have been applied to the default context
     *
     */
    public function inContextOf(string $id): IContainer;

    /**
     * Create a new instance of the given class or interface, or return a
     * shared instance created earlier
     *
     * @template T
     * @psalm-param class-string<T> $id
     * @psalm-return T
     * @param string $id
     * @param mixed ...$params Values to pass to the constructor of the
     * instantiated class. Ignored if `$id` resolves to a shared instance.
     * @return mixed
     */
    public function get(string $id, ...$params);

    /**
     * Resolve the given class or interface to a concrete class name
     *
     * Returns `$id` if nothing has been bound to it in the container.
     *
     */
    public function getName(string $id): string;

    /**
     * Return true if the given class or interface resolves to a concrete
     * class that actually exists
     *
     * If `has($id)` returns `false`, `get($id)` must throw a
     * {@see \Psr\Container\NotFoundExceptionInterface}.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * Add a binding to the container
     *
     * The container will subsequently resolve requests for `$id` to
     * `$instanceOf` (default: `$id`). If set:
     * - `$constructParams` will be unpacked and passed to the constructor of
     *   the instantiated class whenever `$id` is resolved, and
     * - classes in `$shareInstances` will only be instantiated once per request
     *   for `$id`.
     *
     * @return $this
     */
    public function bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null);

    /**
     * Add a shared binding to the container
     *
     * The container will subsequently resolve requests for `$id` to a shared
     * instance of `$instanceOf` (default: `$id`) that will only be created when
     * `$id` is first requested.
     *
     * See {@see IContainer::bind()} for more information.
     *
     * @return $this
     */
    public function singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null);

    /**
     * Add bindings to the container for an IBindable implementation and its
     * services, optionally specifying services to bind or exclude
     *
     * A shared binding will be added for `$id` if it implements
     * {@see IBindableSingleton}.
     *
     * @param string $id The name of a class that implements {@see IBindable} or
     * {@see IBindableSingleton}.
     * @param null|string[] $services
     * @param null|string[] $exceptServices
     * @return $this
     */
    public function service(string $id, ?array $services = null, ?array $exceptServices = null, ?array $constructParams = null, ?array $shareInstances = null);

    /**
     * Add an existing instance to the container as a shared binding
     *
     * @return $this
     */
    public function instance(string $id, $instance);

    /**
     * Make this the global container while running the given callback
     *
     * Even if `$callback` throws an exception, the previous global container
     * will be restored before this method returns.
     *
     * @return mixed The callback's return value.
     */
    public function call(callable $callback);

}
