<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Container\ServiceLifetime;

/**
 * A service container with support for contextual bindings
 *
 */
interface IContainer extends \Psr\Container\ContainerInterface
{
    /**
     * @internal
     */
    public function __construct();

    /**
     * True if a global container has been loaded
     *
     */
    public static function hasGlobalContainer(): bool;

    /**
     * Get the global container, loading it if necessary
     *
     */
    public static function getGlobalContainer(): IContainer;

    /**
     * Set (or unset) the global container
     *
     */
    public static function setGlobalContainer(?IContainer $container): ?IContainer;

    /**
     * Get a copy of the container where the contextual bindings of a class or
     * interface have been applied to the container itself
     *
     */
    public function inContextOf(string $id): IContainer;

    /**
     * Create a new instance of a class or service interface, or get a shared
     * instance created earlier
     *
     * @template T
     * @param class-string<T> $id
     * @param mixed[] $params Values to pass to the constructor of the
     * instantiated class. Ignored if `$id` resolves to a shared instance.
     * @return T
     */
    public function get(string $id, array $params = []);

    /**
     * Create a new instance of a class or service interface, or get a shared
     * instance created earlier, if the instance inherits a class or implements
     * an interface
     *
     * @template T0
     * @template T1
     * @param class-string<T0> $id
     * @param class-string<T1> $serviceId
     * @param mixed[] $params Values to pass to the constructor of the
     * instantiated class. Ignored if `$id` resolves to a shared instance.
     * @return T0&T1
     * @throws \RuntimeException if `$serviceId` is not inherited.
     */
    public function getIf(string $id, string $serviceId, array $params = []);

    /**
     * Apply an explicit service name while creating a new instance of a class
     * or service interface or getting a shared instance created earlier
     *
     * @template T
     * @param class-string<T> $id
     * @param string $serviceId The service name passed to
     * `ReceivesService::setService()`.
     * @param mixed[] $params Values to pass to the constructor of the
     * instantiated class. Ignored if `$id` resolves to a shared instance.
     * @return T
     */
    public function getAs(string $id, string $serviceId, array $params = []);

    /**
     * Resolve a class or interface to a concrete class name
     *
     * Returns `$id` if nothing has been bound to it in the container.
     *
     */
    public function getName(string $id): string;

    /**
     * True if a class or service interface resolves to a concrete class that
     * actually exists
     *
     * If `has($id)` returns `false`, `get($id)` must throw a
     * {@see \Psr\Container\NotFoundExceptionInterface}.
     *
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
     * Add a binding to the container if it hasn't already been bound
     *
     * See {@see IContainer::bind()} for more information.
     *
     * @return $this
     */
    public function bindIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null);

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
     * Add a shared binding to the container if it hasn't already been bound
     *
     * See {@see IContainer::singleton()} for more information.
     *
     * @return $this
     */
    public function singletonIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null);

    /**
     * Add bindings to the container for an IService, optionally specifying
     * services to include or exclude
     *
     * A shared binding is added for `$id` if it implements
     * {@see IServiceSingleton} or if {@see ServiceLifetime::SINGLETON} is set
     * in `$lifetime`.
     *
     * A shared instance is created for each service if `$id` implements
     * {@see IServiceShared} or if {@see ServiceLifetime::SERVICE_SINGLETON} is
     * set in `$lifetime`.
     *
     * @param string $id The name of a class that implements {@see IService},
     * {@see IServiceSingleton} or {@see IServiceShared}.
     * @param string[]|null $services
     * @param string[]|null $exceptServices
     * @param int $lifetime A bitmask of {@see ServiceLifetime} values.
     * @phpstan-param int-mask-of<ServiceLifetime::*> $lifetime
     * @return $this
     */
    public function service(string $id, ?array $services = null, ?array $exceptServices = null, int $lifetime = ServiceLifetime::INHERIT);

    /**
     * Add an existing instance to the container as a shared binding
     *
     * @return $this
     */
    public function instance(string $id, $instance);

    /**
     * Add an existing instance to the container as a shared binding if it
     * hasn't already been bound
     *
     * @return $this
     */
    public function instanceIf(string $id, $instance);

    /**
     * Consolidate a service map and call service() once per concrete class
     *
     * This method simplifies bootstrapping, especially when the same class may
     * be configured at runtime to provide multiple services.
     *
     * @param array<string,string> $serviceMap An array that maps abstract
     * service names to concrete class names.
     *
     * In this example, `MyFactoryClass` must implement {@see IService},
     * {@see IServiceSingleton} or {@see IServiceShared}, and it should also
     * implement `MyFactoryInterface`:
     *
     * ```php
     * [
     *   MyFactoryInterface::class => MyFactoryClass::class,
     * ]
     * ```
     * @param int $lifetime A bitmask of {@see ServiceLifetime} values.
     * @phpstan-param int-mask-of<ServiceLifetime::*> $lifetime
     * @return $this
     */
    public function services(array $serviceMap, int $lifetime = ServiceLifetime::INHERIT);

    /**
     * Get a list of classes bound to the container by calling service()
     *
     * @return string[]
     */
    public function getServices(): array;

    /**
     * Remove a binding from the container
     *
     * @return $this
     */
    public function unbind(string $id);
}
