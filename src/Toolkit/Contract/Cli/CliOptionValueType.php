<?php declare(strict_types=1);

namespace Salient\Contract\Cli;

use DateTimeImmutable;

/**
 * Command line option value types
 *
 * @api
 */
interface CliOptionValueType
{
    /**
     * A boolean value
     *
     * Boolean strings are accepted.
     */
    public const BOOLEAN = 0;

    /**
     * An integer value
     *
     * Integer strings are accepted.
     */
    public const INTEGER = 1;

    /**
     * A string
     */
    public const STRING = 2;

    /**
     * A date and/or time
     *
     * Strings understood by {@see strtotime()} are accepted and normalised to
     * {@see DateTimeImmutable} instances.
     */
    public const DATE = 3;

    /**
     * Path to an existing file or directory
     */
    public const PATH = 4;

    /**
     * Path to an existing file
     */
    public const FILE = 5;

    /**
     * Path to an existing directory
     */
    public const DIRECTORY = 6;

    /**
     * Path to an existing file or directory, or a dash ('-') for standard input
     * or output
     */
    public const PATH_OR_DASH = 7;

    /**
     * Path to an existing file, or a dash ('-') for standard input or output
     */
    public const FILE_OR_DASH = 8;
}
