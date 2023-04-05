<?php declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Concept\Dictionary;

/**
 * Section names commonly used in usage information / help pages
 *
 * @see \Lkrms\Cli\Concept\CliCommand::getUsageSections()
 */
final class CliUsageSectionName extends Dictionary
{
    public const EXIT_STATUS = 'EXIT STATUS';
    public const RETURN_VALUE = 'EXIT STATUS';
    public const ENVIRONMENT = 'ENVIRONMENT';
    public const FILES = 'FILES';
    public const EXAMPLES = 'EXAMPLES';
    public const SEE_ALSO = 'SEE ALSO';
    public const HISTORY = 'HISTORY';
    public const BUGS = 'BUGS';
    public const REPORTING_BUGS = 'REPORTING BUGS';
    public const AUTHOR = 'AUTHOR';
    public const COPYRIGHT = 'COPYRIGHT';
}
