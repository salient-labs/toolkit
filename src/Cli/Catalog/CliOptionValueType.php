<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\ConvertibleEnumeration;

/**
 * Command line option value types
 *
 * @extends ConvertibleEnumeration<int>
 */
final class CliOptionValueType extends ConvertibleEnumeration
{
    /**
     * A boolean value
     *
     * Boolean strings recognised by {@see \Lkrms\Utility\Test::isBoolValue()}
     * are accepted.
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
     * {@see \DateTimeImmutable} instances.
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

    protected static $NameMap = [
        self::BOOLEAN => 'BOOLEAN',
        self::INTEGER => 'INTEGER',
        self::STRING => 'STRING',
        self::DATE => 'DATE',
        self::PATH => 'PATH',
        self::FILE => 'FILE',
        self::DIRECTORY => 'DIRECTORY',
        self::PATH_OR_DASH => 'PATH_OR_DASH',
        self::FILE_OR_DASH => 'FILE_OR_DASH',
    ];

    protected static $ValueMap = [
        'BOOLEAN' => self::BOOLEAN,
        'INTEGER' => self::INTEGER,
        'STRING' => self::STRING,
        'DATE' => self::DATE,
        'PATH' => self::PATH,
        'FILE' => self::FILE,
        'DIRECTORY' => self::DIRECTORY,
        'PATH_OR_DASH' => self::PATH_OR_DASH,
        'FILE_OR_DASH' => self::FILE_OR_DASH,
    ];
}
