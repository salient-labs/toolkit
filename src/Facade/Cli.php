<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Cli\CliCommand;
use Lkrms\Concept\Facade;
use Lkrms\Container\CliAppContainer;
use Lkrms\Container\Container;

/**
 * A facade for CliAppContainer
 *
 * @method static CliAppContainer load(?string $basePath = null)
 * @method static CliAppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static void bindContainer(Container $container)
 * @method static CliAppContainer command(string[] $name, string $id)
 * @method static CliAppContainer enableCache()
 * @method static CliAppContainer enableExistingCache()
 * @method static CliAppContainer enableMessageLog(?string $name = null, array $levels = \Lkrms\Console\ConsoleLevels::ALL_DEBUG)
 * @method static mixed get(string $id, mixed ...$params)
 * @method static array<string,array|string>|string|null|false getCommandTree(string[] $name = [])
 * @method static Container getGlobal()
 * @method static ?CliCommand getNodeCommand(string $name, array<string,array|string>|string|null|false $node)
 * @method static string getProgramName()
 * @method static ?CliCommand getRunningCommand()
 * @method static bool has(string $id)
 * @method static bool hasGlobal()
 * @method static string name(string $id)
 * @method static CliAppContainer pop()
 * @method static CliAppContainer push()
 * @method static int run()
 * @method static never runAndExit()
 * @method static CliAppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 *
 * @uses CliAppContainer
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\CliAppContainer' --generate='Lkrms\Facade\Cli'
 */
final class Cli extends Facade
{
    protected static function getServiceName(): string
    {
        return CliAppContainer::class;
    }
}
