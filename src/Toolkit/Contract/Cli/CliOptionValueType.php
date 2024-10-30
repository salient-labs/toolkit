<?php declare(strict_types=1);

namespace Salient\Contract\Cli;

/**
 * Command line option value types
 *
 * @api
 */
interface CliOptionValueType
{
    /**
     * A boolean value
     */
    public const BOOLEAN = 0;

    /**
     * An integer value
     */
    public const INTEGER = 1;

    /**
     * A string
     */
    public const STRING = 2;

    /**
     * A floating-point value
     */
    public const FLOAT = 3;

    /**
     * A date/time string understood by DateTimeImmutable::__construct()
     */
    public const DATE = 4;

    /**
     * Path to an existing file or directory
     */
    public const PATH = 5;

    /**
     * Path to an existing file
     */
    public const FILE = 6;

    /**
     * Path to an existing directory
     */
    public const DIRECTORY = 7;

    /**
     * Path to an existing file or directory, or a dash ('-') for standard input
     * or output
     */
    public const PATH_OR_DASH = 8;

    /**
     * Path to an existing file, or a dash ('-') for standard input or output
     */
    public const FILE_OR_DASH = 9;

    /**
     * Path to an existing directory, or a dash ('-') for standard input or
     * output
     */
    public const DIRECTORY_OR_DASH = 10;

    /**
     * Path to a writable/creatable file or directory
     */
    public const NEW_PATH = 11;

    /**
     * Path to a writable/creatable file
     */
    public const NEW_FILE = 12;

    /**
     * Path to a writable/creatable directory
     */
    public const NEW_DIRECTORY = 13;

    /**
     * Path to a writable/creatable file or directory, or a dash ('-') for
     * standard input or output
     */
    public const NEW_PATH_OR_DASH = 14;

    /**
     * Path to a writable/creatable file, or a dash ('-') for standard input or
     * output
     */
    public const NEW_FILE_OR_DASH = 15;

    /**
     * Path to a writable/creatable directory, or a dash ('-') for standard
     * input or output
     */
    public const NEW_DIRECTORY_OR_DASH = 16;
}
