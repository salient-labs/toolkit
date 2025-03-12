<?php declare(strict_types=1);

namespace Salient\Contract\Console;

use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Core\Immutable;

interface ConsoleFormatterInterface extends Immutable
{
    /**
     * Get an instance with the given spinner state array
     *
     * @param array{int<0,max>,float}|null $state
     * @param-out array{int<0,max>,float} $state
     * @return static
     */
    public function withSpinnerState(?array &$state);

    /**
     * Get an instance that unescapes text
     *
     * @return static
     */
    public function withUnescape(bool $value = true);

    /**
     * Check if text is unescaped
     */
    public function getUnescape(): bool;

    /**
     * Get an instance that wraps text after formatting
     *
     * @return static
     */
    public function withWrapAfterApply(bool $value = true);

    /**
     * Check if text is wrapped after formatting
     */
    public function getWrapAfterApply(): bool;

    /**
     * Get the format applied to a tag
     *
     * @param ConsoleTag::* $tag
     */
    public function getTagFormat(int $tag): ConsoleFormatInterface;

    /**
     * Get the format applied to a message level and type
     *
     * @param Console::LEVEL_* $level
     * @param Console::TYPE_* $type
     */
    public function getMessageFormat(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): ConsoleMessageFormatInterface;

    /**
     * Get the prefix applied to a message level and type
     *
     * @param Console::LEVEL_* $level
     * @param Console::TYPE_* $type
     */
    public function getMessagePrefix(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): string;

    /**
     * Format a string that may contain inline formatting tags
     *
     * Paragraphs outside preformatted blocks are optionally wrapped to a given
     * width, and backslash-escaped punctuation characters and line breaks are
     * preserved.
     *
     * Escaped line breaks may have a leading space, so the following are
     * equivalent:
     *
     * ```
     * Text with a \
     * hard line break.
     *
     * Text with a\
     * hard line break.
     * ```
     *
     * @param array{int,int}|int|null $wrapToWidth If `null` (the default), text
     * is not wrapped.
     *
     * If `$wrapToWidth` is an `array`, the first line of text is wrapped to the
     * first value, and text in subsequent lines is wrapped to the second value.
     *
     * Widths less than or equal to `0` are added to the width reported by the
     * target, and text is wrapped to the result.
     * @param bool $unformat If `true`, formatting tags are reapplied after text
     * is unwrapped and/or wrapped.
     */
    public function format(
        string $string,
        bool $unwrap = false,
        $wrapToWidth = null,
        bool $unformat = false,
        string $break = "\n"
    ): string;

    /**
     * Format a console message
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

    /**
     * Format a unified diff
     */
    public function formatDiff(string $diff): string;
}
