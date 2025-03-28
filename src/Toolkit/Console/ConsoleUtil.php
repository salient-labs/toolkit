<?php declare(strict_types=1);

namespace Salient\Console;

use Salient\Console\Format\Formatter;
use Salient\Utility\AbstractUtility;
use Salient\Utility\Regex;

/**
 * @internal
 */
final class ConsoleUtil extends AbstractUtility implements HasConsoleRegex
{
    /**
     * Escape inline formatting tags in a string
     */
    public static function escape(string $string, bool $escapeNewlines = false): string
    {
        $string = addcslashes($string, '\#*<>_`~');
        return $escapeNewlines
            ? str_replace("\n", "\\\n", $string)
            : $string;
    }

    /**
     * Remove escapes from inline formatting tags in a string
     */
    public static function removeEscapes(string $string): string
    {
        return Regex::replace(self::ESCAPE_REGEX, '$1', $string);
    }

    /**
     * Remove inline formatting tags from a string
     */
    public static function removeTags(string $string): string
    {
        return Formatter::getNullFormatter()->format($string);
    }
}
