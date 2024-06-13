<?php declare(strict_types=1);

namespace Salient\Console;

use Salient\Console\Contract\ConsoleFormatInterface as Format;
use Salient\Console\Support\ConsoleLoopbackFormat as LoopbackFormat;
use Salient\Console\Support\ConsoleMessageAttributes as MessageAttributes;
use Salient\Console\Support\ConsoleMessageFormat as MessageFormat;
use Salient\Console\Support\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Support\ConsoleTagAttributes as TagAttributes;
use Salient\Console\Support\ConsoleTagFormats as TagFormats;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleTag as Tag;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use LogicException;
use UnexpectedValueException;

/**
 * Formats messages for a console output target
 */
final class ConsoleFormatter
{
    use HasImmutableProperties;

    public const DEFAULT_LEVEL_PREFIX_MAP = [
        Level::EMERGENCY => '! ',  // U+0021
        Level::ALERT => '! ',  // U+0021
        Level::CRITICAL => '! ',  // U+0021
        Level::ERROR => '! ',  // U+0021
        Level::WARNING => '? ',  // U+003F
        Level::NOTICE => '➤ ',  // U+27A4
        Level::INFO => '- ',  // U+002D
        Level::DEBUG => ': ',  // U+003A
    ];

    public const DEFAULT_TYPE_PREFIX_MAP = [
        MessageType::PROGRESS => '⠿ ',  // U+283F
        MessageType::GROUP_START => '» ',  // U+00BB
        MessageType::GROUP_END => '« ',  // U+00AB
        MessageType::SUMMARY => '» ',  // U+00BB
        MessageType::SUCCESS => '✔ ',  // U+2714
        MessageType::FAILURE => '✘ ',  // U+2718
    ];

    /** @link https://github.com/sindresorhus/cli-spinners */
    private const SPINNER = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    /**
     * @var array<string,int&Tag::*>
     */
    private const TAG_MAP = [
        '___' => Tag::HEADING,
        '***' => Tag::HEADING,
        '##' => Tag::HEADING,
        '__' => Tag::BOLD,
        '**' => Tag::BOLD,
        '_' => Tag::ITALIC,
        '*' => Tag::ITALIC,
        '<' => Tag::UNDERLINE,
        '~~' => Tag::LOW_PRIORITY,
    ];

    /**
     * Splits the subject into formattable paragraphs, fenced code blocks and
     * code spans
     */
    private const MARKUP = <<<'REGEX'
/
(?(DEFINE)
  (?<endofline> \h*+ \n )
  (?<endofblock> ^ \k<indent> \k<fence> \h*+ $ )
  (?<endofspan> \k<backtickstring> (?! ` ) )
)
# Do not allow gaps between matches
\G
# Do not allow empty matches
(?= . )
# Claim indentation early so horizontal whitespace before fenced code
# blocks is not mistaken for text
(?<indent> ^ \h*+ )?
(?:
  # Whitespace before paragraphs
  (?<breaks> (?&endofline)+ ) |
  # Everything except unescaped backticks until the start of the next
  # paragraph
  (?<text> (?> (?: [^\\`\n]+ | \\ [-\\!"\#$%&'()*+,.\/:;<=>?@[\]^_`{|}~\n] | \\ | \n (?! (?&endofline) ) )+ (?&endofline)* ) ) |
  # CommonMark-compliant fenced code blocks
  (?> (?(indent)
    (?> (?<fence> ```+ ) (?<infostring> [^\n]* ) \n )
    # Match empty blocks--with no trailing newline--and blocks with an
    # empty line by making the subsequent newline conditional on inblock
    (?<block> (?> (?<inblock> (?: (?! (?&endofblock) ) (?: \k<indent> | (?= (?&endofline) ) ) [^\n]* (?: (?= \n (?&endofblock) ) | \n | \z ) )+ )? ) )
    # Allow code fences to terminate at the end of the subject
    (?: (?(inblock) \n ) (?&endofblock) | \z ) | \z
  ) ) |
  # CommonMark-compliant code spans
  (?<backtickstring> (?> `+ ) ) (?<span> (?> (?: [^`]+ | (?! (?&endofspan) ) `+ )* ) ) (?&endofspan) |
  # Unmatched backticks
  (?<extra> `+ ) |
  \z
) /mxs
REGEX;

    /**
     * Matches inline formatting tags used outside fenced code blocks and code
     * spans
     */
    private const TAG = <<<'REGEX'
/
(?(DEFINE)
  (?<esc> \\ [-\\!"\#$%&'()*+,.\/:;<=>?@[\]^_`{|}~] | \\ )
)
(?<! \\ ) (?: \\\\ )* \K (?|
  \b  (?<tag> _ {1,3}+ )  (?! \s ) (?> (?<text> (?: [^_\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> \b ) _ + )* ) ) (?<! \s ) \k<tag> \b |
      (?<tag> \* {1,3}+ ) (?! \s ) (?> (?<text> (?: [^*\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> ) \* + )* ) )   (?<! \s ) \k<tag>    |
      (?<tag> < )         (?! \s ) (?> (?<text> (?: [^>\\]+ |    (?&esc) | (?! (?<! \s ) > ) > + )* ) )          (?<! \s ) >          |
      (?<tag> ~~ )        (?! \s ) (?> (?<text> (?: [^~\\]+ |    (?&esc) | (?! (?<! \s ) ~~ ) ~ + )* ) )         (?<! \s ) ~~         |
  ^   (?<tag> \#\# ) \h+           (?> (?<text> (?: [^\#\s\\]+ | (?&esc) | \#+ (?! \h* $ ) | \h++ (?! (?: \#+ \h* )? $ ) )* ) ) (?: \h+ \#+ | \h* ) $
) /mx
REGEX;

    /**
     * Matches a CommonMark-compliant backslash escape, or an escaped line break
     * with an optional leading space
     */
    private const ESCAPE = <<<'REGEX'
/
(?|
  \\ ( [-\\ !"\#$%&'()*+,.\/:;<=>?@[\]^_`{|}~] ) |
  # Lookbehind assertions are unnecessary because the first branch
  # matches escaped spaces and backslashes
  \  ? \\ ( \n )
) /x
REGEX;

    private static ConsoleFormatter $DefaultFormatter;
    private static TagFormats $DefaultTagFormats;
    private static MessageFormats $DefaultMessageFormats;
    private static TagFormats $LoopbackTagFormats;
    private TagFormats $TagFormats;
    private MessageFormats $MessageFormats;
    /** @var callable(): (int|null) */
    private $WidthCallback;
    /** @var array<Level::*,string> */
    private array $LevelPrefixMap;
    /** @var array<MessageType::*,string> */
    private array $TypePrefixMap;

    /**
     * @param (callable(): (int|null))|null $widthCallback
     * @param array<Level::*,string> $levelPrefixMap
     * @param array<MessageType::*,string> $typePrefixMap
     */
    public function __construct(
        ?TagFormats $tagFormats = null,
        ?MessageFormats $messageFormats = null,
        ?callable $widthCallback = null,
        array $levelPrefixMap = ConsoleFormatter::DEFAULT_LEVEL_PREFIX_MAP,
        array $typePrefixMap = ConsoleFormatter::DEFAULT_TYPE_PREFIX_MAP
    ) {
        $this->TagFormats = $tagFormats ?: $this->getDefaultTagFormats();
        $this->MessageFormats = $messageFormats ?: $this->getDefaultMessageFormats();
        $this->WidthCallback = $widthCallback ?: fn(): ?int => null;
        $this->LevelPrefixMap = $levelPrefixMap;
        $this->TypePrefixMap = $typePrefixMap;
    }

    /**
     * @return static
     */
    public function withUnescape(bool $value = true)
    {
        return $this->withPropertyValue('TagFormats', $this->TagFormats->withUnescape($value));
    }

    /**
     * @return static
     */
    public function withWrapAfterApply(bool $value = true)
    {
        return $this->withPropertyValue('TagFormats', $this->TagFormats->withWrapAfterApply($value));
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
     * @param MessageType::* $type
     */
    public function getMessageFormat($level, $type = MessageType::STANDARD): MessageFormat
    {
        return $this->MessageFormats->get($level, $type);
    }

    /**
     * True if text should be unescaped for the target
     */
    public function getUnescape(): bool
    {
        return $this->TagFormats->getUnescape();
    }

    /**
     * True if text should be wrapped after formatting
     */
    public function getWrapAfterApply(): bool
    {
        return $this->TagFormats->getWrapAfterApply();
    }

    /**
     * Get the prefix assigned to a message level and type
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @param array{int<0,max>,float}|null $spinnerState
     */
    public function getMessagePrefix(
        $level,
        $type = MessageType::STANDARD,
        ?array &$spinnerState = null
    ): string {
        if ($type === MessageType::UNFORMATTED || $type === MessageType::UNDECORATED) {
            return '';
        }
        if ($type === MessageType::PROGRESS && $spinnerState !== null) {
            $frames = count(self::SPINNER);
            $prefix = self::SPINNER[$spinnerState[0] % $frames] . ' ';
            $now = (float) (hrtime(true) / 1000);
            if ($now - $spinnerState[1] >= 80000) {
                $spinnerState[0]++;
                $spinnerState[0] %= $frames;
                $spinnerState[1] = $now;
            }
        }
        return $prefix
            ?? $this->TypePrefixMap[$type]
            ?? $this->LevelPrefixMap[$level]
            ?? '';
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
    public function formatTags(
        string $string,
        bool $unwrap = false,
        $wrapToWidth = null,
        bool $unformat = false,
        string $break = "\n"
    ): string {
        if ($string === '' || $string === "\r") {
            return $string;
        }

        // [ [ Offset, length, replacement ] ]
        /** @var array<array{int,int,string}> */
        $replace = [];
        $append = '';
        $unescape = $this->getUnescape();
        $wrapAfterApply = $this->getWrapAfterApply();
        $textFormats = $wrapAfterApply
            ? $this->TagFormats
            : $this->getDefaultTagFormats();
        $formattedFormats = $unformat
            ? $this->getLoopbackTagFormats()
            : $this->TagFormats;

        // Preserve trailing carriage returns
        if ($string[-1] === "\r") {
            $append .= "\r";
            $string = substr($string, 0, -1);
        }

        // Normalise line endings and split the string into formattable text,
        // fenced code blocks and code spans
        if (!Regex::matchAll(
            self::MARKUP,
            Str::setEol($string),
            $matches,
            \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL
        )) {
            throw new UnexpectedValueException(
                sprintf('Unable to parse: %s', $string)
            );
        }

        $string = '';
        foreach ($matches as $match) {
            $indent = (string) $match['indent'];

            if ($match['breaks'] !== null) {
                $breaks = $match['breaks'];
                if ($unwrap && strpos($breaks, "\n") !== false) {
                    $breaks = Str::unwrap($breaks, "\n", false, true, true);
                }
                $string .= $indent . $breaks;
                continue;
            }

            // Treat unmatched backticks as plain text
            if ($match['extra'] !== null) {
                $string .= $indent . $match['extra'];
                continue;
            }

            $baseOffset = strlen($string . $indent);

            if ($match['text'] !== null) {
                $text = $match['text'];
                if ($unwrap && strpos($text, "\n") !== false) {
                    $text = Str::unwrap($text, "\n", false, true, true);
                }

                $adjust = 0;
                $text = Regex::replaceCallback(
                    self::TAG,
                    function (array $match) use (
                        &$replace,
                        $textFormats,
                        $formattedFormats,
                        $baseOffset,
                        &$adjust
                    ): string {
                        $text = $this->applyTags(
                            $match,
                            true,
                            $textFormats->getUnescape(),
                            $textFormats
                        );
                        $placeholder = Regex::replace('/[^ ]/u', 'x', $text);
                        $formatted = $textFormats === $formattedFormats
                            ? $text
                            : $this->applyTags(
                                $match,
                                true,
                                $formattedFormats->getUnescape(),
                                $formattedFormats
                            );
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
                    \PREG_OFFSET_CAPTURE
                );

                $string .= $indent . $text;
                continue;
            }

            if ($match['block'] !== null) {
                // Reinstate unwrapped newlines before blocks
                if ($unwrap && $string !== '' && $string[-1] !== "\n") {
                    $string[-1] = "\n";
                }

                /** @var array{fence:string,infostring:string,block:string} $match */
                $formatted = $formattedFormats->apply(
                    $match['block'],
                    new TagAttributes(
                        Tag::CODE_BLOCK,
                        $match['fence'],
                        0,
                        false,
                        $indent,
                        Str::coalesce(trim($match['infostring']), null),
                    )
                );
                $placeholder = '?';
                $replace[] = [
                    $baseOffset,
                    1,
                    $formatted,
                ];

                $string .= $indent . $placeholder;
                continue;
            }

            if ($match['span'] !== null) {
                /** @var array{backtickstring:string,span:string} $match */
                $span = $match['span'];
                // As per CommonMark:
                // - Convert line endings to spaces
                // - If the string begins and ends with a space but doesn't
                //   consist entirely of spaces, remove both
                $span = Regex::replace(
                    '/^ ((?> *[^ ]+).*) $/u',
                    '$1',
                    strtr($span, "\n", ' '),
                );
                $attributes = new TagAttributes(
                    Tag::CODE_SPAN,
                    $match['backtickstring'],
                );
                $text = $textFormats->apply($span, $attributes);
                $placeholder = Regex::replace('/[^ ]/u', 'x', $text);
                $formatted = $textFormats === $formattedFormats
                    ? $text
                    : $formattedFormats->apply($span, $attributes);
                $replace[] = [
                    $baseOffset,
                    strlen($placeholder),
                    $formatted,
                ];

                $string .= $indent . $placeholder;
                continue;
            }
        }

        // Remove backslash escapes and adjust the offsets of any subsequent
        // replacement strings
        $replacements = count($replace);
        $adjustable = [];
        foreach ($replace as $i => [$offset]) {
            $adjustable[$i] = $offset;
        }
        $adjust = 0;
        $string = Regex::replaceCallback(
            self::ESCAPE,
            function (array $match) use (
                $unformat,
                $unescape,
                $wrapAfterApply,
                &$replace,
                &$adjustable,
                &$adjust
            ): string {
                // If the escape character is being wrapped, do nothing other
                // than temporarily replace "\ " with "\x"
                if ($wrapAfterApply && !$unescape) {
                    if ($match[1][0] !== ' ') {
                        return $match[0][0];
                    }
                    $placeholder = '\x';
                    $replace[] = [
                        $match[0][1] + $adjust,
                        strlen($placeholder),
                        $match[0][0],
                    ];
                    return $placeholder;
                }

                $delta = strlen($match[1][0]) - strlen($match[0][0]);
                foreach ($adjustable as $i => $offset) {
                    if ($offset < $match[0][1]) {
                        continue;
                    }
                    $replace[$i][0] += $delta;
                }

                $placeholder = null;
                if ($match[1][0] === ' ') {
                    $placeholder = 'x';
                }

                if ($unformat || !$unescape || $placeholder !== null) {
                    // Use `$replace` to reinstate the escape after wrapping
                    $replace[] = [
                        $match[0][1] + $adjust,
                        strlen($match[1][0]),
                        $unformat || !$unescape ? $match[0][0] : $match[1][0],
                    ];
                }

                $adjust += $delta;

                return $placeholder ?? $match[1][0];
            },
            $string,
            -1,
            $count,
            \PREG_OFFSET_CAPTURE
        );

        if (is_array($wrapToWidth)) {
            for ($i = 0; $i < 2; $i++) {
                if ($wrapToWidth[$i] <= 0) {
                    $width ??= ($this->WidthCallback)();
                    if ($width === null) {
                        $wrapToWidth = null;
                        break;
                    }
                    $wrapToWidth[$i] = max(0, $wrapToWidth[$i] + $width);
                }
            }
        } elseif (
            is_int($wrapToWidth)
            && $wrapToWidth <= 0
        ) {
            $width = ($this->WidthCallback)();
            $wrapToWidth =
                $width === null
                    ? null
                    : max(0, $wrapToWidth + $width);
        }
        if ($wrapToWidth !== null) {
            if (strlen($break) === 1) {
                $string = Str::wordwrap($string, $wrapToWidth, $break);
            } else {
                if (strpos($string, "\x7f") !== false) {
                    $string = $this->insertPlaceholders($string, '/\x7f/', $replace);
                }
                $string = Str::wordwrap($string, $wrapToWidth, "\x7f");
                $string = $this->insertPlaceholders($string, '/\x7f/', $replace, "\n", $break);
            }
        }

        // Get `$replace` in reverse offset order, sorting from scratch if any
        // substitutions were made in the callbacks above
        if (count($replace) !== $replacements) {
            usort($replace, fn(array $a, array $b): int => $b[0] <=> $a[0]);
        } else {
            $replace = array_reverse($replace);
        }

        foreach ($replace as [$offset, $length, $replacement]) {
            $string = substr_replace($string, $replacement, $offset, $length);
        }

        return $string . $append;
    }

    /**
     * Format a message
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @param array{int<0,max>,float}|null $spinnerState
     */
    public function formatMessage(
        string $msg1,
        ?string $msg2 = null,
        $level = Level::INFO,
        $type = MessageType::STANDARD,
        ?array &$spinnerState = null
    ): string {
        $attributes = new MessageAttributes($level, $type);

        if ($type === MessageType::UNFORMATTED) {
            return $this
                ->getDefaultMessageFormats()
                ->get($level, $type)
                ->apply($msg1, $msg2, '', $attributes);
        }

        $prefix = $this->getMessagePrefix($level, $type, $spinnerState);

        return $this
            ->MessageFormats
            ->get($level, $type)
            ->apply($msg1, $msg2, $prefix, $attributes);
    }

    /**
     * Format a unified diff
     */
    public function formatDiff(string $diff): string
    {
        $formats = [
            '---' => $this->TagFormats->get(Tag::DIFF_HEADER),
            '+++' => $this->TagFormats->get(Tag::DIFF_HEADER),
            '@' => $this->TagFormats->get(Tag::DIFF_RANGE),
            '+' => $this->TagFormats->get(Tag::DIFF_ADDITION),
            '-' => $this->TagFormats->get(Tag::DIFF_REMOVAL),
        ];

        return Regex::replaceCallback(
            '/^(-{3}|\+{3}|[-+@]).*/m',
            fn(array $matches) => $formats[$matches[1]]->apply($matches[0]),
            $diff,
        );
    }

    /**
     * Escape special characters, optionally including newlines, in a string
     */
    public static function escapeTags(string $string, bool $newlines = false): string
    {
        // Only escape recognised tag delimiters to minimise the risk of
        // PREG_JIT_STACKLIMIT_ERROR
        $escaped = addcslashes($string, '\#*<>_`~');
        return $newlines
            ? str_replace("\n", "\\\n", $escaped)
            : $escaped;
    }

    /**
     * Unescape special characters in a string
     */
    public static function unescapeTags(string $string): string
    {
        return Regex::replace(
            self::ESCAPE,
            '$1',
            $string,
        );
    }

    /**
     * Remove inline formatting tags from a string
     */
    public static function removeTags(string $string): string
    {
        return self::getDefaultFormatter()->formatTags($string);
    }

    private static function getDefaultFormatter(): self
    {
        return self::$DefaultFormatter ??= new self();
    }

    private static function getDefaultTagFormats(): TagFormats
    {
        return self::$DefaultTagFormats ??= new TagFormats();
    }

    private static function getDefaultMessageFormats(): MessageFormats
    {
        return self::$DefaultMessageFormats ??= new MessageFormats();
    }

    private static function getLoopbackTagFormats(): TagFormats
    {
        return self::$LoopbackTagFormats ??= LoopbackFormat::getTagFormats();
    }

    /**
     * @param array<int|string,array{string,int}|string> $match
     */
    private function applyTags(
        array $match,
        bool $matchHasOffset,
        bool $unescape,
        TagFormats $formats,
        int $depth = 0
    ): string {
        /** @var string */
        $text = $matchHasOffset ? $match['text'][0] : $match['text'];
        $tag = $matchHasOffset ? $match['tag'][0] : $match['tag'];

        $text = Regex::replaceCallback(
            self::TAG,
            fn(array $match): string =>
                $this->applyTags($match, false, $unescape, $formats, $depth + 1),
            $text,
            -1,
            $count,
        );

        if ($unescape) {
            $text = Regex::replace(
                self::ESCAPE,
                '$1',
                $text,
            );
        }

        $tagId = self::TAG_MAP[$tag] ?? null;
        if ($tagId === null) {
            throw new LogicException(sprintf('Invalid tag: %s', $tag));
        }

        return $formats->apply(
            $text,
            new TagAttributes($tagId, $tag, $depth, (bool) $count)
        );
    }

    /**
     * @param array<array{int,int,string}> $replace
     */
    private function insertPlaceholders(
        string $string,
        string $pattern,
        array &$replace,
        string $placeholder = 'x',
        ?string $replacement = null
    ): string {
        return Regex::replaceCallback(
            $pattern,
            function (array $match) use (
                $placeholder,
                $replacement,
                &$replace
            ): string {
                $replacement ??= $match[0][0];
                $replace[] = [
                    $match[0][1],
                    strlen($placeholder),
                    $replacement,
                ];
                return $placeholder;
            },
            $string,
            -1,
            $count,
            \PREG_OFFSET_CAPTURE
        );
    }
}
