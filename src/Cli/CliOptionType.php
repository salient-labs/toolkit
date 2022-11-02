<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concept\Enumeration;

/**
 * Command line option types
 *
 * See {@see \Lkrms\Cli\Concept\CliCommand::getOptionList()} for more
 * information.
 *
 */
final class CliOptionType extends Enumeration
{
    /**
     * Enable a setting
     *
     * Examples:
     * - `-v`
     * - `--verbose`
     */
    public const FLAG = 0;

    /**
     * Set a value
     *
     * Examples:
     * - `-v <level>`
     * - `--verbosity <level>`
     */
    public const VALUE = 1;

    /**
     * Set a value, or don't
     *
     * Examples (note the lack of whitespace):
     * - `-v<level>`
     * - `--verbosity=<level>`
     */
    public const VALUE_OPTIONAL = 2;

    /**
     * Choose from a list of values
     *
     * Examples:
     * - `-f (yes|no|ask)`
     * - `--force (yes|no|ask)`
     */
    public const ONE_OF = 3;

    /**
     * Choose from a list of values, or don't
     *
     * Examples (note the lack of whitespace):
     * - `-f(yes|no|ask)`
     * - `--force=(yes|no|ask)`
     */
    public const ONE_OF_OPTIONAL = 4;

    /**
     * Set the value of a positional parameter
     *
     */
    public const VALUE_POSITIONAL = 5;

}
