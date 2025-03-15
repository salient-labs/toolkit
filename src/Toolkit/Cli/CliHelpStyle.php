<?php declare(strict_types=1);

namespace Salient\Cli;

use Salient\Console\Format\ConsoleFormatter as Formatter;
use Salient\Console\Format\ConsoleLoopbackFormat as LoopbackFormat;
use Salient\Console\Format\ConsoleManPageFormat as ManPageFormat;
use Salient\Console\Format\ConsoleMarkdownFormat as MarkdownFormat;
use Salient\Contract\Cli\CliHelpSectionName;
use Salient\Contract\Cli\CliHelpStyleInterface;
use Salient\Contract\Cli\CliHelpTarget;
use Salient\Contract\Cli\CliOptionVisibility;
use Salient\Contract\Console\Format\FormatterInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Core\Facade\Console;
use Salient\Utility\Regex;
use LogicException;

/**
 * Formatting instructions for help messages
 */
final class CliHelpStyle implements CliHelpStyleInterface
{
    use ImmutableTrait;

    private ?int $Width;
    private FormatterInterface $Formatter;
    private string $Bold = '';
    private string $Italic = '';
    private string $Escape = '\\';
    private string $SynopsisPrefix = '';
    private string $SynopsisNewline = "\n    ";
    private string $SynopsisSoftNewline = '';
    private bool $CollapseSynopsis = false;
    private string $OptionIndent = '    ';
    private string $OptionPrefix = '';
    private string $OptionDescriptionPrefix = "\n    ";
    /** @var CliOptionVisibility::* */
    private int $Visibility = CliOptionVisibility::HELP;

    // --

    /** @var CliHelpTarget::* */
    private int $Target;
    private bool $HasMarkup = false;
    private int $Margin = 0;

    /**
     * @param CliHelpTarget::* $target
     */
    public function __construct(
        int $target = CliHelpTarget::PLAIN,
        ?int $width = null,
        ?FormatterInterface $formatter = null
    ) {
        $this->Target = $target;
        $this->Width = $width;

        if ($target === CliHelpTarget::PLAIN) {
            $this->Formatter = $formatter ?? LoopbackFormat::getFormatter();
            return;
        }

        $this->HasMarkup = true;
        $this->Italic = '_';

        switch ($target) {
            case CliHelpTarget::NORMAL:
                $this->Bold = '__';
                $this->Width ??= self::getConsoleWidth();
                $this->Margin = 4;
                $this->Formatter = $formatter ?? Console::getTtyTarget()->getFormatter();
                break;

            case CliHelpTarget::MARKDOWN:
                $this->Bold = '`';
                $this->SynopsisNewline = " \\\n\ \ \ \ ";
                $this->SynopsisSoftNewline = "\n";
                $this->OptionIndent = '  ';
                $this->OptionPrefix = '- ';
                $this->OptionDescriptionPrefix = "\n\n  ";
                $this->Visibility = CliOptionVisibility::MARKDOWN;
                $this->Formatter = $formatter ?? MarkdownFormat::getFormatter();
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
                $this->Formatter = $formatter ?? ManPageFormat::getFormatter();
                break;

            default:
                throw new LogicException(sprintf('Invalid CliHelpTarget: %d', $target));
        }
    }

    /**
     * @inheritDoc
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->Formatter;
    }

    /**
     * @inheritDoc
     */
    public function getWidth(): ?int
    {
        return $this->Width === null
            ? null
            : $this->Width - $this->Margin;
    }

    /**
     * @inheritDoc
     */
    public function getBold(): string
    {
        return $this->Bold;
    }

    /**
     * @inheritDoc
     */
    public function getItalic(): string
    {
        return $this->Italic;
    }

    /**
     * @inheritDoc
     */
    public function getEscape(): string
    {
        return $this->Escape;
    }

    /**
     * @inheritDoc
     */
    public function getSynopsisPrefix(): string
    {
        return $this->SynopsisPrefix;
    }

    /**
     * @inheritDoc
     */
    public function getSynopsisNewline(): string
    {
        return $this->SynopsisNewline;
    }

    /**
     * @inheritDoc
     */
    public function getSynopsisSoftNewline(): string
    {
        return $this->SynopsisSoftNewline;
    }

    /**
     * @inheritDoc
     */
    public function getCollapseSynopsis(): bool
    {
        return $this->CollapseSynopsis;
    }

    /**
     * @inheritDoc
     */
    public function getOptionIndent(): string
    {
        return $this->OptionIndent;
    }

    /**
     * @inheritDoc
     */
    public function getOptionPrefix(): string
    {
        return $this->OptionPrefix;
    }

    /**
     * @inheritDoc
     */
    public function getOptionDescriptionPrefix(): string
    {
        return $this->OptionDescriptionPrefix;
    }

    /**
     * @inheritDoc
     */
    public function getVisibility(): int
    {
        return $this->Visibility;
    }

    /**
     * @inheritDoc
     */
    public function withCollapseSynopsis(bool $value = true)
    {
        return $this->with('CollapseSynopsis', $value);
    }

    public function prepareHelp(string $text, string $indent = ''): string
    {
        $text = $this->Formatter->format(
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
        return Formatter::escapeTags($string);
    }

    public static function getConsoleWidth(): ?int
    {
        $width = Console::getTtyTarget()->getWidth();

        return $width === null
            ? null
            : max(76, $width);
    }
}
