<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Concept\Enumeration;

/**
 * Console message tags
 *
 */
final class ConsoleTag extends Enumeration
{
    /**
     * Heading
     *
     * - `___` or `***`
     * - Appearance: bold + colour
     * - Example: `___DESCRIPTION___` or `***DESCRIPTION***`
     */
    public const HEADING = 0;

    /**
     * Subheading
     *
     * - `__` or `**`
     * - Appearance: bold
     * - Example: `__command__` or `**command**`
     */
    public const SUBHEADING = 1;

    /**
     * Title
     *
     * - `_` or `*`
     * - Appearance: secondary colour
     * - Example: `_options:_` or `*options:*`
     */
    public const TITLE = 2;

    /**
     * Low priority
     *
     * - `~~`
     * - Appearance: dim
     * - Example: `~~/path/to/script.php:42~~`
     */
    public const LOW_PRIORITY = 3;

}
