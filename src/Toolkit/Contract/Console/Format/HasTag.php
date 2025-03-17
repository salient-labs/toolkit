<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

/**
 * @api
 */
interface HasTag
{
    /**
     * Heading
     *
     * - `___` text `___`
     * - `***` text `***`
     * - `##` text `##` (closing delimiter optional)
     */
    public const TAG_HEADING = 0;

    /**
     * Bold, or strong importance
     *
     * - `__` text `__`
     * - `**` text `**`
     */
    public const TAG_BOLD = 1;

    /**
     * Italic, or emphasis
     *
     * - `_` text `_`
     * - `*` text `*`
     */
    public const TAG_ITALIC = 2;

    /**
     * Underline, or emphasis
     *
     * `<` text `>`
     */
    public const TAG_UNDERLINE = 3;

    /**
     * Low priority
     *
     * `~~` text `~~`
     */
    public const TAG_LOW_PRIORITY = 4;

    /**
     * Inline code span
     *
     * `` ` `` text `` ` ``
     */
    public const TAG_CODE_SPAN = 5;

    /**
     * Fenced code block
     *
     * ` ``` `<br>
     * text<br>
     * ` ``` `
     */
    public const TAG_CODE_BLOCK = 6;

    /**
     * Header in unified diff
     */
    public const TAG_DIFF_HEADER = 7;

    /**
     * Line numbers in unified diff
     */
    public const TAG_DIFF_RANGE = 8;

    /**
     * Additional line in unified diff
     */
    public const TAG_DIFF_ADDITION = 9;

    /**
     * Removed line in unified diff
     */
    public const TAG_DIFF_REMOVAL = 10;
}
