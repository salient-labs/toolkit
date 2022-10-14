<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Concept\ConsoleTarget;
use Lkrms\Console\ConsoleTag as Tag;

/**
 * Formats console messages
 *
 */
final class ConsoleFormatter
{
    /**
     * Matches a preformatted block or span and the text before it
     */
    private const REGEX_PREFORMATTED = <<<'REGEX'
(?xs)
# The end of the previous match
\G
# Text before a preformatted block or span, including recognised escapes
(?P<text> (?: [^\\`]+ | \\ [\\`] | \\ )* )
# A preformatted block
(?: (?<= \n | ^) ``` \n (?P<pre> .*? ) \n ``` (?= \n | $) |
  # ...or span
  ` (?P<code> (?: [^\\`]+ | \\ [\\`] | \\ )* ) ` |
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
        Tag::HEADING      => '(?|\b___(?!\s)(.+?)(?<!\s)___\b|\*\*\*(?!\s)(.+?)(?<!\s)\*\*\*)',
        Tag::SUBHEADING   => '(?|\b__(?!\s)(.+?)(?<!\s)__\b|\*\*(?!\s)(.+?)(?<!\s)\*\*)',
        Tag::TITLE        => '(?|\b_(?!\s)(.+?)(?<!\s)_\b|\*(?!\s)(.+?)(?<!\s)\*)',
        Tag::LOW_PRIORITY => '~~(.+?)~~',
    ];

    private $PregReplace = [];

    public function __construct(ConsoleTarget $target)
    {
        foreach (self::REGEX_MAP as $tag => $regex)
        {
            $this->PregReplace[0][] = "/" . $regex . "/u";
            $this->PregReplace[1][] = $target->getTagFormat($tag)->apply('$1');
        }
    }

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
     */
    public function format(string $string): string
    {
        return preg_replace_callback("/" . self::REGEX_PREFORMATTED . "/u",
            function (array $matches)
            {
                $text = preg_replace(
                    $this->PregReplace[0],
                    $this->PregReplace[1],
                    $this->unescape($matches["text"])
                );
                $pre = ($matches["pre"] ?? "") ?: $this->unescape($matches["code"] ?? "");

                return $text . $pre;
            }, $string);
    }

    private function unescape(string $string): string
    {
        return preg_replace("/" . self::REGEX_ESCAPED . "/u", '$1', $string);
    }

    /**
     * Escape backslashes and backticks in a string
     *
     * Example:
     *
     * ```php
     * Console::info("Message:", "`" . ConsoleFormatter::escape($message) . "`");
     * ```
     */
    public static function escape(string $string): string
    {
        return str_replace(["\\", "`"], ["\\\\", "\\`"], $string);
    }

}
