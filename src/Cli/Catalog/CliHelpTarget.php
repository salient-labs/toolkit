<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Help message types
 *
 * @extends Enumeration<int>
 */
final class CliHelpType extends Enumeration
{
    /**
     * Help is written to a terminal
     */
    public const TTY = 0;

    /**
     * Help is written as Markdown
     */
    public const MARKDOWN = 1;

    /**
     * Help is written as Markdown with man page extensions
     */
    public const MAN_PAGE = 2;
}
