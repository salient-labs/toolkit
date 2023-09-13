<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Catalog\CliHelpType;
use Lkrms\Contract\IApplication;
use LogicException;

/**
 * A service container for CLI applications
 *
 */
interface ICliApplication extends IApplication
{
    /**
     * Get the command started from the command line
     *
     */
    public function getRunningCommand(): ?ICliCommand;

    /**
     * Register a command with the container
     *
     * @param string[] $name The name of the command as an array of subcommands.
     *
     * Valid subcommands start with a letter, followed by any number of letters,
     * numbers, hyphens and underscores.
     * @param class-string<ICliCommand> $id The name of the class to register.
     * @return $this
     * @throws LogicException if `$name` is invalid or conflicts with a
     * registered command.
     */
    public function command(array $name, string $id);

    /**
     * Register one, and only one, ICliCommand for the lifetime of the container
     *
     * The command is registered with an empty name, placing it at the root of
     * the container's subcommand tree.
     *
     * @param class-string<ICliCommand> $id The name of the class to register.
     * @return $this
     * @throws LogicException if another command has already been registered.
     *
     * @see ICliApplication::command()
     */
    public function oneCommand(string $id);

    /**
     * Get the help message type requested from the command line
     *
     * @return CliHelpType::*
     */
    public function getHelpType(): int;

    /**
     * Get the number of columns available for help messages / usage information
     * after adjusting for margins
     *
     * If the command is running in a terminal and `$margins` is the total width
     * of left and right margins applied by {@see ICliApplication::buildHelp()},
     * the return value might be:
     *
     * ```php
     * <?php
     * max(76, \Lkrms\Facade\Console::getWidth()) - $margins
     * ```
     *
     * @return int<72,max>|null
     */
    public function getHelpWidth(bool $terse = false): ?int;

    /**
     * Create a help message from an array that maps section names to content
     *
     * @param array<CliHelpSectionName::*|string,string> $sections
     */
    public function buildHelp(array $sections): string;

    /**
     * Process command-line arguments passed to the script
     *
     * The first applicable action is taken:
     *
     * - If `--help` is the only remaining argument after processing subcommand
     *   arguments, a help message is printed to `STDOUT` and `0` is returned.
     * - If `--version` is the only remaining argument, the application's name
     *   and version number is printed to `STDOUT` and `0` is returned.
     * - If subcommand arguments resolve to a registered command, it is invoked
     *   and its exit status is returned.
     * - If, after processing subcommand arguments, there are no further
     *   arguments but there are further subcommands, a one-line synopsis of
     *   each registered subcommand is printed and `0` is returned.
     *
     * Otherwise, an error is reported, a one-line synopsis of each registered
     * subcommand is printed, and `1` is returned.
     *
     */
    public function run(): int;

    /**
     * Exit after processing command-line arguments passed to the script
     *
     * The value returned by {@see ICliApplication::run()} is used as the exit
     * status.
     *
     * @return never
     */
    public function runAndExit();
}
