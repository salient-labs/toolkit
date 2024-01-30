<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Help message targets
 *
 * @api
 *
 * @extends Enumeration<int>
 */
final class CliHelpTarget extends Enumeration
{
    /**
     * Help is written to the console with minimal formatting
     */
    public const PLAIN = 0;

    /**
     * Help is written to the console with normal formatting
     */
    public const NORMAL = 1;

    /**
     * Help is written as Markdown
     */
    public const MARKDOWN = 2;

    /**
     * Help is written as Markdown with man page extensions
     */
    public const MAN_PAGE = 3;
}
