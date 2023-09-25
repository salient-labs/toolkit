<?php declare(strict_types=1);

namespace Lkrms\Cli\Support;

use Lkrms\Cli\Catalog\CliHelpType;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use LogicException;

/**
 * Formatting instructions for help messages
 */
final class CliHelpStyle
{
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
    public int $HelpVisibility = CliOptionVisibility::HELP;

    /**
     * @readonly
     */
    public bool $CollapseHelpSynopsis = false;

    /**
     * @param CliHelpType::*|null $helpType
     */
    public function __construct(?int $helpType = null)
    {
        if ($helpType === null) {
            return;
        }

        $this->Italic = '_';
        $this->Escape = '\\';

        switch ($helpType) {
            case CliHelpType::TTY:
                $this->Bold = '__';
                break;

            case CliHelpType::MARKDOWN:
                $this->Bold = '`';
                $this->SynopsisNewline = " \\\n\ \ \ \ ";
                $this->OptionIndent = '  ';
                $this->OptionPrefix = '- ';
                $this->OptionDescriptionPrefix = "\n\n  ";
                $this->CollapseHelpSynopsis = true;
                $this->HelpVisibility = CliOptionVisibility::MARKDOWN;
                break;

            case CliHelpType::MAN_PAGE:
                $this->Bold = '`';
                $this->SynopsisPrefix = '| ';
                $this->SynopsisNewline = "\n|     ";
                $this->OptionDescriptionPrefix = "\n\n:   ";
                $this->CollapseHelpSynopsis = true;
                $this->HelpVisibility = CliOptionVisibility::MAN_PAGE;
                break;

            default:
                throw new LogicException(sprintf('Invalid help type: %d', $helpType));
        }
    }
}
