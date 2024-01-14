<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Help message targets
 *
 * @extends Enumeration<int>
 */
final class CliHelpTarget extends Enumeration
{
    /**
     * Help is used internally
     */
    public const INTERNAL = 0;

    /**
     * Help is written to a terminal
     */
    public const TTY = 1;

    /**
     * Help is written as Markdown
     */
    public const MARKDOWN = 2;

    /**
     * Help is written as Markdown with man page extensions
     */
    public const MAN_PAGE = 3;
}
