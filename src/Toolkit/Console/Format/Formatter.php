<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Console\HasConsoleRegex;
use Salient\Contract\Console\Format\FormatInterface;
use Salient\Contract\Console\Format\FormatterInterface;
use Salient\Contract\Console\Format\MessageFormatInterface;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use LogicException;

/**
 * @api
 */
class Formatter implements FormatterInterface, HasConsoleRegex
{
    use ImmutableTrait;

    public const DEFAULT_LEVEL_PREFIX_MAP = [
        Console::LEVEL_EMERGENCY => '! ',
        Console::LEVEL_ALERT => '! ',
        Console::LEVEL_CRITICAL => '! ',
        Console::LEVEL_ERROR => '! ',
        Console::LEVEL_WARNING => '^ ',
        Console::LEVEL_NOTICE => '> ',
        Console::LEVEL_INFO => '- ',
        Console::LEVEL_DEBUG => ': ',
    ];

    public const DEFAULT_TYPE_PREFIX_MAP = [
        Console::TYPE_PROGRESS => '⠿ ',  // U+283F
        Console::TYPE_GROUP_START => '» ',  // U+00BB
        Console::TYPE_GROUP_END => '« ',  // U+00AB
        Console::TYPE_SUMMARY => '» ',  // U+00BB
        Console::TYPE_SUCCESS => '✔ ',  // U+2714
        Console::TYPE_FAILURE => '✘ ',  // U+2718
    ];

    /**
     * @link https://github.com/sindresorhus/cli-spinners
     *
     * @var list<string>
     */
    protected const SPINNER_FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    private const TAG_MAP = [
        '___' => self::TAG_HEADING,
        '***' => self::TAG_HEADING,
        '##' => self::TAG_HEADING,
        '__' => self::TAG_BOLD,
        '**' => self::TAG_BOLD,
        '_' => self::TAG_ITALIC,
        '*' => self::TAG_ITALIC,
        '<' => self::TAG_UNDERLINE,
        '~~' => self::TAG_LOW_PRIORITY,
    ];

    private static self $NullFormatter;
    private static TagFormats $NullTagFormats;
    private static MessageFormats $NullMessageFormats;
    private static TagFormats $LoopbackTagFormats;
    private TagFormats $TagFormats;
    private MessageFormats $MessageFormats;
    /** @var Closure(): (int|null) */
    private Closure $WidthCallback;
    /** @var array<Console::LEVEL_*,string> */
    private array $LevelPrefixMap;
    /** @var array<Console::TYPE_*,string> */
    private array $TypePrefixMap;
    /** @var array{int<0,max>,float|null} */
    private array $SpinnerState;

    /**
     * @api
     *
     * @param (Closure(): (int|null))|null $widthCallback
     * @param array<Console::LEVEL_*,string> $levelPrefixMap
     * @param array<Console::TYPE_*,string> $typePrefixMap
     */
    public function __construct(
        ?TagFormats $tagFormats = null,
        ?MessageFormats $messageFormats = null,
        ?Closure $widthCallback = null,
        array $levelPrefixMap = Formatter::DEFAULT_LEVEL_PREFIX_MAP,
        array $typePrefixMap = Formatter::DEFAULT_TYPE_PREFIX_MAP
    ) {
        $this->TagFormats = $tagFormats ?? self::getNullTagFormats();
        $this->MessageFormats = $messageFormats ?? self::getNullMessageFormats();
        $this->WidthCallback = $widthCallback ?? fn() => null;
        $this->LevelPrefixMap = $levelPrefixMap;
        $this->TypePrefixMap = $typePrefixMap;
        $spinnerState = [0, null];
        $this->SpinnerState = &$spinnerState;
    }

    /**
     * @inheritDoc
     */
    public function removesEscapes(): bool
    {
        return $this->TagFormats->removesEscapes();
    }

    /**
     * @inheritDoc
     */
    public function wrapsAfterFormatting(): bool
    {
        return $this->TagFormats->wrapsAfterFormatting();
    }

    /**
     * @inheritDoc
     */
    public function withRemoveEscapes(bool $remove = true)
    {
        return $this->with('TagFormats', $this->TagFormats->withRemoveEscapes($remove));
    }

    /**
     * @inheritDoc
     */
    public function withWrapAfterFormatting(bool $value = true)
    {
        return $this->with('TagFormats', $this->TagFormats->withWrapAfterFormatting($value));
    }

    /**
     * @inheritDoc
     */
    public function getTagFormat(int $tag): FormatInterface
    {
        return $this->TagFormats->getFormat($tag);
    }

    /**
     * @inheritDoc
     */
    public function getMessageFormat(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): MessageFormatInterface {
        return $this->MessageFormats->getFormat($level, $type);
    }

    /**
     * @inheritDoc
     */
    public function getMessagePrefix(
        int $level,
        int $type = Console::TYPE_STANDARD
    ): string {
        if (
            $type === Console::TYPE_UNDECORATED
            || $type === Console::TYPE_UNFORMATTED
        ) {
            return '';
        }

        if ($type === Console::TYPE_PROGRESS) {
            $now = (float) (hrtime(true) / 1000);
            if ($this->SpinnerState[1] === null) {
                $this->SpinnerState[1] = $now;
            } elseif ($now - $this->SpinnerState[1] >= 80000) {
                $this->SpinnerState[0]++;
                $this->SpinnerState[0] %= count(static::SPINNER_FRAMES);
                $this->SpinnerState[1] = $now;
            }
            $prefix = static::SPINNER_FRAMES[$this->SpinnerState[0]] . ' ';
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
        $wrapTo = null,
        bool $unformat = false,
        string $break = "\n"
    ): string {
        if ($string === '' || $string === "\r") {
            return $string;
        }

        // [ [ Offset, length, replacement ], ... ]
        /** @var array<array{int,int,string}> */
        $replace = [];
        $append = '';
        $removeEscapes = $this->removesEscapes();
        $wrapAfterFormatting = $this->wrapsAfterFormatting();
        $wrapFormats = $wrapAfterFormatting
            ? $this->TagFormats
            : self::getNullTagFormats();
        $formats = $unformat
            ? self::getLoopbackTagFormats()
            : $this->TagFormats;

        // Preserve trailing carriage returns
        if ($string[-1] === "\r") {
            $append .= "\r";
            $string = substr($string, 0, -1);
        }

        // Normalise line endings and split the string into text, code blocks
        // and code spans
        if (!Regex::matchAll(
            self::FORMAT_REGEX,
            Str::setEol($string),
            $matches,
            \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL,
        )) {
            throw new ShouldNotHappenException(sprintf(
                'Unable to parse: %s',
                $string,
            ));
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

            // Treat unmatched backticks as text
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
                    self::TAG_REGEX,
                    function ($matches) use (
                        &$replace,
                        $wrapFormats,
                        $formats,
                        $baseOffset,
                        &$adjust
                    ): string {
                        $text = $this->applyTags(
                            $matches,
                            true,
                            $wrapFormats->removesEscapes(),
                            $wrapFormats,
                        );
                        $placeholder = Regex::replace('/[^ ]/u', 'x', $text);
                        $formatted = $wrapFormats === $formats
                            ? $text
                            : $this->applyTags(
                                $matches,
                                true,
                                $formats->removesEscapes(),
                                $formats,
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
                    \PREG_OFFSET_CAPTURE,
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
                $formatted = $formats->apply(
                    $match['block'],
                    new TagAttributes(
                        self::TAG_CODE_BLOCK,
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
                    self::TAG_CODE_SPAN,
                    $match['backtickstring'],
                );
                $text = $wrapFormats->apply($span, $attributes);
                $placeholder = Regex::replace('/[^ ]/u', 'x', $text);
                $formatted = $wrapFormats === $formats
                    ? $text
                    : $formats->apply($span, $attributes);
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
            self::ESCAPE_REGEX,
            function ($matches) use (
                $unformat,
                $removeEscapes,
                $wrapAfterFormatting,
                &$replace,
                &$adjustable,
                &$adjust
            ): string {
                // If the escape character is being wrapped, do nothing other
                // than temporarily replace "\ " with "\x"
                if ($wrapAfterFormatting && !$removeEscapes) {
                    if ($matches[1][0] !== ' ') {
                        return $matches[0][0];
                    }
                    $placeholder = '\x';
                    $replace[] = [$matches[0][1] + $adjust, 2, $matches[0][0]];
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

                if ($unformat || !$removeEscapes || $placeholder !== null) {
                    // Use `$replace` to reinstate the escape after wrapping
                    $replace[] = [
                        $matches[0][1] + $adjust,
                        strlen($matches[1][0]),
                        $unformat || !$removeEscapes ? $matches[0][0] : $matches[1][0],
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

        if (is_array($wrapTo)) {
            for ($i = 0; $i < 2; $i++) {
                if ($wrapTo[$i] <= 0) {
                    $width ??= ($this->WidthCallback)();
                    if ($width === null) {
                        $wrapTo = null;
                        break;
                    }
                    $wrapTo[$i] = max(0, $wrapTo[$i] + $width);
                }
            }
        } elseif (is_int($wrapTo) && $wrapTo <= 0) {
            $width = ($this->WidthCallback)();
            $wrapTo = $width === null
                ? null
                : max(0, $wrapTo + $width);
        }
        if ($wrapTo !== null) {
            if ($break === "\n") {
                $string = Str::wrap($string, $wrapTo);
            } else {
                // Only replace new line breaks with `$break`
                $wrapped = Str::wrap($string, $wrapTo);
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
            usort($replace, fn($a, $b) => $b[0] <=> $a[0]);
        } else {
            $replace = array_reverse($replace);
        }

        foreach ($replace as [$offset, $length, $replacement]) {
            $string = substr_replace($string, $replacement, $offset, $length);
        }

        return $string . $append;
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
            self::TAG_REGEX,
            fn($matches) =>
                $this->applyTags($matches, false, $unescape, $formats, $depth + 1),
            $text,
            -1,
            $count,
        );

        if ($unescape) {
            $text = Regex::replace(self::ESCAPE_REGEX, '$1', $text);
        }

        $tagId = self::TAG_MAP[$tag] ?? null;
        if ($tagId === null) {
            throw new LogicException(sprintf('Invalid tag: %s', $tag));
        }

        return $formats->apply(
            $text,
            new TagAttributes($tagId, $tag, $depth, (bool) $count),
        );
    }

    /**
     * @inheritDoc
     */
    public function formatDiff(string $diff): string
    {
        $formats = [
            '---' => $this->TagFormats->getFormat(self::TAG_DIFF_HEADER),
            '+++' => $this->TagFormats->getFormat(self::TAG_DIFF_HEADER),
            '@' => $this->TagFormats->getFormat(self::TAG_DIFF_RANGE),
            '+' => $this->TagFormats->getFormat(self::TAG_DIFF_ADDITION),
            '-' => $this->TagFormats->getFormat(self::TAG_DIFF_REMOVAL),
        ];

        $attributes = [
            '---' => new TagAttributes(self::TAG_DIFF_HEADER, '---'),
            '+++' => new TagAttributes(self::TAG_DIFF_HEADER, '+++'),
            '@' => new TagAttributes(self::TAG_DIFF_RANGE, '@'),
            '+' => new TagAttributes(self::TAG_DIFF_ADDITION, '+'),
            '-' => new TagAttributes(self::TAG_DIFF_REMOVAL, '-'),
        ];

        return Regex::replaceCallback(
            '/^(-{3}|\+{3}|[-+@]).*/m',
            fn($matches) =>
                $formats[$matches[1]]->apply($matches[0], $attributes[$matches[1]]),
            $diff,
        );
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
            $formats = self::getNullMessageFormats();
            $prefix = '';
        } else {
            $formats = $this->MessageFormats;
            $prefix = $this->getMessagePrefix($level, $type);
        }

        return $formats
            ->getFormat($level, $type)
            ->apply($msg1, $msg2, $prefix, $attributes);
    }

    /**
     * @internal
     */
    public static function getNullFormatter(): self
    {
        return self::$NullFormatter ??= new self();
    }

    private static function getNullTagFormats(): TagFormats
    {
        return self::$NullTagFormats ??= new TagFormats();
    }

    private static function getNullMessageFormats(): MessageFormats
    {
        return self::$NullMessageFormats ??= new MessageFormats();
    }

    private static function getLoopbackTagFormats(): TagFormats
    {
        return self::$LoopbackTagFormats ??= LoopbackFormat::getFormatter()->TagFormats;
    }
}
