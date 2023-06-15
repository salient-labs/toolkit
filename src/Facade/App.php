<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Concept\FluentInterface;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Container\Application;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IService;
use Lkrms\Sync\Contract\ISyncClassResolver;
use Lkrms\Utility\Environment;

/**
 * A facade for \Lkrms\Container\Application
 *
 * @method static Application load(?string $basePath = null) Load and return an instance of the underlying Application class
 * @method static Application getInstance() Get the underlying Application instance
 * @method static bool isLoaded() True if an underlying Application instance has been loaded
 * @method static void unload() Clear the underlying Application instance
 * @method static Application bind(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a binding with the container (see {@see Container::bind()})
 * @method static Application bindIf(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a binding with the container if it isn't already registered
 * @method static Application call(callable $callback) Move to the next method in the chain after passing the object to a callback (see {@see FluentInterface::call()})
 * @method static Environment env() Get the Env facade's underlying Environment instance
 * @method static Application forEach(array|object $array, callable $callback) Move to the next method in the chain after passing the object to a callback for each key-value pair in an array (see {@see FluentInterface::forEach()})
 * @method static mixed get(class-string $id, mixed[] $params = []) Get an instance from the container (see {@see Container::get()})
 * @method static string getAppName() Get the basename of the file used to run the script, removing known PHP file extensions and recognised version numbers
 * @method static mixed getAs(class-string $id, class-string $serviceId, mixed[] $params = []) Use one identifier to get an instance from the container and another as its service name (see {@see Container::getAs()})
 * @method static string getBasePath() Get the application's root directory
 * @method static string getCachePath() Get a writable cache directory for the application (see {@see Application::getCachePath()})
 * @method static string getConfigPath() Get a writable directory for the application's configuration files
 * @method static string getDataPath() Get a writable data directory for the application (see {@see Application::getDataPath()})
 * @method static IContainer getGlobalContainer() Get the global container
 * @method static string getLogPath() Get a writable directory for the application's log files
 * @method static class-string getName(class-string $id) Get a concrete class name from the container (see {@see Container::getName()})
 * @method static string getProgramName() Get the basename of the file used to run the script
 * @method static array<class-string<IService>> getServices() Get a list of classes bound to the container by calling service()
 * @method static string getTempPath() Get a writable directory for the application's ephemeral data
 * @method static bool has(class-string $id) True if the container can resolve an identifier to an instance (see {@see Container::has()})
 * @method static bool hasGlobalContainer() True if the global container is set
 * @method static Application if(bool $condition, callable $then, callable|null $else = null) Move to the next method in the chain after conditionally passing the object to a callback (see {@see FluentInterface::if()})
 * @method static Container inContextOf(class-string $id) Apply the contextual bindings of a service to a copy of the container
 * @method static bool inProduction() True if the application is in production, false if it's running from source (see {@see Application::inProduction()})
 * @method static Application instance(class-string $id, mixed $instance) Register an existing instance with the container as a shared binding
 * @method static Application instanceIf(class-string $id, mixed $instance) Register an existing instance with the container as a shared binding if it isn't already registered
 * @method static Application loadCache() Load the application's CacheStore, creating a backing database if needed (see {@see Application::loadCache()})
 * @method static Application loadCacheIfExists() Load the application's CacheStore if a backing database already exists (see {@see Application::loadCacheIfExists()})
 * @method static Application loadSync(?string $command = null, mixed[] $arguments = null) Load the application's SyncStore, creating a backing database if needed (see {@see Application::loadSync()})
 * @method static Application logConsoleMessages(?bool $debug = null, string|null $name = null) Log console messages to a file in the application's log directory (see {@see Application::logConsoleMessages()})
 * @method static IContainer|null maybeGetGlobalContainer() Get the global container if set
 * @method static Application registerShutdownReport(int $level = Level::INFO, string[]|null $timers = ['*'], bool $resourceUsage = true) Print a summary of the script's timers and system resource usage when the application terminates (see {@see Application::registerShutdownReport()})
 * @method static IContainer requireGlobalContainer() Get the global container if set, otherwise throw an exception
 * @method static Application service(class-string<IService> $id, class-string[]|null $services = null, class-string[]|null $exceptServices = null, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register an IService with the container, optionally specifying services to include or exclude (see {@see Container::service()})
 * @method static Application services(array<class-string,class-string<IService>> $serviceMap, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register a service map with the container (see {@see Container::services()})
 * @method static IContainer|null setGlobalContainer(IContainer|null $container) Set the global container
 * @method static Application singleton(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a shared binding with the container (see {@see Container::singleton()})
 * @method static Application singletonIf(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a shared binding with the container if it isn't already registered
 * @method static Application syncNamespace(string $prefix, string $uri, string $namespace, class-string<ISyncClassResolver>|null $resolver = null) Register a sync entity namespace with the application's SyncStore (see {@see Application::syncNamespace()})
 * @method static Application unbind(class-string $id) Remove a binding from the container
 * @method static Application unloadSync(bool $silent = false) Close the application's SyncStore (see {@see Application::unloadSync()})
 * @method static Application writeResourceUsage(int $level = Level::INFO) Print a summary of the script's system resource usage (see {@see Application::writeResourceUsage()})
 * @method static Application writeTimers(int $level = Level::INFO, bool $includeRunning = true, ?string $type = null, ?int $limit = 10) Print a summary of the script's timers (see {@see Application::writeTimers()})
 *
 * @uses Application
 *
 * @extends Facade<Application>
 */
final class App extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Application::class;
    }
}
