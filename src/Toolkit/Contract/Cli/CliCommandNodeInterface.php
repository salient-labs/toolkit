<?php declare(strict_types=1);

namespace Salient\Contract\Cli;

use Salient\Cli\Catalog\CliHelpSectionName;
use Salient\Cli\CliCommand;
use Salient\Cli\CliHelpStyle;
use Salient\Contract\Container\HasContainer;
use Salient\Contract\Core\Describable;
use Salient\Contract\Core\Nameable;
use LogicException;

/**
 * A node in a CLI command tree
 *
 * @api
 *
 * @extends HasContainer<CliApplicationInterface>
 *
 * @see CliCommand
 */
interface CliCommandNodeInterface extends HasContainer, Nameable, Describable
{
    /**
     * Get the command name as a string of space-delimited subcommands
     *
     * Returns an empty string if {@see CliCommandNodeInterface::setName()} has
     * not been called, or if an empty array of subcommands was passed to
     * {@see CliCommandNodeInterface::setName()}.
     */
    public function name(): string;

    /**
     * Get the command name as an array of subcommands
     *
     * @return string[]
     */
    public function nameParts(): array;

    /**
     * Get a one-line description of the command
     */
    public function description(): string;

    /**
     * Called immediately after instantiation by a CliApplicationInterface
     *
     * @param string[] $name
     * @throws LogicException if called more than once per instance.
     */
    public function setName(array $name): void;

    /**
     * Get a one-line summary of the command's options
     *
     * Returns a space-delimited string that includes the name of the command,
     * and the name used to run the script.
     */
    public function getSynopsis(?CliHelpStyle $style = null): string;

    /**
     * Get a detailed explanation of the command
     *
     * @return array<CliHelpSectionName::*|string,string> An array that maps
     * help section names to content.
     */
    public function getHelp(?CliHelpStyle $style = null): array;
}
