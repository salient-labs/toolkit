<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleAttribute as Attribute;
use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\IConsoleFormat;
use Lkrms\Support\Catalog\TtyControlSequence as Colour;

/**
 * Applies formatting to console output by adding inline character sequences
 * defined by the target
 */
final class ConsoleFormat implements IConsoleFormat
{
    /**
     * @var string
     */
    private $Before;

    /**
     * @var string
     */
    private $After;

    /**
     * @var string[]
     */
    private $Replace;

    /**
     * @var string[]
     */
    private $ReplaceWith;

    private static ConsoleFormat $DefaultFormat;

    /**
     * @param array<string,string> $replace
     */
    public function __construct(string $before = '', string $after = '', array $replace = [])
    {
        $this->Before = $before;
        $this->After = $after;
        $this->Replace = array_keys($replace);
        $this->ReplaceWith = array_values($replace);
    }

    public function apply(?string $text, array $attributes = []): string
    {
        if ((string) $text === '') {
            return '';
        }

        // Remove indentation from the first line of code in fenced code blocks
        // to prevent doubling up
        $tagId = $attributes[Attribute::TAG_ID] ?? null;
        if ($tagId === Tag::CODE_BLOCK) {
            $indent = $attributes[Attribute::INDENT] ?? '';
            if ($indent !== '') {
                $length = strlen($indent);
                if (substr($text, 0, $length) === $indent) {
                    $text = substr($text, $length);
                }
            }
        }

        if ($this->Replace) {
            $text = str_replace($this->Replace, $this->ReplaceWith, $text);
        }

        $text = $this->Before . $text;

        if ($this->After === '') {
            return $text;
        }

        // Preserve trailing carriage returns
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
        return self::$DefaultFormat
            ?? (self::$DefaultFormat = new self());
    }

    /**
     * Get an instance that uses terminal control sequences to apply a colour to
     * TTY output
     *
     * @param Colour::* $colour The terminal control sequence of the desired
     * colour.
     */
    public static function ttyColour(string $colour): self
    {
        /** @var string&Colour::* $colour */
        return new self(
            $colour,
            Colour::DEFAULT,
            [
                Colour::DEFAULT => $colour,
            ],
        );
    }

    /**
     * Get an instance that uses terminal control sequences to increase the
     * intensity of TTY output and optionally apply a colour
     *
     * @param Colour::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyBold(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                Colour::BOLD . $colour,
                Colour::DEFAULT . Colour::UNBOLD_UNDIM,
                [
                    Colour::UNBOLD_UNDIM => Colour::UNDIM_BOLD,
                    Colour::DEFAULT => $colour,
                ],
            );
        }

        return new self(
            Colour::BOLD,
            Colour::UNBOLD_UNDIM,
            [
                Colour::UNBOLD_UNDIM => Colour::UNDIM_BOLD,
            ],
        );
    }

    /**
     * Get an instance that uses terminal control sequences to decrease the
     * intensity of TTY output and optionally apply a colour
     *
     * @param Colour::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyDim(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                Colour::DIM . $colour,
                Colour::DEFAULT . Colour::UNBOLD_UNDIM,
                [
                    Colour::UNBOLD_UNDIM => Colour::UNBOLD_DIM,
                    Colour::DEFAULT => $colour,
                ],
            );
        }

        return new self(
            Colour::DIM,
            Colour::UNBOLD_UNDIM,
            [
                Colour::UNBOLD_UNDIM => Colour::UNBOLD_DIM,
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
     * @param Colour::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyBoldDim(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                Colour::BOLD . Colour::DIM . $colour,
                Colour::DEFAULT . Colour::UNBOLD_UNDIM,
                [
                    Colour::UNBOLD_UNDIM => Colour::BOLD . Colour::DIM,
                    Colour::DEFAULT => $colour,
                ],
            );
        }

        return new self(
            Colour::BOLD . Colour::DIM,
            Colour::UNBOLD_UNDIM,
            [
                Colour::UNBOLD_UNDIM => Colour::BOLD . Colour::DIM,
            ],
        );
    }

    /**
     * Get an instance that uses terminal control sequences to underline and
     * optionally apply a colour to TTY output
     *
     * @param Colour::*|null $colour The terminal control sequence of the
     * desired colour. If `null`, no colour changes are applied.
     */
    public static function ttyUnderline(?string $colour = null): self
    {
        if ($colour !== null) {
            return new self(
                $colour . Colour::UNDERLINE,
                Colour::NO_UNDERLINE . Colour::DEFAULT,
                [
                    Colour::DEFAULT => $colour,
                    Colour::NO_UNDERLINE => '',
                ],
            );
        }

        return new self(
            Colour::UNDERLINE,
            Colour::NO_UNDERLINE,
            [
                Colour::NO_UNDERLINE => '',
            ],
        );
    }
}
