<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Console\Format\ConsoleLoopbackFormat as LoopbackFormat;
use Salient\Console\Format\ConsoleMessageAttributes as MessageAttributes;
use Salient\Console\Format\ConsoleMessageFormat as MessageFormat;
use Salient\Console\Format\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Format\ConsoleTagAttributes as TagAttributes;
use Salient\Console\Format\ConsoleTagFormats as TagFormats;
use Salient\Contract\Console\Format\ConsoleTag as Tag;
use Salient\Contract\Console\Format\FormatInterface as Format;
use Salient\Contract\Console\Format\FormatterInterface;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use LogicException;
use UnexpectedValueException;

/**
 * Formats messages for a console output target
 */
final class ConsoleFormatter implements FormatterInterface
{
    use ImmutableTrait;

    public const DEFAULT_LEVEL_PREFIX_MAP = [
        Console::LEVEL_EMERGENCY => '! ',  // U+0021
        Console::LEVEL_ALERT => '! ',  // U+0021
        Console::LEVEL_CRITICAL => '! ',  // U+0021
        Console::LEVEL_ERROR => '! ',  // U+0021
        Console::LEVEL_WARNING => '^ ',  // U+005E
        Console::LEVEL_NOTICE => '➤ ',  // U+27A4
        Console::LEVEL_INFO => '- ',  // U+002D
        Console::LEVEL_DEBUG => ': ',  // U+003A
    ];

    public const DEFAULT_TYPE_PREFIX_MAP = [
        Console::TYPE_PROGRESS => '⠿ ',  // U+283F
        Console::TYPE_GROUP_START => '» ',  // U+00BB
        Console::TYPE_GROUP_END => '« ',  // U+00AB
        Console::TYPE_SUMMARY => '» ',  // U+00BB
        Console::TYPE_SUCCESS => '✔ ',  // U+2714
        Console::TYPE_FAILURE => '✘ ',  // U+2718
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
    /** @var array<Console::LEVEL_*,string> */
    private array $LevelPrefixMap;
    /** @var array<Console::TYPE_*,string> */
    private array $TypePrefixMap;
    /** @var array{int<0,max>,float} */
    private array $SpinnerState;

    /**
     * @param (callable(): (int|null))|null $widthCallback
     * @param array<Console::LEVEL_*,string> $levelPrefixMap
     * @param array<Console::TYPE_*,string> $typePrefixMap
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
     * @inheritDoc
     */
    public function withSpinnerState(?array &$state)
    {
        if ($state === null) {
            $state = [0, 0.0];
        }
        $clone = clone $this;
        $clone->SpinnerState = &$state;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withUnescape(bool $value = true)
    {
        return $this->with('TagFormats', $this->TagFormats->withUnescape($value));
    }

    /**
     * @inheritDoc
     */
    public function withWrapAfterApply(bool $value = true)
    {
        return $this->with('TagFormats', $this->TagFormats->withWrapAfterApply($value));
    }

    /**
     * @inheritDoc
     */
    public function getTagFormat(int $tag): Format
    {
        return $this->TagFormats->getFormat($tag);
    }

    /**
     * @inheritDoc
     */
    public function getMessageFormat(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): MessageFormat {
        return $this->MessageFormats->get($level, $type);
    }

    /**
     * @inheritDoc
     */
    public function getUnescape(): bool
    {
        return $this->TagFormats->getUnescape();
    }

    /**
     * @inheritDoc
     */
    public function getWrapAfterApply(): bool
    {
        return $this->TagFormats->getWrapAfterApply();
    }

    /**
     * @inheritDoc
     */
    public function getMessagePrefix(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): string {
        if ($type === Console::TYPE_UNFORMATTED || $type === Console::TYPE_UNDECORATED) {
            return '';
        }
        if ($type === Console::TYPE_PROGRESS && isset($this->SpinnerState)) {
            $frames = count(self::SPINNER);
            $prefix = self::SPINNER[$this->SpinnerState[0] % $frames] . ' ';
            $now = (float) (hrtime(true) / 1000);
            if ($now - $this->SpinnerState[1] >= 80000) {
                $this->SpinnerState[0]++;
                $this->SpinnerState[0] %= $frames;
                $this->SpinnerState[1] = $now;
            }
        }
        return $prefix
            ?? $this->TypePrefixMap[$type]
            ?? $this->LevelPrefixMap[$level]
            ?? '';
    }

    /**
     * @inheritDoc
     */
    public function format(
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
                    /** @var string */
                    $breaks = substr(Str::unwrap(".$breaks.", "\n", false, true, true), 1, -1);
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
                    /** @var string */
                    $text = substr(Str::unwrap(".$text.", "\n", false, true, true), 1, -1);
                }

                $adjust = 0;
                $text = Regex::replaceCallback(
                    self::TAG,
                    function ($matches) use (
                        &$replace,
                        $textFormats,
                        $formattedFormats,
                        $baseOffset,
                        &$adjust
                    ): string {
                        $text = $this->applyTags(
                            $matches,
                            true,
                            $textFormats->getUnescape(),
                            $textFormats
                        );
                        $placeholder = Regex::replace('/[^ ]/u', 'x', $text);
                        $formatted = $textFormats === $formattedFormats
                            ? $text
                            : $this->applyTags(
                                $matches,
                                true,
                                $formattedFormats->getUnescape(),
                                $formattedFormats
                            );
                        $replace[] = [
                            $baseOffset + $matches[0][1] + $adjust,
                            strlen($placeholder),
                            $formatted,
                        ];
                        $adjust += strlen($placeholder) - strlen($matches[0][0]);
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
            function ($matches) use (
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
                    if ($matches[1][0] !== ' ') {
                        return $matches[0][0];
                    }
                    $placeholder = '\x';
                    $replace[] = [
                        $matches[0][1] + $adjust,
                        strlen($placeholder),
                        $matches[0][0],
                    ];
                    return $placeholder;
                }

                $delta = strlen($matches[1][0]) - strlen($matches[0][0]);
                foreach ($adjustable as $i => $offset) {
                    if ($offset < $matches[0][1]) {
                        continue;
                    }
                    $replace[$i][0] += $delta;
                }

                $placeholder = null;
                if ($matches[1][0] === ' ') {
                    $placeholder = 'x';
                }

                if ($unformat || !$unescape || $placeholder !== null) {
                    // Use `$replace` to reinstate the escape after wrapping
                    $replace[] = [
                        $matches[0][1] + $adjust,
                        strlen($matches[1][0]),
                        $unformat || !$unescape ? $matches[0][0] : $matches[1][0],
                    ];
                }

                $adjust += $delta;

                return $placeholder ?? $matches[1][0];
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
            if ($break === "\n") {
                $string = Str::wrap($string, $wrapToWidth);
            } else {
                // Only replace new line breaks with `$break`
                $wrapped = Str::wrap($string, $wrapToWidth);
                $length = strlen($wrapped);
                for ($i = 0; $i < $length; $i++) {
                    if ($wrapped[$i] === "\n" && $string[$i] !== "\n") {
                        $replace[] = [$i, 1, $break];
                    }
                }
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
     * @inheritDoc
     */
    public function formatMessage(
        string $msg1,
        ?string $msg2 = null,
        int $level = Console::LEVEL_INFO,
        int $type = Console::TYPE_STANDARD
    ): string {
        $attributes = new MessageAttributes($level, $type);

        if ($type === Console::TYPE_UNFORMATTED) {
            return $this
                ->getDefaultMessageFormats()
                ->get($level, $type)
                ->apply($msg1, $msg2, '', $attributes);
        }

        $prefix = $this->getMessagePrefix($level, $type);

        return $this
            ->MessageFormats
            ->get($level, $type)
            ->apply($msg1, $msg2, $prefix, $attributes);
    }

    /**
     * @inheritDoc
     */
    public function formatDiff(string $diff): string
    {
        $formats = [
            '---' => $this->TagFormats->getFormat(Tag::DIFF_HEADER),
            '+++' => $this->TagFormats->getFormat(Tag::DIFF_HEADER),
            '@' => $this->TagFormats->getFormat(Tag::DIFF_RANGE),
            '+' => $this->TagFormats->getFormat(Tag::DIFF_ADDITION),
            '-' => $this->TagFormats->getFormat(Tag::DIFF_REMOVAL),
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
    public static function escapeTags(string $string, bool $escapeNewlines = false): string
    {
        // Only escape recognised tag delimiters to minimise the risk of
        // PREG_JIT_STACKLIMIT_ERROR
        $escaped = addcslashes($string, '\#*<>_`~');
        return $escapeNewlines
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
        return self::getDefaultFormatter()->format($string);
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
     * @param array<int|string,array{string,int}|string> $matches
     */
    private function applyTags(
        array $matches,
        bool $matchesHasOffset,
        bool $unescape,
        TagFormats $formats,
        int $depth = 0
    ): string {
        /** @var string */
        $text = $matchesHasOffset ? $matches['text'][0] : $matches['text'];
        /** @var string */
        $tag = $matchesHasOffset ? $matches['tag'][0] : $matches['tag'];

        $text = Regex::replaceCallback(
            self::TAG,
            fn(array $matches): string =>
                $this->applyTags($matches, false, $unescape, $formats, $depth + 1),
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
}
