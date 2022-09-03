<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Cli\CliCommand;
use Lkrms\Concept\Facade;
use Lkrms\Console\ConsoleLevels;
use Lkrms\Container\CliAppContainer;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

/**
 * A facade for CliAppContainer
 *
 * @method static CliAppContainer load(?string $basePath = null) Create and return the underlying CliAppContainer
 * @method static CliAppContainer getInstance() Return the underlying CliAppContainer
 * @method static bool isLoaded() Return true if the underlying CliAppContainer has been created
 * @method static CliAppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a binding to the container
 * @method static mixed call(callable $callback) Make this the global container while running the given callback
 * @method static CliAppContainer command(string[] $name, string $id) Register a CliCommand with the container
 * @method static CliAppContainer enableCache()
 * @method static CliAppContainer enableExistingCache()
 * @method static CliAppContainer enableMessageLog(?string $name = null, array $levels = ConsoleLevels::ALL_DEBUG)
 * @method static mixed get(string $id, mixed ...$params) Finds an entry of the container by its identifier and returns it.
 * @method static IContainer getGlobalContainer() Get the current global container, loading it if necessary
 * @method static string getName(string $id) Resolve the given class or interface to a concrete class
 * @method static string getProgramName() Return the name used to run the script
 * @method static CliCommand|null getRunningCommand() Return the CliCommand started from the command line
 * @method static bool has(string $id) Returns true if the container can return an entry for the given identifier. Returns false otherwise.
 * @method static bool hasGlobalContainer() Return true if a global container has been loaded
 * @method static Container inContextOf(string $id) Get a copy of the container where the contextual bindings of the given class or interface are applied
 * @method static CliAppContainer instance(string $id, mixed $instance) Register an existing instance as a shared binding
 * @method static int run() Process command-line arguments and take appropriate action
 * @method static never runAndExit() Exit after actioning command-line arguments
 * @method static CliAppContainer service(string $id, null|string[] $services = null, null|string[] $exceptServices = null, ?array $constructParams = null, ?array $shareInstances = null) Add bindings to the container for an IBindable implementation and its services, optionally specifying services to bind or exclude
 * @method static IContainer|null setGlobalContainer(?IContainer $container) Set (or unset) the global container
 * @method static CliAppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null) Add a shared binding to the container
 *
 * @uses CliAppContainer
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\CliAppContainer' --generate='Lkrms\Facade\Cli'
 */
final class Cli extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return CliAppContainer::class;
    }
}
