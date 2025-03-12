<?php declare(strict_types=1);

namespace Salient\Contract\Cli;

use Salient\Contract\Cli\CliHelpStyleInterface as CliHelpStyle;
use Salient\Contract\Container\HasContainer;
use Salient\Contract\Core\HasDescription;
use Salient\Contract\Core\HasName;
use LogicException;

/**
 * A node in a CLI command tree
 */
interface CliCommandNodeInterface extends HasContainer, HasName, HasDescription
{
    /**
     * Get the command's service container
     */
    public function getContainer(): CliApplicationInterface;

    /**
     * Get the command name as a string of space-delimited subcommands
     *
     * Returns an empty string if {@see CliCommandNodeInterface::setName()} has
     * not been called, or if an empty array of subcommands was passed to
     * {@see CliCommandNodeInterface::setName()}.
     */
    public function getName(): string;

    /**
     * Get the command name as an array of subcommands
     *
     * @return string[]
     */
    public function getNameParts(): array;

    /**
     * Get a one-line description of the command
     */
    public function getDescription(): string;

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
