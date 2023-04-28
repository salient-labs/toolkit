<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Command line option types
 *
 */
final class CliOptionType extends Enumeration
{
    /**
     * Enable a setting
     *
     * Examples:
     *
     * - `-v`
     * - `--verbose`
     */
    public const FLAG = 0;

    /**
     * Set a value
     *
     * Examples:
     *
     * - `-v <level>`
     * - `--verbosity <level>`
     */
    public const VALUE = 1;

    /**
     * Set a value, or don't
     *
     * Examples (note the lack of whitespace):
     *
     * - `-v<level>`
     * - `--verbosity=<level>`
     */
    public const VALUE_OPTIONAL = 2;

    /**
     * Set the value of a positional parameter
     *
     */
    public const VALUE_POSITIONAL = 3;

    /**
     * Choose from a list of values
     *
     * Examples:
     *
     * - `-f (yes|no|ask)`
     * - `--force (yes|no|ask)`
     */
    public const ONE_OF = 4;

    /**
     * Choose from a list of values, or don't
     *
     * Examples (note the lack of whitespace):
     *
     * - `-f(yes|no|ask)`
     * - `--force=(yes|no|ask)`
     */
    public const ONE_OF_OPTIONAL = 5;

    /**
     * Choose the value of a positional parameter from a list
     *
     */
    public const ONE_OF_POSITIONAL = 6;
}
