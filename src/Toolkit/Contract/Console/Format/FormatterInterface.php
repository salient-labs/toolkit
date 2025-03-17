<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Core\Immutable;

/**
 * @api
 */
interface FormatterInterface extends Immutable, HasTag
{
    /**
     * Check if the formatter removes escapes from strings
     */
    public function removesEscapes(): bool;

    /**
     * Check if the formatter wraps strings after formatting
     *
     * Returns `false` if strings are wrapped after inline formatting tags are
     * removed.
     */
    public function wrapsAfterFormatting(): bool;

    /**
     * Get an instance that removes escapes from strings
     *
     * @return static
     */
    public function withRemoveEscapes(bool $remove = true);

    /**
     * Get an instance that wraps strings after formatting
     *
     * @return static
     */
    public function withWrapAfterFormatting(bool $value = true);

    /**
     * Get the format applied to a given tag
     *
     * @param FormatterInterface::TAG_* $tag
     */
    public function getTagFormat(int $tag): FormatInterface;

    /**
     * Get the format applied to a given message level and type
     *
     * @param Console::LEVEL_* $level
     * @param Console::TYPE_* $type
     */
    public function getMessageFormat(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): MessageFormatInterface;

    /**
     * Get the prefix applied to a given message level and type
     *
     * @param Console::LEVEL_* $level
     * @param Console::TYPE_* $type
     */
    public function getMessagePrefix(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): string;

    /**
     * Format and optionally reflow a string with inline formatting tags
     *
     * @param int|array{int,int}|null $wrapTo - `int`: wrap the string to the
     * given width
     * - `array{int,int}`: wrap the string to `[ <first_line_width>, <width> ]`
     * - `null` (default): do not wrap the string
     *
     * Integers less than or equal to `0` are added to the width of the target
     * and replaced with the result.
     * @param bool $unformat If `true`, inline formatting tags are reapplied
     * after the string is formatted.
     */
    public function format(
        string $string,
        bool $unwrap = false,
        $wrapTo = null,
        bool $unformat = false,
        string $break = "\n"
    ): string;

    /**
     * Format a unified diff
     */
    public function formatDiff(string $diff): string;

    /**
     * Format a console message
     *
     * Inline formatting tags in `$msg1` and `$msg2` have no special meaning;
     * call {@see format()} first if necessary.
     *
     * @param Console::LEVEL_* $level
     * @param Console::TYPE_* $type
     */
    public function formatMessage(
        string $msg1,
        ?string $msg2 = null,
        int $level = Console::LEVEL_INFO,
        int $type = Console::TYPE_STANDARD
    ): string;
}
