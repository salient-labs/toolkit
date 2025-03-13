<?php declare(strict_types=1);

namespace Salient\Contract\Cli;

/**
 * Section names commonly used in help messages
 */
interface CliHelpSectionName
{
    public const NAME = 'NAME';
    public const SYNOPSIS = 'SYNOPSIS';
    public const OPTIONS = 'OPTIONS';
    public const DESCRIPTION = 'DESCRIPTION';
    public const EXIT_STATUS = 'EXIT STATUS';
    public const RETURN_VALUE = 'RETURN VALUE';
    public const CONFIGURATION = 'CONFIGURATION';
    public const ENVIRONMENT = 'ENVIRONMENT';
    public const FILES = 'FILES';
    public const CONFORMING_TO = 'CONFORMING TO';
    public const NOTES = 'NOTES';
    public const EXAMPLES = 'EXAMPLES';
    public const SEE_ALSO = 'SEE ALSO';
    public const HISTORY = 'HISTORY';
    public const BUGS = 'BUGS';
    public const REPORTING_BUGS = 'REPORTING BUGS';
    public const AUTHORS = 'AUTHORS';
    public const COPYRIGHT = 'COPYRIGHT';
}
