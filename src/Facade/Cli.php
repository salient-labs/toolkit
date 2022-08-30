<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Cli\CliCommand;
use Lkrms\Concept\Facade;
use Lkrms\Container\CliAppContainer;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

/**
 * A facade for CliAppContainer
 *
 * @method static CliAppContainer load(?string $basePath = null) Create and return the underlying CliAppContainer
 * @method static CliAppContainer getInstance() Return the underlying CliAppContainer
 * @method static bool isLoaded() Return true if the underlying CliAppContainer has been created
 * @method static CliAppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static CliAppContainer command(string[] $name, string $id) Register a CliCommand with the container
 * @method static CliAppContainer enableCache()
 * @method static CliAppContainer enableExistingCache()
 * @method static CliAppContainer enableMessageLog(?string $name = null, array $levels = \Lkrms\Console\ConsoleLevels::ALL_DEBUG)
 * @method static mixed get(string $id, mixed ...$params)
 * @method static IContainer getGlobalContainer()
 * @method static string getName(string $id)
 * @method static string getProgramName() Return the name used to run the script
 * @method static CliCommand|null getRunningCommand() Return the CliCommand started from the command line
 * @method static bool has(string $id)
 * @method static bool hasGlobalContainer()
 * @method static Container inContextOf(string $id)
 * @method static int run() Process command-line arguments and take appropriate action
 * @method static never runAndExit() Exit after actioning command-line arguments
 * @method static CliAppContainer service(string $id, null|string[] $services = null, null|string[] $exceptServices = null)
 * @method static IContainer|null setGlobalContainer(?IContainer $container)
 * @method static CliAppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
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
