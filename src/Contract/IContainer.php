<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Container\ServiceLifetime;
use Lkrms\Exception\ContainerServiceNotFoundException;

/**
 * A service container with support for contextual bindings
 *
 */
interface IContainer extends \Psr\Container\ContainerInterface
{
    /**
     * Creates a new service container object
     *
     */
    public function __construct();

    /**
     * True if the global container is set
     *
     */
    public static function hasGlobalContainer(): bool;

    /**
     * Get the global container
     *
     */
    public static function getGlobalContainer(): IContainer;

    /**
     * Set the global container
     *
     * @template T of IContainer|null
     * @param T $container
     * @return T
     */
    public static function setGlobalContainer(?IContainer $container): ?IContainer;

    /**
     * Apply the contextual bindings of a service to a copy of the container
     *
     * @param class-string $id
     */
    public function inContextOf(string $id): IContainer;

    /**
     * Get an instance from the container
     *
     * @template T
     * @param class-string<T> $id The identifier to resolve.
     * @param mixed[] $params Values to pass to the constructor of the class
     * being instantiated. Ignored if `$id` resolves to a shared instance.
     * @return T
     */
    public function get(string $id, array $params = []);

    /**
     * Use one identifier to get an instance from the container and another as
     * its service name
     *
     * @template T0
     * @template T1
     * @param class-string<T0> $id The identifier to resolve.
     * @param class-string<T1> $serviceId The identifier to pass to the instance
     * via its {@see ReceivesService::setService()} method.
     * @param mixed[] $params Values to pass to the constructor of the class
     * being instantiated. Ignored if `$id` resolves to a shared instance.
     * @return T0&T1
     */
    public function getAs(string $id, string $serviceId, array $params = []);

    /**
     * Get a concrete class name from the container
     *
     * @template T
     * @param class-string<T> $id The identifier to resolve.
     * @return class-string<T>
     */
    public function getName(string $id): string;

    /**
     * True if the container can resolve an identifier to an instance
     *
     * If `has($id)` returns `false`, `get($id)` throws a
     * {@see ContainerServiceNotFoundException}.
     *
     * @param class-string $id
     */
    public function has(string $id): bool;

    /**
     * Register a binding with the container
     *
     * The container will resolve subsequent requests for `$id` to `$instanceOf`
     * (default: `$id`). If set:
     * - `$constructParams` will be unpacked and passed to the constructor of
     *   the instantiated class whenever `$id` is resolved, and
     * - classes in `$shareInstances` will only be instantiated once per request
     *   for `$id`.
     *
     * @template T0
     * @template T1 of T0
     * @param class-string<T0> $id
     * @param class-string<T1>|null $instanceOf
     * @param mixed[]|null $constructParams
     * @param class-string[]|null $shareInstances
     * @return $this
     */
    public function bind(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    );

    /**
     * Register a binding with the container if it isn't already registered
     *
     * @template T0
     * @template T1 of T0
     * @param class-string<T0> $id
     * @param class-string<T1>|null $instanceOf
     * @param mixed[]|null $constructParams
     * @param class-string[]|null $shareInstances
     * @return $this
     */
    public function bindIf(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    );

    /**
     * Register a shared binding with the container
     *
     * The container will resolve subsequent requests for `$id` to a shared
     * instance of `$instanceOf` (default: `$id`) that will be created when
     * `$id` is first requested.
     *
     * See {@see IContainer::bind()} for more information.
     *
     * @template T0
     * @template T1 of T0
     * @param class-string<T0> $id
     * @param class-string<T1>|null $instanceOf
     * @param mixed[]|null $constructParams
     * @param class-string[]|null $shareInstances
     * @return $this
     */
    public function singleton(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    );

    /**
     * Register a shared binding with the container if it isn't already
     * registered
     *
     * @template T0
     * @template T1 of T0
     * @param class-string<T0> $id
     * @param class-string<T1>|null $instanceOf
     * @param mixed[]|null $constructParams
     * @param class-string[]|null $shareInstances
     * @return $this
     */
    public function singletonIf(
        string $id,
        ?string $instanceOf = null,
        ?array $constructParams = null,
        ?array $shareInstances = null
    );

    /**
     * Register an IService with the container, optionally specifying services
     * to include or exclude
     *
     * A shared instance of `$id` is created if it implements
     * {@see IServiceSingleton} or if `$lifetime` is
     * {@see ServiceLifetime::SINGLETON}.
     *
     * A shared instance of `$id` is created for each service if it implements
     * {@see IServiceShared} or if `$lifetime` is
     * {@see ServiceLifetime::SERVICE_SINGLETON}.
     *
     * @param class-string<IService> $id
     * @param class-string[]|null $services
     * @param class-string[]|null $exceptServices
     * @param int-mask-of<ServiceLifetime::*> $lifetime
     * @return $this
     */
    public function service(
        string $id,
        ?array $services = null,
        ?array $exceptServices = null,
        int $lifetime = ServiceLifetime::INHERIT
    );

    /**
     * Register an existing instance with the container as a shared binding
     *
     * @template T
     * @param class-string<T> $id
     * @param T $instance
     * @return $this
     */
    public function instance(string $id, $instance);

    /**
     * Register an existing instance with the container as a shared binding if
     * it isn't already registered
     *
     * @template T
     * @param class-string<T> $id
     * @param T $instance
     * @return $this
     */
    public function instanceIf(string $id, $instance);

    /**
     * Register a service map with the container
     *
     * This method simplifies bootstrapping, especially when the same class may
     * be configured at runtime to provide multiple services.
     *
     * @param array<class-string|int,class-string<IService>> $serviceMap An
     * array that maps service names to concrete class names. Entries with
     * integer keys are registered without any service names. This allows
     * inactive services to be available for ad-hoc use.
     * @param int-mask-of<ServiceLifetime::*> $lifetime
     * @return $this
     */
    public function services(array $serviceMap, int $lifetime = ServiceLifetime::INHERIT);

    /**
     * Get a list of classes bound to the container by calling service()
     *
     * @return array<class-string<IService>>
     */
    public function getServices(): array;

    /**
     * Remove a binding from the container
     *
     * @param class-string $id
     * @return $this
     */
    public function unbind(string $id);

    /**
     * Prepare the container for garbage collection
     *
     */
    public function unload(): void;
}
