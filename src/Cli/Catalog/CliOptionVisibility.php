<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Command line option visibility flags
 *
 * @extends Enumeration<int>
 */
final class CliOptionVisibility extends Enumeration
{
    /**
     * Don't include the option in any help output
     */
    public const NONE = 0;

    /**
     * Include the option in command synopses
     */
    public const SYNOPSIS = 1;

    /**
     * Include the option when writing help to a terminal
     */
    public const HELP = 2;

    /**
     * Include the option when writing help as Markdown
     */
    public const MARKDOWN = 4;

    /**
     * Include the option when writing help as Markdown with man page extensions
     */
    public const MAN_PAGE = 8;

    /**
     * Include the option when generating shell completions
     */
    public const COMPLETION = 16;

    /**
     * Hide the option's default value if not writing help to a terminal
     */
    public const HIDE_DEFAULT = 32;

    public const ALL = CliOptionVisibility::SYNOPSIS | CliOptionVisibility::HELP | CliOptionVisibility::MARKDOWN | CliOptionVisibility::MAN_PAGE | CliOptionVisibility::COMPLETION;
}
