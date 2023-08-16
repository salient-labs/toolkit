<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Console output attributes
 *
 * @extends Enumeration<string>
 */
final class ConsoleAttribute extends Enumeration
{
    /**
     * The text's message level
     */
    public const LEVEL = 'level';

    /**
     * The text's message type
     */
    public const TYPE = 'type';

    /**
     * The Markdown-like inline formatting tag originally applied to the text
     */
    public const TAG = 'tag';

    /**
     * The info string associated with a fenced code block
     */
    public const INFO_STRING = 'info_string';

    /**
     * True if the text is part 1 of a message
     */
    public const IS_MSG1 = 'is_msg1';

    /**
     * True if the text is part 2 of a message
     */
    public const IS_MSG2 = 'is_msg2';

    /**
     * True if the text is a message prefix
     */
    public const IS_PREFIX = 'is_prefix';
}
