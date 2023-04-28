<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Concept\ConsoleTarget;
use Lkrms\Console\ConsoleTag as Tag;
use RuntimeException;

/**
 * Formats console messages
 *
 */
final class ConsoleFormatter
{
    /**
     * Matches a preformatted block or span and the text before it
     *
     */
    private const REGEX_PREFORMATTED = <<<'REGEX'
        (?xs)
        # The end of the previous match
        \G
        # Text before a preformatted block or span, including recognised escapes
        (?P<text> (?: [^\\`]++ | \\ [\\`] | \\ )*+ )
        # A preformatted block
        (?: (?<= \n | ^) ``` \n (?P<block> .*? ) \n ``` (?= \n | $) |
          # ...or span
          ` (?P<span> (?: [^\\`]++ | \\ [\\`] | \\ )*+ ) ` |
          # ...or invalid syntax
          (?P<invalid> `++ ) |
          # ...or the end of the subject
          $)
        REGEX;

    /**
     * Matches an escaped backslash or backtick (other escapes are ignored)
     *
     */
    private const REGEX_ESCAPED = <<<'REGEX'
        (?xs)
        \\ ( [\\`] )
        REGEX;

    private const REGEX_MAP = [
        Tag::HEADING => '(?|\b___(?!\s)(.+?)(?<!\s)___\b|\*\*\*(?!\s)(.+?)(?<!\s)\*\*\*|(?<=\n|^)##\h+([^\n]+)(?:\h+#+|\h*)(?=\n|$))',
        Tag::BOLD => '(?|\b__(?!\s)(.+?)(?<!\s)__\b|\*\*(?!\s)(.+?)(?<!\s)\*\*)',
        Tag::ITALIC => '(?|\b_(?!\s)(.+?)(?<!\s)_\b|\*(?!\s)(.+?)(?<!\s)\*)',
        Tag::UNDERLINE => '<(?!\s)(.+?)(?<!\s)>',
        Tag::LOW_PRIORITY => '~~(.+?)~~',
    ];

    /**
     * @var array{0:string[],1:string[]}|null
     */
    private $PregReplace;

    /**
     * @var ConsoleFormatter|null
     */
    private static $DefaultInstance;

    public function __construct(?ConsoleTarget $target)
    {
        foreach (self::REGEX_MAP as $tag => $regex) {
            $this->PregReplace[0][] = '/' . $regex . '/u';
            $this->PregReplace[1][] = ($target ? $target->getTagFormat($tag) : new ConsoleFormat())->apply('$1');
        }
    }

    /**
     * Apply inline formatting to a string
     *
     */
    public function format(string $string): string
    {
        $preformatted = [];
        $next = 0;
        $string = preg_replace_callback(
            '/' . self::REGEX_PREFORMATTED . '/u',
            function (array $matches) use (&$preformatted, &$next) {
                /** @var array<int|string,string|null> $matches */
                if (!is_null($matches['invalid'])) {
                    throw new RuntimeException('Argument #1 ($string) contains invalid syntax');
                }
                $text = $matches['text'];
                if ($code = $matches['span']) {
                    $code = $this->unescape($code);
                } else {
                    $code = $matches['block'] ?: '';
                }

                if ($code) {
                    $preformatted[$key = sprintf("\x01%d\x02", $next++)] = $code;
                    $code = $key;
                }

                return $text . $code;
            },
            $string,
            -1,
            $count,
            // Without this, unmatched subpatterns aren't reported at all
            PREG_UNMATCHED_AS_NULL
        );
        $string = $this->unescape(preg_replace(
            $this->PregReplace[0],
            $this->PregReplace[1],
            $string
        ));
        if ($preformatted) {
            $string = str_replace(
                array_keys($preformatted),
                array_values($preformatted),
                $string
            );
        }

        return $string;
    }

    private function unescape(string $string): string
    {
        return preg_replace('/' . self::REGEX_ESCAPED . '/u', '$1', $string);
    }

    /**
     * Escape backslashes and backticks in a string
     */
    public static function escape(string $string): string
    {
        return str_replace(['\\', '`'], ['\\\\', '\`'], $string);
    }

    /**
     * Escape backslashes and backticks in a string before adding backticks
     * around it
     *
     * Example:
     *
     * ```php
     * Console::info('Message:', ConsoleFormatter::escapeAndEnclose($message));
     * ```
     *
     */
    public static function escapeAndEnclose(string $string): string
    {
        return '`' . self::escape($string) . '`';
    }

    /**
     * Remove inline formatting from a string
     *
     */
    public static function removeTags(string $string): string
    {
        return self::getDefaultInstance()->format($string);
    }

    private static function getDefaultInstance(): self
    {
        return self::$DefaultInstance
            ?: (self::$DefaultInstance = new self(null));
    }
}
