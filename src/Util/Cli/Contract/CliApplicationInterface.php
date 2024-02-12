<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

use Lkrms\Container\ApplicationInterface;
use LogicException;

/**
 * A service container for CLI applications
 *
 * @api
 */
interface CliApplicationInterface extends ApplicationInterface
{
    /**
     * Get the name of the file used to run the application
     */
    public function getProgramName(): string;

    /**
     * Get the command invoked by run()
     *
     * This method should only return a command that is currently running.
     */
    public function getRunningCommand(): ?CliCommandInterface;

    /**
     * Get the command most recently invoked by run()
     *
     * This method should only return a command that ran to completion or failed
     * with an exception.
     */
    public function getLastCommand(): ?CliCommandInterface;

    /**
     * Get the return value most recently recorded by run()
     *
     * This method should return `0` if a return value has not been recorded.
     */
    public function getLastExitStatus(): int;

    /**
     * Register a command with the container
     *
     * @param string[] $name The name of the command as an array of subcommands.
     * Valid subcommands start with a letter, followed by any number of letters,
     * numbers, hyphens and underscores.
     * @param class-string<CliCommandInterface> $id
     * @return $this
     * @throws LogicException if `$name` is invalid or conflicts with a
     * registered command.
     */
    public function command(array $name, string $id);

    /**
     * Register one, and only one, command for the lifetime of the container
     *
     * Calling this method should have the same effect as calling
     * {@see CliApplicationInterface::command()} with an empty command name.
     *
     * @param class-string<CliCommandInterface> $id
     * @return $this
     * @throws LogicException if another command has already been registered.
     */
    public function oneCommand(string $id);

    /**
     * Process command line arguments passed to the script and record a return
     * value
     *
     * This method should take the first applicable action:
     *
     * - If `--help` is the only remaining argument after processing subcommand
     *   arguments, print a help message to `STDOUT`. Return value: `0`
     *
     * - If `--version` is the only remaining argument, print the application's
     *   name and version number to `STDOUT`. Return value: `0`
     *
     * - If subcommand arguments resolve to a registered command, create an
     *   instance of the command and run it. Return value: command exit status
     *
     * - If, after processing subcommand arguments, there are no further
     *   arguments but there are further subcommands, print a one-line synopsis
     *   of each registered subcommand. Return value: `0`
     *
     * - Report an error and print a one-line synopsis of each registered
     *   subcommand. Return value: `1`
     *
     * @return $this
     */
    public function run();

    /**
     * Exit with the return value most recently recorded by run()
     *
     * This method should use exit status `0` if a return value has not been
     * recorded.
     *
     * @return never
     */
    public function exit();

    /**
     * Process command line arguments passed to the script and exit with the
     * recorded return value
     *
     * See {@see CliApplicationInterface::run()} for details.
     *
     * @return never
     */
    public function runAndExit();
}
