<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Contract\IContainer;

/**
 * A facade for \Lkrms\Container\AppContainer
 *
 * @method static AppContainer load(?string $basePath = null) Load and return an instance of the underlying AppContainer class
 * @method static AppContainer getInstance() Return the underlying AppContainer instance
 * @method static bool isLoaded() Return true if an underlying AppContainer instance has been loaded
 * @method static void unload() Clear the underlying AppContainer instance
 * @method static AppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container (see {@see Container::bind()})
 * @method static AppContainer bindIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container if it hasn't already been bound (see {@see Container::bindIf()})
 * @method static AppContainer forEach(array|object $array, callable $callback) Move to the next method in the chain after iterating over an array and passing the object to a callback with each key-value pair in the array (see {@see FluentInterface::forEach()})
 * @method static mixed get(string $id, mixed ...$params) Create a new instance of a class or interface, or return a shared instance created earlier (see {@see Container::get()})
 * @method static mixed getAs(string $id, string $serviceId, mixed ...$params) Create a new instance of a class or interface with an explicit service name, or apply a service name to a shared instance created earlier (see {@see Container::getAs()})
 * @method static IContainer getGlobalContainer() Get the global container, loading it if necessary (see {@see Container::getGlobalContainer()})
 * @method static string getName(string $id) Resolve a class or interface to a concrete class name (see {@see Container::getName()})
 * @method static string[] getServices() Get a list of classes bound to the container by calling service() (see {@see Container::getServices()})
 * @method static bool has(string $id) Return true if a class or interface resolves to a concrete class that actually exists (see {@see Container::has()})
 * @method static bool hasGlobalContainer() Return true if a global container has been loaded (see {@see Container::hasGlobalContainer()})
 * @method static AppContainer if(bool $condition, callable $callback) Move to the next method in the chain after conditionally passing the object to a callback (see {@see FluentInterface::if()})
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of a class or interface have been applied to the default context (see {@see Container::inContextOf()})
 * @method static AppContainer instance(string $id, mixed $instance) Add an existing instance to the container as a shared binding (see {@see Container::instance()})
 * @method static AppContainer instanceIf(string $id, mixed $instance) Add an existing instance to the container as a shared binding if it hasn't already been bound (see {@see Container::instanceIf()})
 * @method static AppContainer loadCache() See {@see AppContainer::loadCache()}
 * @method static AppContainer loadCacheIfExists() See {@see AppContainer::loadCacheIfExists()}
 * @method static AppContainer loadSync(?string $command = null, ?array $arguments = null) See {@see AppContainer::loadSync()}
 * @method static AppContainer logConsoleMessages(?bool $debug = true, ?string $name = null) Log console messages to a file in the application's log directory (see {@see AppContainer::logConsoleMessages()})
 * @method static IContainer|null maybeGetGlobalContainer() Similar to getGlobalContainer(), but return null if no global container has been loaded (see {@see Container::maybeGetGlobalContainer()})
 * @method static IContainer requireGlobalContainer() Similar to getGlobalContainer(), but throw an exception if no global container has been loaded (see {@see Container::requireGlobalContainer()})
 * @method static AppContainer service(string $id, string[]|null $services = null, string[]|null $exceptServices = null, int $lifetime = ServiceLifetime::INHERIT) Add bindings to the container for an IService implementation and its services, optionally specifying services to bind or exclude (see {@see Container::service()})
 * @method static AppContainer services(array $serviceMap, int $lifetime = ServiceLifetime::INHERIT) Consolidate a service map and call service() once per concrete class (see {@see Container::services()})
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container (see {@see Container::setGlobalContainer()})
 * @method static AppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container (see {@see Container::singleton()})
 * @method static AppContainer singletonIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container if it hasn't already been bound (see {@see Container::singletonIf()})
 * @method static AppContainer syncNamespace(string $prefix, string $uri, string $namespace) See {@see AppContainer::syncNamespace()}
 * @method static AppContainer unloadSync(bool $silent = false) See {@see AppContainer::unloadSync()}
 * @method static AppContainer writeResourceUsage() See {@see AppContainer::writeResourceUsage()}
 *
 * @uses AppContainer
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Container\AppContainer' 'Lkrms\Facade\App'
 */
final class App extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return AppContainer::class;
    }
}
