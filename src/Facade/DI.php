<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Contract\IContainer;

/**
 * A facade for \Lkrms\Container\Container
 *
 * @method static Container load() Load and return an instance of the underlying Container class
 * @method static Container getInstance() Get the underlying Container instance
 * @method static bool isLoaded() True if an underlying Container instance has been loaded
 * @method static void unload() Clear the underlying Container instance
 * @method static Container bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container (see {@see Container::bind()})
 * @method static Container bindIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container if it hasn't already been bound (see {@see Container::bindIf()})
 * @method static Container call(callable $callback) Move to the next method in the chain after passing the object to a callback (see {@see FluentInterface::call()})
 * @method static Container forEach(array|object $array, callable $callback) Move to the next method in the chain after passing the object to a callback for each key-value pair in an array (see {@see FluentInterface::forEach()})
 * @method static mixed get(class-string $id, mixed[] $params = []) Create a new instance of a class or service interface, or get a shared instance created earlier (see {@see Container::get()})
 * @method static mixed getAs(class-string $id, string $serviceId, mixed[] $params = []) Apply an explicit service name while creating a new instance of a class or service interface or getting a shared instance created earlier (see {@see Container::getAs()})
 * @method static IContainer getGlobalContainer() Get the global container, loading it if necessary
 * @method static mixed getIf(class-string $id, class-string $serviceId, mixed[] $params = []) Create a new instance of a class or service interface, or get a shared instance created earlier, if the instance inherits a class or implements an interface (see {@see Container::getIf()})
 * @method static string getName(string $id) Resolve a class or interface to a concrete class name (see {@see Container::getName()})
 * @method static string[] getServices() Get a list of classes bound to the container by calling service()
 * @method static bool has(string $id) True if a class or service interface resolves to a concrete class that actually exists (see {@see Container::has()})
 * @method static bool hasGlobalContainer() True if a global container has been loaded
 * @method static Container if(bool $condition, callable $then, ?callable $else = null) Move to the next method in the chain after conditionally passing the object to a callback (see {@see FluentInterface::if()})
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of a class or interface have been applied to the container itself
 * @method static Container instance(string $id, $instance) Add an existing instance to the container as a shared binding
 * @method static Container instanceIf(string $id, $instance) Add an existing instance to the container as a shared binding if it hasn't already been bound
 * @method static IContainer|null maybeGetGlobalContainer() Get the global container, returning null if no global container has been loaded
 * @method static IContainer requireGlobalContainer() Get the global container, throwing an exception if no global container has been loaded
 * @method static Container service(string $id, string[]|null $services = null, string[]|null $exceptServices = null, int $lifetime = ServiceLifetime::INHERIT) Add bindings to the container for an IService, optionally specifying services to include or exclude (see {@see Container::service()})
 * @method static Container services(array<string,string> $serviceMap, int $lifetime = ServiceLifetime::INHERIT) Consolidate a service map and call service() once per concrete class (see {@see Container::services()})
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container
 * @method static Container singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container (see {@see Container::singleton()})
 * @method static Container singletonIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container if it hasn't already been bound (see {@see Container::singletonIf()})
 * @method static Container unbind(string $id) Remove a binding from the container
 *
 * @uses Container
 *
 * @extends Facade<Container>
 *
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Container\Container' 'Lkrms\Facade\DI'
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
