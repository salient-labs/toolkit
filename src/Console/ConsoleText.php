<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\ConsoleColour as Colour;

/**
 * Format text for console output
 *
 * @package Lkrms
 */
abstract class ConsoleText
{
    private const TAG_HEADING = 0;
    private const TAG_SUBHEADING = 1;
    private const TAG_TITLE = 2;
    private const TAG_LOW_PRIORITY = 3;

    /**
     * Matches a preformatted block or span and the text before it
     */
    private const REGEX_PREFORMATTED = <<<'REGEX'
(?xs)
# The end of the previous match
\G
# Text before a preformatted block or span, including recognised escapes
(?P<text> (?: \\ [\\`] | [^`] )*? )
# A preformatted block
(?: (?<= \n | ^) ``` \n (?P<pre> .*? ) \n ``` (?= \n | $) |
  # ...or span
  ` (?P<code> (?: \\ [\\`] | [^`] )*? ) ` |
  # ...or the end of the subject
  $)
REGEX;

    /**
     * Matches an escaped backslash or backtick (other escapes are ignored)
     */
    private const REGEX_ESCAPED = <<<'REGEX'
(?xs)
\\ ( [\\`] )
REGEX;

    private const REGEX_MAP = [
        self::TAG_HEADING      => '(?|\b___(?!\s)(.+?)(?<!\s)___\b|\*\*\*(?!\s)(.+?)(?<!\s)\*\*\*)',
        self::TAG_SUBHEADING   => '(?|\b__(?!\s)(.+?)(?<!\s)__\b|\*\*(?!\s)(.+?)(?<!\s)\*\*)',
        self::TAG_TITLE        => '(?|\b_(?!\s)(.+?)(?<!\s)_\b|\*(?!\s)(.+?)(?<!\s)\*)',
        self::TAG_LOW_PRIORITY => '~~(.+?)~~',
    ];

    private const COLOUR_MAP = [
        self::TAG_HEADING      => [Colour::BOLD . Colour::CYAN, Colour::DEFAULT . Colour::UNBOLD],
        self::TAG_SUBHEADING   => [Colour::BOLD, Colour::UNBOLD],
        self::TAG_TITLE        => [Colour::YELLOW, Colour::DEFAULT],
        self::TAG_LOW_PRIORITY => [Colour::DIM, Colour::UNDIM],
    ];

    private static $PregReplace;

    /**
     * Apply inline formatting to a string
     *
     * If `$colour` is `true`, replace inline formatting with escape sequences
     * to set and subsequently clear the relevant terminal display attributes,
     * otherwise remove inline formatting.
     *
     * The following Markdown-like syntax is supported:
     *
     * | Style        | Tag                      | Appearance          | Example                                                                                 |
     * | ------------ | ------------------------ | ------------------- | --------------------------------------------------------------------------------------- |
     * | Heading      | `___` or `***`           | ***Bold + colour*** | `___DESCRIPTION___` or<br>`***DESCRIPTION***`                                           |
     * | Subheading   | `__` or `**`             | **Bold**            | `__command__` or<br>`**command**`                                                       |
     * | Title        | `_` or `*`               | *Secondary colour*  | `_options:_` or<br>`*options:*`                                                         |
     * | Low priority | `~~`                     | Dim                 | `~~/path/to/script.php:42~~`                                                            |
     * | Preformatted | `` ` `` or ```` ``` ```` | `Unchanged`         | `` `<untrusted text>` `` or<br><pre>\`\`\`&#10;&lt;untrusted block&gt;&#10;\`\`\`</pre> |
     *
     * @param string $string
     * @param bool $colour
     * @return string
     */
    public static function format(string $string, bool $colour): string
    {
        if (is_null(self::$PregReplace))
        {
            self::$PregReplace = [[], [], []];

            foreach (self::REGEX_MAP as $tag => $regex)
            {
                $colours = self::COLOUR_MAP[$tag] ?? null;
                self::$PregReplace[0][] = "/" . $regex . "/u";
                self::$PregReplace[1][] = '$1';
                self::$PregReplace[2][] = $colours ? $colours[0] . '$1' . $colours[1] : '$1';
            }
        }

        return preg_replace_callback(
            "/" . self::REGEX_PREFORMATTED . "/u",
            function (array $matches) use ($colour)
            {
                $text = preg_replace(
                    self::$PregReplace[0],
                    self::$PregReplace[$colour ? 2 : 1],
                    self::unescape($matches["text"])
                );
                $pre = ($matches["pre"] ?? "") ?: self::unescape($matches["code"] ?? "");

                return $text . $pre;
            },
            $string
        );
    }

    /**
     * Equivalent to ConsoleText::format($string, true)
     *
     * @param string $string
     * @return string
     * @see ConsoleText::format()
     */
    public static function formatColour(string $string): string
    {
        return self::format($string, true);
    }

    /**
     * Equivalent to ConsoleText::format($string, false)
     *
     * @param string $string
     * @return string
     * @see ConsoleText::format()
     */
    public static function formatPlain(string $string): string
    {
        return self::format($string, false);
    }

    /**
     * Return true if a string contains inline bold formatting
     *
     * @param string $string
     * @return bool
     * @see ConsoleText::format()
     */
    public static function hasBold(string $string): bool
    {
        $string = preg_replace_callback(
            "/" . self::REGEX_PREFORMATTED . "/u",
            function (array $matches) { return $matches["text"]; },
            $string
        );

        return (bool)preg_match("/" . implode("|", [
            self::REGEX_MAP[self::TAG_HEADING],
            self::REGEX_MAP[self::TAG_SUBHEADING],
        ]) . "/u", $string);
    }

    /**
     * Escape backslashes and backticks in a string
     *
     * Usage suggestion:
     *
     * ```php
     * Console::info("Message:", "`" . ConsoleText::escape($message) . "`");
     * ```
     *
     * @param string $string
     * @return string
     */
    public static function escape(string $string): string
    {
        return str_replace(["\\", "`"], ["\\\\", "\\`"], $string);
    }

    private static function unescape(string $string): string
    {
        return preg_replace("/" . self::REGEX_ESCAPED . "/u", '$1', $string);
    }
}
