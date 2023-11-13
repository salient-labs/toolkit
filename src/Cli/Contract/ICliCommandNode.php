<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\CliCommand;
use Lkrms\Contract\HasContainer;
use Lkrms\Contract\HasDescription;
use Lkrms\Contract\HasEnvironment;
use LogicException;

/**
 * A node in a CLI command tree
 *
 * @extends HasContainer<ICliApplication>
 *
 * @see CliCommand
 */
interface ICliCommandNode extends HasContainer, HasEnvironment, HasDescription
{
    /**
     * Get the command name as a string of space-delimited subcommands
     *
     * Returns an empty string if {@see ICliCommandNode::setName()} has not been
     * called, or if an empty array of subcommands was passed to
     * {@see ICliCommandNode::setName()}.
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
     * Called immediately after instantiation by an ICliApplication
     *
     * @param string[] $name
     * @throws LogicException if called more than once per instance.
     */
    public function setName(array $name): void;

    /**
     * Get a one-line summary of the command's syntax
     *
     * Returns usage information that includes the command's name, and the name
     * used to run the script.
     *
     * @param bool $collapse If `true` and the synopsis breaks over multiple
     * lines, collapse non-mandatory options to `[option]...`.
     */
    public function getSynopsis(bool $withMarkup = true, ?int $width = 80, bool $collapse = false): string;

    /**
     * Get a detailed explanation of the command
     *
     * @param bool $collapse If `true` and the command's synopsis breaks over
     * multiple lines, collapse non-mandatory options to `[option]...`. This
     * behaviour may also be enabled via {@see ICliApplication::getHelpStyle()}.
     * @return array<string,string> An array that maps help section names to
     * content. Section names defined in {@see CliHelpSectionName} are
     * recommended but not required.
     */
    public function getHelp(bool $withMarkup = true, ?int $width = 80, bool $collapse = false): array;
}
