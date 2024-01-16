<?php declare(strict_types=1);

namespace Lkrms\Cli\Support;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Catalog\CliHelpTarget;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use Lkrms\Concern\Immutable;
use Lkrms\Facade\Console;
use Lkrms\Utility\Pcre;
use LogicException;

/**
 * Formatting instructions for help messages
 */
final class CliHelpStyle
{
    use Immutable;

    /**
     * @var CliHelpTarget::*
     *
     * @readonly
     */
    public int $Target;

    /**
     * @readonly
     */
    public ?int $Width;

    /**
     * @readonly
     */
    public bool $HasMarkup = false;

    /**
     * @readonly
     */
    public string $Bold = '';

    /**
     * @readonly
     */
    public string $Italic = '';

    /**
     * @readonly
     */
    public string $Escape = '';

    /**
     * @readonly
     */
    public string $SynopsisPrefix = '';

    /**
     * @readonly
     */
    public string $SynopsisNewline = "\n    ";

    /**
     * If true and the synopsis breaks over multiple lines, collapse
     * non-mandatory options to "[option]..."
     *
     * @readonly
     */
    public bool $CollapseSynopsis = false;

    /**
     * @readonly
     */
    public string $OptionIndent = '    ';

    /**
     * @readonly
     */
    public string $OptionPrefix = '';

    /**
     * @readonly
     */
    public string $OptionDescriptionPrefix = "\n    ";

    /**
     * @readonly
     */
    public int $Visibility = CliOptionVisibility::HELP;

    private int $Margin = 0;

    /**
     * @param CliHelpTarget::* $target
     */
    public function __construct(int $target = CliHelpTarget::INTERNAL, ?int $width = null)
    {
        $this->Target = $target;
        $this->Width = $width;

        if ($target === CliHelpTarget::INTERNAL) {
            return;
        }

        $this->HasMarkup = true;
        $this->Italic = '_';
        $this->Escape = '\\';

        switch ($target) {
            case CliHelpTarget::TTY:
                $this->Bold = '__';
                $this->Width ??= self::getConsoleWidth();
                $this->Margin = 4;
                break;

            case CliHelpTarget::MARKDOWN:
                $this->Bold = '`';
                $this->SynopsisNewline = " \\\n\ \ \ \ ";
                $this->CollapseSynopsis = true;
                $this->OptionIndent = '  ';
                $this->OptionPrefix = '- ';
                $this->OptionDescriptionPrefix = "\n\n  ";
                $this->Visibility = CliOptionVisibility::MARKDOWN;
                break;

            case CliHelpTarget::MAN_PAGE:
                $this->Bold = '`';
                $this->SynopsisPrefix = '| ';
                $this->SynopsisNewline = "\n|     ";
                $this->CollapseSynopsis = true;
                $this->OptionDescriptionPrefix = "\n\n:   ";
                $this->Visibility = CliOptionVisibility::MAN_PAGE;
                break;

            default:
                throw new LogicException(sprintf('Invalid CliHelpTarget: %d', $target));
        }
    }

    /**
     * @return static
     */
    public function withCollapseSynopsis(bool $value = true)
    {
        return $this->withPropertyValue('CollapseSynopsis', $value);
    }

    public function getWidth(): ?int
    {
        return $this->Width === null
            ? null
            : $this->Width - $this->Margin;
    }

    /**
     * @param array<CliHelpSectionName::*|string,string> $sections
     */
    public function buildHelp(array $sections): string
    {
        $help = '';
        foreach ($sections as $heading => $content) {
            $content = rtrim((string) $content);

            if ($content === '') {
                continue;
            }

            if ($this->Target === CliHelpTarget::TTY) {
                $content = str_replace("\n", "\n    ", $content);
                $help .= <<<EOF
                    ## $heading
                        $content


                    EOF;
                continue;
            }

            $help .= <<<EOF
                ## $heading

                $content


                EOF;
        }

        return Pcre::replace('/^\h++$/m', '', rtrim($help));
    }

    public static function getConsoleWidth(): ?int
    {
        $width = Console::getWidth();

        return $width === null
            ? null
            : max(76, $width);
    }
}
