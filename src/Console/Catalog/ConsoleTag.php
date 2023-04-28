<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Inline formatting tags for console messages
 *
 * Markdown-like syntax.
 *
 */
final class ConsoleTag extends Enumeration
{
    /**
     * Heading
     *
     * - `___` *text* `___`, `***` *text* `***`, `##` *text*
     * - Appearance: ***bold + colour***
     * - Example: `___NAME___`, `***NAME***`, `## NAME` (closing hashes are
     *   optional)
     */
    public const HEADING = 0;

    /**
     * "Bold", or strong importance
     *
     * - `__` *text* `__`, `**` *text* `**`
     * - Appearance: **bold**
     * - Example: `__command__`, `**command**`
     */
    public const BOLD = 1;

    /**
     * "Italic", or emphasis
     *
     * - `_` *text* `_`, `*` *text* `*`
     * - Appearance: *secondary colour*
     * - Example: `_argument_`, `*argument*`
     */
    public const ITALIC = 2;

    /**
     * "Underline", or emphasis
     *
     * Intended for documentation of arguments, variables and other
     * user-supplied values.
     *
     * - `<` *text* `>`
     * - Appearance: *secondary colour + <u>underline</u>*
     * - Example: `<argument>`
     */
    public const UNDERLINE = 3;

    /**
     * Low priority
     *
     * - `~~` *text* `~~`
     * - Appearance: ~~dim~~
     * - Example: `~~/path/to/script.php:42~~`
     */
    public const LOW_PRIORITY = 4;
}
