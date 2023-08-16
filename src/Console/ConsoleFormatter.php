<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Catalog\ConsoleAttribute as Attribute;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as Type;
use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\ConsoleMessageFormat as MessageFormat;
use Lkrms\Console\Contract\IConsoleFormat as Format;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Pcre;
use RuntimeException;

/**
 * Formats console output for a target
 *
 * @see ConsoleTarget::getFormatter()
 */
final class ConsoleFormatter
{
    public const DEFAULT_LEVEL_PREFIX_MAP = [
        Level::EMERGENCY => ' !! ',
        Level::ALERT => ' !! ',
        Level::CRITICAL => ' !! ',
        Level::ERROR => ' !! ',
        Level::WARNING => '  ! ',
        Level::NOTICE => '==> ',
        Level::INFO => ' -> ',
        Level::DEBUG => '--- ',
    ];

    public const DEFAULT_TYPE_PREFIX_MAP = [
        Type::GROUP_START => '>>> ',
        Type::GROUP_END => '<<< ',
        Type::SUCCESS => ' // ',
    ];

    /**
     * Splits the subject into formattable paragraphs, fenced code blocks and
     * code spans
     *
     */
    private const PARSER_REGEX = <<<'REGEX'
        (?msx)
        (?(DEFINE)
          (?<endofblock> ^ \k<fence> \h*+ $ )
          (?<endofspan> \k<backtickstring> (?! ` ) )
        )
        # Do not allow gaps between matches
        \G
        # Do not allow empty matches
        (?= . )
        (?:
          # Whitespace before paragraphs
          (?<breaks> \n+ ) |
          # Everything except unescaped backticks until the start of the next
          # paragraph
          (?<text> (?> (?: [^\\`\n]+ | \\ [-\\!"\#$%&'()*+,./:;<=>?@[\]^_`{|}~\n] | \\ | \n (?! \n ) )+ \n* ) ) |
          # CommonMark-compliant fenced code blocks
          (?> ^
            (?> (?<fence> ```+ ) (?<infostring> [^\n]* ) \n )
            # Match empty blocks--with no trailing newline--and blocks with an
            # empty line by making the subsequent newline conditional on inblock
            (?<block> (?> (?<inblock> (?: (?! (?&endofblock) ) [^\n]* (?: (?= \n (?&endofblock) ) | \n | \z ) )+ )? ) )
            # Allow code fences to terminate at the end of the subject
            (?: (?(inblock) \n ) (?&endofblock) | \z )
          ) |
          # CommonMark-compliant code spans
          (?<backtickstring> (?> `+ ) ) (?<span> (?> (?: [^`]+ | (?! (?&endofspan) ) `+ )* ) ) (?&endofspan) |
          # Unmatched backticks
          (?<extra> `+ ) |
          \z
        )
        REGEX;

    /**
     * Matches inline formatting tags used outside fenced code blocks and code
     * spans
     *
     */
    private const TAG_REGEX = <<<'REGEX'
        (?xm)
        (?(DEFINE)
          (?<esc> \\ [-\\!"\#$%&'()*+,./:;<=>?@[\]^_`{|}~] | \\ )
        )
        (?<! \\ ) (?: \\\\ )* \K (?|
          \b  (?<tag> _ {1,3}+ )  (?! \s ) (?> (?<text> (?: [^_\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> \b ) _ + )* ) ) (?<! \s ) \k<tag> \b |
              (?<tag> \* {1,3}+ ) (?! \s ) (?> (?<text> (?: [^*\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> ) \* + )* ) )   (?<! \s ) \k<tag>    |
              (?<tag> < )         (?! \s ) (?> (?<text> (?: [^>\\]+ |    (?&esc) | (?! (?<! \s ) > ) > + )* ) )          (?<! \s ) >          |
              (?<tag> ~~ )        (?! \s ) (?> (?<text> (?: [^~\\]+ |    (?&esc) | (?! (?<! \s ) ~~ ) ~ + )* ) )         (?<! \s ) ~~         |
          ^   (?<tag> \#\# ) \h+           (?> (?<text> (?: [^\#\s\\]+ | (?&esc) | \#+ (?! \h* $ ) | \h++ (?! (?: \#+ \h* )? $ ) )* ) ) (?: \h+ \#+ | \h* ) $
        )
        REGEX;

    /**
     * A CommonMark-compliant backslash escape, or an escaped line break with an
     * optional leading space
     *
     */
    private const UNESCAPE_REGEX = <<<'REGEX'
        (?x)
        (?|
          \\ ( [-\\ !"\#$%&'()*+,./:;<=>?@[\]^_`{|}~] ) |
          # Lookbehind assertions are unnecessary because the first branch
          # matches escaped spaces and backslashes
          \  ? \\ ( \n )
        )
        REGEX;

    private static ConsoleFormatter $DefaultFormatter;

    private static ConsoleTagFormats $DefaultTagFormats;

    private static ConsoleMessageFormats $DefaultMessageFormats;

    private ConsoleTagFormats $TagFormats;

    private ConsoleMessageFormats $MessageFormats;

    /**
     * @var (callable(): int|null)|null
     */
    private $WidthCallback;

    /**
     * @var array<Level::*,string>
     */
    private array $LevelPrefixMap;

    /**
     * @var array<Type::*,string>
     */
    private array $TypePrefixMap;

    /**
     * @param (callable(): int|null)|null $widthCallback
     * @param array<Level::*,string> $levelPrefixMap
     * @param array<Type::*,string> $typePrefixMap
     */
    public function __construct(
        ?ConsoleTagFormats $tagFormats = null,
        ?ConsoleMessageFormats $messageFormats = null,
        ?callable $widthCallback = null,
        array $levelPrefixMap = ConsoleFormatter::DEFAULT_LEVEL_PREFIX_MAP,
        array $typePrefixMap = ConsoleFormatter::DEFAULT_TYPE_PREFIX_MAP
    ) {
        $this->TagFormats = $tagFormats ?: $this->getDefaultTagFormats();
        $this->MessageFormats = $messageFormats ?: $this->getDefaultMessageFormats();
        $this->WidthCallback = $widthCallback;
        $this->LevelPrefixMap = $levelPrefixMap;
        $this->TypePrefixMap = $typePrefixMap;
    }

    /**
     * Get the format assigned to a tag
     *
     * @param Tag::* $tag
     */
    public function getTagFormat($tag): Format
    {
        return $this->TagFormats->get($tag);
    }

    /**
     * Get the format assigned to a message level and type
     *
     * @param Level::* $level
     * @param Type::* $type
     */
    public function getMessageFormat($level, $type = Type::DEFAULT): MessageFormat
    {
        return $this->MessageFormats->get($level, $type);
    }

    /**
     * Get the prefix assigned to a message level and type
     *
     * @param Level::* $level
     * @param Type::* $type
     */
    public function getMessagePrefix($level, $type = Type::DEFAULT): string
    {
        return
            $type === Type::UNFORMATTED || $type === Type::UNDECORATED
                ? ''
                : ($this->TypePrefixMap[$type]
                    ?? $this->LevelPrefixMap[$level]
                    ?? '');
    }

    /**
     * Format a string
     *
     * Applies target-defined formats to text that may contain Markdown-like
     * inline formatting tags. Paragraphs outside preformatted blocks are
     * optionally wrapped to a given width, and backslash-escaped punctuation
     * characters and line breaks are preserved.
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
     * @param int|null $wrapToWidth If less than `0`, wrap text to the width
     * reported by the target. If `null` (the default), do not wrap text.
     */
    public function formatTags(
        string $string,
        bool $unwrap = false,
        ?int $wrapToWidth = null,
        bool $unescape = true
    ): string {
        if ($string === '' || $string === "\r") {
            return $string;
        }

        /**
         * [ [ Offset, length, replacement ] ]
         *
         * @var array<array{int,int,string}>
         */
        $replace = [];
        $append = '';
        $plainFormats = $this->getDefaultTagFormats();

        // Preserve trailing carriage returns
        if ($string[-1] === "\r") {
            $append .= "\r";
            $string = substr($string, 0, -1);
        }

        // Normalise line endings and split the string into formattable text,
        // fenced code blocks and code spans
        if (!Pcre::matchAll(
            Regex::delimit(self::PARSER_REGEX) . 'u',
            Convert::lineEndingsToUnix($string),
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
        )) {
            throw new RuntimeException(
                sprintf('Unable to parse: %s', $string)
            );
        }

        $string = '';
        /** @var array<int|string,string|null> $match */
        foreach ($matches as $match) {
            if (($breaks = $match['breaks']) !== null) {
                $string .= $breaks;
                continue;
            }

            // Treat unmatched backticks as plain text
            if (($extra = $match['extra']) !== null) {
                $string .= $extra;
                continue;
            }

            $baseOffset = strlen($string);

            if (($text = $match['text']) !== null) {
                if (strpos($text, "\n") !== false) {
                    if ($unwrap) {
                        $text = Convert::unwrap($text, "\n", false, true, true);
                    }
                }

                $adjust = 0;
                $text = Pcre::replaceCallback(
                    Regex::delimit(self::TAG_REGEX) . 'u',
                    function (array $match) use (
                        $unescape,
                        &$replace,
                        $plainFormats,
                        $baseOffset,
                        &$adjust
                    ): string {
                        /** @var array<int|string,array{string,int}> $match */
                        $text = $this->applyTags($match, true, true, $plainFormats);
                        $placeholder = Pcre::replace('/[^ ]/u', 'x', $text);
                        $formatted =
                            $unescape && $plainFormats === $this->TagFormats
                                ? $text
                                : $this->applyTags($match, true, $unescape, $this->TagFormats);
                        $replace[] = [
                            $baseOffset + $match[0][1] + $adjust,
                            strlen($placeholder),
                            $formatted,
                        ];
                        $adjust += strlen($placeholder) - strlen($match[0][0]);
                        return $placeholder;
                    },
                    $text,
                    -1,
                    $count,
                    PREG_OFFSET_CAPTURE
                );

                $string .= $text;
                continue;
            }

            if (($block = $match['block']) !== null) {
                // Reinstate unwrapped newlines before blocks
                if ($unwrap && $string !== '' && $string[-1] !== "\n") {
                    $string[-1] = "\n";
                }

                $infostring = trim($match['infostring']);
                $formatted = $this->TagFormats->get(Tag::CODE_BLOCK)->apply($block, [
                    Attribute::TAG => $match['fence'],
                    Attribute::INFO_STRING => $infostring === '' ? null : $infostring,
                ]);
                $placeholder = '?';
                $replace[] = [
                    $baseOffset,
                    1,
                    $formatted,
                ];

                $string .= $placeholder;
                continue;
            }

            if (($span = $match['span']) !== null) {
                // As per CommonMark:
                // - Convert line endings to spaces
                // - If the string begins and ends with a space but doesn't
                //   consist entirely of spaces, remove both
                $span = Pcre::replace(
                    '/^ ((?> *[^ ]+).*) $/u',
                    '$1',
                    strtr($span, "\n", ' '),
                );
                $formatted = $this->TagFormats->get(Tag::CODE_SPAN)->apply($span, [
                    Attribute::TAG => $match['backtickstring'],
                ]);
                $placeholder = Pcre::replace('/[^ ]/u', 'x', $span);
                $replace[] = [
                    $baseOffset,
                    strlen($placeholder),
                    $formatted,
                ];

                $string .= $placeholder;
                continue;
            }
        }

        // Remove backslash escapes and adjust the offsets of any subsequent
        // replacement strings
        $adjustable = [];
        foreach ($replace as $i => [$offset]) {
            $adjustable[$i] = $offset;
        }
        $adjust = 0;
        $string = Pcre::replaceCallback(
            Regex::delimit(self::UNESCAPE_REGEX) . 'u',
            function (array $match) use ($unescape, &$replace, &$adjust, &$adjustable): string {
                /** @var array<int|string,array{string,int}> $match */
                $delta = strlen($match[1][0]) - strlen($match[0][0]);
                foreach ($adjustable as $i => $offset) {
                    if ($offset < $match[0][1]) {
                        continue;
                    }
                    $replace[$i][0] += $delta;
                }

                if (!$unescape) {
                    // Use `$replace` to reinstate the escape after wrapping
                    $replace[] = [
                        $match[0][1] + $adjust,
                        strlen($match[1][0]),
                        $match[0][0],
                    ];
                    $adjust += $delta;
                }

                return $match[1][0];
            },
            $string,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );

        if (($wrapToWidth ?? 0) < 0 && $this->WidthCallback) {
            $wrapToWidth = ($this->WidthCallback)();
        }
        if (($wrapToWidth ?? 0) > 0) {
            $string = wordwrap($string, $wrapToWidth);
        }

        // If `$unescape` is false, entries in `$replace` may be out of order
        if (!$unescape) {
            usort($replace, fn(array $a, array $b): int => $a[0] <=> $b[0]);
        }

        $replace = array_reverse($replace);
        foreach ($replace as [$offset, $length, $replacement]) {
            $string = substr_replace($string, $replacement, $offset, $length);
        }

        if (PHP_EOL !== "\n") {
            $string = str_replace("\n", PHP_EOL, $string);
        }

        return $string . $append;
    }

    /**
     * Format a message
     *
     * @param Level::* $level
     * @param Type::* $type
     */
    public function formatMessage(
        string $msg1,
        ?string $msg2 = null,
        $level = Level::INFO,
        $type = Type::DEFAULT
    ): string {
        $attributes = [
            Attribute::LEVEL => $level,
            Attribute::TYPE => $type,
        ];

        if ($type === Type::UNFORMATTED) {
            return $this->getDefaultMessageFormats()
                ->get($level, $type)
                ->apply($msg1, $msg2, '', $attributes);
        }

        $prefix = $this->getMessagePrefix($level, $type);

        return $this->MessageFormats
            ->get($level, $type)
            ->apply($msg1, $msg2, $prefix, $attributes);
    }

    /**
     * Escape special characters, optionally including newlines, in a string
     *
     */
    public static function escapeTags(string $string, bool $newlines = false): string
    {
        $escaped = addcslashes($string, '\!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~');
        return $newlines
            ? str_replace("\n", "\\\n", $escaped)
            : $escaped;
    }

    /**
     * Remove inline formatting tags from a string
     *
     */
    public static function removeTags(string $string): string
    {
        return self::getDefaultFormatter()->formatTags($string);
    }

    private static function getDefaultFormatter(): self
    {
        return self::$DefaultFormatter
            ?? (self::$DefaultFormatter = new self());
    }

    private static function getDefaultTagFormats(): ConsoleTagFormats
    {
        return self::$DefaultTagFormats
            ?? (self::$DefaultTagFormats = new ConsoleTagFormats());
    }

    private static function getDefaultMessageFormats(): ConsoleMessageFormats
    {
        return self::$DefaultMessageFormats
            ?? (self::$DefaultMessageFormats = new ConsoleMessageFormats());
    }

    /**
     * @param array<int|string,array{string,int}|string> $match
     */
    private function applyTags(array $match, bool $matchHasOffset, bool $unescape, ConsoleTagFormats $formats): string
    {
        /** @var string */
        $text = $matchHasOffset ? $match['text'][0] : $match['text'];
        $text = Pcre::replaceCallback(
            Regex::delimit(self::TAG_REGEX) . 'u',
            fn(array $match): string =>
                $this->applyTags($match, false, $unescape, $formats),
            $text
        );

        if ($unescape) {
            $text = Pcre::replace(
                Regex::delimit(self::UNESCAPE_REGEX) . 'u', '$1', $text
            );
        }

        /** @var string */
        $tag = $matchHasOffset ? $match['tag'][0] : $match['tag'];
        switch ($tag) {
            case '___':
            case '***':
            case '##':
                return $formats->get(Tag::HEADING)->apply($text, [
                    Attribute::TAG => $tag,
                ]);

            case '__':
            case '**':
                return $formats->get(Tag::BOLD)->apply($text, [
                    Attribute::TAG => $tag,
                ]);

            case '_':
            case '*':
                return $formats->get(Tag::ITALIC)->apply($text, [
                    Attribute::TAG => $tag,
                ]);

            case '<':
                return $formats->get(Tag::UNDERLINE)->apply($text, [
                    Attribute::TAG => $tag,
                ]);

            case '~~':
                return $formats->get(Tag::LOW_PRIORITY)->apply($text, [
                    Attribute::TAG => $tag,
                ]);
        }

        throw new RuntimeException(sprintf('Invalid tag: %s', $tag));
    }
}
