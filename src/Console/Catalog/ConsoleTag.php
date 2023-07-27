<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Markdown-like formatting tags for console output
 *
 * @extends Enumeration<int>
 */
final class ConsoleTag extends Enumeration
{
    /**
     * Heading
     *
     * Syntax:
     *
     * - `___` text `___`
     * - `***` text `***`
     * - `##` text `##` (closing delimiter is optional)
     *
     * Typical appearance:
     *
     * - HTML: ***bold + italic***
     * - TTY: bold + primary colour
     *
     * Examples:
     *
     * - `___NAME___`
     * - `***NAME***`
     * - `## NAME`
     */
    public const HEADING = 0;

    /**
     * "Bold", or strong importance
     *
     * Syntax:
     *
     * - `__` text `__`
     * - `**` text `**`
     *
     * Typical appearance:
     *
     * - HTML: **bold**
     * - TTY: bold + default colour
     *
     * Examples:
     *
     * - `__command__`
     * - `**command**`
     */
    public const BOLD = 1;

    /**
     * "Italic", or emphasis
     *
     * Syntax:
     *
     * - `_` text `_`
     * - `*` text `*`
     *
     * Typical appearance:
     *
     * - HTML: *italic*
     * - TTY: secondary colour
     *
     * Examples:
     *
     * - `_argument_`
     * - `*argument*`
     */
    public const ITALIC = 2;

    /**
     * "Underline", or emphasis
     *
     * Intended for documentation of arguments, variables and other
     * user-supplied values.
     *
     * Syntax:
     *
     * `<` text `>`
     *
     * Typical appearance:
     *
     * - HTML: *<u>italic + underline</u>*
     * - TTY: secondary colour + underline
     *
     * Example:
     *
     * `<argument>`
     */
    public const UNDERLINE = 3;

    /**
     * Low priority
     *
     * Syntax:
     *
     * `~~` text `~~`
     *
     * Typical appearance:
     *
     * - HTML: <small>small</small>
     * - TTY: dim
     *
     * Example:
     *
     * `~~/path/to/script.php:42~~`
     */
    public const LOW_PRIORITY = 4;

    /**
     * Inline code span
     *
     * Syntax:
     *
     * `` ` `` text `` ` ``
     *
     * Typical appearance:
     *
     * - HTML: <code>monospaced</code>
     * - TTY: bold
     *
     * Example:
     *
     * `` The input format can be specified using the `-f/--from` option. ``
     */
    public const CODE_SPAN = 5;

    /**
     * Fenced code block
     *
     * Syntax:
     *
     * ` ``` `<br>
     * text<br>
     * ` ``` `
     *
     * Typical appearance:
     *
     * - HTML: <code>monospaced block</code>
     * - TTY: unchanged
     *
     * Example:
     *
     * ````
     * ```
     * $baz = Foo::bar();
     * ```
     * ````
     */
    public const CODE_BLOCK = 6;
}
