<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

/**
 * A facade for \Lkrms\Container\Container
 *
 * @method static Container load() Load and return an instance of the underlying Container class
 * @method static Container getInstance() Return the underlying Container instance
 * @method static bool isLoaded() Return true if an underlying Container instance has been loaded
 * @method static void unload() Clear the underlying Container instance
 * @method static Container bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container (see {@see Container::bind()})
 * @method static mixed call(callable $callback) Make this the global container while running the given callback (see {@see Container::call()})
 * @method static IContainer|null coalesce(?IContainer $container, bool $returnNull = true, bool $load = false) Return the first available container (see {@see Container::coalesce()})
 * @method static mixed get(string $id, mixed ...$params) Create a new instance of the given class or interface, or return a shared instance created earlier (see {@see Container::get()})
 * @method static IContainer getGlobalContainer() Get the current global container, loading it if necessary (see {@see Container::getGlobalContainer()})
 * @method static string getName(string $id) Resolve the given class or interface to a concrete class name (see {@see Container::getName()})
 * @method static bool has(string $id) Return true if the given class or interface resolves to a concrete class that actually exists (see {@see Container::has()})
 * @method static bool hasGlobalContainer() Return true if a global container has been loaded (see {@see Container::hasGlobalContainer()})
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of the given class or interface have been applied to the default context (see {@see Container::inContextOf()})
 * @method static Container instance(string $id, mixed $instance) Add an existing instance to the container as a shared binding (see {@see Container::instance()})
 * @method static IContainer|null maybeGetGlobalContainer() Similar to getGlobalContainer(), but return null if no global container has been loaded (see {@see Container::maybeGetGlobalContainer()})
 * @method static IContainer requireGlobalContainer() Similar to getGlobalContainer(), but throw an exception if no global container has been loaded (see {@see Container::requireGlobalContainer()})
 * @method static Container service(string $id, string[]|null $services = null, string[]|null $exceptServices = null, ?array $constructParams = null, ?array $shareInstances = null) Add bindings to the container for an IBindable implementation and its services, optionally specifying services to bind or exclude (see {@see Container::service()})
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container (see {@see Container::setGlobalContainer()})
 * @method static Container singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container (see {@see Container::singleton()})
 *
 * @uses Container
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\Container' --generate='Lkrms\Facade\DI'
 */
final class DI extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Container::class;
    }
}