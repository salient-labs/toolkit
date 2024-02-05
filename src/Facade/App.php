<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Concept\FluentInterface;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Container\Application;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Sync\Contract\ISyncClassResolver;

/**
 * A facade for Application
 *
 * @method static Application addContextualBinding(class-string $class, class-string|string $dependency, (callable($this): mixed)|class-string|mixed $value) Register a contextual binding with the container (see {@see Container::addContextualBinding()})
 * @method static Application apply(callable($this): $this $callback) Move to the next method in the chain after applying a callback to the object
 * @method static Application bind(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a binding with the container (see {@see Container::bind()})
 * @method static Application bindIf(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a binding with the container if it isn't already registered
 * @method static object get(class-string $id, mixed[] $args = []) Resolve a service from the container (see {@see Container::get()})
 * @method static string getAppName() Get the name of the application
 * @method static object getAs(class-string $id, class-string $service, mixed[] $args = []) Resolve a partially-resolved service from the container (see {@see Container::getAs()})
 * @method static string getBasePath() Get the application's root directory
 * @method static string getCachePath(bool $create = true) Get a writable cache directory for the application (see {@see Application::getCachePath()})
 * @method static string getConfigPath(bool $create = true) Get a writable directory for the application's configuration files
 * @method static string getDataPath(bool $create = true) Get a writable data directory for the application (see {@see Application::getDataPath()})
 * @method static ContainerInterface getGlobalContainer() Get the global container, creating it if necessary
 * @method static string getLogPath(bool $create = true) Get a writable directory for the application's log files
 * @method static class-string getName(class-string $id) Resolve a service from the container to a concrete class name
 * @method static string getProgramName() Get the name of the file used to run the application
 * @method static array<class-string> getProviders() Get a list of service providers registered with the container
 * @method static string getTempPath(bool $create = true) Get a writable directory for the application's ephemeral data
 * @method static bool has(class-string $id) True if a service has been bound to the container (see {@see Container::has()})
 * @method static bool hasGlobalContainer() True if the global container is set
 * @method static bool hasInstance(class-string $id) True if a service resolves to a shared instance
 * @method static Application if((callable($this): bool)|bool $condition, (callable($this): $this)|null $then = null, (callable($this): $this)|null $else = null) Move to the next method in the chain after applying a conditional callback to the object (see {@see FluentInterface::if()})
 * @method static Application inContextOf(class-string $id) Apply the contextual bindings of a service to a copy of the container
 * @method static Application instance(class-string $id, object $instance) Register an object with the container as a shared binding
 * @method static Application instanceIf(class-string $id, object $instance) Register an object with the container as a shared binding if it isn't already registered
 * @method static bool isProduction() Check if the application is running in a production environment (see {@see Application::isProduction()})
 * @method static Application logOutput(string|null $name = null, ?bool $debug = null) Log console output to the application's log directory (see {@see Application::logOutput()})
 * @method static ContainerInterface|null maybeGetGlobalContainer() Get the global container if set
 * @method static Application provider(class-string $id, class-string[]|null $services = null, class-string[] $exceptServices = [], int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register a service provider with the container, optionally specifying which of its services to bind or ignore (see {@see Container::provider()})
 * @method static Application providers(array<class-string,class-string> $serviceMap, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register a service map with the container (see {@see Container::providers()})
 * @method static Application registerShutdownReport(Level::* $level = Level::INFO, string[]|string|null $groups = null, bool $resourceUsage = true) Print a summary of the application's runtime performance metrics and system resource usage when it terminates (see {@see Application::registerShutdownReport()})
 * @method static Application reportMetrics(Level::* $level = Level::INFO, bool $includeRunning = true, string[]|string|null $groups = null, ?int $limit = 10) Print a summary of the application's runtime performance metrics (see {@see Application::reportMetrics()})
 * @method static Application reportResourceUsage(Level::* $level = Level::INFO) Print a summary of the application's system resource usage
 * @method static ContainerInterface requireGlobalContainer() Get the global container if set, otherwise throw an exception (see {@see Container::requireGlobalContainer()})
 * @method static Application restoreWorkingDirectory() Change to the application's working directory (see {@see Application::restoreWorkingDirectory()})
 * @method static Application resumeCache() Start a cache store in the application's cache directory if a backing database was created on a previous run (see {@see Application::resumeCache()})
 * @method static void setGlobalContainer(?ContainerInterface $container) Set or unset the global container
 * @method static Application setWorkingDirectory(string|null $directory = null) Set the application's working directory (see {@see Application::setWorkingDirectory()})
 * @method static Application singleton(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a shared binding with the container (see {@see Container::singleton()})
 * @method static Application singletonIf(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a shared binding with the container if it isn't already registered
 * @method static Application startCache() Start a cache store in the application's cache directory (see {@see Application::startCache()})
 * @method static Application startSync(?string $command = null, mixed[] $arguments = null) Start an entity store in the application's data directory (see {@see Application::startSync()})
 * @method static Application stopCache() Stop a previously started cache store
 * @method static Application stopSync() Stop a previously started entity store (see {@see Application::stopSync()})
 * @method static Application syncNamespace(string $prefix, string $uri, string $namespace, class-string<ISyncClassResolver>|null $resolver = null) Register a sync entity namespace with a previously started entity store (see {@see Application::syncNamespace()})
 * @method static Application unbind(class-string $id) Remove a binding from the container
 *
 * @extends Facade<Application>
 *
 * @generated
 */
final class App extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getService()
    {
        return Application::class;
    }
}
