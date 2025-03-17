<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\TagAttributesInterface;
use Salient\Contract\HasEscapeSequence;

/**
 * @api
 */
class TtyFormat extends AbstractFormat implements HasEscapeSequence
{
    use EncloseAndReplaceFormatTrait {
        apply as private doApply;
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
        // - remove block indentation from the first line of code
        // - add a level of indentation to the block
        if (
            $attributes instanceof TagAttributesInterface
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

        return $this->doApply($string, $attributes);
    }

    /**
     * @inheritDoc
     */
    protected static function getTagFormats(): ?TagFormats
    {
        $bold = self::getBold();
        $faint = self::getFaint();
        $boldCyan = self::getBold(self::CYAN_FG);
        $red = self::getColour(self::RED_FG);
        $green = self::getColour(self::GREEN_FG);
        $yellow = self::getColour(self::YELLOW_FG);
        $cyan = self::getColour(self::CYAN_FG);
        $yellowUnderline = self::getUnderline(self::YELLOW_FG);

        return (new TagFormats())
            ->withFormat(self::TAG_HEADING, $boldCyan)
            ->withFormat(self::TAG_BOLD, $bold)
            ->withFormat(self::TAG_ITALIC, $yellow)
            ->withFormat(self::TAG_UNDERLINE, $yellowUnderline)
            ->withFormat(self::TAG_LOW_PRIORITY, $faint)
            ->withFormat(self::TAG_CODE_SPAN, $bold)
            ->withFormat(self::TAG_DIFF_HEADER, $bold)
            ->withFormat(self::TAG_DIFF_RANGE, $cyan)
            ->withFormat(self::TAG_DIFF_ADDITION, $green)
            ->withFormat(self::TAG_DIFF_REMOVAL, $red);
    }

    /**
     * @inheritDoc
     */
    protected static function getMessageFormats(): ?MessageFormats
    {
        $null = new NullFormat();
        $bold = self::getBold();
        $faint = self::getFaint();
        $boldRed = self::getBold(self::RED_FG);
        $boldGreen = self::getBold(self::GREEN_FG);
        $boldYellow = self::getBold(self::YELLOW_FG);
        $boldMagenta = self::getBold(self::MAGENTA_FG);
        $boldCyan = self::getBold(self::CYAN_FG);
        $green = self::getColour(self::GREEN_FG);
        $yellow = self::getColour(self::YELLOW_FG);
        $cyan = self::getColour(self::CYAN_FG);

        return (new MessageFormats())
            ->set(self::LEVELS_ERRORS, self::TYPES_ALL, new MessageFormat($boldRed, $null, $boldRed))
            ->set(self::LEVEL_WARNING, self::TYPES_ALL, new MessageFormat($yellow, $null, $boldYellow))
            ->set(self::LEVEL_NOTICE, self::TYPES_ALL, new MessageFormat($bold, $cyan, $boldCyan))
            ->set(self::LEVEL_INFO, self::TYPES_ALL, new MessageFormat($null, $yellow, $yellow))
            ->set(self::LEVEL_DEBUG, self::TYPES_ALL, new MessageFormat($faint, $faint, $faint))
            ->set(self::LEVELS_INFO, self::TYPE_PROGRESS, new MessageFormat($null, $yellow, $yellow))
            ->set(self::LEVELS_INFO, self::TYPES_GROUP, new MessageFormat($boldMagenta, $null, $boldMagenta))
            ->set(self::LEVELS_INFO, self::TYPE_SUMMARY, new MessageFormat($null, $null, $bold))
            ->set(self::LEVELS_INFO, self::TYPE_SUCCESS, new MessageFormat($green, $null, $boldGreen))
            ->set(self::LEVELS_ERRORS_AND_WARNINGS, self::TYPE_FAILURE, new MessageFormat($yellow, $null, $boldYellow));
    }

    /**
     * Get a format that applies a colour to TTY output
     *
     * @param TtyFormat::*_FG $colour
     */
    protected static function getColour(string $colour): self
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
     * Get a format that increases the intensity of TTY output and optionally
     * applies a colour
     *
     * @param TtyFormat::*_FG|null $colour
     */
    protected static function getBold(?string $colour = null): self
    {
        return $colour !== null
            ? new self(
                self::BOLD . $colour,
                self::DEFAULT_FG . self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::BOLD_NOT_FAINT,
                    self::DEFAULT_FG => $colour,
                ],
            )
            : new self(
                self::BOLD,
                self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::BOLD_NOT_FAINT,
                ],
            );
    }

    /**
     * Get a format that decreases the intensity of TTY output and optionally
     * applies a colour
     *
     * @param TtyFormat::*_FG|null $colour
     */
    protected static function getFaint(?string $colour = null): self
    {
        return $colour !== null
            ? new self(
                self::FAINT . $colour,
                self::DEFAULT_FG . self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::FAINT_NOT_BOLD,
                    self::DEFAULT_FG => $colour,
                ],
            )
            : new self(
                self::FAINT,
                self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::FAINT_NOT_BOLD,
                ],
            );
    }

    /**
     * Get a format that applies bold and faint attributes to TTY output and
     * optionally applies a colour
     *
     * If bold (increased intensity) and faint (decreased intensity) attributes
     * cannot be set simultaneously, output will be faint, not bold.
     *
     * @param TtyFormat::*_FG|null $colour
     */
    protected static function getBoldFaint(?string $colour = null): self
    {
        return $colour !== null
            ? new self(
                self::BOLD . self::FAINT . $colour,
                self::DEFAULT_FG . self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::BOLD . self::FAINT,
                    self::DEFAULT_FG => $colour,
                ],
            )
            : new self(
                self::BOLD . self::FAINT,
                self::NOT_BOLD_NOT_FAINT,
                [
                    self::NOT_BOLD_NOT_FAINT => self::BOLD . self::FAINT,
                ],
            );
    }

    /**
     * Get a format that underlines and optionally applies a colour to TTY
     * output
     *
     * @param TtyFormat::*_FG|null $colour
     */
    protected static function getUnderline(?string $colour = null): self
    {
        return $colour !== null
            ? new self(
                $colour . self::UNDERLINED,
                self::NOT_UNDERLINED . self::DEFAULT_FG,
                [
                    self::DEFAULT_FG => $colour,
                    self::NOT_UNDERLINED => '',
                ],
            )
            : new self(
                self::UNDERLINED,
                self::NOT_UNDERLINED,
                [
                    self::NOT_UNDERLINED => '',
                ],
            );
    }
}
