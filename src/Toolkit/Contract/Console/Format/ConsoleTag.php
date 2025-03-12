<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

/**
 * Console output formatting tags
 */
interface ConsoleTag
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
     * A unified diff header
     */
    public const DIFF_HEADER = 7;

    /**
     * Line numbers in a unified diff
     */
    public const DIFF_RANGE = 8;

    /**
     * An additional line in unified diff output
     */
    public const DIFF_ADDITION = 9;

    /**
     * A removed line in unified diff output
     */
    public const DIFF_REMOVAL = 10;
}
