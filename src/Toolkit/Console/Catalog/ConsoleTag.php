<?php declare(strict_types=1);

namespace Salient\Console\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Console output formatting tags
 *
 * @extends AbstractEnumeration<int>
 */
final class ConsoleTag extends AbstractEnumeration
{
    /**
     * Heading
     *
     * - `___` text `___`
     * - `***` text `***`
     * - `##` text `##` (closing delimiter is optional)
     */
    public const HEADING = 0;

    /**
     * "Bold", or strong importance
     *
     * - `__` text `__`
     * - `**` text `**`
     */
    public const BOLD = 1;

    /**
     * "Italic", or emphasis
     *
     * - `_` text `_`
     * - `*` text `*`
     */
    public const ITALIC = 2;

    /**
     * "Underline", or emphasis
     *
     * `<` text `>`
     */
    public const UNDERLINE = 3;

    /**
     * Low priority
     *
     * `~~` text `~~`
     */
    public const LOW_PRIORITY = 4;

    /**
     * Inline code span
     *
     * `` ` `` text `` ` ``
     */
    public const CODE_SPAN = 5;

    /**
     * Fenced code block
     *
     * ` ``` `<br>
     * text<br>
     * ` ``` `
     */
    public const CODE_BLOCK = 6;

    /**
     * An additional line in unified diff output
     */
    public const DIFF_ADDITION = 7;

    /**
     * A removed line in unified diff output
     */
    public const DIFF_REMOVAL = 8;

    /**
     * A unified diff header
     */
    public const DIFF_HEADER = 9;
}
