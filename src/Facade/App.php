<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Concept\FluentInterface;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Contract\IContainer;
use Lkrms\Utility\Environment;

/**
 * A facade for \Lkrms\Container\AppContainer
 *
 * @method static AppContainer load(?string $basePath = null) Load and return an instance of the underlying AppContainer class
 * @method static AppContainer getInstance() Get the underlying AppContainer instance
 * @method static bool isLoaded() True if an underlying AppContainer instance has been loaded
 * @method static void unload() Clear the underlying AppContainer instance
 * @method static AppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container (see {@see Container::bind()})
 * @method static AppContainer bindIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container if it hasn't already been bound (see {@see Container::bindIf()})
 * @method static AppContainer call(callable $callback) Move to the next method in the chain after passing the object to a callback (see {@see FluentInterface::call()})
 * @method static Environment env() Get the Environment instance that underpins the Env facade
 * @method static AppContainer forEach(array|object $array, callable $callback) Move to the next method in the chain after passing the object to a callback for each key-value pair in an array (see {@see FluentInterface::forEach()})
 * @method static mixed get(string $id, mixed[] $params = []) Create a new instance of a class or service interface, or get a shared instance created earlier (see {@see Container::get()})
 * @method static string getAppName() Get the basename of the file used to run the script, removing known PHP file extensions and recognised version numbers
 * @method static mixed getAs(string $id, string $serviceId, mixed[] $params = []) Apply an explicit service name while creating a new instance of a class or service interface or getting a shared instance created earlier (see {@see Container::getAs()})
 * @method static string getCachePath() Get a writable cache directory for the application (see {@see AppContainer::getCachePath()})
 * @method static string getConfigPath() Get a writable directory for the application's configuration files
 * @method static string getDataPath() Get a writable data directory for the application (see {@see AppContainer::getDataPath()})
 * @method static IContainer getGlobalContainer() Get the global container, loading it if necessary
 * @method static mixed getIf(string $id, string $serviceId, mixed[] $params = []) Create a new instance of a class or service interface, or get a shared instance created earlier, if the instance inherits a class or implements an interface (see {@see Container::getIf()})
 * @method static string getLogPath() Get a writable directory for the application's log files
 * @method static string getName(string $id) Resolve a class or interface to a concrete class name (see {@see Container::getName()})
 * @method static string getProgramName() Get the basename of the file used to run the script
 * @method static string[] getServices() Get a list of classes bound to the container by calling service()
 * @method static string getTempPath() Get a writable directory for the application's ephemeral data
 * @method static bool has(string $id) True if a class or service interface resolves to a concrete class that actually exists (see {@see Container::has()})
 * @method static bool hasGlobalContainer() True if a global container has been loaded
 * @method static AppContainer if(bool $condition, callable $then, ?callable $else = null) Move to the next method in the chain after conditionally passing the object to a callback (see {@see FluentInterface::if()})
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of a class or interface have been applied to the container itself
 * @method static bool inProduction() Return true if the application is in production, false if it's running from source (see {@see AppContainer::inProduction()})
 * @method static AppContainer instance(string $id, $instance) Add an existing instance to the container as a shared binding
 * @method static AppContainer instanceIf(string $id, $instance) Add an existing instance to the container as a shared binding if it hasn't already been bound
 * @method static AppContainer loadCache() Load the application's CacheStore, creating a backing database if needed (see {@see AppContainer::loadCache()})
 * @method static AppContainer loadCacheIfExists() Load the application's CacheStore if a backing database already exists (see {@see AppContainer::loadCacheIfExists()})
 * @method static AppContainer loadSync(?string $command = null, ?array $arguments = null) Load the application's SyncStore, creating a backing database if needed (see {@see AppContainer::loadSync()})
 * @method static AppContainer logConsoleMessages(?bool $debug = null, ?string $name = null) Log console messages to a file in the application's log directory (see {@see AppContainer::logConsoleMessages()})
 * @method static IContainer|null maybeGetGlobalContainer() Get the global container, returning null if no global container has been loaded
 * @method static AppContainer registerShutdownReport($level = Level::DEBUG, ?array $timers = ['*'], bool $resourceUsage = true) Report timers and resource usage when the application terminates (see {@see AppContainer::registerShutdownReport()})
 * @method static IContainer requireGlobalContainer() Get the global container, throwing an exception if no global container has been loaded
 * @method static AppContainer service(string $id, string[]|null $services = null, string[]|null $exceptServices = null, int $lifetime = ServiceLifetime::INHERIT) Add bindings to the container for an IService, optionally specifying services to include or exclude (see {@see Container::service()})
 * @method static AppContainer services(array $serviceMap, int $lifetime = ServiceLifetime::INHERIT) Consolidate a service map and call service() once per concrete class (see {@see Container::services()})
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container
 * @method static AppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container (see {@see Container::singleton()})
 * @method static AppContainer singletonIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container if it hasn't already been bound (see {@see Container::singletonIf()})
 * @method static AppContainer syncNamespace(string $prefix, string $uri, string $namespace) Register a sync entity namespace with the application's SyncStore (see {@see AppContainer::syncNamespace()})
 * @method static AppContainer unbind(string $id) Remove a binding from the container
 * @method static AppContainer unloadSync(bool $silent = false) Close the application's SyncStore (see {@see AppContainer::unloadSync()})
 * @method static AppContainer writeResourceUsage(int $level = Level::INFO) Print a summary of the script's system resource usage (see {@see AppContainer::writeResourceUsage()})
 * @method static AppContainer writeTimers(bool $includeRunning = true, ?string $type = null, int $level = Level::INFO, ?int $limit = 10) Print a summary of the script's timers (see {@see AppContainer::writeTimers()})
 *
 * @uses AppContainer
 *
 * @extends Facade<AppContainer>
 *
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
