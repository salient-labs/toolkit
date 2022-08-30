<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Cli\CliCommand;
use Lkrms\Concept\Facade;
use Lkrms\Container\CliAppContainer;
use Lkrms\Container\Container;
use Lkrms\Container\ContextContainer;

/**
 * A facade for CliAppContainer
 *
 * @method static CliAppContainer load(?string $basePath = null) Create and return the underlying CliAppContainer
 * @method static CliAppContainer getInstance() Return the underlying CliAppContainer
 * @method static bool isLoaded() Return true if the underlying CliAppContainer has been created
 * @method static CliAppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = []) Bind a class to the given identifier
 * @method static CliAppContainer command(string[] $name, string $id) Register a CliCommand with the container
 * @method static ContextContainer context(string $id) Get a context-specific facade for the container
 * @method static CliAppContainer enableCache()
 * @method static CliAppContainer enableExistingCache()
 * @method static CliAppContainer enableMessageLog(?string $name = null, array $levels = \Lkrms\Console\ConsoleLevels::ALL_DEBUG)
 * @method static mixed get(string $id, mixed ...$params) Create a new instance of the given class or interface, or retrieve a singleton created earlier
 * @method static Container getGlobalContainer() Get the current global container, creating one if necessary
 * @method static string getProgramName() Return the name used to run the script
 * @method static CliCommand|null getRunningCommand() Return the CliCommand started from the command line
 * @method static bool has(string $id) Returns true if the given identifier can be resolved to a concrete class
 * @method static bool hasGlobalContainer() Returns true if a global container exists
 * @method static string name(string $id) Get a concrete class name for the given identifier
 * @method static CliAppContainer pop() Pop the most recently pushed container off the stack and activate it
 * @method static CliAppContainer push() Push a copy of the container onto the stack
 * @method static int run() Process command-line arguments and take appropriate action
 * @method static never runAndExit() Exit after actioning command-line arguments
 * @method static CliAppContainer service(string $id, string[] $services = null, string[] $exceptServices = null) Bind an IBindable and its services, optionally specifying the services to bind or exclude
 * @method static CliAppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = []) Bind a class to the given identifier as a shared instance
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
