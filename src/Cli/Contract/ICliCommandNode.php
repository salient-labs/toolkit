<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliCommand;
use Lkrms\Contract\ReturnsContainer;
use LogicException;

/**
 * A node in a CLI command tree
 *
 * @extends ReturnsContainer<CliApplication>
 *
 * @see CliCommand
 */
interface ICliCommandNode extends ReturnsContainer
{
    /**
     * Called immediately after instantiation by a CliApplication
     *
     * @param string[] $name
     * @throws LogicException if called more than once per instance.
     */
    public function setName(array $name): void;

    /**
     * Get a one-line description of the command
     *
     */
    public function getShortDescription(): string;

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
