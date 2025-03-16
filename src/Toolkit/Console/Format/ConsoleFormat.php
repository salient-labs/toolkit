<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Console\Format\ConsoleTagAttributes as TagAttributes;
use Salient\Contract\Console\Format\FormatInterface;
use Salient\Contract\HasEscapeSequence;

/**
 * Applies inline character sequences to console output
 */
final class ConsoleFormat implements FormatInterface, HasEscapeSequence
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
    public function apply(string $string, $attributes = null): string
    {
        if ($string === '') {
            return '';
        }

        // With fenced code blocks:
        // - remove indentation from the first line of code
        // - add a level of indentation to the block
        if (
            $attributes instanceof TagAttributes
            && $attributes->getTag() === self::TAG_CODE_BLOCK
        ) {
            $indent = (string) $attributes->getIndent();
            if ($indent !== '') {
                $length = strlen($indent);
                if (substr($string, 0, $length) === $indent) {
                    $string = substr($string, $length);
                }
            }
            $string = '    ' . str_replace("\n", "\n    ", $string);
        }

        if ($this->Search) {
            $string = str_replace($this->Search, $this->Replace, $string);
        }

        $string = $this->Before . $string;

        if ($this->After === '') {
            return $string;
        }

        // Preserve a trailing carriage return
        if ($string[-1] === "\r") {
            return substr($string, 0, -1) . $this->After . "\r";
        }

        return $string . $this->After;
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
     * @param ConsoleFormat::*_FG $colour The terminal control sequence of the
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
     * @param ConsoleFormat::*_FG|null $colour The terminal control sequence of
     * the desired colour. If `null`, no colour changes are applied.
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
     * @param ConsoleFormat::*_FG|null $colour The terminal control sequence of
     * the desired colour. If `null`, no colour changes are applied.
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
     * @param ConsoleFormat::*_FG|null $colour The terminal control sequence of
     * the desired colour. If `null`, no colour changes are applied.
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
     * @param ConsoleFormat::*_FG|null $colour The terminal control sequence of
     * the desired colour. If `null`, no colour changes are applied.
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
