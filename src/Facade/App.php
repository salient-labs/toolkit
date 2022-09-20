<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Console\ConsoleLevels;
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
 * @method static mixed call(callable $callback) Make this the global container while running the given callback (see {@see Container::call()})
 * @method static AppContainer enableCache() See {@see AppContainer::enableCache()}
 * @method static AppContainer enableExistingCache() See {@see AppContainer::enableExistingCache()}
 * @method static AppContainer enableMessageLog(?string $name = null, array $levels = ConsoleLevels::ALL_DEBUG) See {@see AppContainer::enableMessageLog()}
 * @method static mixed get(string $id, mixed ...$params) Create a new instance of the given class or interface, or return a shared instance created earlier (see {@see Container::get()})
 * @method static IContainer getGlobalContainer() Get the current global container, loading it if necessary (see {@see Container::getGlobalContainer()})
 * @method static string getName(string $id) Resolve the given class or interface to a concrete class name (see {@see Container::getName()})
 * @method static bool has(string $id) Return true if the given class or interface resolves to a concrete class that actually exists (see {@see Container::has()})
 * @method static bool hasGlobalContainer() Return true if a global container has been loaded (see {@see Container::hasGlobalContainer()})
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of the given class or interface have been applied to the default context (see {@see Container::inContextOf()})
 * @method static AppContainer instance(string $id, mixed $instance) Add an existing instance to the container as a shared binding (see {@see Container::instance()})
 * @method static IContainer|null maybeGetGlobalContainer() Similar to getGlobalContainer(), but return null if no global container has been loaded (see {@see Container::maybeGetGlobalContainer()})
 * @method static AppContainer service(string $id, string[]|null $services = null, string[]|null $exceptServices = null, ?array $constructParams = null, ?array $shareInstances = null) Add bindings to the container for an IBindable implementation and its services, optionally specifying services to bind or exclude (see {@see Container::service()})
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container (see {@see Container::setGlobalContainer()})
 * @method static AppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container (see {@see Container::singleton()})
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
