<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;
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
 * @method static mixed call(callable $callback) Make this the global container while running the given callback (see {@see Container::call()})
 * @method static IContainer|null coalesce(?IContainer $container, bool $returnNull = true, bool $load = false) Return the first available container (see {@see Container::coalesce()})
 * @method static mixed get(string $id, mixed ...$params) Create a new instance of the given class or interface, or return a shared instance created earlier (see {@see Container::get()})
 * @method static mixed getAs(string $id, string $serviceId, mixed ...$params) Similar to get(), but override the service name passed to `ReceivesService::setService()` (see {@see Container::getAs()})
 * @method static IContainer getGlobalContainer() Get the current global container, loading it if necessary (see {@see Container::getGlobalContainer()})
 * @method static string getName(string $id) Resolve the given class or interface to a concrete class name (see {@see Container::getName()})
 * @method static bool has(string $id) Return true if the given class or interface resolves to a concrete class that actually exists (see {@see Container::has()})
 * @method static bool hasGlobalContainer() Return true if a global container has been loaded (see {@see Container::hasGlobalContainer()})
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of the given class or interface have been applied to the default context (see {@see Container::inContextOf()})
 * @method static AppContainer instance(string $id, mixed $instance) Add an existing instance to the container as a shared binding (see {@see Container::instance()})
 * @method static AppContainer instanceIf(string $id, mixed $instance) Add an existing instance to the container as a shared binding if it hasn't already been bound (see {@see Container::instanceIf()})
 * @method static AppContainer loadCache() See {@see AppContainer::loadCache()}
 * @method static AppContainer loadCacheIfExists() See {@see AppContainer::loadCacheIfExists()}
 * @method static AppContainer loadSync(?string $command = null, ?array $arguments = null) See {@see AppContainer::loadSync()}
 * @method static AppContainer logConsoleMessages(?bool $debug = true, ?string $name = null) Log console messages to a file in the application's log directory (see {@see AppContainer::logConsoleMessages()})
 * @method static IContainer|null maybeGetGlobalContainer() Similar to getGlobalContainer(), but return null if no global container has been loaded (see {@see Container::maybeGetGlobalContainer()})
 * @method static IContainer requireGlobalContainer() Similar to getGlobalContainer(), but throw an exception if no global container has been loaded (see {@see Container::requireGlobalContainer()})
 * @method static AppContainer service(string $id, string[]|null $services = null, string[]|null $exceptServices = null, ?array $constructParams = null, ?array $shareInstances = null) Add bindings to the container for an IBindable implementation and its services, optionally specifying services to bind or exclude (see {@see Container::service()})
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container (see {@see Container::setGlobalContainer()})
 * @method static AppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container (see {@see Container::singleton()})
 * @method static AppContainer singletonIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container if it hasn't already been bound (see {@see Container::singletonIf()})
 * @method static AppContainer unloadSync(bool $silent = false) See {@see AppContainer::unloadSync()}
 * @method static AppContainer writeResourceUsage() See {@see AppContainer::writeResourceUsage()}
 *
 * @uses AppContainer
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\AppContainer' --generate='Lkrms\Facade\App'
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
