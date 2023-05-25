<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

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
     * For example, an executable script called `sync-util` could register
     * `Acme\Canvas\Sync`, an {@see ICliCommand} inheritor, as follows:
     *
     * ```php
     * (new CliApplication(dirname(__DIR__)))
     *     ->command(['sync', 'canvas'], \Acme\Canvas\Sync::class)
     *     ->runAndExit();
     * ```
     *
     * Then, `Acme\Canvas\Sync` could be invoked with:
     *
     * ```shell
     * ./sync-util sync canvas
     * ```
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
     * Process command-line arguments passed to the script
     *
     * The first applicable action is taken:
     *
     * - If `--help` is the only remaining argument after processing subcommand
     *   arguments, a help message is printed and `0` is returned.
     * - If `--version` is the only remaining argument, the application's name
     *   and version number is printed and `0` is returned.
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
