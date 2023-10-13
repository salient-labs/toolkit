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
use Lkrms\Utility\Catalog\EnvFlag;
use Lkrms\Utility\Env;

/**
 * A facade for \Lkrms\Container\Application
 *
 * @method static Application load(string|null $basePath = null, string|null $appName = null, int-mask-of<EnvFlag::*> $envFlags = EnvFlag::ALL) Load and return an instance of the underlying Application class
 * @method static Application getInstance() Get the underlying Application instance
 * @method static bool isLoaded() True if an underlying Application instance has been loaded
 * @method static void unload() Clear the underlying Application instance
 * @method static Application apply(callable($this): $this $callback) Move to the next method in the chain after applying a callback to the object
 * @method static Application bind(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a binding with the container (see {@see Container::bind()})
 * @method static Application bindIf(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a binding with the container if it isn't already registered
 * @method static Env env() Get a shared instance of Lkrms\Utility\Env
 * @method static mixed get(class-string $id, mixed[] $args = []) Get an object from the container (see {@see Container::get()})
 * @method static string getAppName() Get the name of the application
 * @method static mixed getAs(class-string $id, class-string $service, mixed[] $args = []) Get an object from the container with a given service name (see {@see Container::getAs()})
 * @method static string getBasePath() Get the application's root directory
 * @method static string getCachePath(bool $create = true) Get a writable cache directory for the application (see {@see Application::getCachePath()})
 * @method static string getConfigPath(bool $create = true) Get a writable directory for the application's configuration files
 * @method static class-string[] getContextStack() Get services for which contextual bindings have been applied to the container (see {@see Container::getContextStack()})
 * @method static string getDataPath(bool $create = true) Get a writable data directory for the application (see {@see Application::getDataPath()})
 * @method static IContainer getGlobalContainer() Get the global container, creating it if necessary
 * @method static string getLogPath(bool $create = true) Get a writable directory for the application's log files
 * @method static class-string getName(class-string $id) Resolve a service to a concrete class name (see {@see Container::getName()})
 * @method static string getProgramName() Get the basename of the file used to run the script
 * @method static array<class-string<IService>> getServices() Get a list of classes bound to the container by calling service()
 * @method static string getTempPath(bool $create = true) Get a writable directory for the application's ephemeral data
 * @method static bool has(class-string $id) True if the container can resolve an identifier to an instance (see {@see Container::has()})
 * @method static bool hasGlobalContainer() True if the global container exists
 * @method static Application if((callable($this): bool)|bool $condition, (callable($this): $this)|null $then = null, (callable($this): $this)|null $else = null) Move to the next method in the chain after applying a conditional callback to the object (see {@see FluentInterface::if()})
 * @method static Application inContextOf(class-string $id) Apply the contextual bindings of a service to a copy of the container
 * @method static Application instance(class-string $id, mixed $instance) Register an existing instance with the container as a shared binding
 * @method static Application instanceIf(class-string $id, mixed $instance) Register an existing instance with the container as a shared binding if it isn't already registered
 * @method static bool isProduction() Check if the application is running in a production environment (see {@see Application::isProduction()})
 * @method static Application logOutput(string|null $name = null, ?bool $debug = null) Log console output to the application's log directory (see {@see Application::logOutput()})
 * @method static IContainer|null maybeGetGlobalContainer() Get the global container if it exists
 * @method static Application registerShutdownReport(Level::* $level = Level::INFO, string[]|string|null $timerTypes = null, bool $resourceUsage = true) Print a summary of the application's timers and system resource usage when it terminates (see {@see Application::registerShutdownReport()})
 * @method static Application reportResourceUsage(Level::* $level = Level::INFO) Print a summary of the application's system resource usage
 * @method static Application reportTimers(Level::* $level = Level::INFO, bool $includeRunning = true, string[]|string|null $types = null, ?int $limit = 10) Print a summary of the application's timers (see {@see Application::reportTimers()})
 * @method static IContainer requireGlobalContainer() Get the global container if it exists, otherwise throw an exception (see {@see Container::requireGlobalContainer()})
 * @method static Application resumeCache() Start a cache store in the application's cache directory if a backing database was created on a previous run (see {@see Application::resumeCache()})
 * @method static Application service(class-string<IService> $id, class-string[]|null $services = null, class-string[]|null $exceptServices = null, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register an IService with the container, optionally specifying services to include or exclude (see {@see Container::service()})
 * @method static Application services(array<class-string|int,class-string<IService>> $serviceMap, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register a service map with the container (see {@see Container::services()})
 * @method static IContainer|null setGlobalContainer(IContainer|null $container) Set the global container
 * @method static Application singleton(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a shared binding with the container (see {@see Container::singleton()})
 * @method static Application singletonIf(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a shared binding with the container if it isn't already registered
 * @method static Application startCache() Start a cache store in the application's cache directory (see {@see Application::startCache()})
 * @method static Application startSync(?string $command = null, mixed[] $arguments = null) Start an entity store in the application's data directory (see {@see Application::startSync()})
 * @method static Application stopCache() Stop a previously started cache store
 * @method static Application stopSync() Stop a previously started entity store (see {@see Application::stopSync()})
 * @method static Application syncNamespace(string $prefix, string $uri, string $namespace, class-string<ISyncClassResolver>|null $resolver = null) Register a sync entity namespace with a previously started entity store (see {@see Application::syncNamespace()})
 * @method static Application unbind(class-string $id) Remove a binding from the container
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
