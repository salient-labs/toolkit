<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Catalog\ConsoleTag as Tag;
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
          ^   (?<tag> \#\# ) \h+           (?> (?<text> (?: [^\#\v\\]+ | (?&esc) | (?! (?<! \s ) (?: \h+ \#+ | \h* ) $ ) \# + )* ) ) (?<! \s ) (?: \h+ \#+ | \h* ) $
        )
        REGEX;

    /**
     * Matches a Markdown-compatible backslash escape
     *
     */
    private const UNESCAPE_PUNCTUATION_REGEX = <<<'REGEX'
        (?x)
        \\ ( [-\\!"\#$%&'()*+,./:;<=>?@[\]^_`{|}~] )
        REGEX;

    /**
     * Matches an escaped line break with an optional leading space
     *
     */
    private const UNESCAPE_LINE_BREAK_REGEX = <<<'REGEX'
        (?x)
        (?<! \\ ) (?: \\\\ )* \K \  ? \\ ( \n )
        REGEX;

    private static ConsoleFormatter $DefaultFormatter;

    private static ConsoleTagFormats $DefaultTagFormats;

    private ConsoleTagFormats $TagFormats;

    private bool $PreserveEscapes;

    public function __construct(?ConsoleTagFormats $tagFormats = null, bool $preserveEscapes = false)
    {
        $this->TagFormats = $tagFormats ?: $this->getDefaultTagFormats();
        $this->PreserveEscapes = $preserveEscapes;
    }

    /**
     * Format a string
     *
     * This method applies target-defined formats to text that may contain
     * Markdown-like inline formatting tags. Paragraphs outside preformatted
     * blocks are optionally wrapped to a given width, and backslash-escaped
     * punctuation characters and line breaks are preserved.
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
     */
    public function format(string $string, bool $unwrap = false, ?int $width = null): string
    {
        if ($string === '') {
            return '';
        }

        /**
         * [ [ Offset, length, replacement ] ]
         *
         * @var array<array{int,int,string}>
         */
        $replace = [];
        $append = '';
        $plainTagFormats = $this->getDefaultTagFormats();

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
            $baseOffset = strlen($string);

            if (($text = $match['text']) !== null) {
                if (strpos($text, "\n") !== false) {
                    if ($unwrap) {
                        $text = Convert::unwrap($text, "\n", false, true, true);
                    }
                    $text = Pcre::replace(
                        Regex::delimit(self::UNESCAPE_LINE_BREAK_REGEX) . 'u', '$1', $text
                    );
                }

                $adjust = 0;
                $text = Pcre::replaceCallback(
                    Regex::delimit(self::TAG_REGEX) . 'u',
                    function (array $match) use (
                        &$replace,
                        $plainTagFormats,
                        $baseOffset,
                        &$adjust
                    ): string {
                        /** @var array<int|string,array{string|null,int}> $match */
                        $text = $this->applyTags($match, $plainTagFormats);
                        $placeholder = Pcre::replace('/[^ ]/', 'x', $text);
                        $formatted =
                            $plainTagFormats === $this->TagFormats
                                ? $text
                                : $this->applyTags($match, $this->TagFormats);
                        $replace[] = [
                            $baseOffset + $match['tag'][1] + $adjust,
                            strlen($placeholder),
                            $formatted
                        ];
                        $adjust += strlen($text) - strlen($match[0][0]);
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
                // Preserve newline before (may have been unwrapped)
                if ($string !== '') {
                    $string[-1] = "\n";
                }

                $infostring = trim($match['infostring']);
                $formatted = $this->TagFormats[Tag::CODE_BLOCK]->apply(
                    $block, $match['fence'], ['infoString' => $infostring === '' ? null : $infostring]
                );
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
                    '/^ ((?> *[^ ]+).*) $/',
                    '$1',
                    strtr($span, "\n", ' '),
                );
                $formatted = $this->TagFormats[Tag::CODE_SPAN]->apply(
                    $span, $match['backtickstring']
                );
                $placeholder = Pcre::replace('/[^ ]/', 'x', $span);
                $replace[] = [
                    $baseOffset,
                    strlen($placeholder),
                    $formatted,
                ];

                $string .= $placeholder;
                continue;
            }

            // Treat unmatched backticks as plain text
            if (($extra = $match['extra']) !== null) {
                $string .= $extra;
            }
        }

        // Remove backslash escapes and adjust the offsets of any subsequent
        // replacement strings
        if (!$this->PreserveEscapes) {
            $adjustable = [];
            foreach ($replace as $i => [$offset]) {
                $adjustable[$i] = $offset;
            }
            $string = Pcre::replaceCallback(
                Regex::delimit(self::UNESCAPE_PUNCTUATION_REGEX) . 'u',
                function (array $match) use (&$replace, &$adjustable): string {
                    // Offsets in `$adjustable` aren't changed, and preg_replace
                    // offsets are relative to `$string` before removing any
                    // escapes, so it's safe to discard `$adjustable` entries
                    // for replacements earlier in `$string` than this escape
                    if ($adjustable) {
                        foreach ($adjustable as $i => $offset) {
                            if ($offset < $match[0][1]) {
                                unset($adjustable[$i]);
                                continue;
                            }
                            $replace[$i][0]--;
                        }
                    }
                    return $match[1][0];
                },
                $string,
                -1,
                $count,
                PREG_OFFSET_CAPTURE,
            );
        }

        if ($width !== null && $width > 0) {
            $string = wordwrap($string, $width);
        }

        // Perform formatted text replacement
        $replace = array_reverse($replace);
        foreach ($replace as [$offset, $length, $replacement]) {
            $string = substr_replace($string, $replacement, $offset, $length);
        }

        return $string . $append;
    }

    /**
     * Escape special characters, optionally including newlines, in a string
     *
     */
    public static function escape(
        string $string, bool $newlines = false
    ): string {
        $escaped = addcslashes($string, '\!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~');
        return $newlines
            ? str_replace("\n", "\\\n", $escaped)
            : $escaped;
    }

    /**
     * Remove inline formatting from a string
     *
     */
    public static function removeTags(string $string): string
    {
        return self::getDefaultFormatter()->format($string);
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

    /**
     * @param array<int|string,array{string|null,int}|string|null> $match
     */
    private function applyTags(array $match, ConsoleTagFormats $formats): string
    {
        /** @var string */
        $text = $match['text'][0] ?? $match['text'];
        $text = Pcre::replaceCallback(
            Regex::delimit(self::TAG_REGEX) . 'u',
            fn(array $match): string =>
                $this->applyTags($match, $formats),
            $text
        );

        if (!$this->PreserveEscapes) {
            $text = preg_replace(
                Regex::delimit(self::UNESCAPE_PUNCTUATION_REGEX) . 'u', '$1', $text
            );
        }

        /** @var string */
        $tag = $match['tag'][0] ?? $match['tag'];
        switch ($tag) {
            case '___':
            case '***':
            case '##':
                return $formats[Tag::HEADING]->apply($text, $tag);

            case '__':
            case '**':
                return $formats[Tag::BOLD]->apply($text, $tag);

            case '_':
            case '*':
                return $formats[Tag::ITALIC]->apply($text, $tag);

            case '<':
                return $formats[Tag::UNDERLINE]->apply($text, $tag);

            case '~~':
                return $formats[Tag::LOW_PRIORITY]->apply($text, $tag);
        }

        throw new RuntimeException(sprintf('Invalid tag: %s', $tag));
    }
}
