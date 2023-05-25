<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

use Lkrms\Cli\CliCommand;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Contract\ReturnsDescription;
use Lkrms\Contract\ReturnsEnvironment;
use LogicException;

/**
 * A node in a CLI command tree
 *
 * @extends ReturnsContainer<ICliApplication>
 *
 * @see CliCommand
 */
interface ICliCommandNode extends ReturnsContainer, ReturnsEnvironment, ReturnsDescription
{
    /**
     * Get the command name as a string of space-delimited subcommands
     *
     */
    public function name(): string;

    /**
     * Get a one-line description of the command
     *
     */
    public function description(): string;

    /**
     * Called immediately after instantiation by an ICliApplication
     *
     * @param string[] $name
     * @throws LogicException if called more than once per instance.
     */
    public function setName(array $name): void;

    /**
     * Get a one-line summary of the command's syntax
     *
     * Returns a usage message that includes the command's name, and the name
     * used to run the script.
     */
    public function getSynopsis(bool $withMarkup = true): string;

    /**
     * Get a detailed explanation of the command
     *
     */
    public function getHelp(bool $withMarkup = true, ?int $width = 80): string;
}
