<?php declare(strict_types=1);

namespace Salient\Cli;

use Salient\Console\Support\ConsoleLoopbackFormat as LoopbackFormat;
use Salient\Console\Support\ConsoleManPageFormat as ManPageFormat;
use Salient\Console\Support\ConsoleMarkdownFormat as MarkdownFormat;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Cli\CliHelpSectionName;
use Salient\Contract\Cli\CliHelpTarget;
use Salient\Contract\Cli\CliOptionVisibility;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Exception\LogicException;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Regex;

/**
 * Formatting instructions for help messages
 *
 * @api
 */
final class CliHelpStyle
{
    use HasImmutableProperties;

    /**
     * @readonly
     * @var CliHelpTarget::*
     */
    public int $Target;

    /** @readonly */
    public ?int $Width;
    /** @readonly */
    public Formatter $Formatter;
    /** @readonly */
    public bool $HasMarkup = false;
    /** @readonly */
    public string $Bold = '';
    /** @readonly */
    public string $Italic = '';
    /** @readonly */
    public string $Escape = '\\';
    /** @readonly */
    public string $SynopsisPrefix = '';
    /** @readonly */
    public string $SynopsisNewline = "\n    ";

    /**
     * If not empty, soft-wrap the final form of the synopsis
     *
     * @readonly
     */
    public string $SynopsisSoftNewline = '';

    /**
     * If true and the synopsis breaks over multiple lines, collapse
     * non-mandatory options to "[options]"
     *
     * @readonly
     */
    public bool $CollapseSynopsis = false;

    /** @readonly */
    public string $OptionIndent = '    ';
    /** @readonly */
    public string $OptionPrefix = '';
    /** @readonly */
    public string $OptionDescriptionPrefix = "\n    ";
    /** @readonly */
    public int $Visibility = CliOptionVisibility::HELP;
    private int $Margin = 0;

    /**
     * @param CliHelpTarget::* $target
     */
    public function __construct(
        int $target = CliHelpTarget::PLAIN,
        ?int $width = null,
        ?Formatter $formatter = null
    ) {
        $this->Target = $target;
        $this->Width = $width;

        if ($target === CliHelpTarget::PLAIN) {
            $this->Formatter = $formatter ?: LoopbackFormat::getFormatter();
            return;
        }

        $this->HasMarkup = true;
        $this->Italic = '_';

        switch ($target) {
            case CliHelpTarget::NORMAL:
                $this->Bold = '__';
                $this->Width ??= self::getConsoleWidth();
                $this->Margin = 4;
                $this->Formatter = $formatter ?: Console::getFormatter();
                break;

            case CliHelpTarget::MARKDOWN:
                $this->Bold = '`';
                $this->SynopsisNewline = " \\\n\ \ \ \ ";
                $this->SynopsisSoftNewline = "\n";
                $this->OptionIndent = '  ';
                $this->OptionPrefix = '- ';
                $this->OptionDescriptionPrefix = "\n\n  ";
                $this->Visibility = CliOptionVisibility::MARKDOWN;
                $this->Formatter = $formatter ?: MarkdownFormat::getFormatter();
                break;

            case CliHelpTarget::MAN_PAGE:
                $this->Bold = '`';
                // https://pandoc.org/MANUAL.html#line-blocks
                $this->SynopsisPrefix = '| ';
                $this->SynopsisNewline = "\n|     ";
                $this->SynopsisSoftNewline = "\n  ";
                // https://pandoc.org/MANUAL.html#definition-lists
                $this->OptionDescriptionPrefix = "\n\n:   ";
                $this->Visibility = CliOptionVisibility::MAN_PAGE;
                $this->Formatter = $formatter ?: ManPageFormat::getFormatter();
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

    public function prepareHelp(string $text, string $indent = ''): string
    {
        $text = $this->Formatter->formatTags(
            $text,
            true,
            $this->Width === null
                ? null
                : $this->Width - $this->Margin - strlen($indent),
            true,
        );

        if ($indent !== '') {
            return $indent . str_replace("\n", "\n" . $indent, $text);
        }

        return $text;
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

            if ($this->Target === CliHelpTarget::NORMAL) {
                $content = str_replace("\n", "\n    ", $content);
                $help .= "## $heading\n    $content\n\n";
                continue;
            }

            $help .= "## $heading\n\n$content\n\n";
        }

        return Regex::replace('/^\h++$/m', '', rtrim($help));
    }

    public function maybeEscapeTags(string $string): string
    {
        if ($this->HasMarkup) {
            return $string;
        }
        return $this->Formatter->escapeTags($string);
    }

    public static function getConsoleWidth(): ?int
    {
        $width = Console::getWidth();

        return $width === null
            ? null
            : max(76, $width);
    }
}
