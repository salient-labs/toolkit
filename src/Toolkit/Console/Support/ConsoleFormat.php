<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Salient\Console\Support\ConsoleTagAttributes as TagAttributes;
use Salient\Contract\Catalog\HasEscapeSequence;
use Salient\Contract\Console\ConsoleFormatInterface;
use Salient\Contract\Console\ConsoleTag as Tag;

/**
 * Applies inline character sequences to console output
 */
final class ConsoleFormat implements ConsoleFormatInterface, HasEscapeSequence
{
    /** @var string */
    private $Before;
    /** @var string */
    private $After;
    /** @var string[] */
    private $Search;
    /** @var string[] */
    private $Replace;
    private static ConsoleFormat $DefaultFormat;

    /**
     * @param array<string,string> $replace
     */
    public function __construct(string $before = '', string $after = '', array $replace = [])
    {
        $this->Before = $before;
        $this->After = $after;
        $this->Search = array_keys($replace);
        $this->Replace = array_values($replace);
    }

    /**
     * @inheritDoc
     */
    public function apply(?string $text, $attributes = null): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // With fenced code blocks:
        // - remove indentation from the first line of code
        // - add a level of indentation to the block
        if (
            $attributes instanceof TagAttributes
            && $attributes->Tag === Tag::CODE_BLOCK
        ) {
            $indent = (string) $attributes->Indent;
            if ($indent !== '') {
                $length = strlen($indent);
                if (substr($text, 0, $length) === $indent) {
                    $text = substr($text, $length);
                }
            }
            $text = '    ' . str_replace("\n", "\n    ", $text);
        }

        if ($this->Search) {
            $text = str_replace($this->Search, $this->Replace, $text);
        }

        $text = $this->Before . $text;

        if ($this->After === '') {
            return $text;
        }

        // Preserve a trailing carriage return
        if ($text[-1] === "\r") {
            return substr($text, 0, -1) . $this->After . "\r";
        }

        return $text . $this->After;
    }

    /**
     * Get an instance that doesn't apply any formatting to console output
     */
    public static function getDefaultFormat(): self
    {
        return self::$DefaultFormat ??= new self();
    }

    /**
     * Get an instance that uses terminal control sequences to apply a colour to
     * TTY output
     *
     * @param ConsoleFormat::* $colour The terminal control sequence of the
     * desired colour.
     */
    public static function ttyColour(string $colour): self
    {
        return new self(
            $colour,
            self::DEFAULT_FG,
            [
                self::DEFAULT_FG => $colour,
            ],
        );
    }

    /**
     * Get an instance that uses terminal control sequences to increase the
     * intensity of TTY output and optionally apply a colour
     *
     * @param ConsoleFormat::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyBold(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                self::BOLD . $colour,
                self::DEFAULT_FG . self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::BOLD_NOT_FAINT,
                    self::DEFAULT_FG => $colour,
                ],
            );
        }

        return new self(
            self::BOLD,
            self::NOT_BOLD_NOT_FAINT,
            [
                self::NOT_BOLD_NOT_FAINT => self::BOLD_NOT_FAINT,
            ],
        );
    }

    /**
     * Get an instance that uses terminal control sequences to decrease the
     * intensity of TTY output and optionally apply a colour
     *
     * @param ConsoleFormat::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyDim(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                self::FAINT . $colour,
                self::DEFAULT_FG . self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::FAINT_NOT_BOLD,
                    self::DEFAULT_FG => $colour,
                ],
            );
        }

        return new self(
            self::FAINT,
            self::NOT_BOLD_NOT_FAINT,
            [
                self::NOT_BOLD_NOT_FAINT => self::FAINT_NOT_BOLD,
            ],
        );
    }

    /**
     * Get an instance that uses terminal control sequences to apply bold and
     * dim attributes to TTY output and optionally apply a colour
     *
     * If bold (increased intensity) and dim (decreased intensity) attributes
     * cannot be set simultaneously, output will be dim, not bold.
     *
     * @param ConsoleFormat::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyBoldDim(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                self::BOLD . self::FAINT . $colour,
                self::DEFAULT_FG . self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::BOLD . self::FAINT,
                    self::DEFAULT_FG => $colour,
                ],
            );
        }

        return new self(
            self::BOLD . self::FAINT,
            self::NOT_BOLD_NOT_FAINT,
            [
                self::NOT_BOLD_NOT_FAINT => self::BOLD . self::FAINT,
            ],
        );
    }

    /**
     * Get an instance that uses terminal control sequences to underline and
     * optionally apply a colour to TTY output
     *
     * @param ConsoleFormat::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyUnderline(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                $colour . self::UNDERLINED,
                self::NOT_UNDERLINED . self::DEFAULT_FG,
                [
                    self::DEFAULT_FG => $colour,
                    self::NOT_UNDERLINED => '',
                ],
            );
        }

        return new self(
            self::UNDERLINED,
            self::NOT_UNDERLINED,
            [
                self::NOT_UNDERLINED => '',
            ],
        );
    }
}
